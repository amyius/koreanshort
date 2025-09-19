<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\facade\Log;
use QL\QueryList;
use app\model\Koreanbackup; // 确保模型名称与您的表对应，这里假设为 Koreanbackup

class koreancrew extends Command
{
    private $baseUrl = 'https://2kor.com';
    // 定义要爬取的基础分类URL
    private $targetSections = [
        'korean'  => '/list/1---',     // 韩剧分类
        'movie'   => '/list/3---',     // 韩国电影分类
        'zongyi'  => '/list/4---',     // 韩国综易分类
    ];


    protected function configure()
    {
        $this->setName('koreancrew')
            ->setDescription('Crawl drama data from 2kor.com and avoid duplicates.')
            ->addOption('max_page', 'p', Option::VALUE_OPTIONAL, 'Maximum pages to crawl for each category', 10) // 添加一个选项，控制最大爬取页数
            ->addOption('start_page', 's', Option::VALUE_OPTIONAL, 'Starting page number for crawling', 1); // 添加起始页码选项
    }

    protected function execute(Input $input, Output $output)
    {
        $maxPage = $input->getOption('max_page');
        $startPage = $input->getOption('start_page');
        $output->writeln("任务开始：精确爬取韩剧四大分区数据... (从第 {$startPage} 页开始，每个分区最多爬取 {$maxPage} 页)");

        // 遍历各个分区爬取韩剧数据
         foreach ($this->targetSections as $sectionName => $sectionUrl) {
             $output->writeln("--- 开始处理分区: [{$sectionName}] ---");
             for ($page = $startPage; $page <= $maxPage; $page++) {
                 $listUrl = $this->baseUrl . $sectionUrl . $page . '.html';
                 $output->writeln("正在爬取列表页: {$listUrl}");

                 try {
                     $listData = $this->scrapeListPage($listUrl);

                     if (empty($listData)) {
                         $output->writeln("分区 [{$sectionName}] 在第 {$page} 页没有数据，结束该分区。");
                         break; // 当前分区已到底，跳到下一个分区
                     }

                     foreach ($listData as $item) {
                         // 生成唯一的sid
                         $sid = '2kor_' . md5($item['detail_url']);

                         // 获取详情页信息
                         $detailInfo = $this->scrapeDetailPage($this->baseUrl . $item['detail_url']);

                         // 组合并格式化数据
                         $fullData = array_merge($item, $detailInfo);
                         // 将分区名存入type字段
                         $formattedData = $this->formatData($fullData, $sid, $sectionName);

                         // 检查是否已存在记录
                         $existingRecord = Koreanbackup::where('name', $item['name'])->find();
                         
                         if ($existingRecord) {
                             // 更新已存在的记录
                             $existingRecord->save($formattedData);
                             $output->writeln("-> [更新] '{$formattedData['name']}' 数据已更新。");
                         } else {
                             // 保存新记录到数据库
                             Koreanbackup::create($formattedData);
                             $output->writeln("-> [新增] '{$formattedData['name']}' 已入库。");
                         }

                         sleep(1); // 友好性延时
                     }
                 } catch (\Exception $e) {
                     $output->writeln("<error>爬取页面 {$listUrl} 失败: " . $e->getMessage() . "</error>");
                     Log::error("爬取失败: {$listUrl} | 错误: " . $e->getMessage());
                 }
             }
         }

        $output->writeln("--- 所有韩剧分区爬取完成！ ---");
        return 0;
    }

    /**
     * 爬取列表页数据
     */
     private function scrapeListPage($url)
     {
         $output = $this->output;
         $output->writeln("正在爬取URL: {$url}");
         
         try {
             $response = QueryList::get($url, [], ['timeout' => 30]);
             $title = $response->find('title')->text();
             $output->writeln("-> [成功] 页面标题: {$title}");
             
             // 解析韩剧列表项
             $items = [];
             $response->find('.list ul li')->each(function ($item) use (&$items) {
                 $name = $item->find('p a')->eq(0)->text();
                 $detailUrl = $item->find('p a')->eq(0)->attr('href');
                 $image = $item->find('a.tu.lazyload')->attr('data-original');
                 if (!$image) {
                     $image = $item->find('a.tu img')->attr('src');
                 }
                 // 处理图片URL，添加协议
                 if ($image && strpos($image, '//') === 0) {
                     $image = 'https:' . $image;
                 }
                 $episode = $item->find('.tip')->text();
                 
                 if ($name && $detailUrl) {
                     $items[] = [
                         'name' => trim($name),
                         'detail_url' => $detailUrl,
                         'image' => $image ?: '',
                         'year' => date('Y'), // 默认当前年份
                         'episode' => $episode ?: ''
                     ];
                 }
             });
             
             $output->writeln("找到 " . count($items) . " 个韩剧项目");
             return $items;
             
         } catch (\Exception $e) {
             $output->writeln("<error>请求失败: " . $e->getMessage() . "</error>");
             return [];
         }
      }

