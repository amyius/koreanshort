<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use QL\QueryList;
use think\facade\Filesystem;

class UpdateCover extends Command
{
    protected function configure()
    {
        $this->setName('update:cover')
             ->setDescription('更新2kor记录的封面图片');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始更新cover和image字段...');
        
        // 查询cover字段为空的2kor记录
        $records = Db::table('koreanshort')
            ->where('sid', 'like', '%2kor%')
            ->where('cover', null)
            ->select()
            ->toArray();
        
        $totalCount = count($records);
        $output->writeln("找到 {$totalCount} 条需要更新封面的记录");
        
        if ($totalCount === 0) {
            $output->writeln('没有需要更新的记录');
            return;
        }
        
        // 创建2korimg目录
        $imageDir = './public/upload/2korimg/';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
            $output->writeln("创建目录: {$imageDir}");
        }
        
        foreach ($records as $index => $record) {
            // 每处理10条记录输出进度
            if ($index > 0 && $index % 10 === 0) {
                $output->writeln("-> [进度] 已处理 {$index} 条记录");
            }
            
            $output->writeln("[{$index}/{$totalCount}] 处理记录ID: {$record['id']}, 名称: {$record['name']}");
            
            try {
                // 尝试简体和繁体两种搜索
                $searchNames = [$record['name']];
                
                // 添加简繁体转换（简单映射）
                $traditionalName = $this->convertToTraditional($record['name']);
                if ($traditionalName !== $record['name']) {
                    $searchNames[] = $traditionalName;
                }
                
                $firstResult = null;
                $searchResponse = null;
                
                // 搜索功能不可用，使用分类页面查找
                $detailUrl = $this->findDetailPageInCategories($record['name'], $output);
                if (!$detailUrl) {
                    // 如果搜索失败，尝试繁体名称
                    $traditionalName = $this->convertToTraditional($record['name']);
                    if ($traditionalName !== $record['name']) {
                        $detailUrl = $this->findDetailPageInCategories($traditionalName, $output);
                    }
                }
                $firstResult = null;
                if ($detailUrl) {
                    $firstResult = parse_url($detailUrl, PHP_URL_PATH);
                    $output->writeln("-> [找到] 匹配的详情页: {$detailUrl}");
                }
                
                if (!$firstResult) {
                    $output->writeln("-> [跳过] 未找到匹配的详情页");
                    continue;
                }
                
                // 访问详情页
                $detailUrl = 'https://2kor.com' . $firstResult;
                $output->writeln("-> [详情] {$detailUrl}");
                
                $detailResponse = QueryList::get($detailUrl, [], [
                    'timeout' => 30,
                    'verify' => false,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ]);
                
                // 查找封面图片 - 根据2kor.com实际结构
                $coverImg = $detailResponse->find('img[src*="pic.2kor.com/picd"]')->attr('src');
                if (!$coverImg) {
                    // 尝试其他可能的选择器
                    $coverImg = $detailResponse->find('.poster img, .cover img, .thumbnail img')->attr('src');
                }
                if (!$coverImg) {
                    // 查找所有图片，排除loading.gif和cast-avatar
                    $allImages = $detailResponse->find('img');
                    foreach ($allImages as $img) {
                        $src = $img->attr('src');
                        $class = $img->attr('class');
                        if ($src && !strpos($src, 'loading.gif') && !strpos($class, 'cast-avatar')) {
                            $coverImg = $src;
                            break;
                        }
                    }
                }
                
                if (!$coverImg) {
                    $output->writeln("-> [跳过] 未找到封面图片");
                    continue;
                }
                
                // 处理相对路径和协议
                if (strpos($coverImg, '//') === 0) {
                    // 处理//pic.2kor.com格式
                    $coverImg = 'https:' . $coverImg;
                } elseif (strpos($coverImg, 'http') !== 0) {
                    // 处理相对路径
                    $coverImg = 'https://2kor.com' . $coverImg;
                }
                
                $output->writeln("-> [图片] {$coverImg}");
                
                // 下载图片
                $imageContent = file_get_contents($coverImg);
                if (!$imageContent) {
                    $output->writeln("-> [错误] 图片下载失败");
                    continue;
                }
                
                // 生成文件名
                $extension = pathinfo(parse_url($coverImg, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (!$extension) {
                    $extension = 'jpg';
                }
                $filename = 'cover_' . $record['id'] . '_' . time() . '.' . $extension;
                $filepath = $imageDir . $filename;
                
                // 保存图片
                if (file_put_contents($filepath, $imageContent)) {
                    $output->writeln("-> [保存] {$filepath}");
                    
                    // 压缩图片（如果是JPEG）
                    if (in_array(strtolower($extension), ['jpg', 'jpeg'])) {
                        $this->compressImage($filepath, $filepath, 80);
                        $output->writeln("-> [压缩] 图片已压缩");
                    }
                    
                    // 更新数据库
                    $coverPath = '/upload/2korimg/' . $filename;
                    $updateResult = Db::table('koreanshort')
                        ->where('id', $record['id'])
                        ->update([
                            'cover' => $coverPath,
                            'image' => $coverPath
                        ]);
                    
                    if ($updateResult) {
                        $output->writeln("-> [更新] 数据库更新成功");
                    } else {
                        $output->writeln("-> [错误] 数据库更新失败");
                    }
                } else {
                    $output->writeln("-> [错误] 图片保存失败");
                }
                
            } catch (\Exception $e) {
                $output->writeln("-> [错误] " . $e->getMessage());
            }
            
            // 添加延迟避免请求过快
            sleep(1);
        }
        
        $output->writeln('封面更新完成！');
    }
    
    /**
     * 简繁体转换（简单映射）
     */
    private function convertToTraditional($text)
    {
        $map = [
            '哈尔滨' => '哈爾濱',
            '杀手们' => '殺手們',
            '侵犯' => '侵犯',
            '康奈尔' => '康奈爾',
            '暴君' => '暴君',
            '厨师' => '廚師',
            '让女人哭泣' => '讓女人哭泣',
            '以安医生' => '以安醫生',
            '医生' => '醫生',
            '国家' => '國家',
            '代表' => '代表',
            '队长' => '隊長',
            '团队' => '團隊',
            '电视' => '電視',
            '剧' => '劇',
            '电影' => '電影',
            '爱情' => '愛情',
            '战争' => '戰爭',
            '历史' => '歷史',
            '悬疑' => '懸疑',
            '犯罪' => '犯罪',
            '动作' => '動作',
            '喜剧' => '喜劇',
            '恐怖' => '恐怖',
            '科幻' => '科幻',
            '奇幻' => '奇幻',
            '家庭' => '家庭',
            '青春' => '青春',
            '校园' => '校園',
            '职场' => '職場',
            '商战' => '商戰',
            '复仇' => '復仇',
            '重生' => '重生',
            '穿越' => '穿越',
            '宫廷' => '宮廷',
            '古装' => '古裝',
            '现代' => '現代',
            '都市' => '都市',
            '农村' => '農村',
            '军事' => '軍事',
            '警察' => '警察',
            '律师' => '律師',
            '检察官' => '檢察官',
            '法官' => '法官',
            '记者' => '記者',
            '编剧' => '編劇',
            '导演' => '導演',
            '演员' => '演員',
            '制片' => '製片',
            '监制' => '監製'
        ];
        
        $result = $text;
        foreach ($map as $simplified => $traditional) {
            $result = str_replace($simplified, $traditional, $result);
        }
        
        return $result;
    }
    
    /**
     * 压缩图片
     */
    private function compressImage($source, $destination, $quality = 80)
    {
        $info = getimagesize($source);
        
        if ($info['mime'] == 'image/jpeg') {
            $image = imagecreatefromjpeg($source);
        } elseif ($info['mime'] == 'image/png') {
            $image = imagecreatefrompng($source);
        } else {
            return false;
        }
        
        // 保存压缩后的图片
        if ($info['mime'] == 'image/jpeg') {
            imagejpeg($image, $destination, $quality);
        } elseif ($info['mime'] == 'image/png') {
            imagepng($image, $destination, 9);
        }
        
        imagedestroy($image);
        return true;
    }
    
    /**
     * 通过搜索功能查找详情页
     */
    private function findDetailPageFromSearch($movieName, $output)
    {
        $output->writeln("-> [搜索] 在2kor.com搜索: {$movieName}");
        
        try {
            // 使用POST请求搜索
            $response = QueryList::post('https://2kor.com/search/', [
                'show' => 'searchkey',
                'keyboard' => $movieName
            ], [
                'timeout' => 30,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Referer' => 'https://2kor.com/'
                ]
            ]);
            
            // 查找搜索结果中的详情页链接
            $searchResults = $response->find('.box .list ul li');
            
            foreach ($searchResults as $item) {
                $titleLink = $item->find('p a[href*="detail"]')->first();
                if (!$titleLink) continue;
                
                $href = $titleLink->attr('href');
                $title = trim($titleLink->text());
                
                if (!empty($title)) {
                    $similarity = $this->calculateSimilarity($movieName, $title);
                    
                    if ($similarity > 0.6) {
                        // 返回完整URL
                        if (strpos($href, 'http') === 0) {
                            $detailUrl = $href;
                        } else {
                            $detailUrl = 'https://2kor.com' . $href;
                        }
                        $output->writeln("-> [匹配] 搜索找到匹配: {$title} (相似度: {$similarity})");
                        return $detailUrl;
                    }
                }
            }
            
            $output->writeln("-> [搜索] 未找到匹配的搜索结果");
            
        } catch (\Exception $e) {
            $output->writeln("-> [错误] 搜索失败: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * 通过搜索功能查找详情页（备用方法）
     */
    private function findDetailPageBySearch($movieName) {
        try {
            // 使用POST方式搜索
            $response = QueryList::post('https://2kor.com/search/', [
                'show' => 'searchkey',
                'keyboard' => $movieName
            ], [
                'timeout' => 30,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Referer' => 'https://2kor.com/',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            
            $movieItems = $response->find('.box .list ul li');
            
            foreach ($movieItems as $item) {
                $titleLink = $item->find('p a[href*="detail"]')->first();
                if ($titleLink) {
                    $title = trim($titleLink->text());
                    $href = $titleLink->attr('href');
                    
                    $similarity = $this->calculateSimilarity($movieName, $title);
                    if ($similarity > 0.6) {
                        return 'https://2kor.com' . $href;
                    }
                }
            }
            
            // 如果简体搜索失败，尝试繁体
            $traditionalName = $this->convertToTraditional($movieName);
            if ($traditionalName !== $movieName) {
                $response = QueryList::post('https://2kor.com/search/', [
                    'show' => 'searchkey',
                    'keyboard' => $traditionalName
                ], [
                    'timeout' => 30,
                    'verify' => false,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Referer' => 'https://2kor.com/',
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ]
                ]);
                
                $movieItems = $response->find('.box .list ul li');
                
                foreach ($movieItems as $item) {
                    $titleLink = $item->find('p a[href*="detail"]')->first();
                    if ($titleLink) {
                        $title = trim($titleLink->text());
                        $href = $titleLink->attr('href');
                        
                        $similarity = $this->calculateSimilarity($movieName, $title);
                        if ($similarity > 0.6) {
                            return 'https://2kor.com' . $href;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "搜索失败: " . $e->getMessage() . "\n";
        }
        
        return null;
    }
    
    /**
     * 通过分类页面查找详情页
     */
    private function findDetailPageInCategories($movieName, $output)
    {
        $output->writeln("-> [分类] 在分类页面查找: {$movieName}");
        
        // 分类页面URL
        $categoryUrls = [
            'https://2kor.com/list/1---.html', // 韩剧
            'https://2kor.com/list/3---.html', // 韩国电影
            'https://2kor.com/list/4---.html', // 韩国综艺
            'https://2kor.com/'                // 首页
        ];
        
        foreach ($categoryUrls as $categoryUrl) {
            try {
                $output->writeln("-> [浏览] {$categoryUrl}");
                
                $response = QueryList::get($categoryUrl, [], [
                    'timeout' => 30,
                    'verify' => false,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ]);
                
                // 获取所有详情页链接
                $detailLinks = $response->find('a[href*="detail"]');
                
                foreach ($detailLinks as $link) {
                    $href = $link->attr('href');
                    $title = trim($link->text());
                    
                    if (!empty($title) && strlen($title) > 2) {
                        $similarity = $this->calculateSimilarity($movieName, $title);
                        
                        if ($similarity > 0.6) {
                            // 返回完整URL
                            if (strpos($href, 'http') === 0) {
                                $detailUrl = $href;
                            } else {
                                $detailUrl = 'https://2kor.com' . $href;
                            }
                            $output->writeln("-> [匹配] 分类页找到匹配: {$title} (相似度: {$similarity})");
                            return $detailUrl;
                        }
                    }
                }
                
                sleep(1); // 避免请求过快
                
            } catch (\Exception $e) {
                $output->writeln("-> [错误] 访问分类页失败: " . $e->getMessage());
            }
        }
        
        $output->writeln("-> [分类] 未在分类页面找到匹配结果");
        return null;
    }
    
    /**
     * 计算字符串相似度
     */
    private function calculateSimilarity($str1, $str2)
    {
        // 移除空格和特殊字符
        $str1 = preg_replace('/[\s\-_]+/', '', $str1);
        $str2 = preg_replace('/[\s\-_]+/', '', $str2);
        
        // 转换为小写
        $str1 = mb_strtolower($str1, 'UTF-8');
        $str2 = mb_strtolower($str2, 'UTF-8');
        
        // 简繁体转换
        $str1_converted = $this->convertToTraditional($str1);
        $str2_converted = $this->convertToTraditional($str2);
        
        // 如果完全匹配（原文或转换后）
        if ($str1 === $str2 || $str1_converted === $str2 || $str1 === $str2_converted || $str1_converted === $str2_converted) {
            return 1.0;
        }
        
        // 如果一个字符串包含另一个（原文或转换后）
        if (mb_strpos($str1, $str2, 0, 'UTF-8') !== false || mb_strpos($str2, $str1, 0, 'UTF-8') !== false ||
            mb_strpos($str1_converted, $str2, 0, 'UTF-8') !== false || mb_strpos($str2, $str1_converted, 0, 'UTF-8') !== false ||
            mb_strpos($str1, $str2_converted, 0, 'UTF-8') !== false || mb_strpos($str2_converted, $str1, 0, 'UTF-8') !== false ||
            mb_strpos($str1_converted, $str2_converted, 0, 'UTF-8') !== false || mb_strpos($str2_converted, $str1_converted, 0, 'UTF-8') !== false) {
            return 0.8;
        }
        
        // 使用Levenshtein距离计算相似度（取最高相似度）
        $similarities = [
            $this->calculateLevenshteinSimilarity($str1, $str2),
            $this->calculateLevenshteinSimilarity($str1_converted, $str2),
            $this->calculateLevenshteinSimilarity($str1, $str2_converted),
            $this->calculateLevenshteinSimilarity($str1_converted, $str2_converted)
        ];
        
        return max($similarities);
    }
    
    /**
     * 计算Levenshtein相似度
     */
    private function calculateLevenshteinSimilarity($str1, $str2)
    {
        $len1 = mb_strlen($str1, 'UTF-8');
        $len2 = mb_strlen($str2, 'UTF-8');
        $maxLen = max($len1, $len2);
        
        if ($maxLen == 0) {
            return 1.0;
        }
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $maxLen);
    }
}