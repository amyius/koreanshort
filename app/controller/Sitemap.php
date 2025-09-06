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
        $koreans = Db::name('koreanshort')->distinct(true)->order('publishTime desc')->select();
        // 开始构建sitemap内容
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($koreans as $korean) {
            $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
            $seriesId    = htmlspecialchars($korean['id'],    ENT_QUOTES, 'UTF-8');
            $seriesName  = htmlspecialchars($korean['name'],  ENT_QUOTES, 'UTF-8');

            $xml .= "<url>
                <loc>{$safeBaseUrl}/particulars/{$seriesId}/{$seriesName}</loc>
                <lastmod>" . date('Y-m-d') . "</lastmod>
                <changefreq>weekly</changefreq>
                <priority>0.8</priority>
             </url>";
        }
        $safeBaseUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');

        $xml .= '<url>
                <loc>' . $safeBaseUrl . '/info' . '</loc>
                <changefreq>daily</changefreq>
                <priority>1.0</priority>
             </url>';


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
