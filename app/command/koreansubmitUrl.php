<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;


class koreansubmitUrl extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('koreansubmitUrl')
            ->setDescription('the koreansubmitUrl command');
    }
    protected function execute(Input $input, Output $output)
    {
        $shortplay = Db::name('koreanshort')
            ->orderRaw('RAND()')
            ->limit(70)
            ->select()
            ->toArray();

        $urlList = [];
        foreach ($shortplay as $key => $value) {
            $urlList[] = 'https://www.koreanshort.com/particulars/' . $value['id'] . '/' . $value['name'];
        }
        $thaitaiwanesedramas = Db::name('thai_taiwanese_dramas')
            ->where('area', 'LIKE', '%泰国%')
            ->orderRaw('RAND()')
            ->limit(13)
            ->select()
            ->toArray();
        foreach ($thaitaiwanesedramas as $key => $value) {
            $urlList[] = 'https://www.koreanshort.com/concrete/' . $value['id'] . '/' . $value['name'];
        }

        $thaitaiwanesedramas = Db::name('thai_taiwanese_dramas')
            ->where('area', 'LIKE', '%台湾%')
            ->orderRaw('RAND()')
            ->limit(14)
            ->select()
            ->toArray();
        foreach ($thaitaiwanesedramas as $key => $value) {
            $urlList[] = 'https://www.koreanshort.com/concrete/' . $value['id'] . '/' . $value['name'];
        }

        $thaitaiwanes = Db::name('thai_taiwanese_dramas')
            ->where('area', 'LIKE', '%泰国%')
            ->orderRaw('RAND()')
            ->limit(13)
            ->select()
            ->toArray();
        foreach ($thaitaiwanes as $key => $value) {
            $urlList[] = 'https://www.koreanshort.com/concrete/' . $value['id'] . '/' . $value['name'];
        }

        $urlList[] = 'https://www.koreanshort.com';
        $urlList[] = 'https://www.koreanshort.com/info';
        $urlList[] = 'https://www.koreanshort.com/koreans';
        $data = [
            'siteUrl' => 'https://www.koreanshort.com',
            'urlList' => $urlList
        ];
        $json = json_encode($data);
        $url = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=f8f7030bdb284e75aa41c4100f34569c';
        $ch = curl_init();

        // 设置 cURL 选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // 执行 cURL 请求 
        $response = curl_exec($ch);

        // 检查是否有错误发生
        if (curl_errno($ch)) {
            echo 'CURL Error: ' . curl_error($ch); // 打印错误信息
        } else {
            echo 'Response: ' . $response;
        }
        $output->writeln('提交成功');
    }
}
