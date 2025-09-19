<?php
/**
 * 奇优影院泰剧爬虫
 * 爬取 https://www.czmaigao.com/sqy/23/page/1.html 到 page/17.html 的泰剧数据
 */

class CzmaigaoWebScraper {
    private $pdo;
    private $baseUrl = 'https://www.czmaigao.com';
    private $listBaseUrl = 'https://www.czmaigao.com/sqy/21/page/';
    private $imageDir = './public/thaiandtaiwanese/czmaigao/';
    private $imgs = '/thaiandtaiwanese/czmaigao/';
    private $cookieFile = 'czmaigao_cookies.txt';
    
    // 数据库配置
    private $dbConfig = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => 'LJ1207',
        'database' => 'shortplay',
        'table' => 'thai_taiwanese_dramas',
        'charset' => 'utf8mb4'
    ];
    
    public function __construct() {
        $this->connectDatabase();
        $this->createImageDirectory();
        echo "奇优影院泰剧爬虫初始化完成\n";
    }
    
    /**
     * 连接数据库
     */
    private function connectDatabase() {
        try {
            $dsn = "mysql:host={$this->dbConfig['host']};port={$this->dbConfig['port']};dbname={$this->dbConfig['database']};charset={$this->dbConfig['charset']}";
            $this->pdo = new PDO($dsn, $this->dbConfig['user'], $this->dbConfig['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "数据库连接成功\n";
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * 创建图片存储目录
     */
    private function createImageDirectory() {
        if (!is_dir($this->imageDir)) {
            mkdir($this->imageDir, 0755, true);
            echo "创建图片目录: {$this->imageDir}\n";
        }
    }
    
    /**
     * 获取页面内容
     */
    private function getPageContent($url) {
        // 添加随机延迟，避免被反爬虫检测
        sleep(rand(1, 3));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
            ],
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "cURL错误: $error\n";
            return false;
        }
        
        if ($httpCode !== 200) {
            echo "HTTP状态码: $httpCode，URL: $url\n";
            return false;
        }
        
        echo "成功获取页面内容，长度: " . strlen($content) . " 字节\n";
        return $content;
    }
    
    /**
     * 从列表页面提取剧集链接
     */
    private function getDramaLinksFromPage($pageNum) {
        $url = $this->listBaseUrl . $pageNum . '.html';
        echo "正在爬取第 $pageNum 页: $url\n";
        
        $content = $this->getPageContent($url);
        if (!$content) {
            return [];
        }
        
        $links = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($content);
        $xpath = new DOMXPath($dom);
        
        // 根据网页结构提取剧集链接 - 查找具体的剧集详情页链接
        $dramaNodes = $xpath->query('//ul[@class="myui-vodlist clearfix"]//li//a[@class="myui-vodlist__thumb lazyload"]');
        
        foreach ($dramaNodes as $node) {
            $href = $node->getAttribute('href');
            if (!empty($href) && strpos($href, '/dqy/') !== false) {
                $fullUrl = $this->baseUrl . $href;
                if (!in_array($fullUrl, $links)) {
                    $links[] = $fullUrl;
                }
            }
        }
        
        // 如果上面的选择器没找到，尝试其他可能的选择器
        if (empty($links)) {
            $dramaNodes = $xpath->query('//a[@class="myui-vodlist__thumb lazyload"]');
            foreach ($dramaNodes as $node) {
                $href = $node->getAttribute('href');
                if (!empty($href) && strpos($href, '/dqy/') !== false) {
                    $fullUrl = $this->baseUrl . $href;
                    if (!in_array($fullUrl, $links)) {
                        $links[] = $fullUrl;
                    }
                }
            }
        }
        
        echo "第 $pageNum 页找到 " . count($links) . " 个剧集链接\n";
        return $links;
    }
    
    /**
     * 提取剧集详情信息（优化版：先检查name是否存在）
     */
    private function extractDramaDetails($url) {
        echo "正在提取详情: $url\n";
        
        $content = $this->getPageContent($url);
        if (!$content) {
            return null;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($content);
        $xpath = new DOMXPath($dom);
        
        // 首先提取剧名进行检查
        $titleNodes = $xpath->query('//h1[@class="title text-fff"]');
        if ($titleNodes->length > 0) {
            $dramaName = trim($titleNodes->item(0)->textContent);
            
            // 如果剧名已存在，直接跳过所有后续处理
            if ($this->dramaExists($dramaName)) {
                echo "剧名已存在，跳过详细提取: {$dramaName}\n";
                return null;
            }
            
            echo "剧名不存在，继续提取详细信息: {$dramaName}\n";
        } else {
            echo "无法提取剧名，跳过处理\n";
            return null;
        }
        
        // 打印详情页面的关键HTML结构
        echo "\n=== 详情页面HTML结构分析 ===\n";
        
        // 打印标题区域结构
        echo "标题区域结构:\n";
        foreach ($titleNodes as $node) {
            echo "- " . $node->getAttribute('class') . ": " . trim($node->textContent) . "\n";
        }
        
        // 打印图片区域结构
        $imgNodes = $xpath->query('//img[contains(@class, "lazyload") or contains(@class, "pic")]');
        echo "\n图片区域结构:\n";
        foreach ($imgNodes as $i => $node) {
            if ($i < 3) { // 只显示前3个
                echo "- class: " . $node->getAttribute('class') . "\n";
                echo "  src: " . $node->getAttribute('src') . "\n";
                echo "  data-original: " . $node->getAttribute('data-original') . "\n";
            }
        }
        
        // 打印信息区域结构
        $infoNodes = $xpath->query('//*[contains(@class, "text-muted") or contains(@class, "split-line")]');
        echo "\n信息区域结构:\n";
        foreach ($infoNodes as $i => $node) {
            if ($i < 10) { // 只显示前10个
                echo "- " . $node->getAttribute('class') . ": " . trim($node->textContent) . "\n";
            }
        }
        
        // 打印简介区域结构
        $introNodes = $xpath->query('//*[contains(@class, "content") or contains(text(), "简介")]');
        echo "\n简介区域结构:\n";
        foreach ($introNodes as $i => $node) {
            if ($i < 5) { // 只显示前5个
                $text = trim($node->textContent);
                if (strlen($text) > 100) {
                    $text = substr($text, 0, 100) . "...";
                }
                echo "- " . $node->getAttribute('class') . ": " . $text . "\n";
            }
        }
        
        echo "=== HTML结构分析完成 ===\n\n";
        
        $drama = [
            'catetype' => '台剧',
            'name' => $dramaName, // 使用之前提取的剧名
            'years' => '',
            'cover' => '',
            'detailUrl' => $url,
            'quarklink' => '',
            'oneselfquarklink' => '',
            'baidulink' => '',
            'oneselfbaidulink' => '',
            'tag' => '',
            'crew' => '',
            'intro' => '',
            'conerMemo' => '',
            'director' => '',
            'area' => '台湾',
        ];
        
        // 剧名已在前面提取并检查，这里不需要重复提取
        
        // 提取封面图 - 下载并压缩到本地
        $imgNodes = $xpath->query('//div[@class="myui-content__thumb"]//img');
        if ($imgNodes->length > 0) {
            $imgSrc = $imgNodes->item(0)->getAttribute('data-original');
            if (empty($imgSrc)) {
                $imgSrc = $imgNodes->item(0)->getAttribute('src');
            }
            if (!empty($imgSrc)) {
                // 如果是相对路径，转换为绝对路径
                if (strpos($imgSrc, 'http') !== 0) {
                    $imgSrc = $this->baseUrl . $imgSrc;
                }
                
                // 生成文件名（使用剧名的MD5值）
                $filename = md5($drama['name']);
                
                // 下载并压缩图片
                $localImagePath = $this->downloadAndCompressImage($imgSrc, $filename);
                
                if ($localImagePath) {
                    $drama['cover'] = $localImagePath;
                    echo "封面图下载成功: {$localImagePath}\n";
                } else {
                    $drama['cover'] = $imgSrc; // 下载失败时保存原URL
                    echo "封面图下载失败，保存原URL: {$imgSrc}\n";
                }
            }
        }
        
        // 提取年份 - 从信息区域的文本中提取
        $yearNodes = $xpath->query('//span[@class="text-muted hidden-xs" and contains(text(), "年份：")]/following-sibling::text()[1]');
        if ($yearNodes->length > 0) {
            $yearText = trim($yearNodes->item(0)->nodeValue);
            if (preg_match('/(\d{4})/', $yearText, $matches)) {
                $drama['years'] = $matches[1];
            }
        }
        
        // 如果年份没找到，尝试从所有文本节点中查找年份
        if (empty($drama['years'])) {
            $allTextNodes = $xpath->query('//text()');
            foreach ($allTextNodes as $textNode) {
                $text = trim($textNode->nodeValue);
                if (preg_match('/(\d{4})年/', $text, $matches) || preg_match('/(\d{4})/', $text, $matches)) {
                    $year = intval($matches[1]);
                    if ($year >= 2000 && $year <= 2030) { // 合理的年份范围
                        $drama['years'] = $matches[1];
                        break;
                    }
                }
            }
        }
        
        // 提取主演信息 - 从包含"主演："的span后面的链接中获取
        $crewNodes = $xpath->query('//span[@class="text-muted" and contains(text(), "主演：")]/following-sibling::a');
        if ($crewNodes->length > 0) {
            $crewList = [];
            foreach ($crewNodes as $crewNode) {
                $crewName = trim($crewNode->textContent);
                if (!empty($crewName)) {
                    $crewList[] = $crewName;
                }
            }
            $drama['crew'] = implode(',', $crewList);
        } else {
            // 尝试从其他位置获取主演信息
            $crewNodes = $xpath->query('//span[@class="text-muted" and contains(text(), "主演")]/following-sibling::span//a');
            if ($crewNodes->length > 0) {
                $crewList = [];
                foreach ($crewNodes as $crewNode) {
                    $crewName = trim($crewNode->textContent);
                    if (!empty($crewName)) {
                        $crewList[] = $crewName;
                    }
                }
                $drama['crew'] = implode(',', $crewList);
            }
        }
        
        // 提取导演信息 - 从包含"导演："的span后面获取
        $directorNodes = $xpath->query('//span[@class="text-muted hidden-xs" and contains(text(), "导演：")]/following-sibling::text()[1]');
        if ($directorNodes->length > 0) {
            $drama['director'] = trim($directorNodes->item(0)->nodeValue);
        } else {
            // 尝试从span标签中获取
            $directorNodes = $xpath->query('//span[@class="text-muted" and contains(text(), "导演")]/following-sibling::span');
            if ($directorNodes->length > 0) {
                $drama['director'] = trim($directorNodes->item(0)->textContent);
            }
        }
        
        // 提取简介 - 从content区域获取
        $introNodes = $xpath->query('//div[@class="col-pd text-collapse content"]');
        if ($introNodes->length > 0) {
            $drama['intro'] = trim($introNodes->item(0)->textContent);
        } else {
            // 尝试从其他可能的简介位置获取
            $introNodes = $xpath->query('//span[@class="col-pd text-collapse content"]');
            if ($introNodes->length > 0) {
                $drama['intro'] = trim($introNodes->item(0)->textContent);
            } else {
                // 尝试从包含"简介"的区域获取
                $introNodes = $xpath->query('//*[contains(@class, "content") and contains(text(), "导演")]');
                if ($introNodes->length > 0) {
                    $drama['intro'] = trim($introNodes->item(0)->textContent);
                }
            }
        }
        
        // 提取分类标签 - 从包含"分类："的span后面获取
        $tagNodes = $xpath->query('//span[@class="text-muted" and contains(text(), "分类：")]/following-sibling::text()[1]');
        if ($tagNodes->length > 0) {
            $drama['tag'] = trim($tagNodes->item(0)->nodeValue);
        } else {
            // 尝试从span标签中获取
            $tagNodes = $xpath->query('//span[@class="text-muted" and contains(text(), "类别")]/following-sibling::span//a');
            if ($tagNodes->length > 0) {
                $tagList = [];
                foreach ($tagNodes as $tagNode) {
                    $tagName = trim($tagNode->textContent);
                    if (!empty($tagName)) {
                        $tagList[] = $tagName;
                    }
                }
                $drama['tag'] = implode(',', $tagList);
            }
        }
        
        // 提取更新状态作为集数信息 - 从更新状态获取
        $updateNodes = $xpath->query('//div[@class="myui-content__thumb"]');
        if ($updateNodes->length > 0) {
            $drama['conerMemo'] = trim($updateNodes->item(0)->textContent);
        }
        
        echo "提取到剧集: {$drama['name']}\n";
        return $drama;
    }
    
    /**
     * 下载并压缩图片
     */
    private function downloadAndCompressImage($imageUrl, $filename) {
        if (empty($imageUrl)) {
            return null;
        }
        
        // 确保目录存在
        if (!is_dir($this->imageDir)) {
            mkdir($this->imageDir, 0755, true);
        }
        
        // 获取图片扩展名
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'jpg'; // 默认扩展名
        }
        
        // 物理路径用于保存文件
        $localPath = $this->imageDir . $filename . '.' . $extension;
        // 数据库路径用于cover字段
        $coverPath = $this->imgs . $filename . '.' . $extension;
        
        // 下载图片
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $imageData !== false) {
            // 保存原图到物理路径
            file_put_contents($localPath, $imageData);
            
            // 压缩图片
            $compressedPath = $this->compressImage($localPath);
            
            if ($compressedPath) {
                // 删除原图，保留压缩后的图片
                if ($compressedPath !== $localPath) {
                    unlink($localPath);
                }
                // 返回cover字段路径格式：/thaiandtaiwanese/czmaigao/filename.extension
                // 从压缩后的文件名中提取文件名部分
                $compressedFilename = basename($compressedPath);
                return $this->imgs . $compressedFilename;
            }
        }
        
        return null;
    }
    
    /**
     * 压缩图片
     */
    private function compressImage($imagePath) {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return null;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // 创建图片资源
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($imagePath);
                break;
            default:
                return null;
        }
        
        if (!$source) {
            return null;
        }
        
        // 计算新尺寸（最大宽度400px）
        $maxWidth = 400;
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = ($height * $maxWidth) / $width;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // 创建新图片
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // 处理透明背景
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);
        }
        
        // 缩放图片
        imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // 保存压缩后的图片
        $compressedPath = str_replace('.' . pathinfo($imagePath, PATHINFO_EXTENSION), '_compressed.jpg', $imagePath);
        $result = imagejpeg($newImage, $compressedPath, 80);
        
        // 清理资源
        imagedestroy($source);
        imagedestroy($newImage);
        
        return $result ? $compressedPath : null;
    }
    
    /**
     * 检查剧集是否已存在
     */
    private function dramaExists($name) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->dbConfig['table']} WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * 插入剧集数据（只插入新数据，不更新已存在的）
     */
    private function insertOrUpdateDrama($drama) {
        if ($this->dramaExists($drama['name'])) {
            // 剧集已存在，完全跳过处理
            echo "剧名已存在，跳过处理: {$drama['name']}\n";
            return true;
        } else {
            // 剧集不存在，插入新记录
            $sql = "INSERT INTO {$this->dbConfig['table']} (
                catetype, name, years, cover, detailUrl, quarklink, oneselfquarklink, 
                baidulink, oneselfbaidulink, tag, crew, intro, conerMemo, director, area
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            try {
                $stmt = $this->pdo->prepare($sql);
                $result = $stmt->execute([
                    $drama['catetype'],
                    $drama['name'],
                    $drama['years'],
                    $drama['cover'],
                    $drama['detailUrl'],
                    $drama['quarklink'],
                    $drama['oneselfquarklink'],
                    $drama['baidulink'],
                    $drama['oneselfbaidulink'],
                    $drama['tag'],
                    $drama['crew'],
                    $drama['intro'],
                    $drama['conerMemo'],
                    $drama['director'],
                    $drama['area']
                ]);
                
                if ($result) {
                    echo "成功插入剧集: {$drama['name']}\n";
                    return true;
                }
            } catch (PDOException $e) {
                echo "插入失败: {$drama['name']}, 错误: " . $e->getMessage() . "\n";
            }
        }
        
        return false;
    }
    
    /**
     * 主执行方法
     */
    public function run($startPage = 1, $endPage = 17) {
        echo "开始爬取奇优影院泰剧数据，页面范围: $startPage - $endPage\n";
        
        $totalProcessed = 0;
        $totalInserted = 0;
        $totalFailed = 0;
        
        for ($page = $startPage; $page <= $endPage; $page++) {
            echo "\n=== 处理第 $page 页 ===\n";
            
            $dramaLinks = $this->getDramaLinksFromPage($page);
            
            foreach ($dramaLinks as $link) {
                $totalProcessed++;
                
                $dramaData = $this->extractDramaDetails($link);
                if ($dramaData && !empty($dramaData['name'])) {
                    if ($this->insertOrUpdateDrama($dramaData)) {
                        $totalInserted++;
                    }
                } else {
                    $totalFailed++;
                    echo "提取失败: $link\n";
                }
                
                // 添加延迟避免过于频繁的请求
                sleep(rand(2, 4));
            }
        }
        
        echo "\n=== 爬取完成 ===\n";
        echo "总处理: $totalProcessed 个剧集\n";
        echo "成功插入: $totalInserted 个\n";
        echo "失败: $totalFailed 个\n";
    }
}

// 主程序执行
if (php_sapi_name() === 'cli') {
    $startPage = isset($argv[1]) ? intval($argv[1]) : 1;
    $endPage = isset($argv[2]) ? intval($argv[2]) : 17;
    
    $scraper = new CzmaigaoWebScraper();
    $scraper->run($startPage, $endPage);
} else {
    echo "请在命令行中运行此脚本\n";
    echo "用法: php czmaigaoweb.php [起始页] [结束页]\n";
    echo "示例: php czmaigaoweb.php 1 10\n";
}
?>