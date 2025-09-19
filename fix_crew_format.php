<?php

require_once __DIR__ . '/vendor/autoload.php';

// 引入ThinkPHP框架
require_once __DIR__ . '/app/common.php';

use app\model\Koreanbackup;

echo "开始修正crew字段格式...\n";

// 获取所有包含///的记录
$records = Koreanbackup::where('crew', 'like', '%///%')->select();
$totalCount = count($records);
$updatedCount = 0;

echo "找到 {$totalCount} 条包含///的记录\n";

foreach ($records as $record) {
    $originalCrew = $record->crew;
    
    // 格式化crew字段，将///替换为,
    $newCrew = str_replace('///', ',', $originalCrew);
    $newCrew = preg_replace('/,+/', ',', $newCrew); // 去除多余的逗号
    $newCrew = trim($newCrew, ','); // 去除首尾逗号
    
    if ($newCrew !== $originalCrew) {
        $record->crew = $newCrew;
        $record->save();
        $updatedCount++;
        echo "更新记录 ID: {$record->id}, 剧名: '{$record->name}'\n";
        echo "  原crew: '{$originalCrew}'\n";
        echo "  新crew: '{$newCrew}'\n";
        echo "\n";
    }
}

echo "修正完成！\n";
echo "总记录数: {$totalCount}\n";
echo "更新记录数: {$updatedCount}\n";