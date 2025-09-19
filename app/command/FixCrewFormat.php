<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\Koreanbackup;

class FixCrewFormat extends Command
{
    protected function configure()
    {
        $this->setName('fix:crew')
            ->setDescription('修正crew字段格式，将///替换为,');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("开始修正crew字段格式...");

        // 获取所有包含斜杠分隔符的记录
        $records = Koreanbackup::where('crew', 'like', '%/%')->select();
        $totalCount = count($records);
        $updatedCount = 0;

        $output->writeln("找到 {$totalCount} 条包含斜杠分隔符的记录");

        foreach ($records as $record) {
            $originalCrew = $record->crew;
            
            // 格式化crew字段，处理各种分隔符
            $newCrew = $originalCrew;
            
            // 将///替换为,
            $newCrew = str_replace('///', ',', $newCrew);
            
            // 将//替换为,
            $newCrew = str_replace('//', ',', $newCrew);
            
            // 将单个/替换为,
            $newCrew = str_replace('/', ',', $newCrew);
            
            // 去除多余的逗号
            $newCrew = preg_replace('/,+/', ',', $newCrew);
            
            // 去除首尾逗号和空格
            $newCrew = trim($newCrew, ', ');
            
            // 去除演员名字间多余的空格
            $newCrew = preg_replace('/\s*,\s*/', ',', $newCrew);
            
            if ($newCrew !== $originalCrew) {
                $record->crew = $newCrew;
                $record->save();
                $updatedCount++;
                $output->writeln("更新记录 ID: {$record->id}, 剧名: '{$record->name}'");
                $output->writeln("  原crew: '{$originalCrew}'");
                $output->writeln("  新crew: '{$newCrew}'");
                $output->writeln("");
            }
        }

        $output->writeln("修正完成！");
        $output->writeln("总记录数: {$totalCount}");
        $output->writeln("更新记录数: {$updatedCount}");
        
        return 0;
    }
}