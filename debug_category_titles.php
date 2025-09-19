<?php
require 'vendor/autoload.php';
use QL\QueryList;

echo "=== 调试分类页面HTML结构 ===\n";

$categoryUrl = 'https://2kor.com/list/1---.html';
echo "分析页面: {$categoryUrl}\n";

try {
    $response = QueryList::get($categoryUrl, [], [
        'timeout' => 30,
        'verify' => false,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $html = $response->getHtml();
    
    // 保存HTML用于分析
    file_put_contents('category_page_debug.html', $html);
    echo "HTML已保存到 category_page_debug.html\n";
    
    // 查找所有详情页链接
    $detailLinks = $response->find('a[href*="detail"]');
    echo "找到详情页链接数量: " . $detailLinks->count() . "\n";
    
    echo "\n=== 详情页链接分析 ===\n";
    foreach ($detailLinks as $index => $link) {
        if ($index < 10) { // 只分析前10个
            $href = $link->attr('href');
            $text = $link->text();
            $html = $link->html();
            
            echo "[{$index}] href: {$href}\n";
            echo "     text: '{$text}'\n";
            echo "     html: {$html}\n";
            echo "     ---\n";
        }
    }
    
    // 尝试其他可能的选择器
    echo "\n=== 尝试其他选择器 ===\n";
    
    $selectors = [
        'a[href*="detail"] .title',
        'a[href*="detail"] h3',
        'a[href*="detail"] h4',
        'a[href*="detail"] span',
        '.movie-title',
        '.title',
        '.name',
        '.drama-title'
    ];
    
    foreach ($selectors as $selector) {
        $elements = $response->find($selector);
        echo "选择器 '{$selector}': " . $elements->count() . " 个元素\n";
        
        if ($elements->count() > 0) {
            echo "前5个元素内容:\n";
            foreach ($elements as $index => $element) {
                if ($index < 5) {
                    $text = trim($element->text());
                    echo "  [{$index}] {$text}\n";
                }
            }
        }
        echo "\n";
    }
    
    // 分析页面中包含"华丽"的所有元素
    echo "\n=== 搜索包含'华丽'的元素 ===\n";
    $allElements = $response->find('*');
    foreach ($allElements as $element) {
        $text = $element->text();
        if (mb_strpos($text, '华丽', 0, 'UTF-8') !== false) {
            $tagName = $element->getNode()->tagName;
            $class = $element->attr('class');
            echo "标签: {$tagName}, class: {$class}, 内容: {$text}\n";
        }
    }
    
} catch (Exception $e) {
    echo "访问失败: " . $e->getMessage() . "\n";
}

echo "\n=== 调试完成 ===\n";