<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\facade\Log;
use QL\QueryList;
use app\model\Koreanbackup; // 确保模型名称与您的表对应，这里假设为 Koreanbackup

class koreanplay extends Command
{
    private $baseUrl = 'https://www.thanju.com';
    // 定义要爬取的基础分类URL和ID
    private $targetSections = [
        'korean'  => ['id' => 1, 'name' => '韩剧', 'url' => '/dianshiju.html'],     // 韩剧分类
        'movie'   => ['id' => 2, 'name' => '电影', 'url' => '/dianying.html'],     // 韩国电影分类
        'variety' => ['id' => 3, 'name' => '综艺', 'url' => '/zongyi.html'],      // 韩国综艺分类
    ];
    
    // 支持的年份列表
    private $supportedYears = [2025, 2024, 2023, 2022, 2021, 2020, 2019, 2018, 2017, 2016,20002010,19901999,];


    protected function configure()
    {
        $this->setName('koreanplay')
            ->setDescription('Crawl drama data from thanju.com and avoid duplicates.')
            ->addOption('max_page', 'p', Option::VALUE_OPTIONAL, 'Maximum pages to crawl for each category', 10) // 添加一个选项，控制最大爬取页数
            ->addOption('start_page', 's', Option::VALUE_OPTIONAL, 'Starting page number for crawling', 1) // 添加起始页码选项
            ->addOption('year', 'y', Option::VALUE_OPTIONAL, 'Specific year to crawl (e.g., 2024), or "all" for all years', 'all') // 添加年份选项
            ->addOption('skip_existing', 'k', Option::VALUE_NONE, 'Skip existing records instead of updating them'); // 添加跳过已存在记录选项
    }