    /**
     * 爬取详情页数据
     */
    private function scrapeDetailPage($url)
    {
        $output = $this->output;
        $output->writeln("正在爬取详情页URL: {$url}");
        
        try {
            $response = QueryList::get($url, [], ['timeout' => 30]);
            $title = $response->find('title')->text();
            $output->writeln("-> [成功] 详情页标题: {$title}");
            
            // 解析详情页信息 - 根据2kor.com的HTML结构
            $intro = $response->find('.juqing')->text();
            if (!$intro) {
                $intro = $response->find('.detail .info dl:contains("劇情") dd')->text();
            }
            
            // 获取演员信息
            $crew = $response->find('.detail .info dl:contains("主演") dd')->text();
            if (!$crew) {
                $crew = $response->find('.detail .info dl dt:contains("主演")')->next('dd')->text();
            }
            
            // 获取导演信息 - 从演员表中提取
            $director = '';
            $response->find('.cast-card')->each(function($item) use (&$director) {
                $role = $item->find('.cast-role')->text();
                if (strpos($role, '導演') !== false) {
                    $director = $item->find('.cast-name')->text();
                }
            });
            
            // 获取分类信息
            $category = $response->find('.detail .info dl:contains("電視") dd')->text();
            if (!$category) {
                $category = '1'; // 默认分类
            }
            
            // 获取总集数信息 - 从状态信息中提取
            $totalEpisodes = '';
            $statusText = $response->find('.detail .info dl:contains("狀態") dd')->text();
            if (!$statusText) {
                $statusText = $response->find('.detail .info dl dt:contains("狀態")')->next('dd')->text();
            }
            // 从状态文本中提取总集数，如"更新至第11集"中的"11"
            if ($statusText && preg_match('/第(\d+)集/', $statusText, $matches)) {
                $totalEpisodes = $matches[1];
            } elseif ($statusText && preg_match('/(\d+)集/', $statusText, $matches)) {
                $totalEpisodes = $matches[1];
            }
            
            // 获取更新时间作为publishTime字段
            $publishTime = '';
            $updateText = $response->find('.detail .info dl:contains("更新") dd')->text();
            if (!$updateText) {
                $updateText = $response->find('.detail .info dl:contains("上映") dd')->text();
            }
            if ($updateText && preg_match('/\d{4}-\d{2}-\d{2}/', $updateText, $matches)) {
                $publishTime = $matches[0] . ' 00:00:00'; // 转换为datetime格式
            }
            if (!$publishTime) {
                $publishTime = date('Y-m-d H:i:s'); // 默认当前时间
            }
            
            // 从publishTime中提取年份作为year字段
            $year = '';
            if (preg_match('/^(\d{4})/', $publishTime, $matches)) {
                $year = $matches[1];
            }
            if (!$year) {
                $year = date('Y'); // 默认当前年份
            }
            
            return [
                'intro' => trim($intro) ?: '暂无简介',
                'crew' => trim($crew) ?: '',
                'director' => trim($director) ?: '',
                'category' => trim($category) ?: '韩剧',
                'publishTime' => $publishTime,
                'year' => $year,
                'totalEpisodes' => $totalEpisodes ?: '',
                'conerMemo' => $totalEpisodes ?: '' // 总集数字段
            ];
            
        } catch (\Exception $e) {
            $output->writeln("<error>详情页请求失败: " . $e->getMessage() . "</error>");
            return [
                'intro' => '暂无简介',
                'crew' => '',
                'director' => '',
                'category' => '韩剧',
                'publishTime' => date('Y-m-d H:i:s'),
                'year' => date('Y'),
                'totalEpisodes' => ''
            ];
        }
    }

