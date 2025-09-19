<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\facade\Log;
use QL\QueryList;
use app\model\Koreanbackup; // 确保模型名称与您的表对应，这里假设为 Koreanbackup

class koreanshort extends Command
{
    private $baseUrl = 'https://www.hanjukankan.com';
    // 定义要爬取的基础分类URL
    private $targetSections = [
        'korean_drama'  => '/xvs1xatxbtxctxdtxetxftxgtxhtatbtct',     // 最新韩剧
        'korean_movie'  => '/xvs2xatxbtxctxdtxetxftxgtxhtatbtct',     // 最新韩影
        'korean_variety' => '/xvs3xatxbtxctxdtxetxftxgtxhtatbtct',    // 最新韩综
    ];


    protected function configure()
    {
        $this->setName('koreanshort')
            ->setDescription('Crawl drama data from hanjutv.me and avoid duplicates.')
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
             
             // 动态获取总页数
             $totalPages = $this->getTotalPages($this->baseUrl . $sectionUrl . '1.html', $output);
             $actualMaxPage = min($maxPage, $totalPages);
             $output->writeln("检测到分区 [{$sectionName}] 总共有 {$totalPages} 页，将爬取 {$actualMaxPage} 页");
             
             for ($page = $startPage; $page <= $actualMaxPage; $page++) {
                 $listUrl = $this->baseUrl . $sectionUrl . $page . '.html';
                 $output->writeln("正在爬取列表页: {$listUrl}");

                 try {
                     $listData = $this->scrapeListPage($listUrl);

                     if (empty($listData)) {
                         $output->writeln("分区 [{$sectionName}] 在第 {$page} 页没有数据，结束该分区。");
                         break; // 当前分区已到底，跳到下一个分区
                     }

                     foreach ($listData as $item) {
                         // 核心去重逻辑：根据name字段去重
                         if (Koreanbackup::where('name', $item['name'])->count() > 0) {
                             $output->writeln("-> [跳过] '{$item['name']}' 已存在。");
                             continue;
                         }
                         
                         // 生成唯一的sid
                         $sid = 'hanjukankan_' . md5($item['detail_url']);

                         // 获取详情页信息
                         $detailInfo = $this->scrapeDetailPage($this->baseUrl . $item['detail_url']);

                         // 下载并压缩图片
                         if (!empty($item['image'])) {
                             $compressedImagePath = $this->downloadAndCompressImage($item['image'], $sid, $output);
                             if ($compressedImagePath) {
                                 $item['image'] = $compressedImagePath;
                             }
                         }

                         // 组合并格式化数据
                         $fullData = array_merge($item, $detailInfo);
                         // 将分区名存入type字段
                         $formattedData = $this->formatData($fullData, $sid, $sectionName);

                         // 保存到数据库
                         Koreanbackup::create($formattedData);
                         $output->writeln("-> [成功] 新剧 '{$formattedData['name']}' 已入库。");

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
             $response->find('.module-poster-item')->each(function ($item) use (&$items) {
                 $name = $item->find('.module-poster-item-title')->text();
                 $detailUrl = $item->attr('href');
                 $image = $item->find('.module-item-pic img')->attr('data-original');
                 if (!$image) {
                     $image = $item->find('.module-item-pic img')->attr('src');
                 }
                 $episode = $item->find('.module-item-note')->text();
                 
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
            
            // 解析详情页信息
            $intro = $response->find('.module-info-introduction-content, .vod_content')->text();
            if (!$intro) {
                $intro = $response->find('.module-info-item-content')->text();
            }
            
            // 获取演员信息
            $crew = $response->find('.module-info-item:contains("主演") .module-info-item-content')->text();
            if (!$crew) {
                $crew = $response->find('.module-info-item:contains("演员") .module-info-item-content')->text();
            }
            
            // 格式化crew字段，处理各种分隔符
            if ($crew) {
                // 将///替换为,
                $crew = str_replace('///', ',', $crew);
                
                // 将//替换为,
                $crew = str_replace('//', ',', $crew);
                
                // 将单个/替换为,
                $crew = str_replace('/', ',', $crew);
                
                // 去除多余的逗号
                $crew = preg_replace('/,+/', ',', $crew);
                
                // 去除首尾逗号和空格
                $crew = trim($crew, ', ');
                
                // 去除演员名字间多余的空格
                $crew = preg_replace('/\s*,\s*/', ',', $crew);
            }
            
            // 获取导演信息
            $director = $response->find('.module-info-item:contains("导演") .module-info-item-content')->text();
            
            // 获取分类信息
            $category = $response->find('.module-info-item:contains("类型") .module-info-item-content')->text();
            if (!$category) {
                $category = $response->find('.module-info-item:contains("分类") .module-info-item-content')->text();
            }
            
            // 获取更新时间作为publishTime字段
            $publishTime = '';
            $updateText = $response->find('.module-info-item:contains("更新") .module-info-item-content')->text();
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
                'year' => $year
            ];
            
        } catch (\Exception $e) {
            $output->writeln("<error>详情页请求失败: " . $e->getMessage() . "</error>");
            return [
                'intro' => '暂无简介',
                'crew' => '',
                'director' => '',
                'category' => '韩剧',
                'publishTime' => date('Y-m-d H:i:s'),
                'year' => date('Y')
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
        
        return [
            'sid' => $sid,
            'category' => $data['category'] ?? '1',
            'name' => $data['name'],
            'years' => $data['year'] ?? date('Y'),
            'image' => $data['image'],
            'cover' => $data['image'], 
            'quarklink' => null,
            'baidulink' => null,
            'crew' => $data['crew'] ?? '',
            'finished' => 1,
            'conerMemo' => '',
            'intro' => $data['intro'] ?? '',
            'publishTime' => $publishTime,
            'type' => "score", 
            'create_at' => date('Y-m-d H:i:s')
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
     * 获取分页总数
     */
    private function getTotalPages($url, $output)
    {
        try {
            $response = QueryList::get($url, [], ['timeout' => 30]);
            
            // 查找分页导航中的最大页码
            $maxPage = 1;
            
            // 专门针对hanjukankan.com的分页结构
            $response->find('#page .page-link')->each(function ($item) use (&$maxPage) {
                $href = $item->attr('href');
                $text = trim($item->text());
                $title = $item->attr('title');
                
                // 从链接href中提取页码 - 匹配所有分区的URL模式
                if (preg_match('/xvs[1-4]xatxbtxctxdtxetxftxgtxht(\d+)atbtct\.html/', $href, $matches)) {
                    $pageNum = intval($matches[1]);
                    if ($pageNum > $maxPage) {
                        $maxPage = $pageNum;
                    }
                }
                
                // 从title属性中提取页码
                if ($title && preg_match('/第(\d+)页/', $title, $matches)) {
                    $pageNum = intval($matches[1]);
                    if ($pageNum > $maxPage) {
                        $maxPage = $pageNum;
                    }
                }
                
                // 从文本中提取页码（纯数字）
                if (is_numeric($text) && intval($text) > $maxPage) {
                    $maxPage = intval($text);
                }
            });
            
            // 如果分页导航显示的页数较少，尝试二分查找真实的最大页数
            if ($maxPage <= 10) {
                $output->writeln("-> [分页] 分页导航显示页数较少({$maxPage})，尝试查找真实最大页数...");
                $realMaxPage = $this->findRealMaxPage($url, $maxPage, $output);
                if ($realMaxPage > $maxPage) {
                    $maxPage = $realMaxPage;
                    $output->writeln("-> [分页] 通过探测找到真实最大页码: {$maxPage}");
                }
            }
            
            // 如果上述方法没找到，尝试通用方法
            if ($maxPage <= 1) {
                $pageSelectors = [
                    '.myui-page a',
                    '.pagination a',
                    '.page-link',
                    '.page a',
                    'a[href*=".html"]'
                ];
                
                foreach ($pageSelectors as $selector) {
                    $response->find($selector)->each(function ($item) use (&$maxPage) {
                        $href = $item->attr('href');
                        $text = $item->text();
                        
                        // 从链接中提取页码
                        if (preg_match('/(\d+)\.html$/', $href, $matches)) {
                            $pageNum = intval($matches[1]);
                            if ($pageNum > $maxPage) {
                                $maxPage = $pageNum;
                            }
                        }
                        
                        // 从文本中提取页码
                        if (is_numeric($text) && intval($text) > $maxPage) {
                            $maxPage = intval($text);
                        }
                    });
                    
                    if ($maxPage > 1) {
                        break;
                    }
                }
            }
            
            $output->writeln("-> [分页] 最终检测到最大页码: {$maxPage}");
            return $maxPage;
            
        } catch (\Exception $e) {
            $output->writeln("<error>获取分页信息失败: " . $e->getMessage() . "</error>");
            return 1; // 默认返回1页
        }
    }
    
    /**
     * 通过二分查找确定真实的最大页数
     */
    private function findRealMaxPage($baseUrl, $minPage, $output)
    {
        // 从URL中提取分区标识
        if (!preg_match('/xvs([1-4])xatxbtxctxdtxetxftxgtxht/', $baseUrl, $matches)) {
            return $minPage;
        }
        
        $sectionId = $matches[1];
        $urlPattern = "https://www.hanjukankan.com/xvs{$sectionId}xatxbtxctxdtxetxftxgtxht{PAGE}atbtct.html";
        
        // 二分查找最大页数
        $left = $minPage;
        $right = 100; // 假设最大不超过100页
        $realMaxPage = $minPage;
        
        while ($left <= $right) {
            $mid = intval(($left + $right) / 2);
            $testUrl = str_replace('{PAGE}', $mid, $urlPattern);
            
            try {
                $testResponse = QueryList::get($testUrl, [], ['timeout' => 10]);
                $title = $testResponse->find('title')->text();
                
                // 检查页面是否有效（不是404或错误页面）
                if ($title && !strpos($title, '404') && !strpos($title, '错误')) {
                    $realMaxPage = $mid;
                    $left = $mid + 1;
                    $output->writeln("-> [探测] 第{$mid}页存在");
                } else {
                    $right = $mid - 1;
                    $output->writeln("-> [探测] 第{$mid}页不存在");
                }
            } catch (\Exception $e) {
                $right = $mid - 1;
                $output->writeln("-> [探测] 第{$mid}页访问失败");
            }
            
            // 避免过多请求
            usleep(200000); // 0.2秒延时
        }
        
        return $realMaxPage;
    }

    /**
     * 下载并压缩图片
     */
    private function downloadAndCompressImage($imageUrl, $sid, $output)
    {
        try {
            // 创建上传目录
            $uploadDir = dirname(__DIR__, 2) . '/public/upload/imgs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // 获取图片扩展名
            $imageInfo = pathinfo(parse_url($imageUrl, PHP_URL_PATH));
            $extension = isset($imageInfo['extension']) ? strtolower($imageInfo['extension']) : 'jpg';
            
            // 生成本地文件名
            $fileName = $sid . '.' . $extension;
            $localPath = $uploadDir . $fileName;
            $relativePath = '/upload/imgs/' . $fileName;

            // 如果文件已存在，直接返回路径
            if (file_exists($localPath)) {
                return $relativePath;
            }

            // 处理相对URL
            if (strpos($imageUrl, '//') === 0) {
                $imageUrl = 'https:' . $imageUrl;
            } elseif (strpos($imageUrl, '/') === 0) {
                $imageUrl = $this->baseUrl . $imageUrl;
            }

            // 下载图片
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $imageData = file_get_contents($imageUrl, false, $context);
            if ($imageData === false) {
                $output->writeln("<error>下载图片失败: {$imageUrl}</error>");
                return null;
            }

            // 创建图片资源
            $image = imagecreatefromstring($imageData);
            if ($image === false) {
                $output->writeln("<error>创建图片资源失败: {$imageUrl}</error>");
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
            
            // 保持透明度（对于PNG图片）
            if ($extension === 'png') {
                imagealphablending($compressedImage, false);
                imagesavealpha($compressedImage, true);
                $transparent = imagecolorallocatealpha($compressedImage, 255, 255, 255, 127);
                imagefill($compressedImage, 0, 0, $transparent);
            }

            // 重新采样图片
            imagecopyresampled($compressedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // 保存压缩后的图片
            $saved = false;
            switch ($extension) {
                case 'png':
                    $saved = imagepng($compressedImage, $localPath, 6); // 压缩级别6
                    break;
                case 'gif':
                    $saved = imagegif($compressedImage, $localPath);
                    break;
                default:
                    $saved = imagejpeg($compressedImage, $localPath, 80); // 质量80%
                    break;
            }

            // 释放内存
            imagedestroy($image);
            imagedestroy($compressedImage);

            if ($saved) {
                $output->writeln("-> [图片] 已下载并压缩: {$fileName}");
                return $relativePath;
            } else {
                $output->writeln("<error>保存图片失败: {$localPath}</error>");
                return null;
            }

        } catch (\Exception $e) {
            $output->writeln("<error>处理图片时出错: " . $e->getMessage() . "</error>");
            return null;
        }
    }
}