    protected function execute(Input $input, Output $output)
    {
        $maxPage = $input->getOption('max_page');
        $startPage = $input->getOption('start_page');
        $year = $input->getOption('year');
        $skipExisting = $input->getOption('skip_existing');
        
        $output->writeln("任务开始：精确爬取thanju.com韩剧数据... (从第 {$startPage} 页开始，每个分区最多爬取 {$maxPage} 页)");
        
        // 确定要爬取的年份列表
        $yearsToProcess = [];
        if ($year === 'all') {
            $yearsToProcess = $this->supportedYears;
            $output->writeln("将爬取所有年份: " . implode(', ', $yearsToProcess));
        } elseif (is_numeric($year) && in_array((int)$year, $this->supportedYears)) {
            $yearsToProcess = [(int)$year];
            $output->writeln("将爬取指定年份: {$year}");
        } else {
            $output->writeln("<error>无效的年份参数: {$year}，支持的年份: " . implode(', ', $this->supportedYears) . "</error>");
            return 1;
        }

        // 遍历各个分区和年份爬取数据
        foreach ($this->targetSections as $sectionName => $sectionInfo) {
            $output->writeln("--- 开始处理分区: [{$sectionInfo['name']}] ---");
            
            foreach ($yearsToProcess as $currentYear) {
                $output->writeln("  处理年份: {$currentYear}");
                
                for ($page = $startPage; $page <= $maxPage; $page++) {
                    // 构建年份筛选的URL
                    if ($page == 1) {
                        $listUrl = $this->baseUrl . "/list-select-id-{$sectionInfo['id']}-type--area--year-{$currentYear}-star--state--order-addtime.html";
                    } else {
                        $listUrl = $this->baseUrl . "/list-select-id-{$sectionInfo['id']}-type--area--year-{$currentYear}-star--state--order-addtime-p-{$page}.html";
                    }
                    $output->writeln("正在爬取列表页: {$listUrl}");

                    try {
                        $listData = $this->scrapeListPage($listUrl);

                        if (empty($listData)) {
                            $output->writeln("分区 [{$sectionName}] 年份 {$currentYear} 在第 {$page} 页没有数据，结束该年份。");
                            break; // 当前年份已到底，跳到下一页
                        }

                        foreach ($listData as $item) {
                             // 核心去重逻辑：根据name字段去重
                             $existingRecord = Koreanbackup::where('name', $item['name'])->find();
                             if ($existingRecord) {
                                 if ($skipExisting) {
                                     $output->writeln("-> [跳过] '{$item['name']}' 已存在。");
                                     continue;
                                 } else {
                                     $output->writeln("-> [更新] '{$item['name']}' 已存在，将更新数据。");
                                 }
                             }
                             
                             // 生成唯一的sid
                             $sid = 'thanju_' . md5($item['detail_url']);

                             // 获取详情页信息
                             $detailInfo = $this->scrapeDetailPage($this->baseUrl . $item['detail_url']);

                             // 组合并格式化数据
                             $fullData = array_merge($item, $detailInfo);
                             
                             // 下载并压缩封面图片
                             if (!empty($fullData['image'])) {
                                 $localImagePath = $this->downloadAndCompressImage($fullData['image'], $sid, $output);
                                 if ($localImagePath) {
                                     $fullData['image'] = $localImagePath;
                                     $fullData['cover'] = $localImagePath;
                                 }
                             }
                             
                             // 将分区名存入type字段
                             $formattedData = $this->formatData($fullData, $sid, $sectionName);

                             // 保存到数据库
                             if ($existingRecord && !$skipExisting) {
                                 // 检查已存在记录的cover字段是否为空
                                 if (empty($existingRecord->cover) || $existingRecord->cover === 'null') {
                                     Koreanbackup::where('name', $item['name'])->update($formattedData);
                                     $output->writeln("-> [更新] 剧集 '{$formattedData['name']}' cover字段为空，已更新数据。");
                                 } 
                                 // 检查lastSerialNo字段是否为空，如果为空则只更新这个字段
                                 elseif (empty($existingRecord->lastSerialNo) || $existingRecord->lastSerialNo === 'null') {
                                     if (!empty($formattedData['lastSerialNo'])) {
                                         Koreanbackup::where('name', $item['name'])->update(['lastSerialNo' => $formattedData['lastSerialNo']]);
                                         $output->writeln("-> [更新] 剧集 '{$formattedData['name']}' lastSerialNo字段为空，已更新: {$formattedData['lastSerialNo']}");
                                     } else {
                                         $output->writeln("-> [跳过] 剧集 '{$formattedData['name']}' lastSerialNo字段为空但新数据也为空。");
                                     }
                                 }
                                 // 检查conerMemo字段是否为空，如果为空则只更新这个字段
                                 elseif (empty($existingRecord->conerMemo) || $existingRecord->conerMemo === 'null') {
                                     if (!empty($formattedData['conerMemo'])) {
                                         Koreanbackup::where('name', $item['name'])->update(['conerMemo' => $formattedData['conerMemo']]);
                                         $output->writeln("-> [更新] 剧集 '{$formattedData['name']}' conerMemo字段为空，已更新: {$formattedData['conerMemo']}");
                                     } else {
                                         $output->writeln("-> [跳过] 剧集 '{$formattedData['name']}' conerMemo字段为空但新数据也为空。");
                                     }
                                 } 
                                 else {
                                     $output->writeln("-> [跳过] 剧集 '{$formattedData['name']}' cover、lastSerialNo和conerMemo字段都不为空，跳过更新。");
                                 }
                             } elseif (!$existingRecord) {
                                 Koreanbackup::create($formattedData);
                                 $output->writeln("-> [成功] 新剧 '{$formattedData['name']}' 已入库。");
                             }

                             sleep(1); // 友好性延时
                         }
                    } catch (\Exception $e) {
                        $output->writeln("<error>爬取页面 {$listUrl} 失败: " . $e->getMessage() . "</error>");
                        Log::error("爬取失败: {$listUrl} | 错误: " . $e->getMessage());
                    }
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
             
             // 解析韩剧列表项 - 适配thanju.com的HTML结构
             $items = [];
             $response->find('.myui-vodlist__box')->each(function ($item) use (&$items) {
                 $name = $item->find('.myui-vodlist__detail .title a')->text();
                 $detailUrl = $item->find('.myui-vodlist__detail .title a')->attr('href');
                 if (!$detailUrl) {
                     $detailUrl = $item->find('.myui-vodlist__thumb')->attr('href');
                 }
                 
                 // 获取图片URL - 从style属性中提取background图片
                 $image = '';
                 $thumbStyle = $item->find('.myui-vodlist__thumb')->attr('style');
                 if ($thumbStyle && preg_match('/background:\s*url\(([^)]+)\)/', $thumbStyle, $matches)) {
                     $image = trim($matches[1], '"\' ');
                     if ($image && strpos($image, 'http') !== 0) {
                         $image = 'https:' . $image;
                     }
                 }
                 // 备用方案：尝试其他选择器
                 if (!$image) {
                     $image = $item->find('.myui-vodlist__thumb .lazyload')->attr('data-original');
                     if (!$image) {
                         $image = $item->find('.myui-vodlist__thumb img')->attr('src');
                     }
                     if ($image && strpos($image, 'http') !== 0) {
                         $image = 'https:' . $image;
                     }
                 }
                 
                 // 获取集数信息
                 $episode = $item->find('.myui-vodlist__thumb .pic-text')->text();
                 if (!$episode) {
                     $episode = $item->find('.myui-vodlist__remark')->text();
                 }
                 
                 // 获取评分
                 $score = $item->find('.myui-vodlist__thumb .pic-tag')->text();
                 if ($score) {
                     // 提取数字评分
                     preg_match('/([0-9.]+)分/', $score, $scoreMatches);
                     $score = isset($scoreMatches[1]) ? $scoreMatches[1] : '';
                 }
                 
                 if ($name && $detailUrl) {
                     $items[] = [
                         'name' => trim($name),
                         'detail_url' => $detailUrl,
                         'image' => $image ?: '',
                         'year' => date('Y'), // 默认当前年份
                         'episode' => $episode ?: '',
                         'score' => $score ?: ''
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
            $intro = '';
            $lastSerialNo = '';
            $updateInfo = '';
            $category = '';
            
            // 获取更新信息 - 从正确的CSS选择器中获取
            $updateElement = $response->find('.myui-content__detail p.data:contains("更新：")');
            if ($updateElement->count() > 0) {
                $updateText = $updateElement->text();
                // 移除"更新："前缀，获取实际的更新信息
                $updateInfo = trim(str_replace('更新：', '', $updateText));
                $output->writeln("-> [获取] 更新信息: {$updateInfo}");
                
                // 从更新信息中提取数字作为lastSerialNo
                if (preg_match('/\d+/', $updateInfo, $numberMatches)) {
                    $lastSerialNo = intval($numberMatches[0]);
                } else {
                    $lastSerialNo = 0;
                }
            }
            
            // 获取分类信息
            $tagElement = $response->find('.myui-content__detail p.data:contains("标签：")');
            if ($tagElement->count() > 0) {
                $tagText = $tagElement->text();
                $category = trim(str_replace('标签：', '', $tagText));
                $output->writeln("-> [获取] 分类信息: {$category}");
            }
            
            // 如果从图片alt中没有获取到简介，尝试从文本中获取
            if (!$intro) {
                $intro = $response->find('.myui-content__detail .text-collapse')->text();
                if (!$intro) {
                    $intro = $response->find('.myui-content__detail .desc')->text();
                }
                if (!$intro) {
                    $intro = $response->find('.myui-content__detail .sketch')->text();
                }
                if (!$intro) {
                    $intro = $response->find('.myui-content__detail p')->text();
                }
            }
            
            // 获取演员信息 - 使用更精确的选择器，避免匹配到简介段落
            $crewElement = $response->find('.myui-content__detail p.data:contains("主演")');
            if (!$crewElement->count()) {
                $crewElement = $response->find('.myui-content__detail p.data:contains("演员")');
            }
            if (!$crewElement->count()) {
                // 备选方案：查找包含主演标签的段落，但排除desc类
                $crewElement = $response->find('.myui-content__detail p:contains("主演"):not(.desc)');
            }
            
            $crew = '';
            if ($crewElement->count() > 0) {
                // 提取所有演员链接的文本内容
                $actorLinks = $crewElement->find('a');
                $actors = [];
                foreach ($actorLinks as $link) {
                    $actorName = trim($link->text());
                    if ($actorName && !in_array($actorName, $actors)) {
                        $actors[] = $actorName;
                    }
                }
                
                // 如果没有找到链接，则提取整个文本并清理
                if (empty($actors)) {
                    $crewText = $crewElement->text();
                    $crewText = preg_replace('/^[^：]*：/', '', $crewText);
                    $crewText = preg_replace('/\s*(等|等等|等人).*$/', '', $crewText);
                    $crew = trim($crewText);
                } else {
                    // 限制演员数量，避免过长
                    $crew = implode(' ', array_slice($actors, 0, 8));
                }
                
                // 最终长度限制
                $crew = mb_substr($crew, 0, 150);
            }
            
            // 获取导演信息
            $director = $response->find('.myui-content__detail p:contains("导演")')->text();
            if ($director) {
                $director = preg_replace('/^[^：]*：/', '', $director);
            }
            
            // 如果从图片中没有获取到分类信息，尝试从文本中获取
            if (!$category) {
                $categoryText = $response->find('.myui-content__detail p:contains("类型")')-> text();
                if (!$categoryText) {
                    $categoryText = $response->find('.myui-content__detail p:contains("分类")')-> text();
                }
                if ($categoryText) {
                    $category = preg_replace('/^[^：]*：/', '', $categoryText);
                }
            }
            
            // 获取更新时间作为publishTime字段
            $publishTime = '';
            $updateText = $response->find('.myui-content__detail p:contains("更新")')->text();
            if (!$updateText) {
                $updateText = $response->find('.myui-content__detail p:contains("年份")')->text();
            }
            if ($updateText && preg_match('/\d{4}/', $updateText, $matches)) {
                $publishTime = $matches[0] . '-01-01 00:00:00'; // 转换为datetime格式
            }
            if (!$publishTime) {
                $publishTime = date('Y-m-d H:i:s'); // 默认当前时间
            }
            
            // 从publishTime中提取年份作为year字段
            // $year = '';
            // if (preg_match('/^(\d{4})/', $publishTime, $matches)) {
            //     $year = 
            // }
            // if (!$year) {
            //     $year = date('Y'); // 默认当前年份
            // }
            
            // 如果从图片中没有获取到lastSerialNo，尝试从文本中获取更新信息
            if (!$lastSerialNo) {
                $updateInfoText = $response->find('.myui-content__detail p:contains("期")')-> text();
                if (!$updateInfoText) {
                    $updateInfoText = $response->find('.myui-content__detail p:contains("更新")')-> text();
                }
                if (!$updateInfoText) {
                    $updateInfoText = $response->find('.myui-content__detail .text-muted')-> text();
                }
                if ($updateInfoText) {
                    // 提取类似"出997期 每周更新"的信息，并从中提取数字
                    if (preg_match('/(出\d+期[^，。]*|每[^，。]*更新|更新至[^，。]*)/u', $updateInfoText, $matches)) {
                        $updateInfo = trim($matches[1]);
                        // 从更新信息中提取数字作为lastSerialNo
                        if (preg_match('/\d+/', $updateInfo, $numberMatches)) {
                            $lastSerialNo = intval($numberMatches[0]);
                        } else {
                            $lastSerialNo = 0;
                        }
                    }
                } else {
                    $updateInfo = '';
                }
            }
            
            return [
                'intro' => trim($intro) ?: '暂无简介',
                'crew' => trim($crew) ?: '',
                'director' => trim($director) ?: '',
                'category' => trim($category) ?: '韩剧',
                // 'publishTime' => $publishTime,
                'publishTime' => '2014-01-01 00:00:00',
                'lastSerialNo' => $lastSerialNo,
                'updateInfo' => $updateInfo ?: '',
                'year' => 1-2014
            ];
            
        } catch (\Exception $e) {
            $output->writeln("<error>详情页请求失败: " . $e->getMessage() . "</error>");
            return [
                'intro' => '暂无简介',
                'crew' => '',
                'director' => '',
                'category' => '韩剧',
                'publishTime' => '2014-01-01 00:00:00',
                'lastSerialNo' => 0,
                'updateInfo' => '',
                'year' => 1-2014
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
        
        // 确保图片路径正确
        $imagePath = $data['image'] ?? '';
        $coverPath = $data['cover'] ?? $imagePath;
        
        return [
            'sid' => $sid,
            'category' => $data['category'] ?? '1',
            'name' => $data['name'],
            'years' => $data['year'] ?? date('Y'),
            'image' => $imagePath,
            'cover' => $coverPath, 
            'quarklink' => null,
            'baidulink' => null,
            'crew' => mb_substr($data['crew'] ?? '', 0, 255),
            'finished' => 1,
            'conerMemo' => $data['updateInfo'] ?? '',
            'lastSerialNo' => $data['lastSerialNo'] ?? '',
            'intro' => $data['intro'] ?? '',
            'publishTime' => $publishTime,
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
     * 下载并压缩图片
     */
    private function downloadAndCompressImage($imageUrl, $sid, $output)
    {
        try {
            // 创建上传目录
            $uploadDir = dirname(__DIR__, 2) . '/public/upload/img/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // 获取图片扩展名
            $imageInfo = pathinfo(parse_url($imageUrl, PHP_URL_PATH));
            $extension = isset($imageInfo['extension']) ? strtolower($imageInfo['extension']) : 'jpg';
            
            // 生成本地文件名
            $fileName = $sid . '.' . $extension;
            $localPath = $uploadDir . $fileName;
            $relativePath = '/upload/img/' . $fileName;

            // 如果文件已存在，直接返回路径
            if (file_exists($localPath)) {
                return $relativePath;
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
