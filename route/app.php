<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;
//默认是index控制器下的index方法，所有访问路由就是域名
Route::group('', function () {
    Route::get('', 'Index/index');
    Route::any('/detail/:id/:name?', 'Index/detail');
    Route::any('/search/:keyword/:drama?', 'Index/search')->pattern(['keyword' => '[\w\s%]+']);;
    Route::post('/submits', 'Index/submits');
    Route::get('/rank', 'Index/rank');
    Route::get('/weekly/:id?', 'Index/weekly');
    Route::get('/foreign/:id?', 'Index/foreign');
    Route::get('/series/:id/:name?', 'Index/series');
    Route::any('/koreans/:years?','Index/koreans');
    Route::any('/particulars/:id/:name?','Index/particulars');
    Route::get('/info/:id?', 'Index/Info');
    Route::get('/article/:id?/:name?', 'Index/article');
});

Route::group('comments', function () {
    Route::any('shortdataId/:shortdataId', 'Comments/index');
    Route::any('foreignId/:foreignId', 'Comments/index');
    Route::any('koreanId/:koreanId', 'Comments/index');
});

Route::get('sitemap.xml', 'Sitemap/index');
Route::get('sitemapcurrent.xml', 'Sitemapcurrent/index');
Route::get('sitemapinfo.xml', 'Sitemapinfo/index');
Route::get('sitemapforeign.xml', 'Sitemapforeign/index');
Route::get('sitemapkorean.xml', 'Sitemapkorean/index');
Route::get('sitemapprevious.xml', 'Sitemapprevious/index');