<?php

namespace app\controller;

use app\BaseController;
use think\facade\Log;

class Index extends BaseController
{
    public function index()
    {
        $shortModel = new \app\model\Koreanshort();
        $shortinfo = $shortModel->order('publishTime', 'desc')->limit(32)->select()->toArray();
        $domain = $this->request->domain();
        foreach ($shortinfo as $key => $value) {
            $cover = trim($value['cover'] ?? '');
            if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
                $shortinfo[$key]['cover'] = $cover;
            } elseif (empty($cover)) {
                $shortinfo[$key]['cover'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
            } else {
                $shortinfo[$key]['cover'] = $this->request->domain() . '/' . ltrim($cover, '/');
            }
        }
        $shorthotinfo = $shortModel->orderRaw('RAND()')->limit(32)->select()->toArray();
        foreach ($shorthotinfo as $key => $value) {
            $cover = trim($value['cover'] ?? '');
            if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
                $shorthotinfo[$key]['cover'] = $cover;
            } elseif (empty($cover)) {
                $shorthotinfo[$key]['cover'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
            } else {
                $shorthotinfo[$key]['cover'] = $this->request->domain() . '/' . ltrim($cover, '/');
            }
        }

        $carousel_data = $shortModel->where('cover', '<>', '')->orderRaw('RAND()')->limit(6)->select()->toArray();
        foreach ($carousel_data as $key => $value) {
            $cover = trim($value['cover'] ?? '');
            if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
                $carousel_data[$key]['cover'] = $cover;
            } elseif (empty($cover)) {
                $carousel_data[$key]['cover'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
            } else {
                $carousel_data[$key]['cover'] = $this->request->domain() . '/' . ltrim($cover, '/');
            }
        }
        $response = [
            'types' => 1,
            'shortinfo' => $shortinfo,
            'shorthotinfo' => $shorthotinfo,
            'carousel_data' => $carousel_data
        ];
        return view('index', $response);
    }

