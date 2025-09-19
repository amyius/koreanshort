<?php
/**
 * 看剧屋网站爬虫
 * 爬取 https://www.kanjuwu.net/lb/?30.html 到 https://www.kanjuwu.net/lb/?30-26.html 页面中的数据
 */

class KanjuwuCrawler
{
    private $pdo;
    private $dbConfig;
    private $baseUrl = 'https://www.kanjuwu.net';
    private $listBaseUrl = 'https://www.kanjuwu.net/search.php?searchtype=5&tid=2&area=%E6%B3%B0%E5%9B%BD';  // 泰国剧搜索页面
    private $imageDir = './public/thaiandtaiwanese/taiwanese/';

    /**
     * 构造函数，初始化数据库连接
     */
    public function __construct()
    {
        $this->dbConfig = [
            "host" => "127.0.0.1",
            "port" => 3306,
            "user" => "root",
            "password" => "LJ1207",
            "database" => "shortplay",
            "table" => "Thai_Taiwanese_dramas",
            "charset" => "utf8mb4"
        ];

        try {
            // 创建PDO连接
            $dsn = "mysql:host={$this->dbConfig['host']};port={$this->dbConfig['port']};dbname={$this->dbConfig['database']};charset={$this->dbConfig['charset']}";
            $this->pdo = new PDO($dsn, $this->dbConfig['user'], $this->dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            echo "数据库连接成功\n";
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage() . "\n");
        }

        // 创建图片目录
        if (!is_dir($this->imageDir)) {
            mkdir($this->imageDir, 0755, true);
            echo "创建图片目录: {$this->imageDir}\n";
        }
    }

