<?php
/**
 * 更新数据库中现有记录的lastSerialNo字段（总集数）
 * 通过分析intro字段提取总集数信息
 */

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\Koreanbackup;

class UpdateEpisodes extends Command
{
    protected function configure()
    {
        $this->setName('update:episodes')
             ->setDescription('更新韩剧总集数信息');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始更新总集数信息...');

        // 获取所有没有总集数信息的记录
        $records = Koreanbackup::where('lastSerialNo', '')
                               ->whereOr('lastSerialNo', 'null')
                               ->whereOr('lastSerialNo', 0)
                               ->select();
        $totalCount = count($records);
        $updatedCount = 0;

        $output->writeln("找到 {$totalCount} 条需要更新的记录");

        foreach ($records as $record) {
            try {
                $episodeInfo = $this->extractEpisodeFromIntro($record->intro);
                
                if ($episodeInfo) {
                    $record->lastSerialNo = $episodeInfo;
                    $record->save();
                    $updatedCount++;
                    $output->writeln("更新记录 ID: {$record->id}, 剧名: '{$record->name}', 总集数: {$episodeInfo}");
                }
                
            } catch (\Exception $e) {
                $output->writeln("更新记录 ID: {$record->id} 失败: " . $e->getMessage());
            }
        }

        $output->writeln("");
        $output->writeln("更新完成！");
        $output->writeln("总记录数: {$totalCount}");
        $output->writeln("更新记录数: {$updatedCount}");
        
        return 0;
    }

    /**
     * 从简介中提取总集数信息
     */
    private function extractEpisodeFromIntro($intro)
    {
        if (empty($intro)) {
            return null;
        }
        
        // 移除HTML标签
        $intro = strip_tags($intro);
        
        // 常见的集数表达模式
        $patterns = [
            '/共(\d+)集/',           // 共16集
            '/全(\d+)集/',           // 全16集
            '/(\d+)集全/',           // 16集全
            '/总共(\d+)集/',         // 总共16集
            '/一共(\d+)集/',         // 一共16集
            '/(\d+)集完结/',         // 16集完结
            '/更新至第(\d+)集/',     // 更新至第16集
            '/第(\d+)集完/',         // 第16集完
            '/(\d+)episodes?/i',    // 16episodes
            '/episodes?\s*(\d+)/i',  // episodes 16
            '/(\d+)\s*集/',          // 16 集
            '/集数[：:](\d+)/',      // 集数：16
            '/总集数[：:](\d+)/',    // 总集数：16
            '/(\d+)\s*话/',          // 16话
            '/共(\d+)话/',           // 共16话
            '/全(\d+)话/',           // 全16话
            '/(\d+)\s*回/',          // 16回
            '/共(\d+)回/',           // 共16回
            '/全(\d+)回/',           // 全16回
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $intro, $matches)) {
                $episodes = intval($matches[1]);
                // 验证集数是否合理（1-200集之间）
                if ($episodes >= 1 && $episodes <= 200) {
                    return (string)$episodes;
                }
            }
        }
        
        return null;
    }
}