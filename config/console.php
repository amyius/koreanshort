<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'koreansubmitUrl' => 'app\command\koreansubmitUrl',
        'koreanshort' => 'app\command\koreanshort',
        'koreancrew' => 'app\command\koreancrew',
        'koreanplay' => 'app\command\koreanplay',
        'update:episodes' => 'app\command\UpdateEpisodes',
        'check:data' => 'app\command\CheckData',
        'clean:crew' => 'app\command\CleanCrewField',
        'fix:crew' => 'app\command\FixCrewFormat',
        'update:crew' => 'app\command\UpdateCrew',
        'update:years' => 'app\command\UpdateYears',
    ],
];
