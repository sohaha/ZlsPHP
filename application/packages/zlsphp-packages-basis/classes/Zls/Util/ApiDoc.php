<?php
namespace Zls\Util;
use Z;
/**
 * 生成Api文档
 * @author        影浅-Seekwe
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @since         v1.1.0
 * @updatetime    2018-3-8 16:03:07
 */
class ApiDoc
{
    private static $TYPEMAPS = [
        'string'  => '字符串',
        'phone'   => '手机号码',
        'eamil'   => '电子邮箱',
        'int'     => '整型',
        'float'   => '浮点型',
        'boolean' => '布尔型',
        'date'    => '日期',
        'array'   => '数组',
        'fixed'   => '固定值',
        'enum'    => '枚举类型',
        'object'  => '对象',
        'json'    => 'json',
    ], $REPETITION = [];
    /**
     * @param bool $global
     * @return array
     * @throws \ReflectionException
     */
    public static function all($global = false)
    {
        $arr = [];
        $config = z::config();
        $hmvcName = $config->getRoute()->gethmvcModuleName();
        self::listDirApiPhp($config->getApplicationDir() . $config->getClassesDirName() . '/' . $config->getControllerDirName() . '/',
            $arr, $hmvcName);
        $ret = [];
        foreach ($arr as $k => $class) {
            $_hmvc = $hmvc = $class['hmvc'];
            if (!!$hmvc) {
                $class['controller'] = 'Hmvc_' . $class['controller'];
            }
            $controller = $class['controller'];
            if ($config->hmvcIsDomainOnly($hmvc)) {
                continue;
            }
            $data = self::docComment($controller, $hmvc);
            if (!$data) {
                continue;
            }
            $ret[$controller] = $data[0];
        }
        return $ret;
    }
    public static function listDirApiPhp($dir, &$arr, $hmvc = null, $Subfix = 'Api.php')
    {
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if ((is_dir($dir . "/" . $file)) && $file != "." && $file != "..") {
                        self::listDirApiPhp($dir . $file . "/", $arr, $hmvc);
                    } else {
                        if (z::strEndsWith($file, $Subfix)) {
                            $uri = explode('Controller/', $dir);
                            $arr[] = [
                                'controller' => 'Controller_' . str_replace('/', '_', $uri[1]) . str_replace('.php', '',
                                        $file),
                                'hmvc'       => $hmvc,
                            ];
                        }
                    }
                }
                closedir($dh);
            }
        }
    }
    /**
     * @param null   $controller
     * @param string $hmvcName
     * @param bool   $library
     * @return array|bool
     * @throws \ReflectionException
     */
    public static function docComment($controller = null, $hmvcName = '', $library = false)
    {
        $controller = self::getClassName($controller);
        if (!$controller) {
            return false;
        }
        $methods = self::getMethods($controller, 'public');
        if (!$class = self::apiClass($controller, null, $hmvcName)) {
            return false;
        }
        $methodArr = [];
        foreach ($methods as $method) {
            if (!$library && strpos($method, z::config()->getMethodPrefix()) !== 0) {
                continue;
            }
            $methodArr[] = self::apiMethods($controller, $method, false, $hmvcName, $library);
        }
        return [['class' => $class, 'method' => $methodArr]];
    }
    public static function getClassName($className)
    {
        return (get_class(Z::factory($className)));
    }
    /**
     * @param      $className
     * @param null $access
     * @return array
     * @throws \ReflectionException
     */
    public static function getMethods($className, $access = null)
    {
        $class = new \ReflectionClass($className);
        $methods = $class->getMethods();
        $returnArr = [];
        foreach ($methods as $value) {
            if ($value->class == $className) {
                if ($access != null) {
                    $methodAccess = new \ReflectionMethod($className, $value->name);
                    switch ($access) {
                        case 'public':
                            if ($methodAccess->isPublic()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                        case 'protected':
                            if ($methodAccess->isProtected()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                        case 'private':
                            if ($methodAccess->isPrivate()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                        case 'final':
                            if ($methodAccess->isFinal()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                    }
                } else {
                    $returnArr[] = $value->name;
                }
            }
        }
        return $returnArr;
    }
    /**
     * 扫描class
     * @param  string $controller
     * @param  string $setKey
     * @param string  $hmvc
     * @return array|boolean
     * @throws \ReflectionException
     */
    private static function apiClass($controller, $setKey = null, $hmvc = '')
    {
        if (!class_exists($controller)) {
            return false;
        }
        $rClass = new \ReflectionClass($controller);
        $dComment = $rClass->getDocComment();
        $docInfo = [
            'title'      => null,
            'key'        => null,
            'desc'       => null,
            'url'        => '',
            'hmvc'       => $hmvc,
            'controller' => str_replace('_', '/', substr($controller, !!$hmvc ? 16 : 11)),
            'repetition' => [],
        ];
        $docInfo['controller'] = str_replace('\\', '/', $docInfo['controller']);
        if ($dComment !== false) {
            $doctArr = explode("\n", $dComment);
            $comment = trim($doctArr[1]);
            $docInfo['title'] = trim(substr($comment, strpos($comment, '*') + 1));
            foreach ($doctArr as $comment) {
                if ($desc = self::getDocInfo($comment, 'desc')) {
                    $docInfo['desc'] = trim($desc);
                    continue;
                }
                if ($key = self::getDocInfo($comment, 'key')) {
                    $getParams = explode('|', z::get('_key', $setKey));
                    if (!in_array(trim($key), $getParams)) {
                        return false;
                    }
                    $docInfo['key'] = trim($key);
                }
            }
        }
        if (is_null($docInfo['title'])) {
            $docInfo['title'] = '{请检查函数注释}';
        }
        $docInfo['url'] = ($docInfo['hmvc'] === z::config()->getCurrentDomainHmvcModuleNname()) ? $docInfo['controller'] : $docInfo['hmvc'] . '/' . $docInfo['controller'];
        return $docInfo;
    }
    private static function getDocInfo($str, $key, $resultStr = true)
    {
        $keys = ["@{$key} ", "@api-{$key} "];
        $res = '';
        foreach ($keys as $value) {
            $len = strlen($value);
            if ((stripos($str, $value)) !== false) {
                $pos = (z::strBeginsWith($value, '@api-')) ? $len - 4 : $len;
                $res = $resultStr ? trim(substr(trim(substr($str, strpos($str, '*') + 1)), $len)) : [trim($value), $pos];
                break;
            }
        }
        return $res;
    }
    /**
     * @param        $controller
     * @param null   $method
     * @param bool   $paramsStatus
     * @param string $hmvcName
     * @param bool   $library
     * @return bool
     * @throws \ReflectionException
     */
    public static function apiMethods(
        $controller,
        $method = null,
        $paramsStatus = false,
        $hmvcName = '',
        $library = false
    ) {
        if (!method_exists($controller, $method)) {
            return false;
        }
        $rMethod = new \Reflectionmethod($controller, $method);
        $substrStart = $hmvcName ? 16 : 11;
        if ($hmvcName && z::config()->getCurrentDomainHmvcModuleNname()) {
            $docInfo['url'] = (!$library) ? z::url('/' . str_replace('_', '/', substr($controller, $substrStart)) . '/' . substr($method,
                    strlen(z::config()->getMethodPrefix())) . z::config()->getMethodUriSubfix()) : $method;
        } else {
            $hmvcName = !!$hmvcName ? '/' . $hmvcName : '';
            $docInfo['url'] = (!$library) ? z::url($hmvcName . '/' . str_replace('_', '/', substr($controller, $substrStart)) . '/' . substr($method, strlen(z::config()->getMethodPrefix())) . z::config()->getMethodUriSubfix()) : $method;
        }
        $docInfo['url'] = str_replace('\\', '/', $docInfo['url']);
        $docInfo['title'] = '{未命名}';
        $docInfo['desc'] = '';//'//请使用@desc 注释';
        $docInfo['return'] = [];
        $docInfo['param'] = [];
        $dComment = $rMethod->getDocComment();
        if ($dComment !== false) {
            $doctArr = explode("\n", $dComment);
            $comment = trim($doctArr[1]);
            $docInfo['title'] = trim(substr($comment, strpos($comment, '*') + 1));
            foreach ($doctArr as $comment) {
                if ($desc = self::getDocInfo($comment, 'desc')) {
                    $docInfo['desc'] = trim($desc);
                    continue;
                }
                if ($desc = self::getDocInfo($comment, 'time')) {
                    $docInfo['time'] = trim($desc);
                    continue;
                }
                if (!!$paramsStatus) {
                    if ($return = self::getDocInfo($comment, 'return', false)) {
                        $docInfo['return'][] = self::getParams($return[1], $comment, $return[0]);
                        continue;
                    }
                    if ($return = self::getDocInfo($comment, 'param', false)) {
                        $docInfo['param'][] = self::getParams($return[1], $comment, $return[0]);
                        continue;
                    }
                }
            }
        }
        return $docInfo;
    }
    private static function getParams($pos, $comment, $type = 'return')
    {
        $retArr = explode(' ', substr($comment, $pos + strlen($type)));
        $retArr = array_values(array_filter($retArr));
        $count = count($retArr);
        if ($count < 2) {
            return false;
        }
        $isReturn = Z::strEndsWith($type, 'return');
        if ($isReturn && ($retArr[0] == 'json' || $retArr[0] == 'object')) {
            $data = json_decode(implode(' ', array_slice($retArr, 1)), true);
            return !!$data ? implode(' ', array_slice($retArr, 1)) : false;
        }
        $retArr = array_merge(array_filter($retArr, function ($e) {
            return $e == '' ? false : true;
        }));
        $ret = [];
        if ($isReturn) {
            $ret['title'] = z::arrayGet($retArr, 2, '--');
            $ret['desc'] = implode(' ', array_slice($retArr, 3));
        } else {
            $ret['title'] = z::arrayGet($retArr, 2, '--');
            $query = strtoupper(trim(z::arrayGet($retArr, 3)));
            $ret['query'] = ($query == 'P') ? 'POST' : (($query == 'G' || !$query) ? 'GET' : $query);
            $default = z::arrayGet($retArr, 4, '');
            $ret['default'] = ($default === '""' || $default === '\'\'') ? '' : $default;
            $ret['is'] = (strtoupper(trim(z::arrayGet($retArr, 5))) == 'N') ? '否' : '是';
            $ret['desc'] = implode(' ', array_slice($retArr, 6));
        }
        $ret['name'] = z::arrayGet($retArr, 1, '--');
        $ret['type'] = z::arrayGet(self::$TYPEMAPS, $retArr[0], $retArr[0]);
        return $ret;
    }
    public static function html($type = 'parent', $data)
    {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>接口</title><meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0"><link rel="stylesheet" href="//cdn.bootcss.com/bootstrap/3.2.0/css/bootstrap.min.css"><style>.panel-body,table{word-break:break-all}.w30{width:30%}h3,h4{margin:0}.alert-info{margin-top:10px;}</style></head><body><br/><div class="container" style="width:90%">';
        if (!!$data) {
            if ($type == 'self') {
                $updateTime = z::arrayGet($data, 'time', '--');
                $_host = z::host();
                $url = self::formatUrl($data['url'], '');
                echo <<<DD
<div class="page-header"><h2>{$data['title']}<h4>{$data['desc']}</h4><h5>更新时间 {$updateTime}</h5><h5><a target="_blank" href="{$url}"><button type="button" class="btn btn-primary btn-xs">GET</button></a> <a target="_blank" href="{$url}">
{$_host}{$url}</a></h5></h2></div><h3>请求参数</h3><table class="table table-striped table-bordered" >
<thead>
DD;
                if (count($data['param']) > 0) {
                    echo '<tr><th>参数名</th><th>请求方式</th><th>说明</th><th>类型</th><th>默认</th><th>必填</th><th class="w30">备注</th></tr>';
                    foreach ($data['param'] as $param) {
                        echo '<tr><td>' . $param['name'] . '</td><td>' . $param['query'] . '</td><td>' . $param['title'] . '</td><td>' . $param['type'] . '</td><td>' . $param['default'] . '</td><td>' . $param['is'] . '</td><td>' . $param['desc'] . '</td></tr>';
                    }
                }
                echo '</table><h3>返回示例</h3>';
                $returnHtml = $returnJson = '';
                foreach ($data['return'] as $return) {
                    if (is_string($return)) {
                        $returnJson .= '<div class="text-muted panel panel-default"><div class="bg-warning panel-body">' . self::formatJson($return) . '</div></div>';
                    } elseif (!!$return) {
                        $returnHtml .= '<tr><td>' . $return['name'] . '</td><td>' . $return['type'] . '</td><td>' . $return['title'] . '</td><td>' . $return['desc'] . '</td></tr>';
                    }
                }
                if (!!$returnHtml) {
                    echo '<table class="table table-striped table-bordered"><thead><tr><th>字段</th><th>类型</th><th class="w30">说明</th><th class="w30">备注</th></tr>' . $returnHtml . '</table>';
                }
                echo $returnJson;
                echo '<div role="alert" class="alert alert-info"><strong>温馨提示：</strong> 此接口参数列表根据后台代码自动生成，可将 xxx?_api=self 改成您需要查询的接口</div>';
            } else {
                $token = (!!$token = z::get('_token', '', true)) ? '&_token=' . $token : '';
                foreach ($data as $class) {
                    if (!z::arrayGet($class, 'class.controller')) {
                        continue;
                    }
                    $repetition = '';
                    foreach (z::arrayGet($class, 'class.repetition', []) as $i => $hmvc) {
                        $_url = self::formatUrl(z::url($hmvc . '/' . $class['class']['controller']), '?_api' . $token);
                        $repetition .= '<a href="' . $_url . '" target="_blank"><span class="label label-primary">' . $hmvc . '</span></a>';
                    }
                    echo '<div class="page-header jumbotrons"><h2>';
                    echo $class['class']['title'] . ':' . $class['class']['controller'];
                    echo '</h2><h4>' . $class['class']['desc'] . '</h4><h3>' . $repetition . '</h3></div>';
                    echo '<table class="table table-hover table-bordered"><thead><tr><th class="col-md-4">接口服务</th><th class="col-md-3">接口名称</th><th class="col-md-2">更新时间</th><th class="col-md-4">更多说明</th></tr></thead><tbody>';
                    foreach ($class['method'] as $v) {
                        $updateTime = z::arrayGet($v, 'time', '--');
                        $url = self::formatUrl($v['url'], '?_api=self' . $token);
                        $url .= ($class['class']['key']) ? '&_key=' . $class['class']['key'] : '';
                        echo '<tr><td><a href="' . $v['url'] . '" target="_blank"><button type="button" class="btn btn-primary btn-xs">GET</button></a> <a href="' . $url . '" target="_blank"><button type="button" class="btn btn-success btn-xs">INFO</button>  ' . $v['url'] . '</a></td><td>' . $v['title'] . '</td><td>' . $updateTime . '</td><td>' . $v['desc'] . '</td></tr>';
                    }
                    echo '</tbody></table>';
                }
                echo '<div role="alert" class="alert alert-info"><strong>温馨提示：</strong> 此接口参数列表根据后台代码自动生成，在任意链接追加?_api=all查看所有接口</div>';
            }
        } else {
            echo '<h2>没有找到API接口数据</h2>';
        }
        echo '</div></body></html>';
    }
    public static function formatUrl($url, $args)
    {
        $args = \ltrim($args, '?');
        $parse = parse_url($url);
        $path = z::arrayGet($parse, 'path', '');
        $query = z::arrayGet($parse, 'query', '');
        $query = ($query ? $query . '&' . $args : $args);
        $newUrl = $path . ($query ? '?' . $query : '');
        return $newUrl;
    }
    public static function formatJson($json = '')
    {
        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $indentStr = '&nbsp;';
        $newLine = "<br>";
        $prevChar = '';
        $outOfQuotes = true;
        for ($i = 0; $i <= $strLen; $i++) {
            $char = substr($json, $i, 1);
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;
            } else {
                if (($char == '}' || $char == ']') && $outOfQuotes) {
                    $result .= $newLine;
                    $pos--;
                    for ($j = 0; $j < $pos; $j++) {
                        $result .= $indentStr;
                    }
                }
            }
            $result .= $char;
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos++;
                }
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }
            $prevChar = $char;
        }
        return $result;
    }
}
