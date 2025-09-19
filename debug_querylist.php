<?php
require_once './vendor/autoload.php';

use QL\QueryList;

// 测试QueryList的数据结构
$url = 'https://www.yingshi163.com/show/dianying/area/%E5%8F%B0%E6%B9%BE/page/1/';

echo "正在访问: {$url}\n";

try {
    $response = QueryList::get($url, [], [
        'timeout' => 30,
        'verify' => false,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Referer' => 'https://www.yingshi163.com/'
        ]
    ]);
    
    echo "页面获取成功\n";
    
    // 测试不同的选择器
    echo "\n=== 测试 .stui-vodlist li 选择器 ===\n";
    $movieData1 = $response->rules([
        'title' => ['.stui-vodlist li a', 'title'],
        'url' => ['.stui-vodlist li a', 'href']
    ])->query()->getData();
    
    echo "找到 " . count($movieData1) . " 个项目\n";
    echo "数据结构:\n";
    var_dump(array_slice($movieData1, 0, 3)); // 只显示前3个
    
    echo "\n=== 测试 .stui-vodlist__box 选择器 ===\n";
    $movieData2 = $response->rules([
        'title' => ['.stui-vodlist__box a', 'title'],
        'url' => ['.stui-vodlist__box a', 'href']
    ])->query()->getData();
    
    echo "找到 " . count($movieData2) . " 个项目\n";
    echo "数据结构:\n";
    var_dump(array_slice($movieData2, 0, 3)); // 只显示前3个
    
    echo "\n=== 测试通用 a[href*='/vod/'] 选择器 ===\n";
    $movieData3 = $response->rules([
        'title' => ['a[href*="/vod/"]', 'title'],
        'url' => ['a[href*="/vod/"]', 'href']
    ])->query()->getData();
    
    echo "找到 " . count($movieData3) . " 个项目\n";
    echo "数据结构:\n";
    var_dump(array_slice($movieData3, 0, 3)); // 只显示前3个
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}