    public function particulars($id)
    {
        $koreansModel = new \app\model\Koreanshort();
        $castModel = new \app\model\Koreancast();
        $domain = $this->request->domain();
        $shortinfo = $koreansModel->where('id', $id)->find();
        if ($shortinfo['intro']) {
            preg_match('/类型:&nbsp;([^<]+)/i', $shortinfo['intro'], $matches);
            if (!empty($matches[1])) {
                $rawTypes = strip_tags(html_entity_decode($matches[1]));
                $types = array_map('trim', explode('/', $rawTypes));
            } else {
                $types = ['其他'];
            }
            $shortinfo['tag'] = $types;
            $shortinfo['intro'] = preg_replace('/^<p>(.*?)<\/p>$/is', '$1', $shortinfo['intro']);
        }
        if (!empty($shortinfo['intro'])) {
            $isHtml = strpos(trim($shortinfo['intro']), '<p>') === 0;

            $shortinfo['introOriginal'] = $shortinfo['intro'];

            if ($isHtml) {
                preg_match('#<p>(.*?)</p>#is', $shortinfo['intro'], $m);
                $shortinfo['summarized'] = trim(strip_tags($m[1] ?? ''));
            } else {
                $shortinfo['summarized'] = trim(preg_replace('/^简介：\s*/u', '', $shortinfo['intro']));
            }

            if ($isHtml && preg_match('#类型:&nbsp;([^<]+)#i', $shortinfo['intro'], $m)) {
                $raw = strip_tags(html_entity_decode($m[1]));
                $shortinfo['tag'] = array_map('trim', explode('/', $raw));
            } else {
                $shortinfo['tag'] = ['其他'];
            }

            $shortinfo['metaHtml'] = '';
            if ($isHtml) {
                $fields = [
                    '编剧' => '#编剧:&nbsp;([^<]+)#i',
                    '主演' => '#主演:&nbsp;([^<]+)#i',
                    '类型' => '#类型:&nbsp;([^<]+)#i',
                    '制片国家/地区' => '#制片国家/地区:&nbsp;([^<]+)#i',
                    '语言' => '#语言:&nbsp;([^<]+)#i',
                    '集数' => '#集数:&nbsp;([^<]+)#i',
                    '又名' => '#又名:&nbsp;([^<]+)#i',
                ];
                $out = '';
                foreach ($fields as $label => $pat) {
                    if (preg_match($pat, $shortinfo['intro'], $m)) {
                        $val = trim(strip_tags(html_entity_decode($m[1])));
                        if ($label === '主演') {
                            $val = implode(' / ', preg_split('#\s*/\s*#u', $val));
                        }
                        $out .= "<div>{$label}：{$val}</div>";
                    }
                }
                $shortinfo['metaHtml'] = $out;
            }
        }

        if ($shortinfo['name'] || $shortinfo['crew']) {
            $shortinfo['key'] = $shortinfo['name'] . ',' . $shortinfo['crew'] . ',' . implode(',', $shortinfo['tag'] ?? []);
        }
        if ($shortinfo['oneselfquarklink']) {
            $shortinfo['quark'] = $shortinfo['oneselfquarklink'];
        } else if ($shortinfo['quarklink'] != "未找到" && $shortinfo['quarklink'] != "失效链接") {
            $shortinfo['quark'] = $shortinfo['quarklink'];
        } else {
            $shortinfo['quark'] = "";
        }

        if ($shortinfo['oneselfbaidulink']) {
            $shortinfo['baidu'] = $shortinfo['oneselfbaidulink'];
        } else if ($shortinfo['baidulink'] != "未找到" && $shortinfo['baidulink'] != "失效链接") {
            $shortinfo['baidu'] = $shortinfo['baidulink'];
        } else {
            $shortinfo['baidu'] = "";
        }

        if ($shortinfo['finished']) {
            $shortinfo['status'] = "已完结";
        } else {
            $shortinfo['status'] = "连载中";
        }
        $shortinfo['allEpis'] = $shortinfo['conerMemo'];
        $cover = trim($shortinfo['cover'] ?? '');
        if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
            $shortinfo['cover'] = $cover;
        } elseif (empty($cover)) {
            $shortinfo['cover'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
        } else {
            $shortinfo['cover'] = $this->request->domain() . '/' . ltrim($cover, '/');
        }
        if ($shortinfo['publishTime']) {
            $shortinfo['publishTime'] = date('Y-m-d', strtotime($shortinfo['publishTime']));
        }

        if (!empty($shortinfo['relateStars'])) {
            $starIds  = json_decode($shortinfo['relateStars'], true);
            $starList = $castModel->where('sid', 'in', $starIds)
                ->select()
                ->toArray();
        } else {
            $crewRaw = preg_replace('/^主演[：:]\s*/u', '', $shortinfo['crew'] ?? '');

            $crewNames = array_unique(
                array_filter(
                    array_map('trim', preg_split('/[、,]/u', $crewRaw)),
                    fn($v) => $v !== ''
                )
            );
            $starList = [];
            if ($crewNames) {
                $starList = $castModel->where('name', 'in', $crewNames)
                    ->select()
                    ->toArray();
            }
        }
        foreach ($starList as $k => $item) {
            if (!empty($item['thumb']) && !str_starts_with($item['thumb'], 'http')) {
                $starList[$k]['thumb'] = rtrim($domain, '/') . '/' . ltrim($item['thumb'], '/');
            }
        }

        $shortinfo['starList'] = $starList;
        $shortinfo['description'] = mb_substr($shortinfo['summarized'], 0, 142) .
            (mb_strlen($shortinfo['summarized']) > 142 ? '...' : '');
        $all_data = $koreansModel->orderRaw('publishTime desc')->orderRaw('RAND()')->select()->toArray();

        $total_count = count($all_data);
        $start_index = max(0, floor($total_count / 2) - 11);
        $end_index = min($total_count, $start_index + 16);
        $related_data       = $this->processIntroFields($this->normalizeImageFields(array_slice($all_data, $start_index, $end_index - $start_index)));
        $recommend_data     = $this->processIntroFields($this->normalizeImageFields($koreansModel->orderRaw('publishTime desc')->orderRaw('RAND()')->limit(8)->select()->toArray()));
        $comprehensive_data = $this->processIntroFields($this->normalizeImageFields($koreansModel->order('publishTime desc')->limit(10)->select()->toArray()));

        $response = [
            'shortinfo' => $shortinfo, // 详情
            'related_data' => $related_data, // 相关推荐 
            'recommend_data' => $recommend_data, // 为您推荐(随机8条数据)
            'comprehensive_data' => $comprehensive_data, //综合榜(10条)
        ];
        return view('particulars', ['shortdetail' => $response, 'types' => 0]);
    }

