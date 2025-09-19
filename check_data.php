<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/common.php';

use app\model\Koreanbackup;

// 查看前10条记录的intro内容
$records = Koreanbackup::where('lastSerialNo', '')
                      ->whereOr('lastSerialNo', 'null')
                      ->whereOr('lastSerialNo', 0)
                      ->limit(10)
                      ->select();

echo "查看前10条记录的intro内容：\n";
echo "================================\n";

foreach ($records as $record) {
    echo "ID: {$record->id}\n";
    echo "剧名: {$record->name}\n";
    echo "lastSerialNo: '{$record->lastSerialNo}'\n";
    echo "intro: {$record->intro}\n";
    echo "--------------------------------\n";
}

echo "\n总共找到 " . count($records) . " 条记录\n";