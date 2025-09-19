<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use QL\QueryList;
use Exception;

class UpdateCoverBrowse extends Command
{
    protected function configure()
    {
        $this->setName('update:cover-browse')
             ->setDescription('通过浏览分类页面更新cover和image字段');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始通过浏览方式更新cover和image字段...');
        
        // 查询需要更新的记录
        $records = Db::table('koreanshort')
            ->where('sid', 'like', '%2kor%')
            ->where(function($query) {
                $query->where('cover', '')->whereOr('cover', 'null')->whereOr('cover', null);
            })
            ->field('id,name,sid')
            ->select()
            ->toArray();
            
        $total = count($records);
        $output->writeln("找到 {$total} 条需要更新的记录");
        
        if ($total == 0) {
            $output->writeln('没有需要更新的记录');
            return;
        }
        
        // 创建2korimg目录
        $imageDir = './public/upload/2korimg/';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
            $output->writeln("创建目录: {$imageDir}");
        }
        
        // 获取2kor.com的所有详情页链接
        $allDetailUrls = $this->getAllDetailUrls($output);
        $output->writeln("从2kor.com获取到 " . count($allDetailUrls) . " 个详情页链接");
        
        $processed = 0;
        $updated = 0;
        
        foreach ($records as $record) {
            $processed++;
            $output->writeln("[{$processed}/{$total}] 处理记录ID: {$record['id']}, 名称: {$record['name']}");
            
            // 在所有详情页中查找匹配的
            $matchedUrl = $this->findMatchingDetailUrl($record['name'], $allDetailUrls, $output);
            
            if ($matchedUrl) {
                $imageUrl = $this->getImageFromDetail($matchedUrl, $output);
                
                if ($imageUrl) {
                    $localImagePath = $this->downloadAndCompressImage($imageUrl, $record['id'], $output);
                    
                    if ($localImagePath) {
                        // 更新数据库
                        Db::table('koreanshort')
                            ->where('id', $record['id'])
                            ->update([
                                'cover' => $localImagePath,
                                'image' => $localImagePath
                            ]);
                        
                        $updated++;
                        $output->writeln("-> [成功] 已更新封面图片: {$localImagePath}");
                    }
                } else {
                    $output->writeln("-> [跳过] 未找到封面图片");
                }
            } else {
                $output->writeln("-> [跳过] 未找到匹配的详情页");
            }
            
            // 每处理10条记录休息一下
            if ($processed % 10 == 0) {
                sleep(2);
                $output->writeln("-> [休息] 已处理 {$processed} 条记录，休息2秒...");
            }
        }
        