    public function search()
    {
        $shortModel = new \app\model\Shortdata();
        if ($this->request->isAjax()) {
            $keyword = $this->request->param('keyword');
            $drama   = $this->request->param('drama', 'korean');
            $domain = $this->request->domain();
            if ($drama == "thai") {
                $ThaitaiwaneseModel = new \app\model\Thaitaiwanese();
                $data = $ThaitaiwaneseModel->where('name', 'like', '%' . $keyword . '%')->order('years desc')->select();
                foreach ($data as $item) {
                    $item['id'] = $item['id'];
                    $item['name'] = $item['name'];
                    $item['title'] = $item['name'];
                    $item['cover'] = $this->request->domain() . $item['cover'];
                    $item['img'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
                    $item['episodes'] = $item['conerMemo'];
                    $item['link'] = "/concrete/" . $item['id'] . "/" . rawurlencode(rawurlencode($item['name']));
                    $item['drama'] = "thai";
                }
                return json($data);
            } else if ($drama == "korean") {
                $koreansModel = new \app\model\Koreanshort();
                $data = $koreansModel->where('name', 'like', '%' . $keyword . '%')->order('publishTime desc')->select();
                foreach ($data as $item) {
                    $item['id'] = $item['id'];
                    $item['name'] = $item['name'];
                    $item['title'] = $item['name'];
                    $item['cover'] = $this->request->domain() . $item['cover'];
                    $item['img'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
                    $item['episodes'] = $item['conerMemo'];
                    $item['link'] = "/particulars/" . $item['id'] . "/" . rawurlencode(rawurlencode($item['name']));
                    $item['drama'] = "korean";
                }
                return json($data);
            }
        } else {
            return View('search');
        }
    }

    public function submits()
    {
        $foreignId   = (int)$this->request->param('foreignId', 0);
        $shortdataId = (int)$this->request->param('shortdataId', 0);
        $koreanId    = trim($this->request->param('koreanId', ''));
        $thaiId    = trim($this->request->param('thaiId', ''));
        $content     = trim($this->request->param('content', ''));

        $validCount = 0;
        if ($foreignId   > 0)          $validCount++;
        if ($shortdataId > 0)          $validCount++;
        if ($koreanId    !== '')       $validCount++;

        if ($validCount !== 1) {
            return json(['code' => 0, 'msg' => '必须且只能提供 foreignId、shortdataId、koreanId 中的一个']);
        }

        if ($content === '') {
            return json(['code' => 0, 'msg' => '内容不能为空']);
        }

        $commentModel = new \app\model\Comments();
        $commentModel->content    = $content;
        $commentModel->created_at = date('Y-m-d H:i:s');

        if ($shortdataId) {
            $commentModel->shortdata_id = $shortdataId;
        } elseif ($foreignId) {
            $commentModel->foreign_id = $foreignId;
        } elseif ($thaiId) {
            $commentModel->thai_id = $thaiId;
        } else {
            $commentModel->korean_id = $koreanId;
        }

        $commentModel->save();

        return json(['code' => 1, 'msg' => '发布成功']);
    }

    public function taiwanese()
    {
        if (!$this->request->isAjax()) {
            return view('taiwanese', ['types' => 1]);
        }

        $page      = max((int)$this->request->param('page', 1), 1);
        $years     = trim($this->request->param('years', '2025'));
        $pageSize  = 20;

        if ($years === '2015-2019') {
            $startYear = 2015;
            $endYear   = 2019;
        } elseif ($years === '1-2014') {
            $startYear = 1990;
            $endYear   = 2014;
        } else {
            $startYear = $endYear = (int)$years;
        }

        $query = \app\model\Thaitaiwanese::where('area', 'LIKE', '%台湾%')
            ->whereBetween('years', [$startYear, $endYear])
            ->order('created_at', 'desc');

        $total      = $query->count();
        $lastPage   = max(ceil($total / $pageSize), 1);
        $data       = $query->page($page, $pageSize)
            ->select();

        $domain = $this->request->domain();
        $data->each(function ($item) use ($domain) {
            $cover = trim($item['cover'] ?? '');
            if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
                $item['cover'] = $cover;
            } elseif ($cover === '') {
                $item['cover'] = $domain . '/uploads/images/koreandefualt.png';
            } else {
                $item['cover'] = $domain . '/' . ltrim($cover, '/');
            }
            $item['rank'] = rand(1, 100);
        });

        return json([
            'koreandata' => $data->toArray(),
            'pagination' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $pageSize,
                'total'        => $total,
            ],
            'types' => 1,
        ]);
    }

