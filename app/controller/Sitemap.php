<?php

namespace app\controller;

use app\BaseController;
use think\facade\Log;
use think\facade\Db;
use think\facade\Request;
use think\Response;

class Sitemap extends BaseController
{
    public function index()
    {
        // 获取网站的基础URL
        $baseUrl = Request::domain();

        // 查询需要生成sitemap的页面数据，例如文章列表
        $articles = Db::name('shortdata')
            ->whereBetween('updatedate', ['2025-01-01', '2025-12-31'])
            ->where('is_down', 0)
            ->limit(5000)
            ->distinct(true)
            ->order('updatedate', 'desc')
            ->select();

        // $foreigns = Db::name('foreignshort')->distinct(true)->order('datePublished desc')->select();
        // $koreans = Db::name('koreanshort')->distinct(true)->order('publishTime desc')->select();
        // 开始构建sitemap内容
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($articles as $article) {
            // 获取原始字符串
            $name = $article['name'];

            // 使用 explode() 函数按左括号分割
            $parts = explode('（', $name);

            // 初始化变量
            $title = '';
            $episode = '';
            $author1 = '';
            $author2 = '';

            // 检查是否包含括号部分
            if (count($parts) > 1) {
                $title = trim($parts[0]); // 获取标题部分
                $episodeAndAuthors = trim($parts[1]);

                // 移除右括号
                $episodeAndAuthors = str_replace('）', '', $episodeAndAuthors);

                // 分割集数和作者部分
                $episodeAndAuthorsParts = explode(' ', $episodeAndAuthors);

                // 获取集数部分
                if (count($episodeAndAuthorsParts) > 0) {
                    $episode = trim($episodeAndAuthorsParts[0]);
                }

                // 检查是否有作者部分
                if (count($episodeAndAuthorsParts) > 1) {
                    $authors = trim($episodeAndAuthorsParts[1]);
                    $authorsParts = explode('&', $authors);
                    if (count($authorsParts) > 0) {
                        $author1 = trim($authorsParts[0]);
                    }
                    if (count($authorsParts) > 1) {
                        $author2 = trim($authorsParts[1]);
                    }
                }
            } else {
                // 如果没有括号部分，仅提取标题
                $title = trim($parts[0]);
            }

            // 转义特殊字符
            $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');

            $xml .= '<url>
                    <loc>' . $safeBaseUrl . '/detail/' . $article['id'] . '/' . $safeTitle . '</loc>
                    <lastmod>' . date('Y-m-d', time()) . '</lastmod>
                    <changefreq>daily</changefreq>
                    <priority>0.8</priority>
                 </url>';
        }

        // foreach ($foreigns as $foreign) {
        //     $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        //     $seriesId    = htmlspecialchars($foreign['seriesId'],    ENT_QUOTES, 'UTF-8');
        //     $seriesName  = htmlspecialchars($foreign['seriesName'],  ENT_QUOTES, 'UTF-8'); // ← 关键

        //     $xml .= "<url>
        //         <loc>{$safeBaseUrl}/series/{$seriesId}/{$seriesName}</loc>
        //         <lastmod>" . date('Y-m-d') . "</lastmod>
        //         <changefreq>daily</changefreq>
        //         <priority>0.8</priority>
        //      </url>";
        // }

        // foreach ($koreans as $korean) {
        //     $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        //     $seriesId    = htmlspecialchars($korean['id'],    ENT_QUOTES, 'UTF-8');
        //     $seriesName  = htmlspecialchars($korean['name'],  ENT_QUOTES, 'UTF-8');

        //     $xml .= "<url>
        //         <loc>{$safeBaseUrl}/particulars/{$seriesId}/{$seriesName}</loc>
        //         <lastmod>" . date('Y-m-d') . "</lastmod>
        //         <changefreq>daily</changefreq>
        //         <priority>0.8</priority>
        //      </url>";
        // }
        // $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        // 其他
        // $xml .= '<url>
        //         <loc>' . $safeBaseUrl . '/weekly' . '</loc>
        //         <changefreq>daily</changefreq>
        //         <priority>1.0</priority>
        //      </url>';

        // $xml .= '<url>
        //         <loc>' . $safeBaseUrl . '/rank' . '</loc>
        //         <changefreq>daily</changefreq>
        //         <priority>1.0</priority>
        //      </url>';
        // $xml .= '<url>
        //         <loc>' . $safeBaseUrl . '/foreign' . '</loc>
        //         <changefreq>daily</changefreq>
        //         <priority>1.0</priority>
        //      </url>';
        // $xml .= '<url>
        //         <loc>' . $safeBaseUrl . '/koreans' . '</loc>
        //         <changefreq>daily</changefreq>
        //         <priority>1.0</priority>
        //      </url>';




        $xml .= '<url>
                <loc>' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '</loc>
                <changefreq>daily</changefreq>
                <priority>1.0</priority>
             </url>';

        $xml .= '</urlset>';

        // 设置响应头为XML格式
        return Response::create($xml, 'xml');
    }
}
