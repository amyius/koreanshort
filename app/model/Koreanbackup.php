<?php

namespace app\model;

use think\Model;

class Koreanbackup extends Model
{
    // 设置表名
    protected $name = 'koreanshort';
    
    // 设置主键
    protected $pk = 'id';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'sid'         => 'string',
        'category'    => 'string', 
        'name'        => 'string',
        'years'       => 'string',
        'image'       => 'string',
        'cover'       => 'string',
        'quarklink'   => 'string',
        'baidulink'   => 'string',
        'crew'        => 'string',
        'finished'    => 'int',
        'conerMemo'   => 'string',
        'intro'       => 'text',
        'publishTime' => 'datetime',
        'type'        => 'string',
        'created_at'  => 'datetime',
        'rank'        => 'int',
        'lastSerialNo'=> 'string'
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = false;
    
    // 允许批量赋值的字段
    protected $field = [
        'sid', 'category', 'name', 'years', 'image', 'cover', 
        'quarklink', 'baidulink', 'crew', 'finished', 'conerMemo', 
        'intro', 'publishTime', 'type', 'created_at', 'rank', 'lastSerialNo'
    ];
}