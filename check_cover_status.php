<?php
require 'vendor/autoload.php';
use think\facade\Db;

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== 检查cover字段状态 ===\n";

// 查询cover字段为空的2kor记录
$records = Db::table('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->where('cover', null)
    ->select();

echo "cover字段为空的2kor记录数量: " . count($records) . "\n";

if (count($records) > 0) {
    echo "\n前5条记录:\n";
    $recordsArray = $records->toArray();
    foreach (array_slice($recordsArray, 0, 5) as $record) {
        echo "ID: {$record['id']}, 名称: {$record['name']}, cover: " . ($record['cover'] ?? 'NULL') . "\n";
    }
}

// 查询所有2kor记录的cover字段状态
$allRecords = Db::table('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->field('id, name, cover, image')
    ->select();

echo "\n=== 所有2kor记录的cover状态 ===\n";
echo "总记录数: " . count($allRecords) . "\n";

$withCover = 0;
$withoutCover = 0;

foreach ($allRecords as $record) {
    if (empty($record['cover'])) {
        $withoutCover++;
    } else {
        $withCover++;
    }
}

echo "有cover的记录: {$withCover}\n";
echo "无cover的记录: {$withoutCover}\n";

if ($withoutCover > 0) {
    echo "\n无cover的记录示例:\n";
    $emptyCoverRecords = array_filter($allRecords, function($record) {
        return empty($record['cover']);
    });
    
    foreach (array_slice($emptyCoverRecords, 0, 3) as $record) {
        echo "ID: {$record['id']}, 名称: {$record['name']}\n";
    }
}