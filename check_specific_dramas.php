<?php
require 'vendor/autoload.php';
use QL\QueryList;
use think\facade\Db;

// 检查一些具体剧的真实年份
echo "=== 检查具体剧的真实年份 ===\n";

// 从数据库中获取一些明显不应该是2025年的剧
$dramas = Db::name('koreanshort')
    ->where('sid', 'like', '%2kor%')
    ->where('years', '=', 2025)
    ->whereIn('name', ['Heart to Heart', '微生物', '一起吃飯吧2', '茶母', '清潭洞醜聞'])
    ->field('id,name,sid,years,publishTime')
    ->select();

foreach($dramas as $drama) {
    echo "\n=== 检查剧: {$drama['name']} (ID: {$drama['id']}) ===\n";
    echo "当前数据库记录: 年份={$drama['years']}, 发布时间={$drama['publishTime']}\n";
    
    // 从sid中提取详情页ID
    if (preg_match('/detail\/(\d+)\.html/', $drama['sid'], $matches)) {
        $detailId = $matches[1];
        $detailUrl = "http://2kor.com/detail/{$detailId}.html";
        
        echo "详情页URL: $detailUrl\n";
        
        try {
            $response = QueryList::get($detailUrl, [], [
                'timeout' => 30,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $title = $response->find('title')->text();
            if (empty($title)) {
                echo "页面无法访问或不存在\n";
                continue;
            }
            
            $html = $response->getHtml();
            
            // 查找上映时间
            if (preg_match('/上映[：:]\s*(\d{4}-\d{2}-\d{2})/', $html, $matches)) {
                $realDate = $matches[1];
                $realYear = substr($realDate, 0, 4);
                echo "真实上映时间: $realDate (年份: $realYear)\n";
                
                if ($realYear != 2025) {
                    echo "*** 发现错误！真实年份是 $realYear，但数据库记录为 2025 ***\n";
                }
            } else {
                // 查找任何日期
                if (preg_match_all('/(\d{4}-\d{2}-\d{2})/', $html, $matches)) {
                    $dates = array_unique($matches[1]);
                    echo "页面中的日期: " . implode(', ', $dates) . "\n";
                    
                    // 过滤掉明显的2025年（可能是网站更新时间）
                    $validDates = array_filter($dates, function($date) {
                        $year = substr($date, 0, 4);
                        return $year >= 1990 && $year <= 2024; // 合理的韩剧年份范围
                    });
                    
                    if (!empty($validDates)) {
                        $earliestDate = min($validDates);
                        $earliestYear = substr($earliestDate, 0, 4);
                        echo "可能的真实年份: $earliestYear (基于日期: $earliestDate)\n";
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "访问详情页失败: " . $e->getMessage() . "\n";
        }
    }
    
    echo str_repeat('-', 50) . "\n";
}