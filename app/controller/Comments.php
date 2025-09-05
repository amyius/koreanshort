<?php

namespace app\controller;

use app\BaseController;
use think\facade\DB;

class Comments extends BaseController
{
    public function index()
    {
        $shortdataId = (int)$this->request->param('shortdataId', 0);
        $foreignId   = (int)$this->request->param('foreignId', 0); 
        $koreanId    = trim($this->request->param('koreanId', ''));

        $validCount = ($shortdataId > 0) + ($foreignId > 0) + ($koreanId !== '');
        if ($validCount !== 1) {
            return json(['code' => 0, 'msg' => '必须且只能提供 shortdataId、foreignId、koreanId 中的一个']);
        }

        $commentModel = new \app\model\Comments();

        $query = $commentModel->order('created_at desc')->limit(10);

        if ($shortdataId) {
            $comments = $query->where('shortdata_id', $shortdataId)->select();
        } elseif ($foreignId) {
            $comments = $query->where('foreign_id', $foreignId)->select();
        } else {
            $comments = $query->where('korean_id', $koreanId)->select();
        }

        return json(['code' => 1, 'msg' => 'success', 'data' => $comments]);
    }
}
