<?php
namespace Zls\Action;
/**
 * Http操作封装类
 * @author        影浅-Seekwe
 * @email       seekwe@gmail.com
 * @since         v2.0.15
 * @updatetime    2017-12-29 18:34:07
 * 需要php curl支持
 */
use Z;
class Http
{
    private $responseInfo, $responseHeader, $responseBody, $ch, $lastUrl, $defineIp,
        $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.117 Safari/537.36',
        $referer, $error = ['code' => 0, 'msg' => ''], $sleep = 0, $responseHeaderMulti = [], $responseBodyMulti = [], $responseInfoMulti = [], $responseErrorMulti = [];
    public function __construct()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
    }
    public function instance()
    {
        return $this->ch;
    }
    /**
     * 设置cookie文件存储路径
     * @param string $cookie_path cookie文件路径
     * @return \Zls\Action\Http
     */
    public function setCookieFilePath($cookie_path = null)
    {
        if ($cookie_path !== false) {
            if (empty($cookie_path)) {
                $cookie_path = z::tempPath() . '/' . 'Zls_http_cookie_' . md5(uniqid('', true));
                $functionName = 'Zls_clean_' . md5(uniqid('', true));
                eval('function ' . $functionName . '() {
                $path= "' . $cookie_path . '";
                if (file_exists($path)) {
                    unlink($path);
                }
                }');
                register_shutdown_function($functionName);
            }
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $cookie_path);
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $cookie_path);
        }
        return $this;
    }
    /**
     * 设置证书
     * @param      $certPath
     * @param null $keyPath
     */
    public function setSsl($certPath, $keyPath = null)
    {
        if (!$keyPath) {
            curl_setopt($this->ch, CURLOPT_SSLCERT, $certPath);
        } else {
            curl_setopt($this->ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($this->ch, CURLOPT_SSLCERT, $certPath);
            curl_setopt($this->ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($this->ch, CURLOPT_SSLKEY, $keyPath);
        }
    }
    /**
     * 设置每次请求之后sleep的时间，单位毫秒
     * @param int $microSeconds
     * @return \Zls\Action\Http
     */
    public function sleep($microSeconds)
    {
        $this->sleep = $microSeconds;
        return $this;
    }
    public function userAgent($user_agent)
    {
        $this->userAgent = $user_agent;
    }
    /**
     * 设置ip
     * @param null $forwarded
     * @param null $client
     */
    public function setIP($forwarded = null, $client = null)
    {
        if (!$forwarded) {
            $forwarded = $this->ipRand();
        }
        if (!$client) {
            $client = $this->ipRand();
        }
        $this->defineIp = ['X-FORWARDED-FOR:' . $forwarded, 'CLIENT-IP:' . $client];
    }
    public function ipRand()
    {
        $arr_1 = ["218", "218", "66", "66", "218", "218", "60", "60", "202", "204", "66", "66", "66", "59", "61", "60", "222", "221", "66", "59", "60", "60", "66", "218", "218", "62", "63", "64", "66", "66", "122", "211"];
        $count = count($arr_1) - 1;
        $ip1id = $arr_1[mt_rand(0, $count)];
        $ip2id = $arr_1[mt_rand(0, $count)];
        $ip3id = $arr_1[mt_rand(0, $count)];
        $ip4id = $arr_1[mt_rand(0, $count)];
        return $ip1id . "." . $ip2id . "." . $ip3id . "." . $ip4id;
    }
    public function proxy($ip, $port = 80, $username = '', $password = '')
    {
        curl_setopt($this->ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
        curl_setopt($this->ch, CURLOPT_PROXY, $ip);
        curl_setopt($this->ch, CURLOPT_PROXYPORT, $port);
        if ($username) {
            curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, "{$username}:{$password}");
        }
    }
    /**
     * 使用GET方式请求一个页面
     * @param String     $url         页面地址
     * @param array|null $data        要发送的数据数组或者原始数据，比如：array('user'=>'test','pass'=>'354534'),键是表单字段名称，值是表单字段的值，默认 null
     * @param array|null $header      附加的HTTP头，比如：array('Connection:keep-alive','Cache-Control:max-age=0')，注意冒号前后不能有空格，默认 null
     * @param int        $maxRedirect 遇到301或302时跳转的最大次数 ，默认 0 不跳转
     * @return String 页面数据
     */
    public function get($url, $data = null, array $header = null, $maxRedirect = 0)
    {
        return $this->request('get', $url, $data, $header, $maxRedirect);
    }
    /**
     * 发送一个http请求
     * @param string $type
     * @param string $url
     * @param array  $data
     * @param array  $header
     * @param int    $maxRedirect
     * @param null   $ch       手动设置curl_init
     * @param bool   $exec     是否直接返回curl_init对象
     * @param bool   $atUpload 开启@前缀自动上传文件
     * @return int|resource
     */
    private function request($type, $url, $data, $header = null, $maxRedirect = 0, $ch = null, $exec = true, $atUpload = false)
    {
        if (!$ch) {
            $ch = $this->ch;
        }
        $this->setError(0, '');
        $type = strtolower($type);
        $url = ltrim($url);
        //$this->curl_max_loops = $maxRedirect;
        if ($this->defineIp) {
            $header = $header ? array_merge($this->defineIp, $header) : $this->defineIp;
        }
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        if ($type == 'post') {
            $header = empty($header) ? [] : $header;
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Expect:'], $header));
            curl_setopt($ch, CURLOPT_POST, 1);
            if (empty($data)) {
                $data = '';
            } elseif (is_array($data)) {
                $foundUpload = false;
                if ($atUpload) {
                    foreach ($data as $k => $v) {
                        if (is_string($v) && $v && $v{0} == '@') {
                            $filepath = substr($v, 1);
                            if (file_exists($filepath)) {
                                $foundUpload = true;
                                if (class_exists('CURLFile', false)) {
                                    $data[$k] = new \CURLFile($filepath);
                                    if ($fileName = @basename($filepath)) {
                                        $data[$k]->setPostFilename($fileName);
                                    }
                                }
                            }
                        }
                    }
                }
                $data = $foundUpload ? $data : http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            if (!empty($header)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }
            $imchar = '';
            if (!is_array($data)) {
                $data = [];
            }
            $_data = [];
            foreach ($data as $key => $value) {
                $_data[] = $key . '=' . urlencode($value);
            }
            if (!empty($_data)) {
                $imchar = stripos($url, '?') !== false ? '&' : '?';
                $imchar .= implode('&', $_data);
                $url .= $imchar;
            }
            curl_setopt($ch, CURLOPT_POST, 0);
        }
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        $this->lastUrl = $url;
        if (!$exec) {
            return $ch;
        } else {
            $data = $this->curl_exec_follow($maxRedirect);
            if ($this->sleep) {
                usleep($this->sleep);
            }
            $this->reset();
            if (!$this->errorCode()) {
                $info = explode("\r\n\r\n", $data, 2);
                $this->responseHeader = isset($info[0]) ? $info[0] : '';
                $this->responseBody = isset($info[1]) ? $info[1] : '';
                $this->responseInfo = curl_getinfo($ch);
                //$this->reset();
                return $this->responseBody;
            } else {
                return '';
            }
        }
    }
    private function setError($error_code, $error_msg)
    {
        $this->error['code'] = $error_code;
        $this->error['msg'] = $error_msg;
        return $this;
    }
    /**
     * 带有重定向功能的exec
     * @param string $maxRedirect
     * @return boolean
     */
    private function curl_exec_follow($maxRedirect)
    {
        $maxRedirect = $maxRedirect < 0 ? 0 : $maxRedirect;
        if ($maxRedirect == 0) {
            $this->_autoReferer();
            $content = curl_exec($this->ch);
            if (curl_errno($this->ch)) {
                $this->setError(curl_errno($this->ch), curl_error($this->ch));
                return false;
            } else {
                return $content;
            }
        }
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
        $loops = 0;
        do {
            $this->_autoReferer();
            $data = curl_exec($this->ch);
            $this->reset();
            if (!curl_errno($this->ch)) {
                $info = explode("\r\n\r\n", $data, 2);
                $this->responseHeader = isset($info[0]) ? $info[0] : '';
                $this->responseBody = isset($info[1]) ? $info[1] : '';
                $this->responseInfo = curl_getinfo($this->ch);
                if (!$this->isRedirect()) {
                    $this->setError(0, '');
                    return $data;
                } else {
                    preg_match('/Location:(.*?)$/mi', $this->responseHeader, $matches);
                    $this->lastUrl = $url = $this->parseLocation(trim(array_pop($matches)));
                    curl_setopt($this->ch, CURLOPT_URL, $url);
                }
            } else {
                $this->setError(curl_errno($this->ch), curl_error($this->ch));
                return false;
            }
        } while (++$loops <= $maxRedirect);
        $this->setError(1000, 'MAXREDIRS reached');
        return false;
    }
    /**
     * referer自动设置
     */
    private function _autoReferer()
    {
        if (empty($this->referer)) {
            if (empty($this->lastUrl)) {
                curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
            } else {
                curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
            }
        } else {
            curl_setopt($this->ch, CURLOPT_REFERER, $this->referer);
        }
    }
    /**
     * 每次请求完成后，进行一些清理
     */
    private function reset()
    {
        $this->referer = null;
        curl_setopt($this->ch, CURLOPT_COOKIE, null);
    }
    /**
     * 请求完成后，响应是否是重定向
     * @param null $code
     * @return bool
     */
    public function isRedirect($code = null)
    {
        if (\is_null($code)) {
            $code = $this->code();
        }
        return in_array($code, [301, 302]);
    }
    /**
     * 请求完成后，获取返回的HTTP状态码
     * @return int
     */
    public function code()
    {
        return isset($this->responseInfo['http_code']) ? $this->responseInfo['http_code'] : 0;
    }
    private function parseLocation($url)
    {
        $last_url = parse_url(curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL));
        $last_url = array_merge(['scheme' => '', 'host' => '', 'path' => '', 'query' => ''], $last_url);
        if (preg_match('|^http(s)?://|i', $url)) {
            return $url;
        } else {
            //本站绝对路径网址
            if ($url{0} == '/') {
                return $last_url['scheme'] . '://' . $last_url['host'] . $url;
            } else {
                //本站相对路径网址
                return $last_url['scheme'] . '://' . $last_url['host'] . '/' . trim(dirname($last_url['path']), '/') . '/' . $url;
            }
        }
    }
    /**
     * 获取curl出错代码（大于零的数），如果没有错误，返回0
     * @return int
     */
    public function errorCode()
    {
        return $this->error['code'];
    }
    /**
     * 使用POST方式请求一个页面
     * @param String     $url         页面地址
     * @param array|null $data        要发送的数据数组，比如：array('user'=>'test','pass'=>'354534'),键是表单字段名称，值是表单字段的值，默认 null
     * @param array|null $header      附加的HTTP头，比如：array('Connection:keep-alive','Cache-Control:max-age=0')，注意冒号前后不能有空格，默认 null
     * @param int        $maxRedirect 遇到301或302时跳转的最大次数 ，默认 0 不跳转
     * @param bool       $atUpload
     * @return String 页面数据
     */
    public function post($url, $data = null, array $header = null, $maxRedirect = 0, $atUpload = false)
    {
        return $this->request('post', $url, $data, $header, $maxRedirect, $atUpload);
    }
    /**
     * 设置当次请求使用的referer，当get或者post请求完毕后，referer会被重置为null
     * @param string $referer
     * @return \Zls\Action\Http
     */
    public function setReferer($referer)
    {
        $this->referer = $referer;
        return $this;
    }
    /**
     * 获取curl出错信息，返回数组：形如array('code'=>1000,'msg'=>'xxx'),如果没有错误，code是0
     * @return array
     */
    public function error()
    {
        return $this->error;
    }
    /**
     * 获取curl出错字符串信息，如果没有错误，返回空
     * @return string
     */
    public function errorMsg()
    {
        return $this->error['msg'];
    }
    public function setUserAgent($userAgent)
    {
        curl_setopt($this->ch, CURLOPT_USERAGENT, $userAgent);
        return $this;
    }
    /**
     * 设置请求超时时间，单位秒/毫秒
     * @param      $timeout
     * @param bool $MilliSeconds 是否毫秒
     * @return $this
     */
    public function setTimeout($timeout, $MilliSeconds = false)
    {
        curl_setopt($this->ch, CURLOPT_NOSIGNAL, 1);
        if (!$MilliSeconds) {
            curl_setopt($this->ch, CURLOPT_TIMEOUT, (int)$timeout);
        } else {
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT_MS, (int)$timeout);
        }
        return $this;
    }
    /**
     * 获取请求返回的HTTP头部字符串
     * @return string
     */
    public function header()
    {
        return $this->responseHeader;
    }
    /**
     * 获取请求返回的HTTP头部字符串
     * @return array
     */
    public function multiHeader()
    {
        return $this->responseHeaderMulti;
    }
    /**
     * 获取请求返回的页面内容
     * @param bool $is_json 是否使用json_decode()解码一下,当返回数据是json的时候这个比较有用。默认false
     * @return string|array
     */
    public function data($is_json = false)
    {
        return $is_json ? @json_decode($this->responseBody, true) : $this->responseBody;
    }
    /**
     * 请求完成后，获取请求相关信息，就是curl_getinfo()返回的信息数组
     * @return array
     */
    public function info()
    {
        return $this->responseInfo;
    }
    /**
     * 请求完成后，响应是重定向的时候，这里会返回重定向的链接，如果不是重定向返回null
     * @return string
     */
    public function location()
    {
        if ($this->isRedirect()) {
            preg_match('/Location:(.*?)$/mi', $this->responseHeader, $matches);
            return $this->parseLocation(trim(array_pop($matches)));
        } else {
            return null;
        }
    }
    /**
     * 请求完成后，获取最后一次请求的地址，这个往往是有重定向的时候使用的。
     * @return string
     */
    public function lastUrl()
    {
        return $this->lastUrl;
    }
    /**
     * 设置curl句柄参数
     * @param string $opt curl_setopt()的第二个参数
     * @param string $val curl_setopt()的第三个参数
     * @return \Zls\Action\Http
     */
    public function setOpt($opt, $val)
    {
        curl_setopt($this->ch, $opt, $val);
        return $this;
    }
    /**
     * 设置附加的cookie，这个不会影响自动管理的cookie
     * 1.每次请求完成后附加的cookie会被清空，自动管理的cookie不会受到影响。
     * 2.如果cookie键名和自动管理的cookie中键名相同，那么请求的时候使用的是这里设置的值。
     * 3.如果cookie键名和自动管理的cookie中键名相同，当请求完成后自动管理的cookie中键的值保持之前的不变，这里设置的值会被清除。
     * 比如：
     * 自动管理cookie里面有：name=snail，请求之前用setCookie设置了name=123
     * 那么请求的时候发送的cookie是name=123,请求完成后恢复name=snail，如果再次请求那么发送的cookie中name=snail
     * @param string|array $key cookie的key，也可以是一个键值对数组一次设置多个cookie，此时不需要第二个参数。
     * @param string       $val cookie的value
     * @return \Zls\Action\Http
     */
    public function setCookie($key, $val = null)
    {
        $cookies = [];
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $cookies[] = $k . '=' . urlencode($v);
            }
        } else {
            $cookies[] = ' ' . $key . '=' . urlencode($val);
        }
        if (!empty($cookies)) {
            curl_setopt($this->ch, CURLOPT_COOKIE, implode(';', $cookies));
        }
        return $this;
    }
    public function multiError()
    {
        return $this->responseErrorMulti;
    }
    /**
     * 多线程请求完成后，获取请求相关信息
     * @return array
     */
    public function multiInfo()
    {
        return $this->responseInfoMulti;
    }
    /**
     * 多线程请求
     * @param array $arrUrls
     * @param int   $usleep 等待时间、毫秒
     * @return array|bool
     */
    public function multi($arrUrls = [], $usleep = 100)
    {
        $this->responseInfoMulti = [];
        $this->responseErrorMulti = [];
        $this->responseHeaderMulti = [];
        $mh = curl_multi_init();
        $chs = [];
        $responsesKeyMap = [];
        $arrResponses = [];
        $ch = null;
        $type = 'get';
        $data = [];
        $header = [];
        $maxRedirect = 0;
        foreach ($arrUrls as $urlsKey => $strUrlVal) {
            $_url = z::arrayGet($strUrlVal, 'url', $strUrlVal);
            $_type = z::arrayGet($strUrlVal, 'type', $type);
            $_data = z::arrayGet($strUrlVal, 'data', $data);
            $_header = z::arrayGet($strUrlVal, 'header', $header);
            $_maxRedirect = z::arrayGet($strUrlVal, 'maxRedirect', $maxRedirect);
            $ch = curl_copy_handle($this->ch);
            $ch = $this->request($_type, $_url, $_data, $_header, $_maxRedirect, $ch, false);
            curl_multi_add_handle($mh, $ch);
            $strCh = (string)$ch;
            $responsesKeyMap[$strCh] = $urlsKey;
            $chs[$urlsKey] = $ch;
        }
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($usleep === 0 || (CURLM_CALL_MULTI_PERFORM == $mrc));
        if ($usleep === 0) {
            return true;
        }
        while ($active && CURLM_OK == $mrc) {
            if (-1 == curl_multi_select($mh)) {
                usleep($usleep);
            }
            do {
                $mrc = curl_multi_exec($mh, $active);
                if (CURLM_OK == $mrc) {
                    while ($multiInfo = curl_multi_info_read($mh)) {
                        $curl_info = curl_getinfo($multiInfo['handle']);
                        $curl_error = curl_error($multiInfo['handle']);
                        $curl_results = curl_multi_getcontent($multiInfo['handle']);
                        $strCh = (string)$multiInfo['handle'];
                        $_key = $responsesKeyMap[$strCh];
                        $result = compact('curl_info', 'curl_error', 'curl_results');
                        $_curlInfo = $result['curl_info'];
                        $_code = z::arrayGet($_curlInfo, 'http_code', 0);
                        $_data = $result['curl_results'];
                        $_info = explode("\r\n\r\n", $_data, 2);
                        $this->responseInfoMulti[$_key] = $_curlInfo;
                        $this->responseErrorMulti[$_key] = $result['curl_error'];
                        $this->responseHeaderMulti[$_key] = z::arrayGet($_info, 0, '');
                        $arrResponses[$_key] = z::arrayGet($_info, 1, '');
                        curl_multi_remove_handle($mh, $multiInfo['handle']);
                        curl_close($multiInfo['handle']);
                    }
                }
            } while (CURLM_CALL_MULTI_PERFORM == $mrc);
        }
        curl_multi_close($mh);
        return $arrResponses;
    }
}
