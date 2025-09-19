<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\Koreanbackup;

class UpdateCrew extends Command
{
    protected function configure()
    {
        $this->setName('update:crew')
            ->setDescription('统一crew字段格式为"主演：演员名"的标准格式');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("开始统一crew字段格式...");

        // 获取所有记录
        $records = Koreanbackup::select();
        $totalCount = count($records);
        $updatedCount = 0;
        $skippedCount = 0;

        $output->writeln("找到 {$totalCount} 条记录");

        foreach ($records as $record) {
            $originalCrew = $record->crew;
            
            // 检查是否已经是标准格式
            if ($this->isStandardFormat($originalCrew)) {
                $skippedCount++;
                continue;
            }
            
            // 转换为标准格式
            $newCrew = $this->formatToStandard($originalCrew);
            
            if ($newCrew !== $originalCrew) {
                try {
                    $record->crew = $newCrew;
                    $record->save();
                    $updatedCount++;
                    
                    $output->writeln("更新记录 ID: {$record->id}, 剧名: '{$record->name}'");
                    $output->writeln("  原crew: '{$originalCrew}'");
                    $output->writeln("  新crew: '{$newCrew}'");
                    $output->writeln("");
                } catch (\Exception $e) {
                    $output->writeln("<error>更新记录 ID: {$record->id} 失败: " . $e->getMessage() . "</error>");
                }
            }
        }

        $output->writeln("处理完成！");
        $output->writeln("总记录数: {$totalCount}");
        $output->writeln("已是标准格式(跳过): {$skippedCount}");
        $output->writeln("更新记录数: {$updatedCount}");
        
        return 0;
    }

    /**
     * 检查是否已经是标准格式：主演：演员名
     */
    private function isStandardFormat($crew)
    {
        if (empty($crew)) {
            return true; // 空值认为是标准的
        }
        
        // 检查是否以"主演："开头，且后面跟着演员名（用逗号、顿号或空格分隔）
        return preg_match('/^主演[：:]\s*[\p{Han}\p{Hangul}A-Za-z\s,、，]+$/u', $crew);
    }

    /**
     * 将crew字段转换为标准格式
     */
    private function formatToStandard($crew)
    {
        if (empty($crew)) {
            return '';
        }
        
        // 如果已经包含"主演："，先提取演员部分
        if (preg_match('/主演[：:]\s*(.+)$/u', $crew, $matches)) {
            $actorsPart = trim($matches[1]);
        } else {
            // 如果不包含"主演："，整个字符串都是演员部分
            $actorsPart = $crew;
        }
        
        // 清理演员部分
        $cleanedActors = $this->cleanActorNames($actorsPart);
        
        // 如果清理后为空，返回空字符串
        if (empty($cleanedActors)) {
            return '';
        }
        
        // 返回标准格式
        return '主演：' . $cleanedActors;
    }

    /**
     * 清理演员姓名
     */
    private function cleanActorNames($actorText)
    {
        if (empty($actorText)) {
            return '';
        }
        
        // 移除简介相关内容
        $actorText = preg_replace('/简介[:：].*$/u', '', $actorText);
        $actorText = preg_replace('/讲述.*$/u', '', $actorText);
        $actorText = preg_replace('/故事.*$/u', '', $actorText);
        $actorText = preg_replace('/剧情.*$/u', '', $actorText);
        $actorText = preg_replace('/由.*?制作.*$/u', '', $actorText);
        $actorText = preg_replace('/执导.*$/u', '', $actorText);
        $actorText = preg_replace('/编剧.*$/u', '', $actorText);
        
        // 移除"等"、"等等"、"等人"等后缀
        $actorText = preg_replace('/\s*(等|等等|等人).*$/u', '', $actorText);
        
        // 统一分隔符：将各种分隔符统一为顿号
        $actorText = str_replace(',', '、', $actorText);
        $actorText = str_replace('，', '、', $actorText);
        $actorText = str_replace('/', '、', $actorText);
        $actorText = str_replace('\\', '、', $actorText);
        $actorText = preg_replace('/\s+/u', '、', $actorText);
        
        // 去除多余的分隔符
        $actorText = preg_replace('/、+/u', '、', $actorText);
        $actorText = trim($actorText, '、 ');
        
        // 提取有效的演员姓名
        $actors = explode('、', $actorText);
        $validActors = [];
        
        foreach ($actors as $actor) {
            $actor = trim($actor);
            
            // 过滤掉无效的演员名
            if (empty($actor) || 
                mb_strlen($actor) > 20 || // 太长的可能是简介
                preg_match('/[0-9]{4}/', $actor) || // 包含年份的
                preg_match('/(制作|导演|编剧|企划|出品|发行)/u', $actor) || // 包含职务的
                (preg_match('/^[a-zA-Z\s]+$/', $actor) && mb_strlen($actor) > 15)) { // 太长的英文可能是简介
                continue;
            }
            
            // 只保留有效的演员名（包含中文、韩文、英文字符）
            if (preg_match('/[\p{L}]/u', $actor)) {
                $validActors[] = $actor;
            }
        }
        
        // 限制演员数量，避免过长
        $validActors = array_slice($validActors, 0, 8);
        
        return implode('、', $validActors);
    }
}