    /**
     * 获取页面内容
     */
    private function getPageContent($url)
    {
        echo "获取页面: {$url}\n";
        
        // 添加随机延迟，避免被反爬虫检测
        sleep(rand(2, 5));
        
        // 首先尝试访问首页建立会话
        $this->establishSession();
        
        // 使用cURL获取页面内容，更好地处理反爬虫机制
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ]);
        
        // 设置cookie以模拟真实浏览器
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($html === false || !empty($error)) {
            echo "cURL错误: {$error}\n";
            return null;
        }
        
        if ($httpCode !== 200) {
            echo "HTTP状态码: {$httpCode}\n";
            // 保存错误页面内容用于调试
            if ($html) {
                file_put_contents("error_page_{$httpCode}.html", $html);
                echo "已保存错误页面到 error_page_{$httpCode}.html\n";
            }
            return null;
        }
        
        echo "成功获取页面，内容长度: " . strlen($html) . " 字节\n";
        return $html;
    }
    
    /**
     * 建立会话，访问首页获取cookie
     */
    private function establishSession()
    {
        static $sessionEstablished = false;
        
        if ($sessionEstablished) {
            return;
        }
        
        echo "建立会话，访问首页...\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            echo "会话建立成功\n";
            $sessionEstablished = true;
            sleep(2); // 等待2秒
        } else {
            echo "会话建立失败，HTTP状态码: {$httpCode}\n";
        }
    }
    
    /**
     * 备用的页面获取方法
     */
    private function getPageContentAlternative($url)
    {
        echo "尝试备用URL: {$url}\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($html === false || !empty($error)) {
            echo "备用方法cURL错误: {$error}\n";
            return null;
        }
        
        if ($httpCode !== 200) {
            echo "备用方法HTTP状态码: {$httpCode}\n";
            return null;
        }
        
        echo "备用方法成功获取页面，内容长度: " . strlen($html) . " 字节\n";
        return $html;
    }

    /**
     * 从列表页获取电影链接
     */
    private function getDramaLinksFromPage($pageNum)
    {
        // 构建搜索页面URL，使用page参数
        $url = $this->listBaseUrl . "&page={$pageNum}";
        
        $html = $this->getPageContent($url);
        
        if (!$html) {
            return [];
        }
        
        // 保存HTML到文件以便调试
        file_put_contents("debug_page_{$pageNum}.html", $html);
        echo "已保存页面HTML到 debug_page_{$pageNum}.html\n";
        
        $dramaLinks = [];
        
        // 尝试多种匹配模式来提取剧集链接
        $patterns = [
            // 模式1: 标准的剧集链接
            '/<a[^>]*href="([^"]*\/play\/[^"]*)"[^>]*title="([^"]+)"/',
            // 模式2: 通用的链接模式
            '/<a[^>]*href="([^"]*)"[^>]*title="([^"]+)"[^>]*>.*?<img/',
            // 模式3: 另一种可能的结构
            '/<a class="[^"]*"[^>]*href="([^"]+)"[^>]*>.*?<span[^>]*>([^<]+)<\/span>/',
        ];
        
        foreach ($patterns as $index => $pattern) {
            if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                echo "使用模式" . ($index + 1) . "找到 " . count($matches) . " 个链接\n";
                foreach ($matches as $match) {
                    $link = $match[1];
                    // 如果是相对链接，添加基础URL
                    if (strpos($link, 'http') !== 0) {
                        $link = $this->baseUrl . $link;
                    }
                    $title = trim($match[2]);
                    $dramaLinks[] = [
                        'url' => $link,
                        'title' => $title
                    ];
                    echo "找到剧集: {$title} -> {$link}\n";
                }
                break; // 找到匹配就退出循环
            }
        }
        
        if (empty($dramaLinks)) {
            echo "未找到任何剧集链接，请检查页面结构\n";
            // 输出页面的前1000个字符用于调试
            echo "页面内容预览:\n" . substr($html, 0, 1000) . "...\n";
        }
        
        return $dramaLinks;
    }

    /**
     * 获取剧集详情
     */
    private function getDramaDetails($dramaUrl, $dramaTitle)
    {
        // 检查数据是否已存在
        if ($this->isDramaExists($dramaTitle)) {
            echo "数据已存在，跳过获取详情: {$dramaTitle}\n";
            return null;
        }

        $html = $this->getPageContent($dramaUrl);
        
        if (!$html) {
            return null;
        }
        
        // 初始化剧集数据
        $dramaData = [
            'name' => $dramaTitle,
            'years' => '',
            'director' => '',
            'crew' => '',
            'catetype' => '',
            'area' => '',
            'intro' => '',
            'cover' => '',
            'detailUrl' => $dramaUrl,
            'quarklink' => '',
            'baidulink' => '',
            'oneselfquarklink' => '',
            'oneselfbaidulink' => '',
            'tag' => '',
            'conerMemo' => ''
        ];
        
        // 提取封面图片
        if (preg_match('/<a class="stui-vodlist__thumb picture"[^>]*><img[^>]*data-original="([^"]+)"/', $html, $matches)) {
            $imageUrl = $matches[1];
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = $this->baseUrl . $imageUrl;
            }
            
            $localImagePath = $this->downloadAndCompressImage($imageUrl, $dramaTitle);
            if ($localImagePath) {
                $dramaData['cover'] = $localImagePath;
                echo "图片下载成功: {$localImagePath}\n";
            }
        }
        
        // 提取详情信息
        if (preg_match('/<div class="stui-content__detail">[\s\S]*?<\/div>/', $html, $detailBlock)) {
            $detailHtml = $detailBlock[0];
            
            // 提取年份
            if (preg_match('/年份：<a[^>]*>([^<]+)<\/a>/', $detailHtml, $matches)) {
                $dramaData['years'] = trim($matches[1]);
            }
            
            // 提取地区
            if (preg_match('/地区：<a[^>]*>([^<]+)<\/a>/', $detailHtml, $matches)) {
                $dramaData['area'] = trim($matches[1]);
            }
            
            // 提取类型
            if (preg_match('/类型：<a[^>]*>([^<]+)<\/a>/', $detailHtml, $matches)) {
                $dramaData['catetype'] = trim($matches[1]);
                $dramaData['tag'] = $dramaData['catetype']; // 同时设置tag
            }
            
            // 提取导演
            if (preg_match('/导演：<a[^>]*>([^<]+)<\/a>/', $detailHtml, $matches)) {
                $dramaData['director'] = trim($matches[1]);
            }
            
            // 提取主演
            if (preg_match('/主演：(.*?)(?:<\/p>|<\/div>)/', $detailHtml, $matches)) {
                $actorsHtml = $matches[1];
                if (preg_match_all('/<a[^>]*>([^<]+)<\/a>/', $actorsHtml, $actorMatches)) {
                    $dramaData['crew'] = implode(', ', $actorMatches[1]);
                }
            }
        }
        
        // 提取简介
        if (preg_match('/<span class="detail-content"[^>]*>([\s\S]*?)<\/span>/', $html, $matches)) {
            $intro = trim(strip_tags($matches[1]));
            $dramaData['intro'] = $intro;
        }
        
        // 提取集数信息
        if (preg_match('/<h3 class="title">剧集列表<\/h3>[\s\S]*?<ul class="stui-content__playlist clearfix">([\s\S]*?)<\/ul>/', $html, $matches)) {
            $episodeHtml = $matches[1];
            if (preg_match_all('/<a[^>]*>([^<]+)<\/a>/', $episodeHtml, $episodeMatches)) {
                $episodeCount = count($episodeMatches[0]);
                $dramaData['conerMemo'] = "共{$episodeCount}集";
            }
        }
        $dramaData['quarklink'] = null;
        $dramaData['baidulink'] = null;
        $dramaData['oneselfquarklink'] = null;
        $dramaData['oneselfbaidulink'] = null;
        
        return $dramaData;
    }

    /**
     * 下载并压缩图片
     */
    private function downloadAndCompressImage($imageUrl, $dramaTitle)
    {
        try {
            // 生成文件名 - 使用时分秒格式
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = date('His') . '.' . $extension;
            $localPath = $this->imageDir . $fileName;

            // 下载图片
            $imageData = file_get_contents($imageUrl, false, stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]));

            if ($imageData === false) {
                echo "图片下载失败: {$imageUrl}\n";
                return null;
            }

            // 创建图片资源
            $image = imagecreatefromstring($imageData);
            if ($image === false) {
                echo "图片格式不支持: {$imageUrl}\n";
                return null;
            }

            // 获取原始尺寸
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // 计算压缩后的尺寸（最大宽度400px）
            $maxWidth = 400;
            if ($originalWidth > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = intval(($originalHeight * $maxWidth) / $originalWidth);
            } else {
                $newWidth = $originalWidth;
                $newHeight = $originalHeight;
            }

            // 创建压缩后的图片
            $compressedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($compressedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // 保存压缩后的图片
            $success = false;
            switch (strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    $success = imagejpeg($compressedImage, $localPath, 80);
                    break;
                case 'png':
                    $success = imagepng($compressedImage, $localPath, 8);
                    break;
                case 'gif':
                    $success = imagegif($compressedImage, $localPath);
                    break;
                default:
                    $success = imagejpeg($compressedImage, $localPath, 80);
            }

            // 释放内存
            imagedestroy($image);
            imagedestroy($compressedImage);

            if ($success) {
                echo "图片下载并压缩成功: {$localPath}\n";
                return str_replace('./public/', '/', $localPath);
            } else {
                echo "图片保存失败: {$localPath}\n";
                return null;
            }
        } catch (Exception $e) {
            echo "图片处理失败: " . $e->getMessage() . "\n";
            return null;
        }
    }

    /**
     * 检查剧集是否已存在
     */
    private function isDramaExists($dramaName)
    {
        try {
            $sql = "SELECT COUNT(*) FROM `{$this->dbConfig['table']}` WHERE `name` = :name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['name' => $dramaName]);
            
            $count = $stmt->fetchColumn();
            return $count > 0;
        } catch (PDOException $e) {
            echo "检查数据是否存在失败: {$dramaName} - " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 插入剧集数据到数据库
     */
    private function insertDramaData($dramaData)
    {
        try {
            // 先打印爬取到的所有信息，方便检查
            echo "\n=== 爬取到的详细信息 ===\n";
            echo "剧名: " . ($dramaData['name'] ?? '未获取') . "\n";
            echo "年份: " . ($dramaData['years'] ?? '未获取') . "\n";
            echo "导演: " . ($dramaData['director'] ?? '未获取') . "\n";
            echo "演员: " . ($dramaData['crew'] ?? '未获取') . "\n";
            echo "类型: " . ($dramaData['catetype'] ?? '未获取') . "\n";
            echo "地区: " . ($dramaData['area'] ?? '未获取') . "\n";
            echo "简介: " . (substr($dramaData['intro'] ?? '未获取', 0, 150) . "...") . "\n";
            echo "封面: " . ($dramaData['cover'] ?? '未获取') . "\n";
            echo "详情链接: " . ($dramaData['detailUrl'] ?? '未获取') . "\n";
            echo "集数信息: " . ($dramaData['conerMemo'] ?? '未获取') . "\n";
            echo "标签: " . ($dramaData['tag'] ?? '未获取') . "\n";
            echo "夸克链接: " . ($dramaData['quarklink'] ?? '未获取') . "\n";
            echo "百度链接: " . ($dramaData['baidulink'] ?? '未获取') . "\n";
            echo "自有夸克链接: " . ($dramaData['oneselfquarklink'] ?? '未获取') . "\n";
            echo "自有百度链接: " . ($dramaData['oneselfbaidulink'] ?? '未获取') . "\n";
            echo "===========================\n\n";
            
            // 检查数据是否已存在
            if ($this->isDramaExists($dramaData['name'])) {
                echo "数据已存在，跳过插入: {$dramaData['name']}\n";
                return true;
            }
            
            $sql = "INSERT INTO `{$this->dbConfig['table']}` 
                     (`name`, `years`, `director`, `crew`, `catetype`, `area`, `intro`, `cover`, `detailUrl`, `quarklink`, `baidulink`, `oneselfquarklink`, `oneselfbaidulink`, `tag`, `conerMemo`) 
                     VALUES 
                     (:name, :years, :director, :crew, :catetype, :area, :intro, :cover, :detailUrl, :quarklink, :baidulink, :oneselfquarklink, :oneselfbaidulink, :tag, :conerMemo)";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($dramaData);

            if ($result) {
                echo "成功插入: {$dramaData['name']}\n";
                return true;
            } else {
                echo "插入失败: {$dramaData['name']}\n";
                return false;
            }
        } catch (PDOException $e) {
            echo "数据库操作失败: {$dramaData['name']} - " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 主要爬取方法
     * @param int $startPage 起始页面，默认为1
     * @param int $endPage 结束页面，默认为26
     */
    public function crawl($startPage = 1, $endPage = 26)
    {
        echo "开始爬取看剧屋台湾剧数据...\n";
        echo "爬取范围: 第{$startPage}页 到 第{$endPage}页\n";

        $totalDramas = 0;
        $successCount = 0;

        // 爬取指定页面范围
        for ($page = $startPage; $page <= $endPage; $page++) {
            echo "\n=== 处理第 {$page} 页 ===\n";

            // 获取当前页面的剧集链接
            $dramaLinks = $this->getDramaLinksFromPage($page);

            if (empty($dramaLinks)) {
                echo "第 {$page} 页没有找到剧集链接，跳过\n";
                continue;
            }

            // 处理每个剧集
            foreach ($dramaLinks as $index => $drama) {
                $totalDramas++;
                echo "\n[{$totalDramas}] 处理剧集: {$drama['title']}\n";

                // 获取详情信息
                $dramaData = $this->getDramaDetails($drama['url'], $drama['title']);

                if ($dramaData) {
                    // 插入数据库
                    if ($this->insertDramaData($dramaData)) {
                        $successCount++;
                    }
                } else {
                    echo "获取详情失败或数据已存在，跳过\n";
                }

                // 每处理5个剧集休息一下，避免被封
                if ($totalDramas % 5 == 0) {
                    echo "已处理 {$totalDramas} 个剧集，休息3秒...\n";
                    sleep(3);
                }
            }

            // 每页之间休息
            if ($page < $endPage) {
                echo "第 {$page} 页处理完成，休息5秒...\n";
                sleep(5);
            }
        }

        echo "\n=== 爬取完成 ===\n";
        echo "总共处理: {$totalDramas} 个剧集\n";
        echo "成功插入: {$successCount} 个剧集\n";
        echo "失败数量: " . ($totalDramas - $successCount) . " 个剧集\n";
    }
}

// 执行爬虫
try {
    $crawler = new KanjuwuCrawler();
    
    // 检查命令行参数
    $startPage = 1;
    $endPage = 26;
    
    if (isset($argv[1])) {
        $startPage = (int)$argv[1];
    }
    if (isset($argv[2])) {
        $endPage = (int)$argv[2];
    }
    
    // 参数验证
    if ($startPage < 1) $startPage = 1;
    if ($endPage < $startPage) $endPage = $startPage;
    if ($endPage > 26) $endPage = 26;
    
    echo "使用方法: php kanjuwuweb.php [起始页面] [结束页面]\n";
    echo "示例: php kanjuwuweb.php 5 10 (爬取第5页到第10页)\n";
    echo "当前设置: 第{$startPage}页 到 第{$endPage}页\n\n";
    
    $crawler->crawl($startPage, $endPage);
} catch (Exception $e) {
    echo "爬虫执行失败: " . $e->getMessage() . "\n";
}