    /**
     * 格式化数据以符合数据库结构
     */
    private function formatData($data, $sid, $sectionName)
    {
        // 处理publishTime字段，确保是正确的datetime格式
        $publishTime = $data['publishTime'] ?? date('Y');
        if (is_numeric($publishTime) && strlen($publishTime) == 4) {
            // 如果是4位年份，转换为datetime格式
            $publishTime = $publishTime . '-01-01 00:00:00';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}/', $publishTime)) {
            // 如果不是标准datetime格式，使用当前时间
            $publishTime = date('Y-m-d H:i:s');
        }
        
        // 处理图片下载和压缩
        $localImagePath = $this->downloadAndCompressImage($data['image'], $data['name']);
        
        return [
            'sid' => $sid,
            'category' => $data['category'] ?? '1',
            'name' => $data['name'],
            'years' => $data['year'] ?? date('Y'),
            'image' => $localImagePath ?: $data['image'],
            'cover' => $localImagePath ?: $data['image'], 
            'quarklink' => null,
            'baidulink' => null,
            'crew' => $data['crew'] ?? '',
            'finished' => 1,
            'conerMemo' => $data['conerMemo'] ?? '', // 总集数字段
            'lastSerialNo' => $data['conerMemo'] ?: 0, // 最新集数
            'intro' => $data['intro'] ?? '',
            'publishTime' => $publishTime,
            'create_at' => date('Y-m-d H:i:s'), // 创建时间
            'type' => "score"
        ];      
    }

    /**
     * 提取年份
     */
    private function extractYear($text)
    {
        if (preg_match('/\d{4}/', $text, $matches)) {
            return $matches[0];
        }
        return date('Y');
    }

    /**
     * 清理文本
     */
    private function cleanText($text)
    {
        return trim(preg_replace('/^[^：]*：/', '', $text));
    }

    /**
     * 下载并压缩图片到本地
     */
    private function downloadAndCompressImage($imageUrl, $dramaName)
    {
        if (empty($imageUrl)) {
            return null;
        }
        
        try {
            // 确保upload目录存在
            $uploadDir = __DIR__ . '/../../public/upload/images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一的文件名
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'jpg'; // 默认扩展名
            }
            $fileName = md5($dramaName . $imageUrl) . '.' . $extension;
            $localPath = $uploadDir . $fileName;
            $relativePath = '/upload/images/' . $fileName;
            
            // 检查文件是否已存在
            if (file_exists($localPath)) {
                return $relativePath;
            }
            
            // 下载图片
            $imageData = file_get_contents($imageUrl, false, stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]));
            
            if ($imageData === false) {
                $this->output->writeln("<error>下载图片失败: {$imageUrl}</error>");
                return null;
            }
            
            // 保存原始图片
            file_put_contents($localPath, $imageData);
            
            // 压缩图片
            $this->compressImage($localPath, $extension);
            
            $this->output->writeln("-> [成功] 图片已下载并压缩: {$relativePath}");
            return $relativePath;
            
        } catch (\Exception $e) {
            $this->output->writeln("<error>处理图片失败: " . $e->getMessage() . "</error>");
            return null;
        }
    }
    
    /**
     * 压缩图片
     */
    private function compressImage($imagePath, $extension)
    {
        try {
            $maxWidth = 400;
            $maxHeight = 600;
            $quality = 80;
            
            // 获取原始图片信息
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                return false;
            }
            
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            
            // 如果图片已经很小，不需要压缩
            if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
                return true;
            }
            
            // 计算新尺寸
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);
            
            // 创建图片资源
            $sourceImage = null;
            switch (strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    $sourceImage = imagecreatefromjpeg($imagePath);
                    break;
                case 'png':
                    $sourceImage = imagecreatefrompng($imagePath);
                    break;
                case 'gif':
                    $sourceImage = imagecreatefromgif($imagePath);
                    break;
                case 'webp':
                    $sourceImage = imagecreatefromwebp($imagePath);
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) {
                return false;
            }
            
            // 创建新图片
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // 保持透明度（PNG）
            if (strtolower($extension) === 'png') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefill($newImage, 0, 0, $transparent);
            }
            
            // 重新采样
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            // 保存压缩后的图片
            switch (strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($newImage, $imagePath, $quality);
                    break;
                case 'png':
                    imagepng($newImage, $imagePath, 9);
                    break;
                case 'gif':
                    imagegif($newImage, $imagePath);
                    break;
                case 'webp':
                    imagewebp($newImage, $imagePath, $quality);
                    break;
            }
            
            // 释放内存
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            
            return true;
            
        } catch (\Exception $e) {
            $this->output->writeln("<error>压缩图片失败: " . $e->getMessage() . "</error>");
            return false;
        }
    }
}
