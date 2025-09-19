<?php
require 'vendor/autoload.php';
use QL\QueryList;

echo "=== 探索其他封面图获取方式 ===\n";

// 1. 测试其他韩剧网站
echo "\n=== 1. 测试其他韩剧网站 ===\n";
$alternativeSites = [
    'https://www.hanfan.cc/',
    'https://www.hanjutv.com/',
    'https://www.91mjw.com/',
    'https://www.hanmi.tv/',
    'https://www.hanju.cc/'
];

foreach ($alternativeSites as $site) {
    echo "\n测试网站: {$site}\n";
    try {
        $response = QueryList::get($site, [], [
            'timeout' => 15,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $title = $response->find('title')->text();
        echo "网站标题: {$title}\n";
        
        // 查找搜索功能
        $searchForm = $response->find('form[action*="search"], input[name*="search"], input[name="q"], input[name="keyword"]');
        if ($searchForm->count() > 0) {
            echo "找到搜索功能\n";
        }
        
        // 查找详情页链接
        $detailLinks = $response->find('a[href*="detail"], a[href*="play"], a[href*="drama"]');
        echo "详情页链接数量: " . $detailLinks->count() . "\n";
        
        if ($detailLinks->count() > 0) {
            echo "示例链接:\n";
            $detailLinks->each(function($item, $index) {
                if ($index < 3) {
                    $href = $item->attr('href');
                    $text = trim($item->text());
                    echo "  {$href} - {$text}\n";
                }
            });
        }
        
    } catch (Exception $e) {
        echo "访问失败: " . $e->getMessage() . "\n";
    }
    
    sleep(2);
}

// 2. 测试2kor.com的详情页结构
echo "\n\n=== 2. 深度分析2kor.com详情页结构 ===\n";
$testDetailUrls = [
    'https://2kor.com/detail/3398.html', // 华丽的日子
    'https://2kor.com/detail/3436.html', // 百次的回忆
    'https://2kor.com/detail/3452.html'  // 爱在异域单相思
];

foreach ($testDetailUrls as $detailUrl) {
    echo "\n分析详情页: {$detailUrl}\n";
    try {
        $response = QueryList::get($detailUrl, [], [
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $title = $response->find('title')->text();
        echo "页面标题: {$title}\n";
        
        // 查找所有图片
        $images = $response->find('img');
        echo "图片总数: " . $images->count() . "\n";
        
        if ($images->count() > 0) {
            echo "所有图片信息:\n";
            $images->each(function($item, $index) {
                $src = $item->attr('src');
                $alt = $item->attr('alt');
                $class = $item->attr('class');
                $width = $item->attr('width');
                $height = $item->attr('height');
                
                echo "  [{$index}] src: {$src}\n";
                echo "       alt: {$alt}\n";
                echo "       class: {$class}\n";
                echo "       size: {$width}x{$height}\n";
                echo "       ---\n";
            });
        }
        
        // 查找可能的封面图片容器
        $containers = [
            '.movie-info',
            '.detail-info', 
            '.poster',
            '.cover',
            '.thumb',
            '.pic',
            '.image',
            '.photo'
        ];
        
        foreach ($containers as $container) {
            $element = $response->find($container);
            if ($element->count() > 0) {
                echo "找到容器 '{$container}': " . $element->count() . " 个\n";
                $containerImg = $element->find('img');
                if ($containerImg->count() > 0) {
                    $src = $containerImg->first()->attr('src');
                    echo "  容器内图片: {$src}\n";
                }
            }
        }
        
        // 分析页面HTML结构
        $html = $response->getHtml();
        
        // 查找可能的图片URL模式
        $patterns = [
            '/src=["\']([^"\']*(poster|cover|thumb|pic)[^"\']*)["\']/i',
            '/url\(["\']?([^"\')]*\.(jpg|jpeg|png|webp))["\']?\)/i',
            '/["\']([^"\']*(upload|images|img)[^"\']*)["\']/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                echo "模式匹配 '{$pattern}': " . count($matches[1]) . " 个结果\n";
                foreach (array_slice($matches[1], 0, 3) as $match) {
                    echo "  {$match}\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "分析失败: " . $e->getMessage() . "\n";
    }
    
    sleep(2);
}

// 3. 测试豆瓣电影API（可能需要代理）
echo "\n\n=== 3. 测试豆瓣电影搜索 ===\n";
$testMovies = ['华丽的日子', '百次的回忆', '哈尔滨'];

foreach ($testMovies as $movie) {
    echo "\n搜索电影: {$movie}\n";
    try {
        $searchUrl = 'https://movie.douban.com/j/subject_suggest?q=' . urlencode($movie);
        $response = QueryList::get($searchUrl, [], [
            'timeout' => 15,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer' => 'https://movie.douban.com/'
            ]
        ]);
        
        $content = $response->getHtml();
        echo "响应内容: " . substr($content, 0, 200) . "...\n";
        
        // 尝试解析JSON
        $data = json_decode($content, true);
        if ($data && is_array($data)) {
            echo "找到 " . count($data) . " 个结果\n";
            foreach (array_slice($data, 0, 2) as $item) {
                if (isset($item['title']) && isset($item['pic'])) {
                    echo "  标题: {$item['title']}\n";
                    echo "  图片: {$item['pic']}\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "搜索失败: " . $e->getMessage() . "\n";
    }
    
    sleep(2);
}

// 4. 测试TMDB API（需要API key）
echo "\n\n=== 4. 测试TMDB搜索 ===\n";
echo "TMDB需要API key，这里只测试URL格式\n";
foreach ($testMovies as $movie) {
    $tmdbUrl = 'https://api.themoviedb.org/3/search/movie?api_key=YOUR_API_KEY&query=' . urlencode($movie) . '&language=zh-CN';
    echo "TMDB搜索URL: {$tmdbUrl}\n";
}

// 5. 测试本地图片生成
echo "\n\n=== 5. 测试本地默认图片生成 ===\n";
echo "可以考虑为没有封面的剧集生成默认图片\n";

$defaultImagePath = './public/upload/2korimg/default_cover.jpg';
if (!file_exists($defaultImagePath)) {
    // 创建一个简单的默认封面图片
    $width = 300;
    $height = 400;
    $image = imagecreatetruecolor($width, $height);
    
    // 设置背景色（深灰色）
    $bgColor = imagecolorallocate($image, 64, 64, 64);
    imagefill($image, 0, 0, $bgColor);
    
    // 设置文字颜色（白色）
    $textColor = imagecolorallocate($image, 255, 255, 255);
    
    // 添加文字
    $text = '暂无封面';
    $fontSize = 20;
    $textBox = imagettfbbox($fontSize, 0, './public/js/arial.ttf', $text);
    
    // 如果字体文件不存在，使用内置字体
    if (!file_exists('./public/js/arial.ttf')) {
        imagestring($image, 5, ($width - strlen($text) * 10) / 2, $height / 2 - 10, $text, $textColor);
    } else {
        $x = ($width - $textBox[4]) / 2;
        $y = ($height - $textBox[5]) / 2;
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, './public/js/arial.ttf', $text);
    }
    
    // 保存图片
    if (imagejpeg($image, $defaultImagePath, 80)) {
        echo "创建默认封面图片: {$defaultImagePath}\n";
    } else {
        echo "创建默认封面图片失败\n";
    }
    
    imagedestroy($image);
} else {
    echo "默认封面图片已存在: {$defaultImagePath}\n";
}

echo "\n=== 探索完成 ===\n";