    public function thai()
    {
        if (!$this->request->isAjax()) {
            return view('thai', ['types' => 1]);
        }

        $page      = max((int)$this->request->param('page', 1), 1);
        $years     = trim($this->request->param('years', '2025'));
        $pageSize  = 20;

        if ($years === '2015-2019') {
            $startYear = 2015;
            $endYear   = 2019;
        } elseif ($years === '1-2014') {
            $startYear = 1990;
            $endYear   = 2014;
        } else {
            $startYear = $endYear = (int)$years;
        }

        $query = \app\model\Thaitaiwanese::where('area', 'LIKE', '%泰国%')
            ->whereBetween('years', [$startYear, $endYear])
            ->order('created_at', 'desc');

        $total      = $query->count();
        $lastPage   = max(ceil($total / $pageSize), 1);
        $data       = $query->page($page, $pageSize)
            ->select();

        $domain = $this->request->domain();
        $data->each(function ($item) use ($domain) {
            $cover = trim($item['cover'] ?? '');
            if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
                $item['cover'] = $cover;
            } elseif ($cover === '') {
                $item['cover'] = $domain . '/uploads/images/koreandefualt.png';
            } else {
                $item['cover'] = $domain . '/' . ltrim($cover, '/');
            }
            $item['rank'] = rand(1, 100);
        });

