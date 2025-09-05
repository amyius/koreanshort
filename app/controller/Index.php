<?php

namespace app\controller;

use app\BaseController;
use think\facade\Log;

class Index extends BaseController
{
    public function index()
    {
        $yesterdayDate = date('Y-m-d', strtotime("-1 day"));
        $shortModel = new \app\model\Koreanshort();
        $shortinfo = $shortModel->order('publishTime', 'desc')->limit(32)->select()->toArray();
        $domain = $this->request->domain();
        foreach ($shortinfo as $key => $value) {
            if ($value['cover'] == '') {
                $shortinfo[$key]['cover'] = $this->request->domain() . '/uploads/images/img_4129fc24b709a042692db1d3db0328b7.jpg';
            } else if (!preg_match('/^https?:\/\//', $value['cover'])) {
                $shortinfo[$key]['cover'] = $domain . '/' . $value['cover'];
            } else {
                $shortinfo[$key]['cover'] = $value['cover'];
            }
        }
        $shorthotinfo = $shortModel->orderRaw('RAND()')->limit(32)->select()->toArray();
        foreach ($shorthotinfo as $key => $value) {

            if ($value['cover'] == '') {
                $shorthotinfo[$key]['cover'] = $this->request->domain() . '/uploads/images/img_4129fc24b709a042692db1d3db0328b7.jpg';
            } else if (!preg_match('/^https?:\/\//', $value['cover'])) {
                $shorthotinfo[$key]['cover'] = $domain . '/' . $value['cover'];
            }
        }
        $carousel_data = $shortModel->order('rank', 'asc')->limit(6)->select()->toArray();
        foreach ($carousel_data as $key => $value) {
            $domain = $this->request->domain();
            if ($value['cover'] == '' || $value['cover'] == 'null') {
                $carousel_data[$key]['cover'] = $domain . "/static/img/fj1.jpg";
            } else if (!preg_match('/^https?:\/\//', $value['cover'])) {
                $carousel_data[$key]['cover'] = $domain . '/' . $value['cover'];
            }
        }
        $response = [
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
            $html = preg_replace('#<p>\s*&nbsp;\s*</p>#i', '', $shortinfo['intro']);

            if (!preg_match('#^\s*<p>#i', $html)) {
                $html = '<p>' . $html;
            }

            preg_match('#<p>(.*?)</p>#is', $html, $introMatch);
            $shortinfo['summarized'] = trim(strip_tags($introMatch[1] ?? ''));
            $fields = [
                '编剧'           => '#编剧:&nbsp;([^<]+)#i',
                '主演'           => '#主演:&nbsp;([^<]+)#i',
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
                    $value = implode(' / ', preg_split('#\s*/\s*#u', $value));
                }

                $out .= "<div>{$label}：{$value}</div>";
            }

            $shortinfo['metaHtml'] = $out;
        }
        if ($shortinfo['name'] || $shortinfo['crew']) {
            $shortinfo['key'] = $shortinfo['name'] . ',' . $shortinfo['crew'] . ',' . implode(',', $shortinfo['tag'] ?? []);
        }
        if ($shortinfo['finished']) {
            $shortinfo['status'] = "已完结";
        } else {
            $shortinfo['status'] = "连载中";
        }
        $shortinfo['allEpis'] = $shortinfo['conerMemo'];
        if ($shortinfo['cover']) {
            $shortinfo['cover'] = $domain . $shortinfo['cover'];
        }
        if ($shortinfo['publishTime']) {
            $shortinfo['publishTime'] = date('Y-m-d', strtotime($shortinfo['publishTime']));
        }
        if ($shortinfo['relateStars']) {
            $starIds = json_decode($shortinfo['relateStars'], true);

            $starList = $castModel->where('sid', 'in', $starIds)
                ->select()
                ->toArray();


            foreach ($starList as $k => $item) {
                if (!empty($item['thumb'])) {
                    $starList[$k]['thumb'] = $domain . $item['thumb'];
                }
            }
            $shortinfo['starList'] = $starList;
        }

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
        return view('particulars', ['shortdetail' => $response]);
    }

    public function search()
    {
        $shortModel = new \app\model\Shortdata();
        if ($this->request->isAjax()) {
            $keyword = $this->request->param('keyword');
            $drama   = $this->request->param('drama', 'korean');
            $domain = $this->request->domain();
            $koreansModel = new \app\model\Koreanshort();
            $data = $koreansModel->where('name', 'like', '%' . $keyword . '%')->order('publishTime desc')->select();
            foreach ($data as $item) {
                $item['id'] = $item['id'];
                $item['name'] = $item['name'];
                $item['title'] = $item['name'];
                $item['cover'] = $this->request->domain() . $item['cover'];
                $item['img'] = $this->request->domain() . '/uploads/images/img_4129fc24b709a042692db1d3db0328b7.jpg';
                $item['episodes'] = $item['conerMemo'];
                $item['link'] = "/particulars/" . $item['id'] . "/" . rawurlencode(rawurlencode($item['name']));
                $item['drama'] = "korean";
            }
            return json($data);
        } else {
            return View('search');
        }
    }

    public function submits()
    {
        $foreignId   = (int)$this->request->param('foreignId', 0);
        $shortdataId = (int)$this->request->param('shortdataId', 0);
        $koreanId    = trim($this->request->param('koreanId', ''));
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
        } else {
            $commentModel->korean_id = $koreanId;
        }

        $commentModel->save();

        return json(['code' => 1, 'msg' => '发布成功']);
    }

    public function rank()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->param();
            // type为0时总榜 hotnum ，1为最新日榜 rdj,2为周榜 zdj,3为月榜 ydj,
            $type = isset($params['type']) ? $params['type'] : '0';
            $shortModel = new \app\model\Shortdata();
            if ($type == 1) {
                $yesterdayDate = date('Y-m-d', strtotime("-1 day"));
                $rank = $shortModel->where('updatedate', $yesterdayDate)->where('is_down', 0)->order('rdj desc')->order('cover desc')->limit(30)->select();
            } else if ($type == 2) {
                $startOfWeek = date('Y-m-d', strtotime('this week monday'));
                $endOfWeek = date('Y-m-d', strtotime('this week sunday'));
                $rank = $shortModel->whereBetween('updatedate', [$startOfWeek, $endOfWeek])->where('is_down', 0)->order('zdj desc')->order('cover desc')->limit(30)->select();
            } else if ($type == 3) {
                // 计算本月的起始日期和结束日期
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');
                $rank = $shortModel->whereBetween('updatedate', [$startOfMonth, $endOfMonth])->where('is_down', 0)->order('ydj desc')->order('cover desc')->limit(30)->select();
            } else {
                $rank = $shortModel->where('is_down', 0)->order('hotnum desc')->order('cover desc')->limit(30)->select();
            }
            foreach ($rank as $item) {
                $domain = $this->request->domain();
                if ($item['cover'] == '' || $item['cover'] == 'null') {
                    $item['cover'] = $domain . "/uploads/images/img_4129fc24b709a042692db1d3db0328b7.jpg";
                } else if (!preg_match('/^https?:\/\//', $item['cover'])) {
                    $item['cover'] = $domain . '/' . $item['cover'];
                }
                $title = $this->processArticleName($item['name']);
                $item['title'] = $title['title'];
                if ($item['tag'] == '' || $item['tag'] == null) {
                    $item['tag'] = ['其他'];
                } else {
                    $item['tag'] = explode('|', $item['tag']);
                }
            }
            return json(['code' => 1, 'data' => $rank]);
        }
        return view('rank');
    }

    public function weekly()
    {
        if ($this->request->isAjax()) {
            $shortModel = new \app\model\Shortdata();
            $params = $this->request->param();
            $date = isset($params['date']) ? $params['date'] : date('Y-m-d');

            $hotWeekly = $shortModel->where('updatedate', $date)
                ->where('is_down', 0)
                ->order('hotnum', 'desc')
                ->order('cover', 'desc')
                ->limit(3)
                ->select();
            foreach ($hotWeekly as &$item) {
                $item['is_hot'] = 1;
            }

            // 获取剩余数据
            $remainingWeekly = $shortModel->where('updatedate', $date)
                ->where('is_down', 0)
                ->order('hotnum', 'desc')
                ->order('cover', 'desc')
                ->limit(3, PHP_INT_MAX)
                ->select();
            foreach ($remainingWeekly as &$item) {
                $item['is_hot'] = 0;
            }

            // 合并数据
            $weekly = $hotWeekly->merge($remainingWeekly);
            $domain = $this->request->domain();
            foreach ($weekly as $key => $value) {
                if ($value['cover'] == '' || $value['cover'] == NULL) {
                    $weekly[$key]['cover'] = $this->request->domain() . '/uploads/images/img_4129fc24b709a042692db1d3db0328b7.jpg';
                } else if (!preg_match('/^https?:\/\//', $value['cover'])) {
                    $weekly[$key]['cover'] = $domain . '/' . $value['cover'];
                } else {
                    $weekly[$key]['cover'] = $value['cover'];
                }
                $title = $this->processArticleName($value['name']);
                $weekly[$key]['title'] = $title['title'];
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                if ($date == $yesterday) {
                    $weekly[$key]['is_new'] = 1;
                } else {
                    $weekly[$key]['is_new'] = 0;
                }

                if ($value['tag'] && $value['tag'] != "其他") {
                    $tag = explode('|', $value['tag']);
                    $value['tag'] = $tag;
                } else {
                    $weekly[$key]['tag'] = [$value['tag']];
                }
            }

            //查询当前这周数据
            // if ($type == 1) {
            //     $mondayDate = date('Y-m-d', strtotime('this week monday'));
            //     $rank = $shortModel->where('updatedate', $mondayDate)->order('zdj desc')->order('cover desc')->select();
            // } else if ($type == 2) {
            //     // 计算本周二的日期
            //     $tuesdayDate = date('Y-m-d', strtotime('this week tuesday'));
            //     $rank = $shortModel->where('updatedate', $tuesdayDate)->order('zdj desc')->order('cover desc')->select();
            // } else if ($type == 3) {
            //     // 计算本周三的日期
            //     $wednesdayDate = date('Y-m-d', strtotime('this week wednesday'));
            //     $rank = $shortModel->where('updatedate', $wednesdayDate)->order('zdj desc')->order('cover desc')->select();
            // } else if ($type == 4) {
            //     // 计算本周四的日期
            //     $thursdayDate = date('Y-m-d', strtotime('this week thursday'));
            //     $rank = $shortModel->where('updatedate', $thursdayDate)->order('zdj desc')->order('cover desc')->select();
            // } else if ($type == 5) {
            //     // 计算本周五的日期
            //     $fridayDate = date('Y-m-d', strtotime('this week friday'));
            //     $rank = $shortModel->where('updatedate', $fridayDate)->order('zdj desc')->order('cover desc')->select();
            // } else if ($type == 6) {
            //     // 计算本周六的日期
            //     $saturdayDate = date('Y-m-d', strtotime('this week saturday'));
            //     $rank = $shortModel->where('updatedate', $saturdayDate)->order('zdj desc')->order('cover desc')->select();
            // } else if ($type == 7) {
            //     // 计算本周日的日期
            //     $sundayDate = date('Y-m-d', strtotime('this week sunday'));
            //     $rank = $shortModel->where('updatedate', $sundayDate)->order('zdj desc')->order('cover desc')->select();
            // }
            return json(['code' => 1, 'data' => $weekly]);
        }
        // $startOfWeek = date('Y-m-d', strtotime('this week monday'));
        // $endOfWeek = date('Y-m-d', strtotime('this week sunday'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days', strtotime($yesterday)));

        // 格式化日期
        $startOfWeek = $sevenDaysAgo;
        $endOfWeek = date('Y-m-d', strtotime($yesterday));
        $shortModel = new \app\model\Shortdata();
        $yesterdayDate = date('Y-m-d', strtotime("-1 day"));
        $yesterdayUpdateCount = $shortModel->where('is_down', 0)->where('updatedate', $yesterdayDate)->count();
        return view('weekly', [
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'yesterdayUpdateCount' => $yesterdayUpdateCount
        ]);
    }

    public function foreign()
    {
        if ($this->request->isAjax()) {
            $page = max((int)$this->request->param('page', 1), 1);
            $day  = trim($this->request->param('day', 'all'));

            $pageSize = 20;

            $foreignModel = new \app\model\Foreigndata();

            $query = $foreignModel->order('datePublished', 'desc');

            switch ($day) {
                case 'month':
                    $start = date('Y-m-d 00:00:00', strtotime('first day of this month'));
                    $end   = date('Y-m-d 23:59:59',  strtotime('last day of this month'));
                    $query->whereBetween('datePublished', [$start, $end]);
                    break;

                case 'all':
                default:
                    break;
            }

            $total = (clone $query)->count();
            $lastPage = max(ceil($total / $pageSize), 1);

            $data = $query
                ->page($page, $pageSize)
                ->select()
                ->each(function ($item) {
                    $item->datePublished = date('Y-m-d', strtotime($item->datePublished));
                    $item->types         = [$item->types];
                });

            $pagination = [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $pageSize,
                'total'        => $total,
            ];
            $response = [
                'foreigndata' => $data,
                'pagination' => $pagination,
            ];
            return json($response);
        }
        return view('foreign');
    }

    public function series($id)
    {
        $foreignModel = new \app\model\Foreigndata();
        $shortinfo = $foreignModel->where('seriesId', $id)->find();

        if ($shortinfo['seriesName'] || $shortinfo['types']) {
            $shortinfo['key'] = $shortinfo['seriesName'] . ',' . $shortinfo['types'];
        }
        if ($shortinfo['datePublished']) {
            $shortinfo['datePublished'] = date('Y-m-d', strtotime($shortinfo['datePublished']));
        }
        $all_data = $foreignModel->orderRaw('viewCount desc')->orderRaw('RAND()')->select()->toArray();

        $total_count = count($all_data);
        $start_index = max(0, floor($total_count / 2) - 11);
        $end_index = min($total_count, $start_index + 16);

        $related_data = array_slice($all_data, $start_index, $end_index - $start_index);

        $recommend_data = $foreignModel->orderRaw('viewCount desc')->orderRaw('RAND()')->limit(8)->select()->toArray(); // 确保转换为数组

        $comprehensive_data = $foreignModel->order('datePublished desc')->limit(10)->select()->toArray();

        $response = [
            'shortinfo' => $shortinfo, // 详情
            'related_data' => $related_data, // 相关推荐 
            'recommend_data' => $recommend_data, // 为您推荐(随机8条数据)
            'comprehensive_data' => $comprehensive_data, //综合榜(10条)
        ];
        return view('series', ['shortdetail' => $response]);
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

                    $item['thumb'] = $image['thumb'] ?? '';
                    $item['poster'] = $image['poster'] ?? '';
                    $item['posterThumb'] = $image['posterThumb'] ?? '';
                    $item['updPoster'] = $image['updPoster'] ?? '';
                    $item['cover'] = isset($item['cover']) ? $this->request->domain() . $item['cover'] : '';
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
            ];
            return json($response);
        }
        return view('koreans');
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

        return view('info', ['info' => $response]);
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
        return view('article', ['article' => $response]);
    }

    private function processArticleName($name)
    {
        $title = '';
        $episode = '';
        $author1 = '';
        $author2 = '';

        if (preg_match('/^(.*?)（(\d+集)）(.*)$/', $name, $matches)) {
            $title = trim($matches[1]);
            $episode = trim($matches[2]);
            $authors = trim($matches[3]);

            $authorsParts = preg_split('/[&＆]/u', $authors, 2);;
            if (count($authorsParts) > 0) {
                $author1 = trim($authorsParts[0]);
            }
            if (count($authorsParts) > 1) {
                $author2 = trim($authorsParts[1]);
            }
        } else {
            $title = trim($name);
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return [
            'title' => $safeTitle,
            'episodes' => $episode,
            'author1' => $author1,
            'author2' => $author2
        ];
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

        $html = preg_replace('#<p>\s*&nbsp;\s*</p>#i', '', $item['intro']);

        if (!preg_match('#^\s*<p>#i', $html)) {
            $html = '<p>' . $html;
        }

        preg_match('#<p>(.*?)</p>#is', $html, $introMatch);
        $item['summarized'] = trim(strip_tags($introMatch[1] ?? ''));

        $fields = [
            '编剧'           => '#编剧:&nbsp;([^<]+)#i',
            '主演'           => '#主演:&nbsp;([^<]+)#i',
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
                $value = implode(' / ', preg_split('#\s*/\s*#u', $value));
            }

            $out .= "<div>{$label}：{$value}</div>";
        }

        $item['metaHtml'] = $out;
        if ($item['publishTime']) {
            $item['publishTime'] = date('Y-m-d');
        }
    }
}
