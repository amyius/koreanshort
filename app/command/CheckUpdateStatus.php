<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class CheckUpdateStatus extends Command
{
    protected function configure()
    {
        $this->setName('check:status')
            ->setDescription('检查years和publishTime字段的更新状态');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('=== 检查更新状态 ===');
        
        // 统计总数和已更新数
        $total = Db::name('koreanshort')
            ->where('sid', 'like', '%2kor%')
            ->count();
            
        $updated = Db::name('koreanshort')
            ->where('sid', 'like', '%2kor%')
            ->where('years', '>', 0)
            ->count();
            
        $output->writeln("总记录数: {$total}");
        $output->writeln("已更新记录: {$updated}");
        $output->writeln("更新进度: " . round(($updated / $total) * 100, 2) . "%");
        
        // 显示最近更新的记录
        $output->writeln('\n=== 最近更新的10条记录 ===');
        $recent = Db::name('koreanshort')
            ->where('sid', 'like', '%2kor%')
            ->where('years', '>', 0)
            ->order('id desc')
            ->limit(10)
            ->field('id,name,years,publishTime')
            ->select();
            
        foreach($recent as $r) {
            $output->writeln("ID: {$r['id']}, 名称: {$r['name']}, 年份: {$r['years']}, 发布时间: {$r['publishTime']}");
        }
        
        // 年份分布
        $output->writeln('\n=== 年份分布 ===');
        $yearStats = Db::name('koreanshort')
            ->where('sid', 'like', '%2kor%')
            ->where('years', '>', 0)
            ->field('years, count(*) as count')
            ->group('years')
            ->order('years desc')
            ->select();
            
        foreach($yearStats as $stat) {
            $output->writeln("年份 {$stat['years']}: {$stat['count']} 部");
        }
        
        // 未更新的记录示例
        $output->writeln('\n=== 未更新的记录示例 ===');
        $notUpdated = Db::name('koreanshort')
            ->where('sid', 'like', '%2kor%')
            ->where('years', '=', 0)
            ->limit(5)
            ->field('id,name,years,publishTime')
            ->select();
            
        foreach($notUpdated as $r) {
            $output->writeln("ID: {$r['id']}, 名称: {$r['name']}, 年份: {$r['years']}, 发布时间: {$r['publishTime']}");
        }
        
        return 0;
    }
}