        return json([
            'koreandata' => $data->toArray(),
            'pagination' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $pageSize,
                'total'        => $total,
            ],
            'types' => 1,
        ]);
    }

    public function concrete($id)
    {
        $ThaitaiwaneseModel = new \app\model\Thaitaiwanese();
        $domain = $this->request->domain();
        $shortinfo = $ThaitaiwaneseModel->where('id', $id)->find();

        if ($shortinfo['name'] || $shortinfo['crew']) {
            $shortinfo['key'] = $shortinfo['name'] . ',' . $shortinfo['crew'];
        }
        if ($shortinfo['oneselfquarklink']) {
            $shortinfo['quark'] = $shortinfo['oneselfquarklink'];
        } else if ($shortinfo['quarklink'] != "未找到" && $shortinfo['quarklink'] != "失效链接") {
            $shortinfo['quark'] = $shortinfo['quarklink'];
        } else {
            $shortinfo['quark'] = "";
        }

        if ($shortinfo['oneselfbaidulink']) {
            $shortinfo['baidu'] = $shortinfo['oneselfbaidulink'];
        } else if ($shortinfo['baidulink'] != "未找到" && $shortinfo['baidulink'] != "失效链接") {
            $shortinfo['baidu'] = $shortinfo['baidulink'];
        } else {
            $shortinfo['baidu'] = "";
        }

        if ($shortinfo['finished']) {
            $shortinfo['status'] = "已完结";
        } else {
            $shortinfo['status'] = "连载中";
        }
        $shortinfo['allEpis'] = $shortinfo['conerMemo'];
        $cover = trim($shortinfo['cover'] ?? '');
        if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
            $shortinfo['cover'] = $cover;
        } elseif (empty($cover)) {
            $shortinfo['cover'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
        } else {
            $shortinfo['cover'] = $this->request->domain() . '/' . ltrim($cover, '/');
        }
        if ($shortinfo['publishTime']) {
            $shortinfo['publishTime'] = date('Y-m-d', strtotime($shortinfo['publishTime']));
        }

        $shortinfo['description'] = mb_substr($shortinfo['intro'], 0, 142) .
            (mb_strlen($shortinfo['intro']) > 142 ? '...' : '');
        $all_data = $ThaitaiwaneseModel->orderRaw('years desc')->orderRaw('RAND()')->select()->toArray();

        $total_count = count($all_data);
        $start_index = max(0, floor($total_count / 2) - 11);
        $end_index = min($total_count, $start_index + 16);
        $related_data       = $this->processIntroFields($this->normalizeImageFields(array_slice($all_data, $start_index, $end_index - $start_index)));
        $recommend_data     = $this->processIntroFields($this->normalizeImageFields($ThaitaiwaneseModel->orderRaw('years desc')->orderRaw('RAND()')->limit(8)->select()->toArray()));
        $comprehensive_data = $this->processIntroFields($this->normalizeImageFields($ThaitaiwaneseModel->order('years desc')->limit(10)->select()->toArray()));

        $response = [
            'shortinfo' => $shortinfo, // 详情
            'related_data' => $related_data, // 相关推荐 
            'recommend_data' => $recommend_data, // 为您推荐(随机8条数据)
            'comprehensive_data' => $comprehensive_data, //综合榜(10条)
        ];
        return view('concrete', ['shortdetail' => $response, 'types' => 0]);
    }

    public function koreans()
    {
        if ($this->request->isAjax()) {
            $page = max((int)$this->request->param('page', 1), 1);
            $years  = trim($this->request->param('years', '2025'));

            $pageSize = 20;

            $koreansModel = new \app\model\Koreanshort();

            $query = $koreansModel->order('publishTime', 'desc')->where('years', $years);

            $total = (clone $query)->count();
            $lastPage = max(ceil($total / $pageSize), 1);

            $data = $query
                ->page($page, $pageSize)
                ->select()
                ->each(function ($item) {
                    if (is_string($item['image'])) {
                        $image = json_decode($item['image'], true);
                    } else {
                        $image = $item['image'] ?? [];
                    }
                    $item['lastSerialNo'] = $item['lastSerialNo'] ?? '';
                    $item['thumb'] = $image['thumb'] ?? '';
                    $item['poster'] = $image['poster'] ?? '';
                    $item['posterThumb'] = $image['posterThumb'] ?? '';
                    $item['updPoster'] = $image['updPoster'] ?? '';
                    $cover = trim($item['cover'] ?? '');
                    if (preg_match('#^https?://#i', $cover) || preg_match('#^//#i', $cover) || (strpos($cover, '.') !== false && $cover[0] !== '/')) {
                        $item['cover'] = $cover;
                    } elseif (empty($cover)) {
                        $item['cover'] = $this->request->domain() . '/uploads/images/koreandefualt.png';
                    } else {
                        $item['cover'] = $this->request->domain() . '/' . ltrim($cover, '/');
                    }
                    unset($item['image']);
                    $intro = $item['intro'] ?? '';
                    preg_match('/类型:&nbsp;([^<]+)/i', $intro, $matches);
                    if (!empty($matches[1])) {
                        $rawTypes = strip_tags(html_entity_decode($matches[1]));
                        $types = array_map('trim', explode('/', $rawTypes));
                    } else {
                        $types = [];
                    }
                    $item['tag'] = $types;

                    return $item;
                })->toArray();
            $pagination = [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $pageSize,
                'total'        => $total,
            ];
            $response = [
                'koreandata' => $data,
                'pagination' => $pagination,
                'types' => 1,
            ];
            return json($response);
        }
        return view('koreans', ['types' => 1]);
    }

    public function info()
    {
        $shortinfoModel = new \app\model\Shortinfo();
        $domain = $this->request->domain();

        $page = $this->request->param('page', 1);
        $pageSize = 30;

        $paginator = $shortinfoModel->order('publishTime', 'desc')->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
        ]);

        $shortinfo = $paginator->toArray();
        foreach ($shortinfo['data'] as &$item) {
            $item['thumb'] = $domain . $item['thumb'];
            $item['publishTime'] = date('Y-m-d', strtotime($item['publishTime']));
        }

        $current = (int)$shortinfo['current_page'];
        $last = (int)$shortinfo['last_page'];
        $half = 2;
        $start = max(1, $current - $half);
        $end = min($last, $current + $half);

        if ($end - $start + 1 < 5) {
            if ($start == 1) {
                $end = min($last, 5);
            } else {
                $start = max(1, $last - 4);
            }
        }
        $window = range($start, $end);

        $pagination = [
            'current_page' => $current,
            'last_page' => $last,
            'per_page' => (int)$shortinfo['per_page'],
            'total' => (int)$shortinfo['total'],
            'window' => $window,
        ];

        $response = [
            'shortinfos' => $shortinfo['data'],
            'pagination' => $pagination,
        ];

        return view('info', ['info' => $response, 'types' => 0]);
    }

    public function article($id)
    {
        $shortinfoModel = new \app\model\Shortinfo();
        $koreansModel = new \app\model\Koreanshort();
        $domain = $this->request->domain();
        $articleinfo = $shortinfoModel->where('id', $id)->find();
        $articleinfo['thumb'] = $domain . $articleinfo['thumb'];
        $shortinfo = $shortinfoModel->limit(5)->select()->toArray();
        $koreaninfo = $koreansModel->order('rank desc')->limit(10)->select()->toArray();
        foreach ($koreaninfo as $item) {
            $item['thumb'] = $domain . $item['cover'];
        }
        foreach ($shortinfo as $item) {
            $item['thumb'] = $domain . $item['thumb'];
        }
        $response = [
            'articledetail' => $articleinfo,
            'shortinfo' => $shortinfo,
            'koreaninfo' => $koreaninfo,
        ];
        return view('article', ['article' => $response, 'types' => 0]);
    }


    private function normalizeImageFields(array $items): array
    {
        return array_map(function ($item) {
            $image = [];
            if (isset($item['image'])) {
                if (is_string($item['image'])) {
                    $image = json_decode($item['image'], true) ?: [];
                } elseif (is_array($item['image'])) {
                    $image = $item['image'];
                }
            }

            $item['thumb']       = $image['thumb'] ?? '';
            $item['poster']      = $image['poster'] ?? '';
            $item['posterThumb'] = $image['posterThumb'] ?? '';
            $item['updPoster']   = $image['updPoster'] ?? '';

            return $item;
        }, $items);
    }

    private function processIntroFields(array $data): array
    {
        foreach ($data as &$item) {
            $this->processIntro($item);
        }
        unset($item);
        return $data;
    }

    private function processIntro(array &$item): void
    {
        if (empty($item['intro'])) {
            return;
        }

        $html = $item['intro'];

        if (preg_match('#^简介：#u', $html)) {
            $html = '<p>' . $html . '</p>';
        }

        $html = preg_replace('#<p>\s*&nbsp;\s*</p>#i', '', $html);

        preg_match('#<p>(.*?)</p>#is', $html, $introMatch);
        $item['summarized'] = trim(strip_tags($introMatch[1] ?? ''));

        $fields = [
            '编剧'           => '#编剧:&nbsp;([^<]+)#i',
            '主演'           => '#主演:&nbsp;(.+?)<br#is',
            '类型'           => '#类型:&nbsp;([^<]+)#i',
            '制片国家/地区' => '#制片国家/地区:&nbsp;([^<]+)#i',
            '语言'           => '#语言:&nbsp;([^<]+)#i',
            '集数'           => '#集数:&nbsp;([^<]+)#i',
            '又名'           => '#又名:&nbsp;([^<]+)#i',
        ];

        $out = '';
        foreach ($fields as $label => $pattern) {
            preg_match($pattern, $html, $m);
            $value = trim(strip_tags(html_entity_decode($m[1] ?? '')));

            if ($value === '') continue;

            if ($label === '主演') {
                $actors = preg_split('#\s*/\s*#u', $value);
                $value = implode(' / ', array_map('trim', $actors));
            }

            $out .= "<div>{$label}：{$value}</div>";
        }

        $item['metaHtml'] = $out;

        if (!empty($item['publishTime'])) {
            $timestamp = strtotime($item['publishTime']);
            if ($timestamp !== false) {
                $item['publishTime'] = date('Y-m-d', $timestamp);
            }
        }
    }
}