        $output->writeln("\n更新完成！");
        $output->writeln("总记录数: {$total}");
        $output->writeln("已处理: {$processed}");
        $output->writeln("成功更新: {$updated}");
    }
    
    /**
     * 获取2kor.com所有详情页链接
     */
    private function getAllDetailUrls($output)
    {
        $allUrls = [];
        
        // 分类页面URL
        $categoryUrls = [
            'https://2kor.com/list/1---.html', // 韩剧
            'https://2kor.com/list/3---.html', // 韩国电影
            'https://2kor.com/list/4---.html'  // 韩国综艺
        ];
        
        foreach ($categoryUrls as $categoryUrl) {
            $output->writeln("-> [浏览] {$categoryUrl}");
            
            try {
                $response = QueryList::get($categoryUrl, [], [
                    'timeout' => 30,
                    'verify' => false,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ]);
                
                // 获取详情页链接和标题
                $detailLinks = $response->find('a[href*="detail"]');
                $detailLinks->each(function($item) use (&$allUrls) {
                    $href = $item->attr('href');
                    $text = trim($item->text());
                    
                    if ($href && !empty($text) && strlen($text) > 2) {
                        $fullUrl = 'https://2kor.com' . $href;
                        $allUrls[$fullUrl] = $text; // 使用URL作为key，标题作为value
                    }
                });
                
                $output->writeln("-> [获取] 从该分类获取到 " . $detailLinks->count() . " 个链接");
                
            } catch (Exception $e) {
                $output->writeln("-> [错误] 获取分类页失败: " . $e->getMessage());
            }
            
            sleep(1); // 避免请求过快
        }
        
        // 也从主页获取一些
        try {
            $output->writeln("-> [浏览] 主页");
            $response = QueryList::get('https://2kor.com/', [], [
                'timeout' => 30,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $detailLinks = $response->find('a[href*="detail"]');
            $detailLinks->each(function($item) use (&$allUrls) {
                $href = $item->attr('href');
                $text = trim($item->text());
                
                if ($href && !empty($text) && strlen($text) > 2) {
                    $fullUrl = 'https://2kor.com' . $href;
                    $allUrls[$fullUrl] = $text;
                }
            });
            
            $output->writeln("-> [获取] 从主页获取到 " . $detailLinks->count() . " 个链接");
            
        } catch (Exception $e) {
            $output->writeln("-> [错误] 获取主页失败: " . $e->getMessage());
        }
        
        return $allUrls;
    }
    
    /**
     * 在详情页链接中查找匹配的
     */
    private function findMatchingDetailUrl($name, $allDetailUrls, $output)
    {
        $name = trim($name);
        $bestMatch = null;
        $bestScore = 0;
        
        // 简繁体转换
        $traditionalName = $this->convertToTraditional($name);
        $searchNames = [$name, $traditionalName];
        
        foreach ($allDetailUrls as $url => $title) {
            foreach ($searchNames as $searchName) {
                $score = $this->calculateMatchScore($searchName, $title);
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $url;
                }
            }
        }
        
        if ($bestMatch && $bestScore >= 5) { // 设置最低匹配分数
            $output->writeln("-> [匹配] 找到匹配页面 (分数: {$bestScore}): {$bestMatch}");
            return $bestMatch;
        }
        
        return null;
    }
    
    /**
     * 计算匹配分数
     */
    private function calculateMatchScore($searchName, $title)
    {
        $score = 0;
        
        // 完全匹配
        if ($searchName === $title) {
            return 100;
        }
        
        // 包含匹配
        if (stripos($title, $searchName) !== false) {
            $score += 50;
        }
        
        if (stripos($searchName, $title) !== false) {
            $score += 30;
        }
        
        // 分词匹配
        $searchWords = preg_split('/[\s\-_]+/', $searchName);
        $titleWords = preg_split('/[\s\-_]+/', $title);
        
        foreach ($searchWords as $word) {
            if (strlen($word) > 1) {
                foreach ($titleWords as $titleWord) {
                    if (stripos($titleWord, $word) !== false || stripos($word, $titleWord) !== false) {
                        $score += 5;
                    }
                }
            }
        }
        
        return $score;
    }
    
    /**
     * 从详情页获取图片
     */
    private function getImageFromDetail($detailUrl, $output)
    {
        try {
            $response = QueryList::get($detailUrl, [], [
                'timeout' => 30,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            // 尝试多种图片选择器
            $selectors = [
                '.poster img',
                '.cover img',
                '.movie-poster img',
                '.detail-poster img',
                '.thumb img',
                'img[src*="poster"]',
                'img[src*="cover"]',
                'img[src*="thumb"]',
                '.detail-info img',
                '.movie-info img',
                'img[alt*="海报"]',
                'img[alt*="封面"]'
            ];
            
            foreach ($selectors as $selector) {
                $img = $response->find($selector);
                if ($img->count() > 0) {
                    $src = $img->first()->attr('src');
                    if ($src) {
                        if (!str_starts_with($src, 'http')) {
                            $src = 'https://2kor.com' . $src;
                        }
                        $output->writeln("-> [图片] 找到图片: {$src}");
                        return $src;
                    }
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $output->writeln("-> [错误] 获取详情页失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 下载并压缩图片
     */
    private function downloadAndCompressImage($imageUrl, $recordId, $output)
    {
        try {
            $imageData = file_get_contents($imageUrl);
            if (!$imageData) {
                return null;
            }
            
            $filename = $recordId . '_' . time() . '.jpg';
            $localPath = './public/upload/2korimg/' . $filename;
            
            // 创建图片资源
            $image = imagecreatefromstring($imageData);
            if (!$image) {
                return null;
            }
            
            // 获取原始尺寸
            $width = imagesx($image);
            $height = imagesy($image);
            
            // 计算新尺寸（最大300x400）
            $maxWidth = 300;
            $maxHeight = 400;
            
            if ($width > $maxWidth || $height > $maxHeight) {
                $ratio = min($maxWidth / $width, $maxHeight / $height);
                $newWidth = intval($width * $ratio);
                $newHeight = intval($height * $ratio);
                
                $newImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                
                imagejpeg($newImage, $localPath, 80);
                imagedestroy($newImage);
            } else {
                imagejpeg($image, $localPath, 80);
            }
            
            imagedestroy($image);
            
            return '/upload/2korimg/' . $filename;
            
        } catch (Exception $e) {
            $output->writeln("-> [错误] 下载图片失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 简体转繁体
     */
    private function convertToTraditional($text)
    {
        $map = [
            '暴君' => '暴君',
            '厨师' => '廚師',
            '杀手' => '殺手',
            '们' => '們',
            '哈尔滨' => '哈爾濱',
            '被不良少年盯上' => '被不良少年盯上',
            '超级巨星' => '超級巨星',
            '柳白' => '柳白',
            '华丽' => '華麗',
            '日子' => '日子',
            '记忆' => '記憶',
            '回忆' => '回憶',
            '爱在异域' => '愛在異域',
            '单相思' => '單相思',
            '卷卷' => '卷卷',
            '初恋' => '初戀',
            '认识' => '認識',
            '哥哥' => '哥哥',
            '惊人' => '驚人',
            '星期六' => '星期六'
        ];
        
        return strtr($text, $map);
    }
}