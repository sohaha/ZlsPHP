<?php

namespace Controller;

use Z;

/**
 * ZlsApi 接口示例
 * @desc          示例用的
 * @key           zls
 * @author        影浅 seekwe@gmail.com
 */
class ZlsApi extends Index
{
    /**
     * 入口
     * @time       2017-05-30 21:32
     * @param string say 示例字段 G 运行成功Api
     * @api-return int code 状态码
     * @api-return string data.clientIp 客户端IP
     * @return array {"code":200,"msg":"ok","data":{"0":"运行成功Api","clientIp":"172.19.0.1","1":"http://zls.test/ZlsApi/Index.go","2":"index[runtime:0.001s,memory:1.92kb]","3":"[runtime:0.006s,memory:9.9kb]"}}
     */
    public function zIndex()
    {
        Z::debug('index');

        return [
            200, 'ok', [
                Z::getPost('say', '运行成功Api'),
                'clientIp' => Z::clientIp(),
                Z::host(true, true, true),
                Z::debug('index', true, true),
                Z::debug(),
            ],
        ];
    }

    /**
     * POST请求入口
     * @time 2019-1-7 13:04:28
     */
    public function POSTzIndex()
    {
        return [200, 'IS_POST'];
    }

    /**
     * 控制器返回数组时，直接输出 json 数据
     * @param $contents
     * @param $methodName
     * @param $controllerShort
     * @param $args
     * @param $controller
     * @return mixed|string
     */
    public function after($contents, $methodName, $controllerShort, $args, $controller)
    {
        if (is_array($contents)) {
            return Z::json($contents);
        }

        return $contents;
    }
}
