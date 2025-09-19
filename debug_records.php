<?php
require_once 'vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

use app\model\Koreanbackup;

// 查询前20条包含2kor的记录
$records = Koreanbackup::where('sid', 'like', '%2kor%')->limit(20)->select();

echo "前20条包含2kor的记录:\n";
echo "=================================\n";

foreach($records as $index => $record) {
    echo "[{$index}] ID: {$record->id}\n";
    echo "    名称: {$record->name}\n";
    echo "    SID: {$record->sid}\n";
    echo "    年份: {$record->years}\n";
    echo "    发布时间: {$record->publishTime}\n";
    echo "    -------------------------\n";
}

echo "\n总共找到: " . count($records) . " 条记录\n";