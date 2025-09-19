<?php
require_once 'vendor/autoload.php';

use think\facade\Db;

// 检查更新状态
$updated = Db::name('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->where('years', '>', 0)
    ->count();

$total = Db::name('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->count();

echo "=== 更新状态统计 ===\n";
echo "已更新记录: {$updated} / {$total}\n";
echo "更新进度: " . round(($updated / $total) * 100, 2) . "%\n\n";

// 检查最近更新的记录
echo "=== 最近更新的10条记录 ===\n";
$recent = Db::name('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->where('years', '>', 0)
    ->order('id desc')
    ->limit(10)
    ->field('id,name,years,publishTime')
    ->select();

foreach($recent as $r) {
    echo "ID: {$r['id']}, 名称: {$r['name']}, 年份: {$r['years']}, 发布时间: {$r['publishTime']}\n";
}

// 检查年份分布
echo "\n=== 年份分布统计 ===\n";
$yearStats = Db::name('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->where('years', '>', 0)
    ->field('years, count(*) as count')
    ->group('years')
    ->order('years desc')
    ->select();

foreach($yearStats as $stat) {
    echo "年份 {$stat['years']}: {$stat['count']} 部\n";
}

// 检查未更新的记录
echo "\n=== 未更新的记录示例 ===\n";
$notUpdated = Db::name('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->where('years', '=', 0)
    ->limit(5)
    ->field('id,name,years,publishTime')
    ->select();

foreach($notUpdated as $r) {
    echo "ID: {$r['id']}, 名称: {$r['name']}, 年份: {$r['years']}, 发布时间: {$r['publishTime']}\n";
}