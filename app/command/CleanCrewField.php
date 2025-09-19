<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\Koreanbackup;

class CleanCrewField extends Command
{
    protected function configure()
    {
        $this->setName('clean:crew')
            ->setDescription('清理crew字段中的简介信息，只保留演员姓名');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("开始清理crew字段中的简介信息...");

        // 获取所有记录
        $records = Koreanbackup::select();
        $totalCount = count($records);
        $updatedCount = 0;

        foreach ($records as $record) {
            $originalCrew = $record->crew;
            $cleanedCrew = $this->cleanCrewField($originalCrew);
            
            // 如果清理后的内容与原内容不同，则更新
            if ($cleanedCrew !== $originalCrew) {
                try {
                    // 确保字符编码正确
                    $cleanedCrew = mb_convert_encoding($cleanedCrew, 'UTF-8', 'UTF-8');
                    // 移除可能的特殊字符
                    $cleanedCrew = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanedCrew);
                    // 最终长度限制
                    $cleanedCrew = mb_substr($cleanedCrew, 0, 200);
                    
                    $record->crew = $cleanedCrew;
                    $record->save();
                    $updatedCount++;
                    $output->writeln("更新记录 ID: {$record->id}, 剧名: '{$record->name}'");
                    $output->writeln("  原crew长度: " . mb_strlen($originalCrew));
                    $output->writeln("  新crew长度: " . mb_strlen($cleanedCrew));
                    $output->writeln("  新crew: '{$cleanedCrew}'");
                    $output->writeln("");
                } catch (\Exception $e) {
                    $output->writeln("<error>更新记录 ID: {$record->id} 失败: " . $e->getMessage() . "</error>");
                    $output->writeln("  原crew: " . mb_substr($originalCrew, 0, 100) . "...");
                    $output->writeln("  新crew: " . mb_substr($cleanedCrew, 0, 100) . "...");
                    $output->writeln("");
                }
            }
        }

        $output->writeln("更新完成！");
        $output->writeln("总记录数: {$totalCount}");
        $output->writeln("更新记录数: {$updatedCount}");
        
        return 0;
    }

    /**
     * 清理crew字段，移除简介信息
     */
    private function cleanCrewField($crew)
    {
        if (empty($crew)) {
            return $crew;
        }

        // 如果包含常见的简介关键词，则进行清理
        $introKeywords = ['由', '制作', '企划', '执导', '编剧', '改编', '讲述', '故事', '剧情', '简介'];
        $hasIntro = false;
        
        foreach ($introKeywords as $keyword) {
            if (strpos($crew, $keyword) !== false) {
                $hasIntro = true;
                break;
            }
        }

        if (!$hasIntro) {
            // 如果没有简介关键词，直接返回
            return $crew;
        }

        // 尝试提取演员姓名
        $cleanedCrew = $this->extractActorNames($crew);
        
        // 如果提取失败，返回空字符串
        if (empty($cleanedCrew)) {
            return '';
        }

        return $cleanedCrew;
    }

    /**
     * 从包含简介的文本中提取演员姓名
     */
    private function extractActorNames($text)
    {
        // 首先尝试提取"主演："后面的内容
        if (preg_match('/主演[：:]\s*([^\n\r简介]*?)(?:\s*简介|$)/u', $text, $matches)) {
            $actorText = trim($matches[1]);
            
            // 清理常见的后缀
            $actorText = preg_replace('/\s*(等|等等|等人).*$/', '', $actorText);
            
            // 提取演员姓名（支持中文、韩文、英文姓名）
            $actors = [];
            // 匹配中文姓名（2-4个字符）
            if (preg_match_all('/[\x{4e00}-\x{9fff}]{2,4}/u', $actorText, $chineseMatches)) {
                $actors = array_merge($actors, $chineseMatches[0]);
            }
            
            // 匹配韩文姓名
            if (preg_match_all('/[\x{ac00}-\x{d7af}]{2,4}/u', $actorText, $koreanMatches)) {
                $actors = array_merge($actors, $koreanMatches[0]);
            }
            
            // 匹配英文姓名（首字母大写的单词）
            if (preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $actorText, $englishMatches)) {
                $actors = array_merge($actors, $englishMatches[0]);
            }
            
            // 去重并限制数量
            $actors = array_unique($actors);
            $actors = array_slice($actors, 0, 8);
            
            if (!empty($actors)) {
                $result = implode(' ', $actors);
                // 确保不超过数据库字段长度
                return mb_substr($result, 0, 200);
            }
        }
        
        // 如果上面的方法失败，尝试简单的文本清理
        // 移除简介部分
        $cleanText = preg_replace('/简介[：:].*$/u', '', $text);
        $cleanText = preg_replace('/由.*?制作.*?执导.*?编剧.*$/u', '', $cleanText);
        $cleanText = preg_replace('/主演[：:]/', '', $cleanText);
        $cleanText = trim($cleanText);
        
        if (!empty($cleanText) && mb_strlen($cleanText) <= 200) {
            return $cleanText;
        }

        return '';
    }
}