<?php
require 'vendor/autoload.php';
use QL\QueryList;

echo "=== 详细分析HTML结构 ===\n";

try {
    $response = QueryList::get('https://2kor.com/list/1---.html', [], [
        'timeout' => 30,
        'verify' => false,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $html = $response->getHtml();
    
    // 保存完整HTML
    file_put_contents('full_category_page.html', $html);
    echo "完整HTML已保存到 full_category_page.html\n";
    
    // 查找.list容器
    $listContainer = $response->find('.list')->first();
    if ($listContainer) {
        echo "\n找到.list容器\n";
        file_put_contents('list_container.html', $listContainer->html());
        echo "列表容器HTML已保存到 list_container.html\n";
        
        // 查找ul
        $ul = $listContainer->find('ul')->first();
        if ($ul) {
            echo "\n找到ul元素\n";
            file_put_contents('ul_element.html', $ul->html());
            echo "ul元素HTML已保存到 ul_element.html\n";
            
            // 查找所有li
            $lis = $ul->find('li');
            echo "\n找到li元素数量: " . $lis->count() . "\n";
            
            // 分析前3个li元素
            foreach ($lis as $index => $li) {
                if ($index < 3) {
                    echo "\n=== LI元素 {$index} ===\n";
                    $liHtml = $li->html();
                    echo "HTML: {$liHtml}\n";
                    
                    // 保存单个li元素
                    file_put_contents("li_element_{$index}.html", $liHtml);
                    
                    // 查找所有a标签
                    $allLinks = $li->find('a');
                    echo "找到a标签数量: " . $allLinks->count() . "\n";
                    
                    foreach ($allLinks as $linkIndex => $link) {
                        $href = $link->attr('href');
                        $text = $link->text();
                        $title = $link->attr('title');
                        $class = $link->attr('class');
                        
                        echo "  链接[{$linkIndex}]: href='{$href}', text='{$text}', title='{$title}', class='{$class}'\n";
                    }
                    
                    // 查找所有p标签
                    $allPs = $li->find('p');
                    echo "找到p标签数量: " . $allPs->count() . "\n";
                    
                    foreach ($allPs as $pIndex => $p) {
                        $pText = $p->text();
                        $pHtml = $p->html();
                        echo "  p[{$pIndex}]: text='{$pText}', html='{$pHtml}'\n";
                        
                        // 查找p内的a标签
                        $pLinks = $p->find('a');
                        foreach ($pLinks as $pLinkIndex => $pLink) {
                            $pHref = $pLink->attr('href');
                            $pText = $pLink->text();
                            echo "    p内链接[{$pLinkIndex}]: href='{$pHref}', text='{$pText}'\n";
                        }
                    }
                    
                    echo "\n";
                }
            }
        } else {
            echo "未找到ul元素\n";
        }
    } else {
        echo "未找到.list容器\n";
        
        // 尝试查找其他可能的容器
        $possibleSelectors = ['.box', '.content', '.main', '.wrap', 'ul', 'li'];
        
        foreach ($possibleSelectors as $selector) {
            $elements = $response->find($selector);
            echo "选择器 '{$selector}': " . $elements->count() . " 个元素\n";
        }
    }
    
} catch (Exception $e) {
    echo "访问失败: " . $e->getMessage() . "\n";
}

echo "\n=== 分析完成 ===\n";