<?php
/**
 * Zls
 * @package       ZlsPHP
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          https://docs.73zls.com/zls-php/#/
 * @since         v2.1.19
 * @updatetime    2018-6-20 17:16:37
 */
defined('IN_ZLS') || define("IN_ZLS", '2.1.19');
defined('ZLS_PATH') || define('ZLS_PATH', __DIR__ . '/');
defined('ZLS_RUN_MODE_PLUGIN') || define('ZLS_RUN_MODE_PLUGIN', true);
defined('ZLS_APP_PATH') || define('ZLS_APP_PATH', Z::realPath(ZLS_PATH . 'application', true));
defined('ZLS_INDEX_NAME') || define('ZLS_INDEX_NAME', pathinfo(__FILE__, PATHINFO_BASENAME));
defined('ZLS_PACKAGES_PATH') || define('ZLS_PACKAGES_PATH', ZLS_APP_PATH . 'packages/');
define('ZLS_FRAMEWORK', __FILE__);
interface Zls_Logger
{
    public function write(\Zls_Exception $exception);
}
interface Zls_Request
{
    public function getPathInfo();
    public function getQueryString();
}
interface Zls_Uri_Rewriter
{
    public function rewrite($uri);
}
interface Zls_Exception_Handle
{
    public function handle(\Zls_Exception $exception);
}
interface Zls_Maintain_Handle
{
    public function handle();
}
interface Zls_Database_SlowQuery_Handle
{
    public function handle($sql, $value, $explainString, $time, $trace);
}
interface Zls_Database_Index_Handle
{
    public function handle($sql, $value, $explainString, $time, $trace);
}
interface Zls_Cache
{
    public function set($key, $value, $cacheTime = 0);
    public function get($key);
    public function delete($key);
    public function clean();
    public function &instance($key = null, $isRead = true);
    public function reset();
}
/**
 * 内置方法
 * @method \Zls_Router router()
 */
class Z
{
    private static $dbInstances = [];
    /**
     * 返回文件夹路径 / 不存在则创建
     * @param  string  $path     文件夹路径
     * @param  boolean $addSlash 是否追加/
     * @param  boolean $isFile   是否是文件路径
     * @param boolean  $entr
     * @return string
     */
    public static function realPathMkdir($path, $addSlash = false, $isFile = false, $entr = true)
    {
        return self::tap(self::realPath($path, $addSlash, $entr), function ($path) use ($isFile) {
            if ($isFile) {
                $path = explode('/', $path);
                array_pop($path);
                $path = implode('/', $path);
            }
            if (!file_exists($path)) {
                mkdir($path, 0700, true);
            }
        });
    }
    /**
     * 简化临时变量
     * @param  string|array $value
     * @param  Closure      $callback
     * @return string|object|array
     */
    public static function tap($value, $callback)
    {
        $return = $callback($value);
        return is_null($return) ? $value : $return;
    }
    public static function realPath($path, $addSlash = false, $entr = true)
    {
        $unipath = PATH_SEPARATOR == ':';
        $separator = DIRECTORY_SEPARATOR;
        $prefix = realpath(($entr === false) ? (ZLS_PATH . '../') : ($entr !== true ? $entr : ZLS_PATH));
        if (strpos($path, ':') === false && strlen($path) && $path{0} != '/') {
            $path = $prefix . $separator . $path;
        }
        $path = str_replace(['/', '\\'], $separator, $path);
        $parts = array_filter(explode($separator, $path), 'strlen');
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        $path = implode($separator, $absolutes);
        $path = $unipath ? (strlen($path) && $path{0} != '/' ? '/' . $path : $path) : $path;
        $path = str_replace(['/', '\\'], '/', $path);
        return $path . ($addSlash ? '/' : '');
    }
    public static function dump()
    {
        static $isXdebug = null;
        if (is_null($isXdebug) && $isXdebug = extension_loaded('xdebug')) {
            ini_set('xdebug.var_display_max_data', -1);
            ini_set('xdebug.var_display_max_depth', -1);
            ini_set('xdebug.var_display_max_children', -1);
            $isXdebug = !!ini_get('xdebug.overload_var_dump');
        }
        $beautify = !(Z::isCli() && !Z::isSwoole(true)) && !$isXdebug;
        echo $beautify ? '<pre style="line-height:1.5em;font-size:14px;">' : "\n";
        @ob_start();
        $args = func_get_args();
        empty($args) ? null : call_user_func_array('var_dump', $args);
        $html = @ob_get_clean();
        echo $beautify ? htmlspecialchars($html) : $html;
        echo $beautify ? "</pre>" : "\n";
    }
    public static function isCli()
    {
        return PHP_SAPI == 'cli';
    }
    /**
     * swoole环境
     * @param bool $isHttp
     * @return bool
     */
    public static function isSwoole($isHttp = false)
    {
        $isSwoole = array_key_exists('swoole', self::config()->getZMethods());
        return $isHttp ? $isSwoole && self::di()->has('SwooleResponse') : $isSwoole;
    }
    /**
     * @param null $configName
     * @param bool $caching
     * @param null $default
     * @return mixed|null|Zls_Config|array
     */
    public static function &config($configName = null, $caching = true, $default = null)
    {
        if (empty($configName)) {
            return Zls::getConfig();
        }
        $_info = explode('.', $configName);
        $configFileName = current($_info);
        static $loadedConfig = [];
        $cfg = null;
        if ($caching && self::arrayKeyExists($configFileName, $loadedConfig)) {
            $cfg = $loadedConfig[$configFileName];
        } elseif ($filePath = \Zls::getConfig()->find($configFileName)) {
            $loadedConfig[$configFileName] = $cfg = include($filePath);
        } else {
            Z::throwIf(true, 500, 'config file [ ' . $configFileName . '.php ] not found', 'ERROR');
        }
        if ($cfg && count($_info) > 1) {
            $val = self::arrayGet($cfg, implode('.', array_slice($_info, 1)), $default);
            return $val;
        } else {
            return $cfg;
        }
    }
    /**
     * 数组是否包含key
     * @param      $key
     * @param      $arr
     * @param bool $explode
     * @return bool
     */
    public static function arrayKeyExists($key, $arr, $explode = true)
    {
        if (empty($arr) || !is_array($arr)) {
            return false;
        }
        $keys = ($explode === true) ? explode('.', $key) : [$key];
        while (count($keys) != 0) {
            if (empty($arr) || !is_array($arr)) {
                return false;
            }
            $key = array_shift($keys);
            if (!array_key_exists($key, $arr)) {
                return false;
            }
            $arr = $arr[$key];
        }
        return true;
    }
    /**
     * 简化抛出异常
     * @param        $boolean
     * @param        $exception
     * @param string $message
     * @param string $type
     */
    public static function throwIf($boolean, $exception, $message = '', $type = 'NOTICE')
    {
        if ($boolean) {
            if (is_string($exception) || is_numeric($exception)) {
                $_exception = ucfirst($exception);
                $code = is_numeric($exception) ? $exception : 500;
                if (in_array($_exception, [500, 404, 'Database'])) {
                    $exception = 'Zls_Exception_' . $_exception;
                }
                if (self::strBeginsWith($exception, 'Zls_Exception_')) {
                    $trace = self::arrayGet(debug_backtrace(false), 0, ['file' => '', 'line' => 0]);
                    throw new $exception($message, $code, $type, $trace['file'], $trace['line']);
                } else {
                    throw new $exception($message, $code);
                }
            } else {
                throw $exception;
            }
        }
    }
    /**
     * 验证字符串开头
     * @param string $str 源字符串
     * @param        $sub
     * @return bool
     */
    public static function strBeginsWith($str, $sub)
    {
        return (substr($str, 0, strlen($sub)) == $sub);
    }
    /**
     * 获取数组的值
     * @param      $arr
     * @param      $keys
     * @param null $default
     * @param bool $explode
     * @return mixed
     */
    public static function arrayGet($arr, $keys, $default = null, $explode = true)
    {
        if (is_array($keys)) {
            $key = array_shift($keys);
        } else {
            $key = $keys;
            $keys = null;
        }
        $_keys = $explode ? explode('.', $key) : [$key];
        $a = $arr;
        while (count($_keys) != 0) {
            $key = array_shift($_keys);
            if (!isset($a[$key])) {
                return $keys ? self::arrayGet($arr, $keys, $default, $explode) : $default;
            }
            $a = $a[$key];
        }
        return $a;
    }
    /**
     * 容器
     * @return Zls_Di
     */
    public static function di()
    {
        static $di = null;
        if (!$di) {
            $di = new \Zls_Di();
        }
        return $di;
    }
    public static function wasteTime($time = null)
    {
        $wasteTime = 0;
        if ($time) {
            return Zls::$zlsTime = $time;
        } elseif (Zls::$zlsTime) {
            $wasteTime = z::microtime() - Zls::$zlsTime;
        }
        return $wasteTime;
    }
    /**
     * 获取当前UNIX毫秒时间戳
     * @return float
     */
    public static function microtime()
    {
        // 获取当前毫秒时间戳
        list($s1, $s2) = explode(' ', microtime());
        $currentTime = (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
        return $currentTime;
    }
    public static function memory($memory = null)
    {
        return $memory ? Zls::$zlsMemory = $memory : Zls::$zlsMemory ? number_format((memory_get_usage() - Zls::$zlsMemory) / 1024) : 0;
    }
    /**
     * 获取系统临时目录路径
     * @return string
     */
    public static function tempPath()
    {
        if (!function_exists('sys_get_temp_dir')) {
            $_tmpKeys = ['TMPDIR', 'TEMP', 'TMP', 'upload_tmp_dir'];
            foreach ($_tmpKeys as $v) {
                if (!empty($_ENV[$v])) {
                    return realpath($_ENV[$v]);
                }
            }
            $tempfile = tempnam(uniqid(rand(), true), '');
            if (file_exists($tempfile)) {
                unlink($tempfile);
                return realpath(dirname($tempfile));
            }
        }
        return sys_get_temp_dir();
    }
    /**
     * 追踪打印日志
     * @param bool $instance
     * @return boolean|Zls_Trace
     */
    public static function trace($instance = false)
    {
        if (self::config()->getTraceStatus()) {
            $_trace = self::log(null, false);
            if ($instance === true) {
                return $_trace;
            }
            $trace = debug_backtrace();
            foreach ($trace as $t) {
                if (self::arrayGet($t, 'function') == 'trace') {
                    $_trace->log($t, 'trace');
                    break;
                }
            }
            return true;
        }
        return false;
    }
    /**
     * 保存日志
     * @param string $log
     * @param string $type
     * @param bool   $debug
     * @return bool|Zls_Trace
     */
    public static function log($log = '', $type = 'log', $debug = false)
    {
        if (!$state = self::tap(self::config()->getTraceStatus(), function (&$state) use ($type) {
            if (is_array($state)) {
                $state = self::arrayGet($state, $type, true);
            }
        })) {
            return false;
        }
        $trace = new \Zls_Trace();
        if (!!$type) {
            if ($debug) {
                $debug = self::debug(null, false, true);
                $current = self::arrayGet(debug_backtrace(), 0, ['file' => '', 'line' => '']);
                $debug['file'] = $current['file'] ? self::safePath($current['file']) : null;
                $debug['line'] = $current['line'];
            }
            $trace->output($log, $type, $debug);
        }
        return $trace;
    }
    /**
     * 获取执行时间与内存
     * @param string $name
     * @param bool   $output
     * @param bool   $suffix
     * @param bool   $resString
     * @param bool   $unset
     * @return array|string
     * @internal param bool $end
     */
    public static function debug($name = '', $output = false, $suffix = true, $resString = true, $unset = true)
    {
        static $_run = [];
        static $_mem = [];
        if (!!$output && $name) {
            $runTime = self::microtime() - $_run[$name];
            $res = ['runtime' => $runTime / 1000 . ($suffix ? 's' : ''), 'memory' => self::convertRam(memory_get_usage() - $_mem[$name], $suffix)];
            if ($unset) {
                unset($_run[$name], $_mem[$name]);
            }
            if ($resString) {
                $res = vsprintf($name . '[runtime:%s,memory:%s', [$res['runtime'], $res['memory'] . ']']);
            }
            return $res;
        } elseif ($name) {
            $_run[$name] = self::microtime();
            $_mem[$name] = memory_get_usage();
            return $_run;
        } else {
            $runTime = ceil(Zls::$zlsTime) !== Zls::$zlsTime ? Zls::$zlsTime * 1000 : Zls::$zlsTime;
            $runTime = self::microtime() - $runTime;
            if (substr_count($runTime, "E")) {
                $runTime = floatval(substr($runTime, 5));
            }
            return ['runtime' => ($runTime / 1000) . ($suffix ? 's' : ''), 'memory' => (\Zls::$zlsMemory ? self::convertRam(memory_get_usage() - \Zls::$zlsMemory, $suffix) : 'null')];
        }
    }
    /**
     * 计算内存消耗
     * @param      $size
     * @param bool $suffix
     * @return string
     */
    public static function convertRam($size, $suffix = true)
    {
        if (!$suffix) {
            return $size;
        }
        if ($size <= 0) {
            return 0;
        }
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $i = floor(log($size, 1024));
        return @round($size / pow(1024, $i), 2) . $unit[$i];
    }
    /**
     * 屏蔽路径中系统的绝对路径部分，转换为安全的用于显示
     * @param string  $path
     * @param string  $prefix
     * @param boolean $entr
     * @return string
     */
    public static function safePath($path, $prefix = '~APPPATH~', $entr = false)
    {
        if (!$path) {
            return '';
        }
        $path = self::realPath($path);
        $siteRoot = self::realPath(self::server('DOCUMENT_ROOT'));
        $_path = str_replace($siteRoot, '', $path);
        $entr = $entr === true ? ZLS_PATH : ($entr === false ? ZLS_APP_PATH : $entr);
        $entr = self::realPath($entr);
        $relPath = str_replace($siteRoot, '', rtrim($entr, '/'));
        return $prefix . str_replace($relPath, '', $_path);
    }
    public static function server($key = null, $default = null)
    {
        return is_null($key) ? $_SERVER : self::arrayGet($_SERVER, strtoupper($key), $default);
    }
    /**
     * 数组指定key过滤
     * @param      $keys
     * @param      $arr
     * @param bool $in
     * @return array
     */
    public static function arrayKeyFilter($keys, $arr, $in = false)
    {
        $keys = !is_array($keys) ? explode(',', $keys) : $keys;
        $arr = self::arrayFilter($arr, function ($v, $k) use ($keys, $in) {
            return (!$in && !in_array($k, $keys, true)) ? true : ($in && in_array($k, $keys, true));
        });
        return $arr;
    }
    /**
     * 数组过滤
     * @param array $arr
     * @param callable $callback
     * @return array
     */
    public static function arrayFilter(array $arr, callable $callback)
    {
        if (self::phpCanV('5.6.0')) {
            return array_filter($arr, $callback, ARRAY_FILTER_USE_BOTH);
        } else {
            $newArr = [];
            foreach ($arr as $k => $v) {
                if ($_value = $callback($v, $k)) {
                    $newArr[$k] = $v;
                }
            }
            return $newArr;
        }
    }
    public static function phpCanV($version = '5.4.0')
    {
        return version_compare(phpversion(), $version, '>=');
    }
    /**
     * 数组去重并重排
     * @param $arr
     * @return array
     */
    public static function arrayUnique($arr)
    {
        return array_values(array_flip(array_flip($arr)));
    }
    public static function resetZls()
    {
        $config = self::config();
        Zls::$loadedModules = [];
        if ($config->getCacheConfig()) {
            self::cache()->reset();
        }
        self::clearDbInstances();
        //        $db->close();
        //    },self::$dbInstances);
        //    //self::db()->close();
        //}
        self::di()->remove();
        \Zls_Logger_Dispatcher::setMemReverse();
    }
    /**
     * 获取缓存操作对象
     * @param string|array $cacheType
     * @return \Zls_Cache
     */
    public static function cache($cacheType = null)
    {
        return self::config()->getCacheHandle($cacheType);
    }
    public static function clearDbInstances($key = null)
    {
        if (!is_null($key)) {
            self::$dbInstances[$key]->close();
            unset(self::$dbInstances[$key]);
        } else {
            array_map(function (\Zls_Database_ActiveRecord $db) {
                $db->close();
            }, self::$dbInstances);
            self::$dbInstances = [];
        }
    }
    /**
     * 执行任务
     * @param        $taksName
     * @param array  $args
     * @param string $user
     * @param string $phpPath
     * @return string
     */
    public static function task($taksName, $args = [], $user = '', $phpPath = null)
    {
        $phpPath = $phpPath ?: self::phpPath();
        $argc = '';
        foreach ($args as $key => $value) {
            $argc .= " --{$key}=$value";
        }
        $index = ZLS_PATH . '/' . ZLS_INDEX_NAME;
        if (!self::isWin() && (!$user && $user !== '')) {
            $user = trim(self::command('whoami', '', true));
        }
        $cmd = "{$phpPath} {$index}  -task {$taksName}{$argc}";
        self::command($cmd, $user, false);
        return $cmd;
    }
    /**
     * 获取php执行路径
     * @return mixed|string
     */
    public static function phpPath()
    {
        static $phpPath;
        if (!$phpPath) {
            if (substr(strtolower(PHP_OS), 0, 3) == 'win') {
                $path = z::arrayGet(ini_get_all(), 'extension_dir.local_value', 'php');
                $phpPath = str_replace('\\', '/', $path);
                $phpPath = str_replace(['/ext/', '/ext'], ['/', '/'], $phpPath);
                $realPath = $phpPath . 'php.exe';
            } else {
                $realPath = PHP_BINDIR . '/php';
            }
            if (strpos($realPath, 'ephp.exe') !== false) {
                $realPath = str_replace('ephp.exe', 'php.exe', $realPath);
            }
            $phpPath = $realPath;
        }
        return $phpPath;
    }
    /**
     * Windows环境
     * @return bool
     */
    public static function isWin()
    {
        return DIRECTORY_SEPARATOR === '\\';
    }
    /**
     * 执行外部命令
     * @param        $cmd
     * @param string $user
     * @param bool   $return
     * @param bool   $escape
     * @return string
     */
    public static function command($cmd, $user = '', $return = true, $escape = true)
    {
        $disabled = explode(',', ini_get('disable_functions'));
        if ($escape && !in_array('escapeshellcmd', $disabled)) {
            $cmd = escapeshellcmd($cmd);
        }
        if (!$return) {
            if (self::isWin()) {
                $cmd = "start /b {$cmd} > NUL ";
            } else {
                $cmd = $user ? 'sudo -u ' . $user . ' ' . $cmd . ' > /dev/null &' : $cmd . ' > /dev/null &';
            }
        }
        @ob_start();
        switch (true) {
            case !in_array('shell_exec', $disabled):
                echo shell_exec($cmd);
                break;
            case !in_array('passthru', $disabled):
                passthru($cmd);
                break;
            case !in_array('exec', $disabled):
                exec($cmd, $res);
                echo implode("\n", $res);
                break;
            case !in_array('system', $disabled):
                system($cmd);
                break;
            case !in_array('popen', $disabled):
                $fp = popen($cmd, 'r');
                if ($return) {
                    while (!feof($fp)) {
                        echo fread($fp, 1024);
                    }
                }
                pclose($fp);
                break;
            default:
                @ob_end_clean();
                self::throwIf(true, 500, 'Your environment doesn\'t support task execution, Please check the PHP ini disable_functions, [ shell_exec,popen,exec,system ] must open one of them.');
        }
        $result = @ob_get_clean();
        return $result ? self::toUtf8($result) : $result;
    }
    /**
     * 非utf8字符串转换成utf8
     * @param $str
     * @return null|string
     */
    public static function toUtf8($str)
    {
        $encode = mb_detect_encoding($str, ['UTF-8', 'GB2312', 'GBK']);
        return $encode === 'UTF-8' ? $str : mb_convert_encoding($str, "UTF-8", $encode);
    }
    /**
     * 扫描目录文件
     * @param              $dir
     * @param int          $depth
     * @param null|Closure $fn
     * @return array
     */
    public static function scanFile($dir, $depth = 0, $fn = null)
    {
        $dirs = ['folder' => [], 'file' => []];
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if ($depth >= 0 && $file != "." && $file != ".." && !(($fn instanceof Closure) && ($fn($dir, $file) === false))) {
                        if ((is_dir($dir . "/" . $file))) {
                            $dirs['folder'][$file] = self::scanFile($dir . '/' . $file . '/', $depth - 1);
                        } else {
                            $dirs['file'][] = $file;
                        }
                    }
                }
                closedir($dh);
            }
        }
        return $dirs;
    }
    public static function stripSlashes($var)
    {
        if (!get_magic_quotes_gpc()) {
            return $var;
        }
        if (is_array($var)) {
            foreach ($var as $key => $val) {
                if (is_array($val)) {
                    $var[$key] = self::stripSlashes($val);
                } else {
                    $var[$key] = stripslashes($val);
                }
            }
        } elseif (is_string($var)) {
            $var = stripslashes($var);
        }
        return $var;
    }
    /**
     * 实例业务层
     * @param       $businessName
     * @param bool  $shared
     * @param array $args
     * @return object
     */
    public static function business($businessName, $shared = true, $args = [])
    {
        $name = Zls::getConfig()->getBusinessDirName() . '/' . $businessName;
        $object = self::factory($name, $shared, null, $args);
        Z::throwIf(!($object instanceof Zls_Business), 500, '[ ' . $name . ' ] not a valid Zls_Business', 'ERROR');
        return $object;
    }
    /**
     * 超级方法
     * @param string  $className      可以是完整的控制器类名，模型类名，类库类名
     * @param string  $hmvcModuleName hmvc模块名称，是配置里面的数组的键名，插件模式下才会用到这个参数
     * @param boolean $shared
     * @param array   $args
     * @return object
     */
    public static function factory($className, $shared = false, $hmvcModuleName = null, $args = [])
    {
        if (self::config()->getRoute()->getHmvcModuleName() && !self::strBeginsWith($className, 'Hmvc_')) {
            $className = 'Hmvc_' . $className;
        }
        if (self::strEndsWith(strtolower($className), '.php')) {
            $className = substr($className, 0, strlen($className) - 4);
        }
        $className = str_replace(['/', '_'], '\\', $className);
        if ($hmvcModuleName) {
            $hmvcFlip = self::config()->getHmvcModules();
            $hmvcModuleName = self::arrayGet($hmvcFlip, $hmvcModuleName, $hmvcModuleName);
            $className = $hmvcModuleName . '\\' . $className;
        }
        if (!self::di()->has($className)) {
            self::di()->bind($className, ['class' => $className, 'hmvc' => $hmvcModuleName]);
        }
        return ($shared !== true) ? self::di()->make($className, $args) : self::di()->makeShared($className, $args);
    }
    /**
     * 验证字符串结尾
     * @param $str
     * @param $sub
     * @return bool
     */
    public static function strEndsWith($str, $sub)
    {
        return (substr($str, strlen($str) - strlen($sub)) == $sub);
    }
    /**
     * 逗号字符串
     * @param        $str
     * @param array  $intersect
     * @param string $delimiter
     * @return array
     */
    public static function strComma($str, $intersect = [], $delimiter = ',')
    {
        if (!is_array($str)) {
            $str = explode($delimiter, $str);
        }
        if ($intersect) {
            $str = array_intersect($intersect, $str);
        }
        return $str;
    }
    /**
     * 将驼峰式字符串转化为特定字符串
     * @param        $str
     * @param string $delimiter 分隔符
     * @return string
     */
    public static function strCamel2Snake($str, $delimiter = '_')
    {
        $str = str_split($str);
        foreach ($str as $k => &$v) {
            if (preg_match('/^[A-Z]+$/', $v)) {
                $last = self::arrayGet($str, ($k - 1));
                if ($last && ($last != '/')) {
                    $v = $delimiter . $v;
                }
            }
        }
        return strtolower(implode('', $str));
    }
    /**
     * 判断是否是插件模式运行
     * @return boolean
     */
    public static function isPluginMode()
    {
        return (defined('ZLS_RUN_MODE_PLUGIN') && ZLS_RUN_MODE_PLUGIN);
    }
    /**
     * 实例控制器
     * @param       $_controllerShort
     * @param null  $methodName
     * @param array $args
     * @param null  $hmvcModuleName
     * @param bool  $before
     * @param bool  $after
     * @return object
     */
    public static function controller($_controllerShort, $methodName = null, $args = [], $hmvcModuleName = null, $before = false, $after = false)
    {
        if (!!$hmvcModuleName) {
            Zls::checkHmvc($hmvcModuleName);
        }
        $controllerName = Zls::getConfig()->getControllerDirName() . '_' . $_controllerShort;
        $controllerObject = self::factory($controllerName, true);
        Z::throwIf(!($controllerObject instanceof Zls_Controller), 500, '[ ' . $controllerName . ' ] not a valid Zls_Controller', 'ERROR');
        if ($methodName) {
            $_method = Zls::getConfig()->getMethodPrefix() . $methodName;
            if ($before) {
                if (method_exists($controllerObject, 'before')) {
                    $controllerObject->before($methodName, $_controllerShort, $args, $controllerName);
                }
            }
            Z::throwIf(!method_exists($controllerObject, $_method), 404, 'Method [ ' . $controllerName . '->' . $_method . '() ] not found');
            $contents = $controllerObject->$_method($args);
            if ($after) {
                if (method_exists($controllerObject, 'after')) {
                    $contents = $controllerObject->after($contents, $methodName, $_controllerShort, $args, $controllerName);
                }
            }
            return $contents;
        } else {
            return $controllerObject;
        }
    }
    /**
     * @param       $daoName
     * @param bool  $shared
     * @param array $args
     * @return object
     */
    public static function dao($daoName, $shared = true, $args = [])
    {
        $name = Zls::getConfig()->getDaoDirName() . '/' . $daoName;
        $object = self::factory($name, $shared, null, $args);
        Z::throwIf(!($object instanceof Zls_Dao), 500, '[ ' . $name . ' ] not a valid Zls_Dao', 'ERROR');
        return $object;
    }
    /**
     * @param       $beanName
     * @param       $row
     * @param bool  $shared
     * @param array $args
     * @return object
     */
    public static function bean($beanName, $row = [], $shared = true, $args = [])
    {
        if (gettype($beanName) === 'object') {
            $object = $beanName;
            $name = get_class($beanName);
        } else {
            $name = Zls::getConfig()->getBeanDirName() . '/' . $beanName;
            $object = self::factory($name, $shared, null, $args);
        }
        self::throwIf(!($object instanceof Zls_Bean), 500, '[ ' . $name . ' ] not a valid Zls_Bean', 'ERROR');
        return self::tap($object, function ($object) use ($row) {
            foreach ($row as $key => $value) {
                $method = "set" . Z::strSnake2Camel($key);
                $object->{$method}($value);
            }
        });
    }
    /**
     * 将特定字符串转化为按驼峰式
     * @param        $str
     * @param string $Delimiter 分隔符
     * @param bool   $ucfirst
     * @return mixed|string
     */
    public static function strSnake2Camel($str, $ucfirst = true, $Delimiter = '_')
    {
        $str = ucwords(str_replace($Delimiter, ' ', $str));
        $str = str_replace(' ', '', lcfirst($str));
        return $ucfirst ? ucfirst($str) : $str;
    }
    /**
     * 模型
     * @param       $modelName
     * @param bool  $shared
     * @param array $args
     * @return object
     */
    public static function model($modelName, $shared = true, $args = [])
    {
        $name = Zls::getConfig()->getModelDirName() . '/' . $modelName;
        $object = self::factory($name, $shared, null, $args);
        Z::throwIf(!($object instanceof Zls_Model), 500, '[ ' . $name . ' ] not a valid Zls_Model', 'ERROR');
        return $object;
    }
    /**
     * @param       $lName
     * @param bool  $shared
     * @param array $args
     * @return object
     */
    public static function library($lName, $shared = false, $args = [])
    {
        return self::factory($lName, $shared, null, $args);
    }
    /**
     * @param $functionFilename
     */
    public static function functions($functionFilename)
    {
        static $loadedFunctionsFile = [];
        if (self::arrayKeyExists($functionFilename, $loadedFunctionsFile)) {
            return;
        } else {
            $loadedFunctionsFile[$functionFilename] = 1;
        }
        $config = Zls::getConfig();
        $found = false;
        foreach ($config->getPackages() as $packagePath) {
            $filePath = $packagePath . $config->getFunctionsDirName() . '/' . $functionFilename . '.php';
            if (file_exists($filePath)) {
                self::includeOnce($filePath);
                $found = true;
                break;
            }
        }
        Z::throwIf(!$found, 500, 'functions file [ ' . $functionFilename . '.php ] not found', 'ERROR');
    }
    /**
     * 引入文件 优化版
     * @param  string $filePath 文件路径
     * @return void
     */
    public static function includeOnce($filePath)
    {
        static $includeFiles = [];
        $key = md5(self::realPath($filePath));
        if (!self::arrayKeyExists($key, $includeFiles, false)) {
            include $filePath;
            $includeFiles[$key] = 1;
        }
    }
    /**
     * 解析命令行参数 $GLOBALS['argv'] 到一个数组
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public static function getOpt($key = null, $default = null)
    {
        if (!self::isCli()) {
            return null;
        }
        static $result = [];
        static $parsed = false;
        if (!$parsed) {
            $parsed = true;
            $params = self::arrayGet($GLOBALS, 'argv', []);
            $jumpKey = [];
            foreach ($params as $k => $p) {
                if (!in_array($k, $jumpKey, true)) {
                    if (!!$p && $p{0} == '-') {
                        $pname = substr($p, 1);
                        if ($pname) {
                            $value = true;
                            $nextparm = z::arrayGet($params, $k + 1);
                            if ($value === true && $nextparm !== null && !(!!$nextparm && is_string($nextparm) && $nextparm{0} == '-')) {
                                $value = $nextparm;
                                $jumpKey[] = $k + 1;
                            }
                            //$value = null;
                            //}
                            $result[$pname] = $value;
                        }
                    } else {
                        $result[] = $p;
                    }
                }
            }
        }
        return empty($key) ? $result : (self::arrayKeyExists($key, $result) ? $result[$key] : $default);
    }
    public static function postGet($key = null, $default = null, $xssClean = true)
    {
        if (is_null($key)) {
            $value = self::post() ?: self::get();
        } else {
            $postValue = self::arrayGet($_POST, $key);
            $value = is_null($postValue) ? self::arrayGet($_GET, $key, $default) : $postValue;
        }
        return $xssClean ? self::xssClean($value) : $value;
    }
    public static function post($key = null, $default = null, $xssClean = true)
    {
        $value = is_null($key) ? $_POST : self::arrayGet($_POST, $key, $default);
        return $xssClean ? self::xssClean($value) : $value;
    }
    /**
     * xss过滤
     * @param  array|string $var
     * @return array|string
     */
    public static function xssClean($var)
    {
        if (is_array($var)) {
            foreach ($var as $key => $val) {
                if (is_array($val)) {
                    $var[$key] = self::xssClean($val);
                } else {
                    $var[$key] = self::xssClean0($val);
                }
            }
        } elseif (is_string($var)) {
            $var = self::xssClean0($var);
        }
        return $var;
    }
    private static function xssClean0($data)
    {
        $data = str_replace(['&amp;', '&lt;', '&gt;'], ['&amp;amp;', '&amp;lt;', '&amp;gt;'], $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');
        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
        $data = preg_replace(
            '#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu',
            '$1=$2nojavascript...',
            $data
        );
        $data = preg_replace(
            '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu',
            '$1=$2novbscript...',
            $data
        );
        $data = preg_replace(
            '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u',
            '$1=$2nomozbinding...',
            $data
        );
        $data = preg_replace(
            '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i',
            '$1>',
            $data
        );
        $data = preg_replace(
            '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i',
            '$1>',
            $data
        );
        $data = preg_replace(
            '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu',
            '$1>',
            $data
        );
        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
        do {
            $old_data = $data;
            $data = preg_replace(
                '#</*(?:applet|b(?:ase|gsound|link)|embed|iframe|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i',
                '',
                $data
            );
        } while ($old_data !== $data);
        return $data;
    }
    public static function get($key = null, $default = null, $xssClean = true)
    {
        $value = is_null($key) ? $_GET : self::arrayGet($_GET, $key, $default);
        return $xssClean ? self::xssClean($value) : $value;
    }
    /**
     * 获取session值
     * @param null $key
     * @param null $default
     * @param bool $xssClean
     * @return array|mixed|null|string
     */
    public static function session($key = null, $default = null, $xssClean = false)
    {
        $id = self::sessionStart();
        $session = (self::isSwoole(true) && ($sessionHandle = self::config()->getSessionHandle())) ? $sessionHandle->swooleRead($id) : $_SESSION;
        //$session = (self::isSwoole(true) && ($sessionHandle = self::config()->getSessionHandle())) ? $sessionHandle->swooleGet(null) : $_SESSION;
        $value = is_null($key) ? (empty($session) ? [] : $session) : self::arrayGet($session, $key, $default);
        return $xssClean ? self::xssClean($value) : $value;
    }
    /**
     * 开启session
     * @param  string $id 自定义session_id
     * @return array|mixed|string
     */
    public static function sessionStart($id = null)
    {
        if (!self::di()->has('ZlsSessionID')) {
            $sessionId = '';
            if (!self::isCli()) {
                if (self::phpCanV()) {
                    $started = session_status() === PHP_SESSION_ACTIVE ? true : false;
                } else {
                    $started = session_id() === '' ? false : true;
                }
                if (!$started && !headers_sent()) {
                    if (!is_null($id)) {
                        session_id($id);
                    }
                    session_start();
                }
                $sessionId = session_id();
            } elseif (self::isSwoole(true)) {
                $sessionConfig = self::config()->getSessionConfig();
                $sessionName = $sessionConfig['session_name'];
                $sessionId = $id ?: z::cookieRaw($sessionName);
                if (!$sessionId) {
                    $sessionId = md5(uniqid(z::clientIp(), true)) . mt_rand(1000, 9999);
                    z::setCookieRaw($sessionName, $sessionId, time() + $sessionConfig['lifetime'], '/');
                }
                $sessionHandle = self::config()->getSessionHandle();
                self::throwIf(!$sessionHandle, 500, 'swoole mode must set the SessionHandle');
                $sessionHandle->swooleInit($sessionId);
            }
            if ($sessionId) {
                self::di()->bind('ZlsSessionID', function () use ($sessionId) {
                    return $sessionId;
                });
            }
            return $sessionId;
        } else {
            return self::di()->makeShared('ZlsSessionID');
        }
    }
    public static function cookieRaw($key = null, $default = null, $xssClean = false)
    {
        $value = is_null($key) ? $_COOKIE : self::arrayGet($_COOKIE, $key, $default);
        return $xssClean ? self::xssClean($value) : $value;
    }
    /**
     * 获取客户端IP
     * @param array $source
     * @param array $check
     * @return bool|mixed|string
     */
    public static function clientIp($check = null, $source = null)
    {
        $clientIpConditions = self::config()->getClientIpConditions();
        if (is_null($check)) {
            $check = $clientIpConditions['check'];
        }
        if (is_null($source)) {
            $source = $clientIpConditions['source'];
        }
        array_walk($source, function (&$v) {
            $v = strtoupper($v);
        });
        array_walk($check, function (&$v) {
            $v = strtoupper($v);
        });
        $checkClientIp = function ($ip) {
            if (empty($ip)) {
                return false;
            }
            $whitelist = self::config()->getBackendServerIpWhitelist();
            foreach ($whitelist as $okayIp) {
                if ($okayIp == $ip) {
                    return $ip;
                }
            }
            return false;
        };
        foreach ($source as $v) {
            if ($ip = self::server($v)) {
                if (!in_array($v, $check)) {
                    return $ip;
                }
                if ($ip = $checkClientIp($v)) {
                    return $ip;
                } else {
                    continue;
                }
            }
        }
        return "Unknown";
    }
    public static function setCookieRaw($key, $value, $life = null, $path = '/', $domian = null, $httpOnly = false)
    {
        if (!self::isSwoole()) {
            if (!self::isCli()) {
                self::header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
            }
            if (!is_null($domian)) {
                $autoDomain = $domian;
            } else {
                $host = explode(':', self::server('HTTP_HOST'));
                $domian = $host[0];
                $is_ip = preg_match('/^((25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(25[0-5]|2[0-4]\d|[01]?\d\d?)$/', $domian);
                $notRegularDomain = preg_match('/^[^\\.]+$/', $domian);
                if ($is_ip) {
                    $autoDomain = $domian;
                } elseif ($notRegularDomain) {
                    $autoDomain = null;
                } else {
                    $autoDomain = '.' . $domian;
                }
            }
            setcookie(
                $key,
                $value,
                ($life ? $life + time() : null),
                $path,
                $autoDomain,
                (self::server('SERVER_PORT') == 443 ? 1 : 0),
                $httpOnly
            );
        } else {
            z::di()->makeShared('SwooleResponse')->cookie($key, $value, $life, $path, $domian, $httpOnly);
        }
        $_COOKIE[$key] = $value;
    }
    /**
     * @param $content
     */
    public static function header($content)
    {
        if (self::isSwoole(true)) {
            $header = explode(':', $content);
            $k = array_shift($header);
            $c = join(':', $header);
            self::di()->makeShared('SwooleResponse')->header($k, trim($c));
        } elseif (!self::isCli()) {
            header($content);
        }
    }
    /**
     * 设置session配置
     * @param null $key
     * @param null $value
     */
    public static function sessionSet($key = null, $value = null)
    {
        $id = self::sessionStart();
        if (is_array($key)) {
            $_SESSION = array_merge($_SESSION, $key);
        } else {
            self::arraySet($_SESSION, $key, $value);
        }
        if (self::isSwoole(true) && ($sessionHandle = self::config()->getSessionHandle())) {
            $sessionHandle->swooleWrite($id, $_SESSION);
        }
    }
    /**
     * 设置数组
     * @param      $arr
     * @param      $key
     * @param      $value
     * @param bool $explode
     */
    public static function arraySet(&$arr, $key, $value, $explode = true)
    {
        $keys = $explode ? explode('.', $key) : [$key];
        if (count($keys) == 1) {
            $arr[$key] = $value;
            return;
        }
        $a = [];
        $b = $arr;
        while (count($keys) != 0) {
            $k = array_shift($keys);
            $b = isset($b[$k]) ? $b[$k] : [];
            $a[$k] = $b;
        }
        $ka = array_keys($a);
        $a[end($ka)] = $value;
        for ($index = count($ka) - 2; $index >= 0; $index--) {
            $k = $ka[$index];
            $nextK = $ka[$index + 1];
            $a[$k] = array_merge($a[$k], [$nextK => $a[$nextK]]);
        }
        $arr[$ka[0]] = $a[$ka[0]];
    }
    /**
     * 删除/清空指定session
     * @param null $key
     */
    public static function sessionUnset($key = null)
    {
        $id = self::sessionStart();
        if (is_null($key)) {
            session_unset();
        } else {
            self::arraySet($_SESSION, $key, null);
        }
        if (self::isSwoole(true) && ($sessionHandle = self::config()->getSessionHandle())) {
            $sessionHandle->swooleDestroy($id);
        }
    }
    /**
     * 获取原始的POST数据，即php://input获取到的
     * @param null $key
     * @param null $default
     * @param bool $xssClean
     * @return string
     */
    public static function postRaw($key = null, $default = null, $xssClean = true)
    {
        $input = file_get_contents('php://input') ?: self::server('ZLS_POSTRAW');
        if (!$key) {
            return $input;
        } else {
            parse_str($input, $data);
            $value = is_null($key) ? $data : self::arrayGet($data, $key, $default);
            return $xssClean ? self::xssClean($value) : $value;
        }
    }
    /**
     * 获取cookie
     * @param null $key
     * @param null $default
     * @param bool $xssClean
     * @return array|string
     */
    public static function cookie($key = null, $default = null, $xssClean = false)
    {
        $key = is_null($key) ? null : self::config()->getCookiePrefix() . $key;
        $value = self::cookieRaw($key, $default, $xssClean);
        return $xssClean ? self::xssClean($value) : $value;
    }
    /**
     * 设置cookie参数
     * @param        $key
     * @param        $value
     * @param null   $life
     * @param string $path
     * @param null   $domian
     * @param bool   $http_only
     */
    public static function setCookie($key, $value, $life = null, $path = '/', $domian = null, $http_only = false)
    {
        $key = self::config()->getCookiePrefix() . $key;
        self::setCookieRaw($key, $value, $life, $path, $domian, $http_only);
    }
    /**
     * 服务器的ip
     * @return string
     */
    public static function serverIp()
    {
        return self::isCli() ? gethostbyname(self::hostname()) : self::server('SERVER_ADDR');
    }
    /**
     * 服务器的hostname
     * @return string
     */
    public static function hostname()
    {
        return function_exists('gethostname') ? gethostname() : (function_exists('php_uname') ? php_uname('n') : 'unknown');
    }
    /**
     * 数组扁平化
     * @param array   $arr
     * @param         $key
     * @param null    $default
     * @param boolean $explode
     * @param bool    $keepKey
     * @return array
     */
    public static function arrayValues($arr, $key, $default = null, $explode = true, $keepKey = true)
    {
        return self::arrayMap($arr, function ($value) use ($key, $default, $explode) {
            if (is_array($key)) {
                $result = [];
                foreach ($key as $_key) {
                    $result[$_key] = self::arrayGet($value, $_key, $default, $explode);
                }
            } else {
                $result = self::arrayGet($value, $key, $default, $explode);
            }
            return $result;
        }, $keepKey);
    }
    /**
     * 遍历数组并传递每个值给给定回调
     * @param array   $arr
     * @param Closure $closure
     * @param bool    $keepKey 保持key值
     * @return array
     */
    public static function arrayMap($arr, Closure $closure, $keepKey = true)
    {
        return $keepKey ? array_map($closure, $arr) : array_map($closure, $arr, array_keys($arr));
    }
    public static function createSqlite3Database($path)
    {
        return new PDO('sqlite:' . $path);
    }
    /**
     * 获取缓存数据,不存在则写入
     * @param      $key
     * @param null $closure
     * @param int  $time
     * @param null $cacheType
     * @return mixed
     */
    public static function cacheDate($key, $closure = null, $time = 600, $cacheType = null)
    {
        $data = self::cache($cacheType)->get($key);
        if (!$data && ($closure instanceof Closure)) {
            //只有存在数据才会进行缓存
            if ($data = $closure()) {
                self::cache($cacheType)->set($key, $data, $time);
            }
        }
        return $data;
    }
    /**
     * 删除文件夹和子文件夹
     * @param string  $dirPath     文件夹路径
     * @param boolean $includeSelf 是否删除最父层文件夹
     * @return boolean
     */
    public static function rmdir($dirPath, $includeSelf = true)
    {
        if (empty($dirPath)) {
            return false;
        }
        $dirPath = self::realPath($dirPath) . '/';
        foreach (scandir($dirPath) as $value) {
            if ($value == '.' || $value == '..') {
                continue;
            }
            $path = $dirPath . $value;
            if (!is_dir($path)) {
                @unlink($path);
            } else {
                self::rmdir($path);
                @rmdir($path);
            }
        }
        if ($includeSelf) {
            @rmdir($dirPath);
        }
        return true;
    }
    /**
     * 生成控制器方法的url
     * @param string $url     控制器方法
     * @param array  $getData get传递的参数数组，键值对，键是参数名，值是参数值
     * @param array  $opt     subfix是否自动添加当前的路由后缀,isHmvc是否自动添加hmvc模块名
     * @return string
     */
    public static function url($url = '', $getData = [], $opt = ['subfix' => true, 'ishmvc' => false])
    {
        $config = self::config();
        $route = $config->getRoute();
        $routeType = z::tap($route->getType(), function (&$type) use ($config) {
            if (!$type && (!!$getRouters = $config->getRouters())) {
                $routeType = get_class(end($getRouters));
                $config->getRoute()->setType($routeType);
                $type = $routeType;
            }
        });
        if ($routeType) {
            $routeObj = self::factory($routeType, true);
            return $routeObj->url($url, $getData, $opt);
        } else {
            return $url;
        }
    }
    /**
     * 获取入口文件所在目录url路径。
     * 只能在web访问时使用，在命令行下面会抛出异常。
     * @param null|string $subpath 子路径或者文件路径，如果非空就会被附加在入口文件所在目录的后面
     * @param bool        $addSlash
     * @return string
     */
    public static function urlPath($subpath = null, $addSlash = false)
    {
        self::throwIf(self::isCli() && !Z::isSwoole(), 500, 'urlPath() can not be used in cli mode');
        $root = str_replace(["/", "\\"], '/', self::server('DOCUMENT_ROOT'));
        chdir($root);
        $root = getcwd();
        $root = str_replace(["/", "\\"], '/', $root);
        $path = self::realPath($subpath, $addSlash, true);
        return preg_replace('|^' . self::realPath($root, $addSlash) . '|', '', $path);
    }
    /**
     * 获取当前网站域名
     * @param bool $prefix
     * @param bool $uri
     * @param bool $query
     * @return string
     */
    public static function host($prefix = true, $uri = false, $query = false)
    {
        $host = '';
        $queryStr = '';
        if ($prefix !== false) {
            $protocol = (self::server('HTTPS') == 'on' || self::server('SERVER_PORT') == 443) ? "https://" : "http://";
            $host .= (is_string($prefix)) ? $prefix . self::server('HTTP_HOST') : $protocol . self::server('HTTP_HOST');
        }
        if (!!$uri) {
            $path = strstr(self::server('REQUEST_URI'), '?', true) ?: self::server('REQUEST_URI');
            if (!$path) {
                $path = strstr(self::server('SCRIPT_NAME'), ZLS_PATH . '/' . ZLS_INDEX_NAME, true) . self::arrayGet(
                        $_SERVER,
                        'PATH_INFO',
                        self::arrayGet($_SERVER, 'REDIRECT_PATH_INFO')
                    );
            }
            $host .= $path;
        }
        if ($query === true) {
            $queryStr = self::server('QUERY_STRING') ?: http_build_query(self::get());
        } elseif (is_array($query)) {
            $queryStr = http_build_query($query);
        } elseif (is_string($query)) {
            $queryStr = $query;
        }
        if ($queryStr) {
            $host .= '?' . $queryStr;
        }
        return $host;
    }
    /**
     * 获取数据
     * @param array|null $map 字段映射数组,格式：array('表单name名称'=>'表字段名称',...)
     * @param null       $sourceData
     * @param bool       $replenish
     * @return array []
     */
    public static function readData(array $map, $sourceData = null, $replenish = true)
    {
        $data = [];
        $formdata = is_null($sourceData) ? self::post() : $sourceData;
        if (gettype(key($map)) == 'integer') {
            $_map = $map;
            $map = [];
            foreach ($_map as $item) {
                $map[$item] = $item;
            }
        }
        if (!$replenish) {
            foreach ($formdata as $formKey => $val) {
                if (self::arrayKeyExists($formKey, $map)) {
                    $data[$map[$formKey]] = $val;
                }
            }
        } else {
            foreach ($map as $formKey => $tableKey) {
                if (self::arrayKeyExists($formKey, $formdata)) {
                    $data[$tableKey] = $formdata[$formKey];
                } else {
                    $data[$tableKey] = '';
                }
            }
        }
        return $data;
    }
    /**
     * 数据验证
     * @param string       $value
     * @param array|string $rule
     * @param null         $db
     * @return mixed
     */
    public static function checkValue($value = '', $rule = [], $db = null)
    {
        $_err = '';
        $_errKey = '';
        $redata = [];
        $rules = [];
        foreach ($rule as $k => $v) {
            if (is_int($k)) {
                $rules[$v] = true;
            } else {
                $rules[$k] = $v;
            }
        }
        return z::tap(self::checkData(['value' => $value], ['value' => $rules], $redata, $_err, $_errKey, $db), function ($v) use ($redata, &$value) {
            if ($v) {
                $value = $redata['value'];
            }
        });
    }
    /**
     * 数据验证
     * @param  array  $data          需要检验的数据
     * @param  array  $rules         验证规则
     * @param  array  &$returnData   验证通过后，处理过的数据
     * @param  string &$errorMessage 验证失败时的错误信息
     * @param  string &$errorKey     验证失败的时候验证失败的那个key字段名称
     * @param  object &$db           数据库连接对象
     * @return mixed
     * @throws
     */
    public static function checkData($data = [], $rules = [], &$returnData = null, &$errorMessage = '', &$errorKey = null, &$db = null)
    {
        static $checkRules;
        if (empty($checkRules)) {
            $defaultRules = (class_exists('\Zls\Action\CheckRules')) ? z::extension('Action\CheckRules')->getRules() : [];
            $userRules = self::config()->getDataCheckRules();
            $checkRules = (!empty($userRules) && is_array($userRules)) ? array_merge(
                $defaultRules,
                $userRules
            ) : $defaultRules;
        }
        $getCheckRuleInfo = function ($_rule) {
            $matches = [];
            preg_match('|([^\[]+)(?:\[(.*)\](.?))?|', $_rule, $matches);
            $matches[1] = self::arrayKeyExists(1, $matches) ? $matches[1] : '';
            $matches[3] = !empty($matches[3]) ? $matches[3] : ',';
            $matches[2] = self::arrayKeyExists(2, $matches) ? explode($matches[3], $matches[2]) : [];
            return $matches;
        };
        $returnData = $data;
        foreach ($rules as $key => $keyRules) {
            foreach ($keyRules as $rule => $message) {
                $matches = $getCheckRuleInfo($rule);
                $_v = self::arrayGet($returnData, $key);
                $_r = $matches[1];
                $args = $matches[2];
                if (($_r == 'function') && (is_array($message) ? method_exists($message[0], $message[1]) : (is_callable($message)))) {
                    $ruleFunction = $message;
                } elseif (!Z::arrayKeyExists($_r, $checkRules) || !is_callable($checkRules[$_r])) {
                    Z::throwIf(true, 500, 'error rule [ ' . $_r . ' ]');
                }
                $db = (is_object($db) && ($db instanceof \Zls_Database_ActiveRecord)) ? $db : Z::db();
                $break = false;
                $returnValue = null;
                $isOkay = false;
                if ($_r == 'function') {
                    if (is_array($message)) {
                        $errorMessage = call_user_func_array($message, [$key, $_v, $data, $args, &$returnValue, &$break, &$db]);
                        $isOkay = !$errorMessage;
                    } elseif (is_callable($message) || (is_string($message) && function_exists($message))) {
                        $errorMessage = $message($key, $_v, $data, $args, $returnValue, $break, $db);
                        $isOkay = !$errorMessage;
                    }
                } else {
                    $ruleFunction = $checkRules[$_r];
                    $isOkay = $ruleFunction($key, $_v, $data, $args, $returnValue, $break, $db);
                    $errorMessage = $isOkay ? null : $message;
                }
                if (!$isOkay) {
                    $errorKey = $key;
                    return false;
                }
                if (!is_null($returnValue)) {
                    $returnData[$key] = $returnValue;
                }
                if ($break) {
                    break;
                }
            }
        }
        return true;
    }
    public static function extension($className, $shared = true, $args = [])
    {
        return self::factory('Zls_' . $className, $shared, null, $args);
    }
    /**
     * 获取数据库操作对象
     * @staticvar array $instances   数据库单例容器
     * @param string|array $group         配置组名称
     * @param boolean      $isNewInstance 是否刷新单例
     * @return \Zls_Database_ActiveRecord
     */
    public static function &db($group = '', $isNewInstance = false)
    {
        if (is_array($group)) {
            $groupString = json_encode($group);
            $key = md5($groupString);
            if (!self::arrayKeyExists($key, self::$dbInstances) || $isNewInstance) {
                $group['group'] = $groupString;
                self::$dbInstances[$key] = new \Zls_Database_ActiveRecord($group);
            }
        } else {
            $config = self::config()->getDatabaseConfig();
            Z::throwIf(empty($config), 'Database', 'database configuration is empty , did you forget to use "->setDatabaseConfig()" in index.php ?');
            if (empty($group)) {
                $group = $config['default_group'];
            }
            $key = $group;
            if (!self::arrayKeyExists($group, self::$dbInstances) || $isNewInstance) {
                $config = self::config()->getDatabaseConfig($group);
                Z::throwIf(empty($config), 'Database', 'unknown database config group [ ' . $group . ' ]');
                $config['group'] = $group;
                self::$dbInstances[$key] = new \Zls_Database_ActiveRecord($config);
            }
        }
        return self::$dbInstances[$key];
    }
    public static function getPost($key = null, $default = null, $xssClean = true)
    {
        if (is_null($key)) {
            $value = self::get() ?: self::post();
        } else {
            $getValue = self::arrayGet($_GET, $key);
            $value = is_null($getValue) ? self::arrayGet($_POST, $key, $default) : $getValue;
        }
        return $xssClean ? self::xssClean($value) : $value;
    }
    /**
     * 分页方法
     * @param int    $total    一共多少记录
     * @param int    $page     当前是第几页
     * @param int    $pagesize 每页多少
     * @param string $url      url是什么，url里面的{page}会被替换成页码
     * @param int    $a_count  分页条中页码链接的总数量,不包含当前页
     * @return array $result
     */
    public static function page($total, $page = 1, $pagesize = 10, $url = '{page}', $a_count = 6)
    {
        $a_num = ($a_count > 0) ? $a_count : 10;
        $a_num = $a_num % 2 == 0 ? $a_num + 1 : $a_num;
        $pages = ceil($total / $pagesize);
        $curpage = intval($page) ? intval($page) : 1;
        $curpage = $curpage > $pages || $curpage <= 0 ? 1 : $curpage;
        $start = $curpage - ($a_num - 1) / 2;
        $end = $curpage + ($a_num - 1) / 2;
        $start = $start <= 0 ? 1 : $start;
        $end = $end > $pages ? $pages : $end;
        if ($pages >= $a_num) {
            if ($curpage <= ($a_num - 1) / 2) {
                $end = $a_num;
            }
            if ($end - $curpage <= ($a_num - 1) / 2) {
                $start -= floor($a_num / 2) - ($end - $curpage);
            }
        }
        $result = [
            'pages'   => [],
            'total'   => $total,
            'count'   => $pages,
            'curpage' => $curpage,
            'prefix'  => $curpage == 1 ? '' : str_replace('{page}', $curpage - 1, $url),
            'start'   => str_replace('{page}', 1, $url),
            'end'     => str_replace('{page}', $pages, $url),
            'subfix'  => ($curpage == $pages || $pages == 0) ? '' : str_replace('{page}', $curpage + 1, $url),
        ];
        for ($i = $start; $i <= $end; $i++) {
            $result['pages'][$i] = str_replace('{page}', $i, $url);
        }
        return $result;
    }
    public static function json()
    {
        $args = func_get_args();
        $handle = self::config()->getOutputJsonRender();
        if (is_callable($handle)) {
            return call_user_func_array($handle, $args);
        } else {
            return '';
        }
    }
    /**
     * 重定向
     * @param      $url
     * @param null $msg
     * @param int  $time
     * @param null $view
     */
    public static function redirect($url, $msg = null, $time = 3, $view = null)
    {
        if (self::isSwoole(true)) {
            z::di()->makeShared('SwooleResponse')->status(302);
        }
        if (empty($msg) && empty($view)) {
            self::header('Location: ' . $url);
        } else {
            $time = intval($time) ? intval($time) : 3;
            self::header("refresh:{$time};url={$url}"); //单位秒
            if (!empty($view)) {
                $msg = self::view()->set(['msg' => $msg, 'url' => $url, 'time' => $time])->load($view);
            }
        }
        self::finish($msg);
    }
    /**
     * @return null|object
     */
    public static function view()
    {
        static $view = null;
        if (is_null($view)) {
            $view = self::factory('Zls_View');
        }
        return $view;
    }
    /**
     * exit/die代替
     * @param $msg
     */
    public static function finish($msg = '')
    {
        self::throwIf(self::isSwoole(), 'Exception', $msg);
        die($msg);
    }
    /**
     * @param      $msg
     * @param null $url
     * @param int  $time
     * @param null $view
     * @throws Exception
     */
    public static function message($msg, $url = null, $time = 3, $view = null)
    {
        $time = intval($time) ? intval($time) : 3;
        if (!empty($url)) {
            self::header("refresh:{$time};url={$url}"); //单位秒
        }
        if (!empty($view)) {
            $msg = self::view()->set(['msg' => $msg, 'url' => $url, 'time' => $time])->load($view);
        }
        self::finish($msg);
    }
    /**
     * @param $name
     * @param $arguments
     * @return mixed|object
     */
    public static function __callStatic($name, $arguments)
    {
        $methods = self::config()->getZMethods();
        self::throwIf(empty($methods[$name]), 500, $name . ' not found in ->setZMethods() or it is empty');
        if (is_string($methods[$name])) {
            $className = $methods[$name] . '_' . self::arrayGet($arguments, 0);
            self::throwIf(!$className, 500, $name . $methods[$name] . '() need argument of class name ');
            return self::factory($className);
        } elseif (is_callable($methods[$name])) {
            return call_user_func_array($methods[$name], $arguments);
        } else {
            self::throwIf(true, 500, $name . ' unknown type of method [ ' . $name . ' ]');
        }
    }
    /**
     * 加密
     * @param        $str
     * @param string $key
     * @param string $attachKey
     * @return string
     */
    public static function encrypt($str, $key = '', $attachKey = '')
    {
        if (!$str) {
            return '';
        }
        $iv = $key = substr(md5(self::getEncryptKey($key, $attachKey)), 0, 16);
        $blockSize = 16;
        $msgLength = strlen($str);
        if ($msgLength % $blockSize != 0) {
            $str .= str_repeat("\0", $blockSize - ($msgLength % $blockSize));
        }
        return bin2hex(openssl_encrypt($str, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv));
    }
    /**
     * @param $key
     * @param $attachKey
     * @return string
     */
    private static function getEncryptKey($key, $attachKey)
    {
        $_key = $key ? $key : self::config()->getEncryptKey();
        self::throwIf(!$key && !$_key, 500, 'encrypt key can not empty or you can set it in index.php : ->setEncryptKey()');
        return $_key . $attachKey;
    }
    /**
     * 解密
     * @param        $str
     * @param string $key
     * @param string $attachKey
     * @return bool|string
     */
    public static function decrypt($str, $key = '', $attachKey = '')
    {
        if (!$str) {
            return '';
        }
        $iv = $key = substr(md5(self::getEncryptKey($key, $attachKey)), 0, 16);
        return trim(@openssl_decrypt(hex2bin($str), 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv), "\0") ?: false;
    }
    /**
     * @param $class
     * @return bool
     */
    public static function classIsExists($class)
    {
        if (class_exists($class, false)) {
            return true;
        }
        $classNamePath = str_replace('_', '/', $class);
        foreach (self::config()->getPackages() as $path) {
            if (file_exists($filePath = $path . self::config()->getClassesDirName() . '/' . $classNamePath . '.php')) {
                return true;
            }
        }
        return false;
    }
    /**
     * 检测ip是否在白名单内
     * @param $clientIp
     * @return bool
     * @internal param string $ip
     */
    public static function isWhiteIp($clientIp)
    {
        $config = Z::config();
        $isWhite = false;
        foreach ($config->getMaintainIpWhitelist() as $ip) {
            $info = explode('/', $ip);
            $netmask = empty($info[1]) ? '32' : $info[1];
            if ($info && Z::ipInfo($clientIp . '/' . $netmask, 'netaddress') == Z::ipInfo($info[0] . '/' . $netmask, 'netaddress')) {
                $isWhite = true;
                break;
            }
        }
        return $isWhite;
    }
    /**
     * 获取IP段信息
     * @param string $ipAddr
     * @param string $key
     * @return array
     * $ipAddr格式：192.168.1.10/24、192.168.1.10/32
     * 传入Ip地址对Ip段地址进行处理得到相关的信息
     * 没有$key时，返回数组：array(,netmask=>网络掩码,count=>网络可用IP数目,start=>可用IP开始,end=>可用IP结束,netaddress=>网络地址,broadcast=>广播地址,)
     * 有$key时返回$key对应的值，$key是上面数组的键。
     */
    public static function ipInfo($ipAddr, $key = null)
    {
        $ipAddr = str_replace(" ", "", $ipAddr);
        $arr = explode('/', $ipAddr);
        $ipAddr = $arr[0];
        $ipAddrArr = explode('.', $ipAddr);
        foreach ($ipAddrArr as $k => $v) {
            $ipAddrArr[$k] = intval($v);
        }
        $ipAddr = implode('.', $ipAddrArr);
        $netbits = intval((self::arrayKeyExists(1, $arr) ? $arr[1] : 0));
        $subnetMask = long2ip(ip2long("255.255.255.255") << (32 - $netbits));
        $ip = ip2long($ipAddr);
        $nm = ip2long($subnetMask);
        $nw = ($ip & $nm);
        $bc = $nw | (~$nm);
        $ips = [];
        $ips['netmask'] = long2ip($nm);
        $ips['count'] = ($bc - $nw - 1);
        if ($ips['count'] <= 0) {
            $ips['count'] += 4294967296;
        }
        if ($netbits == 32) {
            $ips['count'] = 0;
            $ips['start'] = long2ip($ip);
            $ips['end'] = long2ip($ip);
        } else {
            $ips['start'] = long2ip($nw + 1);
            $ips['end'] = long2ip($bc - 1);
        }
        $bc = sprintf('%u', $bc);
        $nw = sprintf('%u', $nw);
        $ips['netaddress'] = long2ip((int)$nw);
        $ips['broadcast'] = long2ip((int)$bc);
        return is_null($key) ? $ips : $ips[$key];
    }
    /**
     * 判断是否是ajax请求，只对xmlhttprequest的ajax请求有效
     * @return boolean
     */
    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }
    /**
     * 判断是否是Post请求
     * @return boolean
     */
    public static function isPost()
    {
        return (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');
    }
    /**
     * 判断是否是PUT请求
     * @return boolean
     */
    public static function isPut()
    {
        return (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'PUT');
    }
    /**
     * 判断是否是delete请求
     * @return boolean
     */
    public static function isDelete()
    {
        return (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'DELETE');
    }
}
class Zls
{
    public static $loadedModules = [];
    public static $zlsTime;
    public static $zlsMemory = false;
    /**
     * 包类库自动加载器
     * @param string $className
     * @return bool
     */
    public static function classAutoloader($className)
    {
        $className = str_replace(['/', '_'], '\\', $className);
        $config = self::getConfig();
        $className = str_replace(['Hmvc\\', 'Packages\\'], '', $className);
        $classPath = $config->getClassesDirName() . '/' . str_replace('\\', '/', $className) . '.php';
        $alias = $config->getAlias();
        if (isset($alias[$className])) {
            return class_alias($alias[$className], $className);
        } else {
            foreach ($config->getPackages() as $path) {
                if (file_exists($filePath = $path . $classPath)) {
                    Z::includeOnce($filePath);
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * 获取运行配置
     * @return Zls_Config
     */
    public static function &getConfig()
    {
        static $zlsConfig;
        if (!$zlsConfig) {
            $zlsConfig = new \Zls_Config();
        }
        return $zlsConfig;
    }
    /**
     * 初始化框架配置
     * @param string $timeZone
     * @return \Zls_Config
     */
    public static function initialize($timeZone = 'PRC')
    {
        date_default_timezone_set($timeZone);
        $zlsConfig = self::getConfig();
        if (function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }
        spl_autoload_register(['Zls', 'classAutoloader']);
        if (get_magic_quotes_gpc()) {
            $_GET = Z::stripSlashes($_GET);
            $_POST = Z::stripSlashes($_POST);
            $_COOKIE = Z::stripSlashes($_COOKIE);
        }
        $zlsConfig->setApplicationDir(ZLS_APP_PATH);
        $zlsConfig->addPackage(ZLS_APP_PATH);
        $zlsConfig->composer();
        return $zlsConfig;
    }
    /**
     * 运行调度
     * @return bool
     */
    public static function run()
    {
        $config = Zls::getConfig();
        $exceptionLevel = $config->getExceptionLevel();
        error_reporting(empty($exceptionLevel) ? E_ALL ^ E_DEPRECATED : $config->getExceptionLevel());
        if ($config->getExceptionControl()) {
            \Zls_Logger_Dispatcher::initialize();
        }
        if (Z::isCli() && !Z::isSwoole()) {//todo 去掉is
            self::runCli();
        } elseif (Z::isPluginMode()) {
            self::runPlugin();
        } else {
            self::initSession();
            self::getConfig()->bootstrap();
            self::runWeb();
        }
        return true;
    }
    /**
     * 命令行模式运行
     */
    private static function runCli()
    {
        self::initDebug();
        $executes = [];
        $args = Z::getOpt();
        $hmvcModuleName = Z::arrayGet($args, 'hmvc');
        $isTask = strtolower(current(array_slice(array_keys($args), 1))) === 'task';
        $activity = $isTask ? str_replace('/', '_', Z::arrayGet($args, 'task')) : Z::arrayGet($args, 1);
        // }
        if (!empty($hmvcModuleName)) {
            self::checkHmvc($hmvcModuleName);
        }
        $taskObject = null;
        try {
            if ($isTask) {
                $taskName = Zls::getConfig()->getTaskDirName() . '_' . $activity;
                $taskObject = z::factory($taskName, true);
                Z::throwIf(!($taskObject instanceof Zls_Task), 500, '[ ' . $taskName . ' ] not a valid Zls_Task', 'ERROR');
            } else {
                $command = new Zls_Command($args);
                $taskObject = $command->instance();
                $executes = $command->executes();
            }
        } catch (\Zls_Exception_500 $e) {
            Z::finish($e->getMessage());
        }
        if (!$executes) {
            $executes = ['execute'];
        }
        foreach ($executes as $execute) {
            //}
            $taskObject->$execute($args);
        }
    }
    public static function initDebug()
    {
        self::$zlsMemory = function_exists('memory_get_usage') ? memory_get_usage() : false;
        self::$zlsTime = Z::microtime();
    }
    /**
     * 检测并加载hmvc模块,成功返回模块文件夹名称，失败返回false或抛出异常
     * @param string   $hmvcModuleName hmvc模块在URI中的名称，即注册配置hmvc模块数组的键名称
     * @param  boolean $throwException
     * @return boolean
     */
    public static function checkHmvc($hmvcModuleName, $throwException = true)
    {
        if (!empty($hmvcModuleName)) {
            $config = \Zls::getConfig();
            $hmvcModules = $config->getHmvcModules();
            if (empty($hmvcModules[$hmvcModuleName])) {
                Z::throwIf($throwException, 500, 'Hmvc Module [ ' . $hmvcModuleName . ' ] not found, please check your config.', 'ERROR');
                return false;
            }
            $hmvcModuleDirName = $hmvcModules[$hmvcModuleName];
            if (!Z::arrayKeyExists($hmvcModuleName, self::$loadedModules)) {
                self::$loadedModules[$hmvcModuleName] = 1;
                $hmvcModulePath = $config->getApplicationDir() . $config->getHmvcDirName() . '/' . $hmvcModuleDirName . '/';
                $config->setApplicationDir($hmvcModulePath)->addMasterPackage($hmvcModulePath)->bootstrap();
            }
            return $hmvcModuleDirName;
        }
        return false;
    }
    /**
     * 插件模式运行
     */
    private static function runPlugin()
    {
        self::initDebug();
    }
    public static function initSession($id = null)
    {
        $sessionConfig = self::getConfig()->getSessionConfig();
        $sessionHandle = self::getConfig()->getSessionHandle();
        $haveSessionHandle = $sessionHandle && $sessionHandle instanceof Zls_Session;
        @ini_set('session.auto_start', 0);
        @ini_set('session.gc_probability', 1);
        @ini_set('session.gc_divisor', 100);
        @ini_set('session.gc_maxlifetime', $sessionConfig['lifetime']);
        @ini_set('session.cookie_lifetime', $sessionConfig['lifetime']);
        @ini_set('session.referer_check', '');
        @ini_set('session.entropy_file', '/dev/urandom');
        @ini_set('session.entropy_length', 16);
        @ini_set('session.use_cookies', 1);
        @ini_set('session.use_only_cookies', 1);
        @ini_set('session.use_trans_sid', 0);
        @ini_set('session.hash_function', 1);
        @ini_set('session.hash_bits_per_character', 5);
        session_cache_limiter('nocache');
        session_set_cookie_params(
            $sessionConfig['lifetime'],
            $sessionConfig['cookie_path'],
            preg_match('/^[^\\.]+$/', Z::server('HTTP_HOST')) ? null : $sessionConfig['cookie_domain']
        );
        if (!empty($sessionConfig['session_save_path'])) {
            session_save_path($sessionConfig['session_save_path']);
        }
        session_name($sessionConfig['session_name']);
        register_shutdown_function('session_write_close');
        if ($haveSessionHandle) {
            $sessionHandle->init(session_id());
        }
        return $sessionConfig['autostart'] ? Z::sessionStart($id) : false;
    }
    /**
     * web模式运行
     * @param bool $result
     * @return bool|mixed|null|string
     * @throws ReflectionException
     */
    public static function runWeb($result = false)
    {
        self::initDebug();
        $config = Z::config();
        $_apiDoc = (isset($_GET['_api']) && !!$config->getApiDocToken() && (Z::get('_token', '', true) === $config->getApiDocToken()) && class_exists('\Zls\Action\ApiDoc'));
        $config = self::getConfig();
        $class = '';
        $method = '';
        foreach ($config->getRouters() as $router) {
            $route = $router->find(null);
            if (is_object($route) && $route->found()) {
                $config->setRoute($route);
                $route->setType(get_class($router));
                $class = $route->getController();
                $method = $route->getMethod();
                break;
            } elseif (is_string($route) || is_int($route) || is_null($route)) {
                $config->getRoute()->setType(get_class($router));
                echo $route;
                if (!$_apiDoc) {
                    return false;
                }
            }
        }
        if ($config->getIsMaintainMode()) {
            $isWhite = Z::isWhiteIp(Z::clientIp());
            if (!$isWhite) {
                $handle = $config->getMaintainModeHandle();
                if (is_object($handle)) {
                    Z::finish($handle->handle());
                }
            }
        }
        Z::throwIf(empty($route), 500, 'none router was found in configuration', 'ERROR');
        $_route = $config->getRoute();
        if ($hmvcModuleName = ($_route->getHmvcModuleName() !== null) ? $_route->getHmvcModuleName() : $config->getCurrentDomainHmvcModuleNname()) {
            if (Zls::checkHmvc($hmvcModuleName, false)) {
                $_route->setHmvcModuleName($hmvcModuleName);
                $_route->setFound(true);
            }
        } else {
            $_route->setHmvcModuleName(false);
        }
        if (empty($class)) {
            $class = $config->getControllerDirName() . '_' . $config->getDefaultController();
            $_route->setController($class);
        }
        if (empty($method)) {
            $method = $config->getMethodPrefix() . $config->getDefaultMethod();
            $_route->setMethod($method);
        }
        $config->setRoute($_route);
        $contents = !$_apiDoc ? $config->getSeparationRouter($config->getRoute()->getController(), $config->getRoute()->getHmvcModuleName()) : null;
        if (!$contents) {
            Z::throwIf(!Z::classIsExists($class), 404, 'Controller [ ' . $class . ' ] not found');
            $controllerObject = Z::factory($class);
            Z::throwIf(!($controllerObject instanceof Zls_Controller), 404, '[ ' . $class . ' ] not a valid Zls_Controller');
            if (!!$_apiDoc) {
                /**
                 * @var \Zls\Action\ApiDoc $docComment
                 */
                $docComment = Z::extension('Action\ApiDoc');
                if ($_GET['_api'] == 'self') {
                    $docComment::html(
                        'self',
                        $docComment::apiMethods($docComment::getClassName($class), $method, true, $config->getRoute()->gethmvcModuleName())
                    );
                } else {
                    if ($_GET['_api'] == 'all') {
                        $docComment::html('parent', $docComment::all());
                    } else {
                        $docComment::html(
                            'parent',
                            $docComment::docComment($docComment::getClassName($_route->getController()), $config->getRoute()->gethmvcModuleName())
                        );
                    }
                }
                return false;
            }
            $_method = str_replace($config->getMethodPrefix(), '', $method);
            $_controllerShort = preg_replace('/^' . $config->getControllerDirName() . '_/', '', $class);
            $_controller = $_route->getController();
            $_args = $_route->getArgs();
            if (method_exists($controllerObject, 'before')) {
                $controllerObject->before($_method, $_controllerShort, $_args, $_controller);
            }
            if (!method_exists($controllerObject, $method)) {
                $containCall = method_exists($controllerObject, 'call');
                Z::throwIf(!$containCall, 404, 'Method [ ' . $class . '->' . $method . '() ] not found');
                Z::finish($controllerObject->call($_method, $_controllerShort, $_args, $_controller));
            }
            $cacheClassName = preg_replace('/^' . Z::config()->getControllerDirName() . '_/', '', $class);
            $cacheMethodName = preg_replace('/^' . Z::config()->getMethodPrefix() . '/', '', $method);
            $methodKey = $cacheClassName . ':' . $cacheMethodName;
            $cacheMethodConfig = $config->getMethodCacheConfig();
            if (!empty($cacheMethodConfig) && Z::arrayKeyExists(
                    $methodKey,
                    $cacheMethodConfig
                ) && $cacheMethodConfig[$methodKey]['cache'] && ($cacheMethoKey = $cacheMethodConfig[$methodKey]['key']())) {
                if (!($contents = Z::cache()->get($cacheMethoKey))) {
                    @ob_start();
                    $response = call_user_func_array([$controllerObject, $method], $route->getArgs());
                    $contents = @ob_get_clean();
                    $contents .= is_array($response) ? Z::view()->set($response)->load("$cacheClassName/$cacheMethodName") : $response;
                    Z::cache()->set($cacheMethoKey, $contents, $cacheMethodConfig[$methodKey]['time']);
                }
            } else {
                if (method_exists($controllerObject, 'after')) {
                    @ob_start();
                    $response = call_user_func_array([$controllerObject, $method], $route->getArgs());
                    $contents = @ob_get_clean();
                    $contents .= is_array($response) ? Z::view()->set($response)->load("$cacheClassName/$cacheMethodName") : $response;
                } else {
                    $response = call_user_func_array([$controllerObject, $method], $route->getArgs());
                    $contents = is_array($response) ? Z::view()->set($response)->load("$cacheClassName/$cacheMethodName") : $response;
                }
            }
            if (method_exists($controllerObject, 'after')) {
                $contents = $controllerObject->after($contents, $_method, $_controllerShort, $_args, $_controller);
            }
        }
        if (!$result) {
            echo $contents;
            return '';
        } else {
            return $contents;
        }
    }
}
class Zls_Command
{
    private $command;
    private $executes;
    public function __construct($args)
    {
        if (z::arrayGet($args, 1) === 'artisan') {
            $args = array_values(array_diff($args, ['artisan']));
        }
        $first = z::arrayGet($args, 1);
        $config = Z::config();
        $taskObject = '';
        $commandMain = '\Zls\Command\Main';
        if (!class_exists($commandMain)) {
            Z::finish('Warning: command not installed, Please install "composer require zls/command"' . PHP_EOL);
        }
        $defaultCmd = 'Main';
        $argsCommandName = $first ?: $defaultCmd;
        $name = ucfirst($argsCommandName);
        $command = explode(':', $name);
        $name = array_shift($command);
        $executes = $command;
        $commandLists = $config->getCommands();
        if ($name === $defaultCmd) {
            $commandName = $commandMain;
        } elseif (Z::arrayKeyExists($argsCommandName, $commandLists)) {
            $commandName = $commandLists[$argsCommandName];
        } else {
            $commandName = 'Command_' . $name;
        }
        try {
            $taskObject = z::factory($commandName, true);
        } catch (\Zls_Exception_500 $e) {
            $err = $e->getMessage();
            $errSub = 'not found';
            Z::throwIf(!Z::strEndsWith($err, $errSub), 500, $err);
            try {
                $taskObject = z::factory('\\Zls\\Command\\' . $name, true);
            } catch (\Zls_Exception_500 $e) {
                $err = $e->getMessage();
                Z::throwIf(!Z::strEndsWith($err, $errSub), 500, $err);
                Z::finish("Command { {$name} } is not defined" . PHP_EOL);
            }
        }
        $this->command = $taskObject;
        if (Z::arrayGet($args, ['h', 'H', '-help']) && $commandName !== $commandMain) {
            $executes = ['help'];
        }
        foreach ($executes as $execute) {
            Z::throwIf(!method_exists($taskObject, $execute) && !method_exists($taskObject, '__call'), 500, "Command { {$name} } is not { {$execute} } handle");
        }
        $this->executes = $executes;
    }
    public function instance()
    {
        return $this->command;
    }
    public function executes()
    {
        return $this->executes;
    }
}
class Zls_Di
{
    protected static $_instances = [];
    protected static $_service = [];
    protected static $applicationDir;
    public function lists()
    {
        return ['service' => self::$_service, 'instances' => self::$_instances];
    }
    public function bind($name, $definition)
    {
        self::$_service[$name] = $definition;
    }
    public function remove($name = null)
    {
        if (!is_null($name)) {
            unset(self::$_service[$name]);
            foreach (self::$_instances as $k => $v) {
                if (z::strBeginsWith($k, $name . ':')) {
                    unset(self::$_instances[$k]);
                }
            }
        } else {
            self::$_service = [];
            self::$_instances = [];
        }
    }
    public function makeShared($name, $args = [])
    {
        $original = $name;
        if (!!$args) {
            $name = $name . ':' . json_encode($args);
        }
        if (!isset(self::$_instances[$name])) {
            self::$_instances[$name] = $this->factory($original, $args);
        }
        return self::$_instances[$name];
    }
    private function factory($name, $args = [])
    {
        $contain = isset(self::$_service[$name]);
        Z::throwIf(!$contain, 500, 'Service ' . $name . '\' wasn\'t found in the dependency injection container', 'ERROR');
        $definition = self::$_service[$name];
        if (is_array($definition)) {
            if (Z::isPluginMode()) {
                self::$applicationDir = Z::config()->getApplicationDir();
                \Zls::checkHmvc($definition['hmvc']);
            }
            $definition = $definition['class'];
        }
        if (!$definition instanceof Closure) {
            $definition = $this->getClosure($definition, $args);
        }
        return call_user_func_array($definition, $args);
    }
    private function getClosure($definition, $args)
    {
        return function () use ($definition, $args) {
            if (!is_object($definition)) {
                $classNameFn = function ($definition) {
                    $className1 = str_replace(['\\', '/'], '_', $definition);
                    $className2 = str_replace(['/', '_'], '\\', $definition);
                    return class_exists($className1) ? $className1 : (class_exists($className2) ? $className2 : '');
                };
                if (!$className = $classNameFn($definition)) {
                    preg_match('/Hmvc.?(.*)/i', $definition, $match);
                    $newDefinition = Z::arrayGet($match, 1, $definition);
                    if (!$className = $classNameFn($newDefinition)) {
                        $className = $classNameFn('Packages_' . $newDefinition);
                    }
                }
                Z::throwIf(!$className, 500, 'class [ ' . $definition . ' ] not found', 'ERROR');
                $closure = null;
                if (!$args) {
                    $closure = new $className();
                } else {
                    $class = new ReflectionClass($className);
                    $closure = $class->newInstanceArgs($args);
                    //要容错?
                }
            } else {
                $closure = clone $definition;
            }
            if (self::$applicationDir) {
                Z::config()->setApplicationDir(self::$applicationDir);
                self::$applicationDir = null;
            }
            return $closure;
        };
    }
    public function make($name, $args = [])
    {
        return $this->factory($name, $args);
    }
    public function has($name)
    {
        return isset(self::$_service[$name]);
    }
}
class Zls_PDO extends PDO
{
    protected $transactionCounter = 0;
    private $isLast;
    public function isInTransaction()
    {
        return !$this->isLast;
    }
    public function beginTransaction()
    {
        if (!$this->transactionCounter++) {
            return parent::beginTransaction();
        }
        $this->exec('SAVEPOINT trans' . $this->transactionCounter);
        return $this->transactionCounter >= 0;
    }
    public function commit()
    {
        if (!--$this->transactionCounter) {
            $this->isLast = true;
            return parent::commit();
        }
        $this->isLast = false;
        return $this->transactionCounter >= 0;
    }
    public function rollback()
    {
        if (--$this->transactionCounter) {
            $this->exec('ROLLBACK TO trans' . ($this->transactionCounter + 1));
            return true;
        }
        return parent::rollback();
    }
}
class Zls_Database_ActiveRecord extends Zls_Database
{
    public $arFrom;
    protected $_lastInsertBatchCount = 0;
    private $arSelect;
    private $arJoin;
    private $arWhere;
    private $arGroupby;
    private $arHaving;
    private $arLimit;
    private $arOrderby;
    private $arSet;
    private $primaryKey;
    private $arUpdateBatch;
    private $arInsert;
    private $arInsertBatch;
    private $_asTable;
    private $_asColumn;
    private $_values;
    private $_sqlType;
    private $_currentSql;
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->_reset();
    }
    protected function _reset()
    {
        $this->arSelect = [];
        $this->arFrom = [];
        $this->arJoin = [];
        $this->arWhere = [];
        $this->arGroupby = [];
        $this->arHaving = [];
        $this->arOrderby = [];
        $this->arLimit = '';
        $this->primaryKey = '';
        $this->arSet = [];
        $this->arUpdateBatch = [];
        $this->arInsert = [];
        $this->arInsertBatch = [];
        $this->_asTable = [];
        $this->_asColumn = [];
        $this->_values = [];
        $this->_sqlType = 'select';
        $this->_currentSql = '';
    }
    public function select($select, $wrap = true)
    {
        foreach (explode(',', $select) as $key) {
            $this->arSelect[] = [$key, $wrap];
        }
        return $this;
    }
    public function join($table, $on, $type = '')
    {
        $this->arJoin[] = [$table, $on, strtoupper($type)];
        return $this;
    }
    public function groupBy($key)
    {
        $key = explode(',', $key);
        foreach ($key as $k) {
            $this->arGroupby[] = trim($k);
        }
        return $this;
    }
    public function having($having, $leftWrap = 'AND', $rightWrap = '')
    {
        $this->arHaving[] = [$having, $leftWrap, $rightWrap, count($this->arHaving)];
        return $this;
    }
    public function orderBy($key, $type = 'desc')
    {
        $this->arOrderby[$key] = $type;
        return $this;
    }
    public function limit($offset, $count)
    {
        $this->arLimit = "$offset , $count";
        return $this;
    }
    public function insert($table, array $data)
    {
        $this->_sqlType = 'insert';
        $this->arInsert = $data;
        $this->_lastInsertBatchCount = 0;
        $this->from($table);
        return $this;
    }
    /**
     * 查询表
     * @param string|array|Closure $from
     * @param string               $as 别名
     * @return $this
     */
    public function from($from, $as = '')
    {
        if (is_array($from)) {
            $as = current($from);
            if (!$from = key($from)) {
                $from = $as;
                $as = '';
            }
        } elseif ($from instanceof Closure) {
            $_db = $this->cloneDb();
            $from($_db);
            $from = ' (' . $_db->getSql() . ') ';
            $values = $_db->getSqlValues();
            foreach ($values as $value) {
                array_push($this->_values, $value);
            }
            if (!$as) {
                $as = '_tmp' . md5($from);
            }
        }
        $this->arFrom = [$from, $as];
        if ($as) {
            $this->_asTable[$as] = 1;
        }
        return $this;
    }
    public function cloneDb()
    {
        return clone $this;
    }
    public function getSql()
    {
        if ($this->_currentSql) {
            return $this->_currentSql;
        }
        switch ($this->_sqlType) {
            case 'select':
                $this->_currentSql = $this->_getSelectSql();
                break;
            case 'update':
                $this->_currentSql = $this->_getUpdateSql();
                break;
            case 'updateBatch':
                $this->_currentSql = $this->_getUpdateBatchSql();
                break;
            case 'insert':
                $this->_currentSql = $this->_getInsertSql();
                break;
            case 'insertBatch':
                $this->_currentSql = $this->_getInsertBatchSql();
                break;
            case 'replace':
                $this->_currentSql = $this->_getReplaceSql();
                break;
            case 'replaceBatch':
                $this->_currentSql = $this->_getReplaceBatchSql();
                break;
            case 'delete':
                $this->_currentSql = $this->_getDeleteSql();
                break;
            default:
        }
        $resetSql = $this->resetSql();
        if ($resetSql instanceof Closure) {
            $this->_currentSql = $resetSql($this->_currentSql);
        }
        return $this->_currentSql;
    }
    private function _getSelectSql()
    {
        $from = $this->_getFrom();
        $where = $this->_getWhere();
        $having = '';
        foreach ($this->arHaving as $w) {
            $having .= call_user_func_array([$this, '_compileWhere'], $w);
        }
        $having = trim($having);
        if ($having) {
            $having = "\n" . ' HAVING ' . $having;
        }
        $groupBy = trim($this->_compileGroupBy());
        if ($groupBy) {
            $groupBy = "\n" . ' GROUP BY ' . $groupBy;
        }
        $orderBy = trim($this->_compileOrderBy());
        if ($orderBy) {
            $orderBy = "\n" . ' ORDER BY ' . $orderBy;
        }
        $limit = $this->_getLimit();
        $select = $this->_compileSelect();
        if ($this->_isSqlsrv() && !!$limit) {
            $limitArg = explode(',', $limit);
            if (count($limitArg) > 1) {
                $offset = $limitArg[1];
                if ($limit = $limitArg[0]) {
                    $limit = (int)$offset * ((int)$limit - 1);
                }
                if (!!$orderBy) {
                    $orderBy = $orderBy . ' OFFSET ' . $limit . ' ROWS FETCH NEXT ' . $offset . '  ROWS ONLY ';
                } else {
                    if ($limit > 0) {
                        $primaryKey = $this->getPrimaryKey() ?: $this->execute('EXEC sp_pkeys @table_name=\'' . trim(strtr($from, ['[' => '', ']' => ''])) . '\'')->value('COLUMN_NAME');
                        if ($primaryKey) {
                            $orderBy = "\n" . ' ORDER BY ' . $primaryKey . ' ASC OFFSET ' . $limit . ' ROWS FETCH NEXT ' . $offset . '  ROWS ONLY ';
                        }
                    } else {
                        $select = ' TOP ' . $offset . ' ' . $select;
                    }
                }
            } else {
            }
            $limit = '';
        }
        $sql = "\n" . ' SELECT '
            . $select
            . "\n" . ' FROM ' . $from
            . $where
            . $groupBy
            . $having
            . $orderBy
            . $limit;
        return $sql;
    }
    private function _getFrom()
    {
        $table = ' ' . call_user_func_array([$this, '_compileFrom'], $this->arFrom) . ' ';
        foreach ($this->arJoin as $join) {
            $table .= call_user_func_array([$this, '_compileJoin'], $join);
        }
        return $table;
    }
    private function _getWhere()
    {
        $where = '';
        $hasEmptyIn = false;
        foreach ($this->arWhere as $w) {
            if (is_array($w[0])) {
                foreach ($w[0] as $k => &$v) {
                    if (is_array($v) && empty($v)) {
                        $hasEmptyIn = true;
                        break;
                    } elseif ($v instanceof Closure) {
                        $_db = $this->cloneDb();
                        $v($_db);
                        $v = [' (' . $_db->getSql() . ') ', $_db->getSqlValues()];
                        $w[5] = true;
                    }
                }
                if ($hasEmptyIn) {
                    break;
                }
            }
            $where .= call_user_func_array([$this, '_compileWhere'], $w);
        }
        if ($hasEmptyIn) {
            return ' WHERE 0';
        }
        $where = trim($where);
        if ($where) {
            $where = "\n" . ' WHERE ' . $where;
        }
        return $where;
    }
    private function _compileGroupBy()
    {
        $groupBy = [];
        foreach ($this->arGroupby as $key) {
            $_key = explode('.', $key);
            if (count($_key) == 2) {
                $groupBy[] = $this->_protectIdentifier($this->_checkPrefix($_key[0])) . '.' . $this->_protectIdentifier($_key[1]);
            } else {
                $groupBy[] = $this->_protectIdentifier($_key[0]);
            }
        }
        return implode(' , ', $groupBy);
    }
    private function _protectIdentifier($str)
    {
        if (stripos($str, '(') || stripos($str, ')') || trim($str) == '*') {
            return $str;
        }
        $_str = explode(' ', $str);
        $point = (!$this->_isSqlsrv()) ? '``' : '[]';
        $point[3] = ($point[0] === '[') ? '[dbo].[' : $point[0];
        if (count($_str) == 3 && strtolower($_str[1]) == 'as') {
            return $point[3] . $_str[0] . $point[1] . ' AS ' . $point[0] . $_str[2] . $point[1];
        } else {
            return $point[3] . $str . $point[1];
        }
    }
    private function _checkPrefix($str)
    {
        $prefix = $this->getTablePrefix();
        if ($prefix && strpos($str, $prefix) === false) {
            if (!Z::arrayKeyExists($str, $this->_asTable)) {
                return $prefix . $str;
            }
        }
        return $str;
    }
    private function _compileOrderBy()
    {
        $orderby = [];
        foreach ($this->arOrderby as $key => $type) {
            $type = strtoupper($type);
            $_key = explode('.', $key);
            if (count($_key) == 2) {
                $orderby[] = $this->_protectIdentifier($this->_checkPrefix($_key[0])) . '.' . $this->_protectIdentifier($_key[1]) . ' ' . $type;
            } else {
                $orderby[] = $this->_protectIdentifier($_key[0]) . ' ' . $type;
            }
        }
        return implode(' , ', $orderby);
    }
    private function _getLimit()
    {
        $limit = $this->arLimit;
        if ($limit && !$this->_isSqlsrv()) {
            $limit = "\n" . ' LIMIT ' . $limit;
        }
        return $limit;
    }
    private function _compileSelect()
    {
        $selects = $this->arSelect;
        if (empty($selects)) {
            $selects[] = ['*', true];
        }
        foreach ($selects as $key => $_value) {
            $protect = $_value[1];
            $value = trim($_value[0]);
            if ($value != '*') {
                $_info = explode('.', $value);
                if (count($_info) == 2) {
                    $_v = $this->_checkPrefix($_info[0]);
                    $_info[0] = $protect ? $this->_protectIdentifier($_v) : $_v;
                    $_info[1] = $protect ? $this->_protectIdentifier($_info[1]) : $_info[1];
                    $value = implode('.', $_info);
                } else {
                    $value = $protect ? $this->_protectIdentifier($value) : $value;
                }
            }
            $selects[$key] = $value;
        }
        return implode(',', $selects);
    }
    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        if (!$this->primaryKey) {
            $primaryKey = '';
            if ($this->_isSqlsrv()) {
                $primaryKey = $this->execute('EXEC sp_pkeys @table_name=\'' . trim(strtr($this->_getFrom(), ['[' => '', ']' => ''])) . '\'')->value('COLUMN_NAME');
            } else {
                $sql = 'SHOW FULL COLUMNS FROM ' . trim(strtr($this->_getFrom(), ['`' => '']));
                $result = $this->execute($sql)->rows();
                foreach ($result as $val) {
                    if (strtolower($val['Key']) == 'pri') {
                        $primaryKey = $val['Field'];
                        break;
                    }
                }
            }
            $this->primaryKey = $primaryKey;
        }
        return $this->primaryKey;
    }
    public function setPrimaryKey($primaryKey)
    {
        return $this->primaryKey = $primaryKey;
    }
    private function _getUpdateSql()
    {
        $sql[] = "\n" . 'UPDATE ';
        $sql[] = $this->_getFrom();
        $sql[] = "\n" . 'SET';
        $sql[] = $this->_compileSet();
        $sql[] = $this->_getWhere();
        $sql[] = $this->_getLimit();
        return implode(' ', $sql);
    }
    private function _compileSet()
    {
        $set = [];
        foreach ($this->arSet as $key => $value) {
            list($value, $wrap) = $value;
            if ($wrap) {
                $set[] = $this->_protectIdentifier($key) . ' = ' . '?';
                $this->_values[] = $value;
            } else {
                $set[] = $this->_protectIdentifier($key) . ' = ' . $value;
            }
        }
        return implode(' , ', $set);
    }
    private function _getUpdateBatchSql()
    {
        $sql[] = "\n" . 'UPDATE ';
        $sql[] = $this->_getFrom();
        $sql[] = "\n" . 'SET';
        $sql[] = $this->_compileUpdateBatch();
        $sql[] = $this->_getWhere();
        return implode(' ', $sql);
    }
    private function _compileUpdateBatch()
    {
        list($values, $index) = $this->arUpdateBatch;
        if (count($values) && Z::arrayKeyExists("0.$index", $values)) {
            $ids = [];
            $final = [];
            $_values = [];
            foreach ($values as $key => $val) {
                $ids[] = $val[$index];
                foreach (array_keys($val) as $field) {
                    if ($field != $index) {
                        if (is_array($val[$field])) {
                            $_column = explode(' ', key($val[$field]));
                            $column = $this->_protectIdentifier($_column[0]);
                            $op = isset($_column[1]) ? $_column[1] : '';
                            $final[$field][] = 'WHEN ' . $this->_protectIdentifier($index) . ' = ' . $val[$index] . ' THEN ' . $column . ' ' . $op . ' ' . "?";
                            $_values[$field][] = current($val[$field]);
                        } else {
                            $final[$field][] = 'WHEN ' . $this->_protectIdentifier($index) . ' = ' . $val[$index] . ' THEN ' . "?";
                            $_values[$field][] = $val[$field];
                        }
                    }
                }
            }
            foreach ($_values as $field => $value) {
                if ($field == $index) {
                    continue;
                }
                if (!empty($_values[$field]) && is_array($_values[$field])) {
                    foreach ($value as $v) {
                        $this->_values[] = $v;
                    }
                }
            }
            $_values = null;
            $sql = "";
            $cases = '';
            foreach ($final as $k => $v) {
                $cases .= $this->_protectIdentifier($k) . ' = CASE ' . "\n";
                foreach ($v as $row) {
                    $cases .= $row . "\n";
                }
                $cases .= 'ELSE ' . $this->_protectIdentifier($k) . ' END, ';
            }
            $sql .= substr($cases, 0, -2);
            return $sql;
        }
        return '';
    }
    private function _getInsertSql()
    {
        $sql[] = "\n" . 'INSERT INTO ';
        $sql[] = $this->_getFrom();
        $sql[] = $this->_compileInsert();
        return implode(' ', $sql);
    }
    private function _compileInsert()
    {
        $keys = [];
        $values = [];
        foreach ($this->arInsert as $key => $value) {
            $keys[] = $this->_protectIdentifier($key);
            $values[] = '?';
            $this->_values[] = $value;
        }
        if (!empty($keys)) {
            return '(' . implode(',', $keys) . ') ' . "\n" . 'VALUES (' . implode(',', $values) . ')';
        }
        return '';
    }
    /**
     * @return string
     */
    private function _getInsertBatchSql()
    {
        $sql[] = "\nINSERT INTO ";
        $sql[] = $this->_getFrom();
        $sql[] = $this->_compileInsertBatch();
        return implode(' ', $sql);
    }
    private function _compileInsertBatch()
    {
        $keys = [];
        $values = [];
        if (!empty($this->arInsertBatch[0])) {
            foreach ($this->arInsertBatch[0] as $key => $value) {
                $keys[] = $this->_protectIdentifier($key);
            }
            foreach ($this->arInsertBatch as $row) {
                $_values = [];
                foreach ($row as $key => $value) {
                    $_values[] = '?';
                    $this->_values[] = $value;
                }
                $values[] = '(' . implode(',', $_values) . ')';
            }
            return '(' . implode(',', $keys) . ') ' . "\n VALUES " . implode(' , ', $values);
        }
        return '';
    }
    private function _getReplaceSql()
    {
        $sql[] = "\nREPLACE INTO ";
        $sql[] = $this->_getFrom();
        $sql[] = $this->_compileInsert();
        return implode(' ', $sql);
    }
    private function _getReplaceBatchSql()
    {
        $sql[] = "\nREPLACE INTO ";
        $sql[] = $this->_getFrom();
        $sql[] = $this->_compileInsertBatch();
        return implode(' ', $sql);
    }
    private function _getDeleteSql()
    {
        $sql[] = "\nDELETE FROM ";
        $sql[] = $this->_getFrom();
        $sql[] = $this->_getWhere();
        return implode(' ', $sql);
    }
    public function replace($table, array $data)
    {
        $this->_sqlType = 'replace';
        $this->arInsert = $data;
        $this->from($table);
        return $this;
    }
    public function insertBatch($table, array $data)
    {
        $this->_sqlType = 'insertBatch';
        $this->arInsertBatch = $data;
        $this->_lastInsertBatchCount = count($data);
        $this->from($table);
        return $this;
    }
    public function replaceBatch($table, array $data)
    {
        $this->_sqlType = 'replaceBatch';
        $this->arInsertBatch = $data;
        $this->_lastInsertBatchCount = count($data);
        $this->from($table);
        return $this;
    }
    public function delete($table, array $where = [])
    {
        $this->from($table);
        $this->where($where);
        $this->_sqlType = 'delete';
        return $this;
    }
    public function where($where, $leftWrap = 'AND', $rightWrap = '')
    {
        if (!empty($where)) {//&& is_array($where)
            $this->arWhere[] = [$where, $leftWrap, $rightWrap, count($this->arWhere)];
        }
        return $this;
    }
    public function update($table, array $data = [], array $where = [])
    {
        $this->from($table);
        $this->where($where);
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $this->set($key, (($value === false) ? 0 : 1), true);
            } elseif (is_null($value)) {
                $this->set($key, 'NULL', false);
            } else {
                $this->set($key, $value, true);
            }
        }
        return $this;
    }
    public function set($key, $value, $wrap = true)
    {
        $this->_sqlType = 'update';
        $this->arSet[$key] = [$value, $wrap];
        return $this;
    }
    /**
     * 批量更新
     * @param string $table  表名
     * @param array  $values 必须包含$index字段
     * @param string $index  唯一字段名称，一般是主键id
     * @return object|int
     */
    public function updateBatch($table, array $values, $index)
    {
        $this->from($table);
        $this->_sqlType = 'updateBatch';
        $this->arUpdateBatch = [$values, $index];
        if (!empty($values[0])) {
            $ids = [];
            foreach ($values as $val) {
                $ids[] = $val[$index];
            }
            $this->where([$index => $ids]);
        }
        return $this;
    }
    /**
     * 加表前缀，保护字段名和表名
     * @param String $str 比如：user.id , id
     * @return String
     */
    public function wrap($str)
    {
        $_key = explode('.', $str);
        if (count($_key) == 2) {
            return $this->_protectIdentifier($this->_checkPrefix($_key[0])) . '.' . $this->_protectIdentifier($_key[1]);
        } else {
            return $this->_protectIdentifier($_key[0]);
        }
    }
    public function __toString()
    {
        return $this->getSql();
    }
    protected function _getValues()
    {
        return $this->_values;
    }
    private function __clone()
    {
        $this->_reset();
    }
    private function _compileWhere($where, $leftWrap = 'AND', $rightWrap = '', $index = -1, $child = false)
    {
        $_where = [];
        if ($index == 0) {
            $str = strtoupper(trim($leftWrap));
            foreach (['AND', 'OR'] as $v) {
                if (stripos($str, $v) !== false) {
                    $leftWrap = '';
                    break;
                }
            }
        }
        if (is_string($where)) {
            return ' ' . $leftWrap . ' ' . $where . $rightWrap . ' ';
        }
        foreach ($where as $key => $value) {
            $key = trim($key);
            $_key = explode(' ', $key, 2);
            $op = count($_key) == 2 ? strtoupper($_key[1]) : '';
            $key = explode('.', $_key[0]);
            if (count($key) == 2) {
                $key = $this->_protectIdentifier($this->_checkPrefix($key[0])) . '.' . $this->_protectIdentifier($key[1]);
            } else {
                $key = $this->_protectIdentifier(current($key));
            }
            if ($child) {
                $_where[] = $key . ($op ? $op : ' =') . $value[0];
                foreach ($value[1] as $v) {
                    array_push($this->_values, $v);
                }
            } elseif (is_array($value) && !$child) {
                if ($op !== 'BETWEEN') {
                    $op = $op ? $op . ' IN ' : ' IN ';
                    $perch = '(' . implode(',', array_fill(0, count($value), '?')) . ')';
                } else {
                    $perch = '? AND ?';
                    $op = ' BETWEEN ';
                }
                $_where[] = $key . ' ' . $op . $perch;
                foreach ($value as $v) {
                    array_push($this->_values, $v);
                }
            } elseif (is_bool($value)) {
                $op = $op ? $op : '=';
                $value = $value ? 1 : 0;
                $_where[] = $key . ' ' . $op . ' ? ';
                array_push($this->_values, $value);
            } elseif (is_null($value)) {
                $op = $op ? $op : 'IS';
                $_where[] = $key . ' ' . $op . ' NULL ';
            } else {
                $op = $op ? $op : '=';
                $_where[] = $key . ' ' . $op . ' ? ';
                array_push($this->_values, $value);
            }
        }
        return ' ' . $leftWrap . ' ' . implode(' AND ', $_where) . $rightWrap . ' ';
    }
    private function _compileFrom($from, $as = '')
    {
        if ($as) {
            $this->_asTable[$as] = 1;
            $as = ' AS ' . $this->_protectIdentifier($as) . ' ';
        }
        return $this->_protectIdentifier($this->_checkPrefix($from)) . $as;
    }
    private function _compileJoin($table, $on, $type = '')
    {
        if (is_array($table)) {
            $this->_asTable[current($table)] = 1;
            $table = $this->_protectIdentifier($this->_checkPrefix(key($table))) . ' AS ' . $this->_protectIdentifier(current($table)) . ' ';
        } else {
            $table = $this->_protectIdentifier($this->_checkPrefix($table));
        }
        list($left, $right) = explode('=', $on);
        $_left = explode('.', $left);
        $_right = explode('.', $right);
        if (count($_left) == 2) {
            $_left[0] = $this->_protectIdentifier($this->_checkPrefix($_left[0]));
            $_left[1] = $this->_protectIdentifier($_left[1]);
            $left = ' ' . implode('.', $_left) . ' ';
        } else {
            $left = $this->_protectIdentifier($left);
        }
        if (count($_right) == 2) {
            $_right[0] = $this->_protectIdentifier($this->_checkPrefix($_right[0]));
            $_right[1] = $this->_protectIdentifier($_right[1]);
            $right = ' ' . implode('.', $_right) . ' ';
        } else {
            $right = $this->_protectIdentifier($right);
        }
        $on = $left . ' = ' . $right;
        return ' ' . $type . ' JOIN ' . $table . ' ON ' . $on . ' ';
    }
}
class Zls_Database_Resultset
{
    private $_resultSet = [];
    private $_rowsKey = '';
    public function __construct($resultSet)
    {
        $this->_resultSet = $resultSet;
    }
    public function total()
    {
        return count($this->_resultSet);
    }
    public function bean($beanClassName, $index = null)
    {
        $row = $this->row($index);
        $object = Z::bean($beanClassName, $row, false);
        return $object;
    }
    public function row($index = null, $isAssoc = true)
    {
        if (!is_null($index) && Z::arrayKeyExists($index, $this->_resultSet)) {
            return $isAssoc ? $this->_resultSet[$index] : array_values($this->_resultSet[$index]);
        } else {
            $row = current($this->_resultSet);
            return $isAssoc ? (is_array($row) ? $row : []) : array_values($row);
        }
    }
    public function beans($beanClassName, $toArray = true)
    {
        $rowsKey = $this->_rowsKey;
        $this->_rowsKey = '';
        $objects = [];
        $rows = $this->rows();
        foreach ($rows as $row) {
            $object = Z::bean($beanClassName, $row, false);
            if ($toArray) {
                $object = $object->toArray();
            }
            if ($rowsKey) {
                $objects[$row[$rowsKey]] = $object;
            } else {
                $objects[] = $object;
            }
        }
        return $objects;
    }
    public function rows($isAssoc = true)
    {
        $key = $this->_rowsKey;
        $this->_rowsKey = '';
        if ($key) {
            if ($isAssoc) {
                $rows = [];
                foreach ($this->_resultSet as $row) {
                    $rows[$row[$key]] = $row;
                }
                return $rows;
            } else {
                $rows = [];
                foreach ($this->_resultSet as $row) {
                    $rows[$row[$key]] = array_values($row);
                }
                return $rows;
            }
        } else {
            if ($isAssoc) {
                return $this->_resultSet;
            } else {
                $rows = [];
                foreach ($this->_resultSet as $row) {
                    $rows[] = array_values($row);
                }
                return $rows;
            }
        }
    }
    public function values($columnName)
    {
        $rowsKey = $this->_rowsKey;
        $this->_rowsKey = '';
        $columns = [];
        foreach ($this->_resultSet as $row) {
            if (Z::arrayKeyExists($columnName, $row)) {
                if ($rowsKey) {
                    $columns[$row[$rowsKey]] = $row[$columnName];
                } else {
                    $columns[] = $row[$columnName];
                }
            } else {
                return [];
            }
        }
        return $columns;
    }
    public function value($columnName, $default = null, $index = null)
    {
        $row = $this->row($index);
        return ($columnName && Z::arrayKeyExists($columnName, $row)) ? $row[$columnName] : $default;
    }
    public function key($columnName)
    {
        $this->_rowsKey = $columnName;
        return $this;
    }
}
abstract class Zls_Bean
{
    final public function toArray($fields = [])
    {
        $args = get_object_vars($this);
        $methods = array_diff(get_class_methods($this), get_class_methods(__CLASS__));
        foreach ($methods as $method) {
            $key = static::_get($method);
            if (!!$fields && is_array($fields) && !in_array($key, $fields)) {
                continue;
            }
            $args[$key] = $this->$method();
        }
        return $args;
    }
    private static function _get($method)
    {
        return lcfirst(Z::strCamel2Snake(str_replace('get', '', $method)));
    }
    public function __call($method, $args)
    {
        if (z::strBeginsWith($method, 'set')) {
            $method = static::_set($method);
            return $this->$method = z::arrayGet($args, 0);
        } elseif (z::strBeginsWith($method, 'get')) {
            $method = static::_get($method);
            return $this->$method;
        }
        Z::throwIf(true, 500, 'Call to undefined method ' . get_called_class() . '::' . $method . '()');
        return false;
    }
    private static function _set($method)
    {
        return lcfirst(Z::strCamel2Snake(str_replace('set', '', $method)));
    }
}
abstract class Zls_Dao
{
    private $db;
    private $rs;
    private $_cacheTime = null;
    private $_cacheKey;
    public function __construct()
    {
        $this->db = Z::db();
    }
    /**
     * 获取排除字段
     * @param $field
     * @return array
     */
    public function getReversalColumns($field)
    {
        return array_diff(static::getColumns(), is_array($field) ? $field : explode(',', $field));
    }
    abstract public function getColumns();
    /**
     * 读取数据
     * @param      $data
     * @param null $field     字段
     * @param bool $replenish 自动补齐
     * @return array
     */
    public function readData($data, $field = null, $replenish = false)
    {
        if (!$field) {
            $field = static::getColumns();
        }
        return z::readData($field, $data, $replenish);
    }
    public function bean($row, $beanName = '')
    {
        return Z::bean($beanName ?: $this->getBean(), $row)->toArray();
    }
    public function getBean()
    {
        $beanName = strstr(get_class($this), 'Dao', false);
        $beanName = str_replace('Dao_', '', $beanName);
        $beanName = str_replace('Dao\\', '', $beanName);
        return $beanName;
    }
    public function beans($rows, $beanName = '')
    {
        $beanName = $beanName ?: $this->getBean();
        $objects = [];
        foreach ($rows as $row) {
            $object = Z::bean($beanName, $row, false);
            foreach ($row as $key => $value) {
                $method = "set" . Z::strSnake2Camel($key);
                $object->{$method}($value);
            }
            $objects[] = $object->toArray();
        }
        return $objects;
    }
    /**
     * 添加数据
     * @param array $data 需要添加的数据
     * @return int 最后插入的id，失败为0
     */
    public function insert($data)
    {
        $num = $this->getDb()->insert($this->getTable(), $data)->execute();
        return $num ? $this->getDb()->lastId() : 0;
    }
    /**
     * 获取Dao中使用的数据库操作对象
     * @return Zls_Database_ActiveRecord
     */
    public function &getDb()
    {
        return $this->db;
    }
    /**
     * 设置Dao中使用的数据库操作对象
     * @param Zls_Database_ActiveRecord $db
     * @return \Zls_Dao
     */
    public function setDb(Zls_Database_ActiveRecord $db)
    {
        $this->db = $db;
        return $this;
    }
    /**
     * 获取表名
     * @return string
     */
    public function getTable()
    {
        $className = str_replace('Dao', '', get_called_class());
        $className = str_replace('\\', '_', $className);
        $className = substr($className, 1);
        return Z::strCamel2Snake($className);
    }
    /**
     * 批量添加数据
     * @param array $rows 需要添加的数据
     * @return int 插入的数据中第一条的id，失败为0
     */
    public function insertBatch($rows)
    {
        $num = $this->getDb()->insertBatch($this->getTable(), $rows)->execute();
        return $num ? $this->getDb()->lastId() : 0;
    }
    /**
     * 更新数据
     * @param array     $data  需要更新的数据
     * @param array|int $where 可以是where条件关联数组，还可以是主键值。
     * @return boolean
     */
    public function update($data, $where)
    {
        $where = is_array($where) ? $where : [$this->getPrimaryKey() => $where];
        return $this->getDb()->where($where)->update($this->getTable(), $data)->execute();
    }
    /**
     * 获取主键
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->getDb()->from($this->getTable())->getPrimaryKey();
    }
    /**
     * 更新数据
     * @param array  $data  需要批量更新的数据
     * @param string $index 需要批量更新的数据中的主键名称
     * @return boolean
     */
    public function updateBatch($data, $index = null)
    {
        if (!$index) {
            $index = $this->getPrimaryKey();
        }
        return $this->getDb()->updateBatch($this->getTable(), $data, $index)->execute();
    }
    /**
     * 获取所有数据
     * @param array|null  $where   where条件数组
     * @param array|null  $orderBy 排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param int|null    $limit   limit数量，比如：10
     * @param string|null $fields  要搜索的字段，比如：id,name。留空默认*
     * @return array
     */
    public function findAll($where = null, array $orderBy = [], $limit = null, $fields = null)
    {
        if (!is_null($fields)) {
            $this->getDb()->select($fields);
        }
        if (!is_null($where)) {
            $this->getDb()->where($where);
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        if (!is_null($limit)) {
            $this->getDb()->limit(0, $limit);
        }
        if (!is_null($this->_cacheTime)) {
            $this->getDb()->cache($this->_cacheTime, $this->_cacheKey);
        }
        $this->rs = $this->getDb()->from($this->getTable())->execute();
        $this->cache();
        return $this->rs->rows();
    }
    public function cache($cacheTime = 0, $cacheKey = '')
    {
        $this->_cacheTime = (int)$cacheTime;
        $this->_cacheKey = $cacheKey;
        return $this;
    }
    /**
     * 根据条件获取一个字段的值或者数组
     * @param string       $col     字段名称
     * @param string|array $where   可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param boolean      $isRows  返回多行记录还是单行记录，true：多行，false：单行
     * @param array        $orderBy 当返回多行记录时，可以指定排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @return array
     */
    public function findCol($col, $where, $isRows = false, array $orderBy = [])
    {
        $row = $this->find($where, $isRows, $orderBy);
        if (!$isRows) {
            return isset($row[$col]) ? $row[$col] : null;
        } else {
            $vals = [];
            foreach ($row as $v) {
                $vals[] = $v[$col];
            }
            return $vals;
        }
    }
    /**
     * 获取一条或者多条数据
     * @param string|array $values  可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param boolean      $isRows  返回多行记录还是单行记录，true：多行，false：单行
     * @param array        $orderBy 当返回多行记录时，可以指定排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param string|null  $fields  要搜索的字段，比如：id,name。留空默认*
     * @return array
     */
    public function find($values, $isRows = false, array $orderBy = [], $fields = null)
    {
        if (!is_null($fields)) {
            $this->getDb()->select($fields);
        }
        if (!empty($values)) {
            if (is_array($values)) {
                $is_asso = array_diff_assoc(array_keys($values), range(0, sizeof($values))) ? true : false;
                if ($is_asso) {
                    $this->getDb()->where($values);
                } else {
                    $this->getDb()->where([$this->getPrimaryKey() => array_values($values)]);
                }
            } else {
                $this->getDb()->where([$this->getPrimaryKey() => $values]);
            }
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        if (!$isRows) {
            $this->getDb()->limit(0, 1);
        }
        if (!is_null($this->_cacheTime)) {
            $this->getDb()->cache($this->_cacheTime, $this->_cacheKey);
        }
        $this->rs = $this->getDb()->from($this->getTable())->execute();
        $this->cache();
        if ($isRows) {
            return $this->rs->rows();
        } else {
            return $this->rs->row();
        }
    }
    public function reaultset()
    {
        return $this->rs;
    }
    /**
     * 根据条件删除记录
     * @param string $values 可以是一个主键的值或者主键主键的值数组
     * @param array  $cond   附加的where条件，关联数组
     * @return int|boolean  成功则返回影响的行数，失败返回false
     */
    public function delete($values = null, array $cond = null)
    {
        if (empty($values) && empty($cond)) {
            return 0;
        }
        if (!empty($values)) {
            $this->getDb()->where([$this->getPrimaryKey() => is_array($values) ? array_values($values) : $values]);
        }
        if (!empty($cond)) {
            $this->getDb()->where($cond);
        }
        return $this->getDb()->delete($this->getTable())->execute();
    }
    /**
     * 分页方法
     * @param int    $page          第几页
     * @param int    $pagesize      每页多少条
     * @param string $url           基础url，里面的{page}会被替换为实际的页码
     * @param string $fields        select的字段，全部用*，多个字段用逗号分隔
     * @param array  $where         where条件，关联数组
     * @param array  $orderBy       排序字段，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param int    $pageBarACount 分页条a的数量
     * @return array
     */
    public function getPage(
        $page = 1,
        $pagesize = 10,
        $url = '{page}',
        $fields = '*',
        array $where = null,
        array $orderBy = [],
        $pageBarACount = 6
    ) {
        $data = [];
        if (is_array($where)) {
            $this->getDb()->where($where);
        }
        $total = $this->getDb()->select('count(*) as total')
            ->from($this->getTable())
            ->execute()
            ->value('total');
        if (is_array($where)) {
            $this->getDb()->where($where);
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        if ($page < 1) {
            $page = 1;
        }
        if ($pagesize < 1) {
            $pagesize = 1;
        }
        $data['items'] = $this->getDb()
            ->select($fields)
            ->limit(($page - 1) * $pagesize, $pagesize)
            ->from($this->getTable())->execute()->rows();
        $data['page'] = Z::page($total, $page, $pagesize, $url, $pageBarACount);
        return $data;
    }
    /**
     * SQL搜索
     * @param int    $page          第几页
     * @param int    $pagesize      每页多少条
     * @param string $url           基础url，里面的{page}会被替换为实际的页码
     * @param string $fields        select的字段，全部用*，多个字段用逗号分隔
     * @param string $cond          是条件字符串，SQL语句where后面的部分，不要带limit
     * @param array  $values        $cond中的问号的值数组，$cond中使用?可以防止sql注入
     * @param int    $pageBarACount 分页条a的数量，可以参考手册分页条部分
     * @return array
     */
    public function search(
        $page = 1,
        $pagesize = 10,
        $url = '{page}',
        $fields = '*',
        $cond = '',
        array $values = [],
        $pageBarACount = 10
    ) {
        $data = [];
        $table = $this->getDb()->getTablePrefix() . $this->getTable();
        $rs = $this->getDb()
            ->execute(
                'select count(*) as total from ' . $table . (strpos(trim($cond), 'order') === 0 ? ' ' : ' where ') . $cond,
                $values
            );
        $total = $rs->total() > 1 ? $rs->total() : $rs->value('total');
        $data['items'] = $this->getDb()
            ->execute(
                'select ' . $fields . ' from ' . $table . (strpos(
                    trim($cond),
                    'order'
                ) === 0 ? ' ' : ' where ') . $cond . ' limit ' . (($page - 1) * $pagesize) . ',' . $pagesize,
                $values
            )
            ->rows();
        $data['page'] = Z::page($total, $page, $pagesize, $url, $pageBarACount);
        return $data;
    }
}
abstract class Zls_Database
{
    private $driverType;
    private $database;
    private $tablePrefix;
    private $pconnect;
    private $debug;
    private $timeout;
    private $trace;
    private $charset;
    private $collate;
    private $tablePrefixSqlIdentifier;
    private $slowQueryTime;
    private $slowQueryHandle;
    private $slowQueryDebug;
    private $minIndexType;
    private $indexDebug;
    private $indexHandle;
    private $masters;
    private $slaves;
    private $resetSql;
    private $attribute;
    private $connectionMasters;
    private $connectionSlaves;
    private $versionThan56 = false;
    private $_errorMsg;
    private $_lastSql;
    private $_lastPdoInstance;
    private $_isInTransaction = false;
    private $_config;
    private $_lastInsertId = 0;
    private $_traceRes = [];
    private $_cacheTime = null;
    private $_cacheKey;
    private $_masterPdo = null;
    private $_locked = false;
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }
    public function getDefaultConfig()
    {
        return [
            'debug'                    => true,
            'driverType'               => 'mysql',
            'production'               => true,
            'trace'                    => false,
            'timeout'                  => 5,
            'pconnect'                 => false,
            'charset'                  => 'utf8',
            'collate'                  => 'utf8_general_ci',
            'database'                 => '',
            'tablePrefix'              => '',
            'tablePrefixSqlIdentifier' => '_prefix_',
            'slowQueryDebug'           => false,
            'slowQueryTime'            => 3000,
            'slowQueryHandle'          => null,
            'indexDebug'               => false,
            /**
             * 索引使用的最小情况，只有小于最小情况的时候才会记录sql到日志
             * minIndexType值从好到坏依次是:
             * system > const > eq_ref > ref > fulltext > ref_or_null
             * > index_merge > unique_subquery > index_subquery > range
             * > index > ALL一般来说，得保证查询至少达到range级别，最好能达到ref
             */
            'minIndexType'             => 'ALL',
            'indexHandle'              => null,
            'attribute'                => [],
            'masters'                  => [
                'master01' => [
                    'hostname' => '127.0.0.1',
                    'port'     => 3306,
                    'username' => 'root',
                    'password' => '',
                ],
            ],
            'slaves'                   => [],
        ];
    }
    public function &getLastPdoInstance()
    {
        return $this->_lastPdoInstance;
    }
    /**
     * 锁定数据库连接，后面的读写都使用同一个主数据库连接
     */
    public function lock()
    {
        $this->_locked = true;
        return $this;
    }
    /**
     * 解锁数据库连接，后面的读写使用不同的数据库连接
     */
    public function unlock()
    {
        $this->_locked = false;
        return $this;
    }
    public function lastId()
    {
        if ($this->_isSqlite()) {
            return $this->_lastInsertBatchCount > 1 ? ($this->_lastInsertId - $this->_lastInsertBatchCount + 1) : $this->_lastInsertId;
        } else {
            return $this->_lastInsertId;
        }
    }
    public function _isSqlite()
    {
        return $this->_driverTypeIsString() && strtolower($this->getDriverType()) == 'sqlite';
    }
    public function _driverTypeIsString()
    {
        return gettype($this->getDriverType()) == 'string';
    }
    public function getDriverType()
    {
        return $this->driverType;
    }
    public function setDriverType($driverType)
    {
        $this->driverType = $driverType;
        return $this;
    }
    public function resetSql()
    {
        return $this->resetSql;
    }
    public function error()
    {
        return $this->_errorMsg;
    }
    public function close()
    {
        $this->_masterPdo = null;
        $this->_lastPdoInstance = null;
        $this->connectionMasters = [];
        $this->connectionSlaves = [];
        return $this;
    }
    public function lastSql()
    {
        return $this->_lastSql;
    }
    public function getSlowQueryDebug()
    {
        return $this->slowQueryDebug;
    }
    public function setSlowQueryDebug($slowQueryDebug)
    {
        $this->slowQueryDebug = $slowQueryDebug;
        return $this;
    }
    public function getIndexDebug()
    {
        return $this->indexDebug;
    }
    public function setIndexDebug($indexDebug)
    {
        $this->indexDebug = $indexDebug;
        return $this;
    }
    public function &getSlowQueryHandle()
    {
        return $this->slowQueryHandle;
    }
    public function setSlowQueryHandle(Zls_Database_SlowQuery_Handle $slowQueryHandle)
    {
        $this->slowQueryHandle = $slowQueryHandle;
        return $this;
    }
    public function &getIndexHandle()
    {
        return $this->indexHandle;
    }
    public function setIndexHandle(Zls_Database_Index_Handle $indexHandle)
    {
        $this->indexHandle = $indexHandle;
        return $this;
    }
    public function getConfig()
    {
        return $this->_config;
    }
    public function setConfig(array $config = [])
    {
        foreach (($this->_config = array_merge($this->getDefaultConfig(), $config)) as $key => $value) {
            $this->{$key} = $value;
        }
        $this->connectionMasters = [];
        $this->connectionSlaves = [];
        $this->_errorMsg = '';
        $this->_lastSql = '';
        $this->_isInTransaction = false;
        $this->_lastInsertId = 0;
        $this->_lastPdoInstance = null;
        $this->_cacheKey = '';
        $this->_cacheTime = null;
        $this->_masterPdo = '';
        $this->_locked = false;
    }
    public function getMasters()
    {
        return $this->masters;
    }
    public function setMasters($masters)
    {
        $this->masters = $masters;
        return $this;
    }
    public function getMaster($key)
    {
        return $this->masters[$key];
    }
    public function getSlaves()
    {
        return $this->slaves;
    }
    public function setSlaves($slaves)
    {
        $this->slaves = $slaves;
        return $this;
    }
    public function getSlave($key)
    {
        return $this->slaves[$key];
    }
    /**
     * @return bool
     * @throws \Zls_Exception_Database
     */
    public function begin()
    {
        if (!$this->_init()) {
            return false;
        }
        $this->_masterPdo->beginTransaction();
        $this->_isInTransaction = true;
        return true;
    }
    /**
     * @return bool
     * @throws Zls_Exception_Database
     */
    private function _init()
    {
        $info = [
            'master' => [
                'getMasters',
                'connectionMasters',
            ],
            'slave'  => [
                'getSlaves',
                'connectionSlaves',
            ],
        ];
        try {
            foreach ($info as $type => $group) {
                $configGroup = $this->{$group[0]}();
                $connections = &$this->{$group[1]};
                foreach ($configGroup as $key => $config) {
                    if (!Z::arrayKeyExists($key, $connections)) {
                        try {
                            if ($this->_driverTypeIsString()) {
                                $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
                                $options[PDO::ATTR_PERSISTENT] = $this->getPconnect();
                                $options[PDO::ATTR_STRINGIFY_FETCHES] = false;
                                $options[PDO::ATTR_EMULATE_PREPARES] = false;
                                $options[PDO::ATTR_ORACLE_NULLS] = PDO::NULL_TO_STRING;
                                if ($this->_isMysql()) {
                                    $options[PDO::ATTR_TIMEOUT] = $this->getTimeout();
                                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $this->getCharset() . ' COLLATE ' . $this->getCollate();
                                    $dsn = 'mysql:host=' . $config['hostname'] . ';port=' . $config['port'] . ';dbname=' . $this->getDatabase() . ';charset=' . $this->getCharset();
                                    $connections[$key] = new \Zls_PDO($dsn, $config['username'], $config['password'], $options);
                                    $connections[$key]->exec('SET NAMES ' . $this->getCharset());
                                } elseif ($this->_isSqlsrv()) {
                                    $dsn = 'sqlsrv:Server=' . $config['hostname'] . ',' . $config['port'] . ';Database=' . $this->getDatabase() . ';';
                                    unset($options[PDO::ATTR_PERSISTENT], $options[PDO::ATTR_EMULATE_PREPARES]);
                                    $options = $options + [PDO::SQLSRV_ATTR_QUERY_TIMEOUT => $this->getTimeout(), PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true];
                                    $connections[$key] = new \Zls_PDO($dsn, $config['username'], $config['password'], $options);
                                    $connections[$key]->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
                                } elseif ($this->_isSqlite()) {
                                    Z::throwIf(!file_exists($this->getDatabase()), 'Database', 'sqlite3 database file [' . Z::realPath($this->getDatabase()) . '] not found', 'ERROR');
                                    $connections[$key] = new \Zls_PDO('sqlite:' . $this->getDatabase(), null, null, $options);
                                } else {
                                    Z::throwIf(true, 'Database', 'unknown driverType [ ' . $this->getDriverType() . ' ]', 'ERROR');
                                }
                            } else {
                                $db = $this->getDriverType();
                                $connections[$key] = ($db instanceof Closure) ? $db() : $db;
                            }
                            $getAttribute = $this->getAttribute();
                            if (!empty($getAttribute) && is_array($getAttribute)) {
                                foreach ($getAttribute as $k => $v) {
                                    $connections[$key]->setAttribute($k, $v);
                                }
                            }
                        } catch (\PDOException $e) {
                            $err = Z::toUtf8($e->getMessage());
                            throw new \Zls_Exception_Database($err);
                        }
                    }
                }
            }
            if (empty($this->connectionSlaves) && !empty($this->connectionMasters)) {
                $this->connectionSlaves[0] = $this->connectionMasters[array_rand($this->connectionMasters)];
            }
            if (empty($this->_masterPdo) && !empty($this->connectionMasters)) {
                $this->_masterPdo = $this->connectionMasters[array_rand($this->connectionMasters)];
            }
            return !(empty($this->connectionMasters) && empty($this->connectionSlaves));
        } catch (\Exception $e) {
            $this->_displayError($e);
        }
        return false;
    }
    public function getPconnect()
    {
        return $this->pconnect;
    }
    public function setPconnect($pconnect)
    {
        $this->pconnect = $pconnect;
        return $this;
    }
    public function _isMysql()
    {
        return $this->_driverTypeIsString() && strtolower($this->getDriverType()) == 'mysql';
    }
    /**
     * @return mixed
     */
    public function getTimeout()
    {
        return $this->timeout;
    }
    /**
     * @param mixed $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }
    /**
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }
    public function setCharset($charset)
    {
        $this->charset = $charset;
        return $this;
    }
    /**
     * @return string
     */
    public function getCollate()
    {
        return $this->collate;
    }
    public function setCollate($collate)
    {
        $this->collate = $collate;
        return $this;
    }
    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
    public function _isSqlsrv()
    {
        return $this->_driverTypeIsString() && strtolower($this->getDriverType()) == 'sqlsrv';
    }
    public function getAttribute()
    {
        return $this->attribute;
    }
    /**
     * @param     $message
     * @param int $code
     * @throws Zls_Exception_Database
     */
    protected function _displayError($message, $code = 0)
    {
        $sql = $this->_lastSql ? ' , ' . "\n" . 'with query : ' . $this->_lastSql : '';
        $group = "Database Group : [ " . $this->group . " ] , error : ";
        if ($message instanceof Exception) {
            $this->_errorMsg = $message->getMessage() . $sql;
        } else {
            $this->_errorMsg = $message . $sql;
        }
        if ($this->getDebug() || $this->_isInTransaction) {
            if ($message instanceof Exception) {
                throw new \Zls_Exception_Database(
                    $group . $this->_errorMsg,
                    500,
                    'Zls_Exception_Database',
                    $message->getFile(),
                    $message->getLine()
                );
            } else {
                throw new \Zls_Exception_Database($group . $message . $sql, $code);
            }
        }
    }
    public function getDebug()
    {
        return $this->debug;
    }
    public function setDebug($debug)
    {
        $this->debug = $debug;
        return $this;
    }
    /**
     * @return Zls_PDO
     * @throws Zls_Exception_Database
     */
    public function pod()
    {
        if (!$this->_masterPdo) {
            $this->_init();
        }
        return $this->_masterPdo;
    }
    public function commit()
    {
        if (!$this->_init()) {
            return false;
        }
        $this->_masterPdo->commit();
        $this->_isInTransaction = $this->_masterPdo->isInTransaction();
    }
    public function rollback()
    {
        if (!$this->_init()) {
            return false;
        }
        $this->_masterPdo->rollback();
    }
    public function cache($cacheTime, $cacheKey = '')
    {
        $this->_cacheTime = (int)$cacheTime;
        $this->_cacheKey = $cacheKey;
        return $this;
    }
    /**
     * @return string
     */
    public function reset()
    {
        return Z::tap($this->arFrom ? vsprintf(str_replace('?', '%s', $this->getSql()), z::arrayMap($this->getSqlValues(), function ($e) {
            return is_string($e) ? "'{$e}'" : $e;
        })) : '', function () {
            $this->_cacheKey = '';
            $this->_cacheTime = null;
            $this->_reset();
        });
    }
    abstract public function getSql();
    public function getSqlValues()
    {
        return $this->_getValues();
    }
    abstract protected function _getValues();
    /**
     * 执行一个sql语句，写入型的返回bool或者影响的行数（insert,delete,replace,update），搜索型的返回结果集
     * @param string $sql    sql语句
     * @param array  $values 参数
     */
    public function execute($sql = '', array $values = [])
    {
        $trace = [];
        if ($this->slowQueryDebug || $this->indexDebug) {
            $trace = Z::tap(debug_backtrace(), function (&$trace) {
                $_trace = (Z::arrayGet($trace, '1.class') == 'Zls_Dao') ? $trace[1] : $trace[0];
                $trace = [
                    'file'     => $_trace['file'],
                    'line'     => $_trace['line'],
                    'class'    => $_trace['class'],
                    'function' => $_trace['function'],
                ];
            });
        }
        if (!$this->_init()) {
            return false;
        }
        $startTime = Z::microtime();
        $sql = $sql ? $this->_checkPrefixIdentifier($sql) : $this->getSql();
        $this->_lastSql = $sql;
        $values = !empty($values) ? $values : $this->_getValues();
        $cacheHandle = null;
        if (is_numeric($this->_cacheTime)) {
            $cacheHandle = Z::config()->getCacheHandle();
            Z::throwIf(empty($cacheHandle), 500, 'no cache handle found , please set cache handle', 'ERROR');
            $key = empty($this->_cacheKey) ? md5($sql . var_export($values, true)) : $this->_cacheKey;
            if ($this->_cacheTime > 0) {
                $return = $cacheHandle->get($key);
                if (!is_null($return)) {
                    $this->_cacheKey = '';
                    $this->_cacheTime = null;
                    $this->_reset();
                    return $return;
                }
            } else {
                $cacheHandle->delete($key);
            }
        }
        $isWriteType = $this->_isWriteType($sql);
        $isWritetRowsType = $this->_isWriteRowsType($sql);
        $isWriteInsertType = $this->_isWriteInsertType($sql);
        $return = false;
        try {
            if ($this->_isInTransaction) {
                $pdo = &$this->_masterPdo;
                $this->_lastPdoInstance = &$pdo;
                if ($sth = $pdo->prepare($sql)) {
                    if ($isWriteType) {
                        $status = $sth->execute($values);
                        $return = $isWritetRowsType ? $sth->rowCount() : $status;
                        $this->_lastInsertId = $isWriteInsertType ? $pdo->lastInsertId() : 0;
                    } else {
                        $return = $sth->execute($values) ? $sth->fetchAll(PDO::FETCH_ASSOC) : [];
                        $return = new \Zls_Database_Resultset($return);
                    }
                } else {
                    $errorInfo = $pdo->errorInfo();
                    $this->_displayError($errorInfo[2], $errorInfo[1]);
                }
            } else {
                if ($this->isLocked()) {
                    $pdo = &$this->_masterPdo;
                } else {
                    if ($isWriteType) {
                        $pdo = &$this->connectionMasters[array_rand($this->connectionMasters)];
                    } else {
                        $pdo = &$this->connectionSlaves[array_rand($this->connectionSlaves)];
                    }
                }
                $this->_lastPdoInstance = &$pdo;
                if ($sth = $pdo->prepare($sql)) {
                    if ($isWriteType) {
                        $status = $sth->execute($values);
                        $return = $isWritetRowsType ? $sth->rowCount() : $status;
                        $this->_lastInsertId = $isWriteInsertType ? $pdo->lastInsertId() : 0;
                    } else {
                        $return = $sth->execute($values) ? $sth->fetchAll(PDO::FETCH_ASSOC) : [];
                        $return = new \Zls_Database_Resultset($return);
                    }
                } else {
                    $errorInfo = $pdo->errorInfo();
                    $this->_displayError($errorInfo[2], $errorInfo[1]);
                }
            }
            $usingTime = (Z::microtime() - $startTime) . '';
            $explainRows = [];
            if ($this->_isMysql() && ($this->slowQueryDebug || $this->indexDebug) && (($this->_isExplain56Type($sql) && $this->versionThan56) || ($this->_isExplainType($sql) && !$this->versionThan56))) {
                reset($this->connectionMasters);
                $sth = $this->connectionMasters[key($this->connectionMasters)]->prepare('EXPLAIN ' . $sql);
                $sth->execute($values);
                $explainRows = $sth->fetchAll(PDO::FETCH_ASSOC);
            }
            if ($this->slowQueryDebug && ($usingTime >= $this->getSlowQueryTime())) {
                if ($this->slowQueryHandle instanceof Zls_Database_SlowQuery_Handle) {
                    $this->slowQueryHandle->handle($sql, var_export($values, true), var_export($explainRows, true), $usingTime, $trace);
                }
            }
            if ($this->indexDebug && $this->indexHandle instanceof Zls_Database_Index_Handle) {
                $badIndex = false;
                if ($this->_isMysql()) {
                    $order = [
                        'system'          => 1,
                        'const'           => 2,
                        'eq_ref'          => 3,
                        'ref'             => 4,
                        'fulltext'        => 5,
                        'ref_or_null'     => 6,
                        'index_merge'     => 7,
                        'unique_subquery' => 8,
                        'index_subquery'  => 9,
                        'range'           => 10,
                        'index'           => 11,
                        'all'             => 12,
                    ];
                    foreach ($explainRows as $row) {
                        if (Z::arrayKeyExists(
                                strtolower($row['type']),
                                $order
                            ) && Z::arrayKeyExists(strtolower($this->getMinIndexType()), $order)) {
                            $key = $order[strtolower($row['type'])];
                            $minKey = $order[strtolower($this->getMinIndexType())];
                            if ($key > $minKey) {
                                if (stripos($row['Extra'], 'optimized') === false) {
                                    $badIndex = true;
                                    break;
                                }
                            }
                        }
                    }
                } elseif (strtolower($this->getDriverType()) == 'sqlite') {
                }
                if ($badIndex) {
                    $this->indexHandle->handle($sql, var_export($values, true), var_export($explainRows, true), $usingTime, $trace);
                }
            }
        } catch (\Exception $exc) {
            $this->_reset();
            $this->_displayError($exc);
        }
        if (!is_null($this->_cacheTime) && !!$return->row()) {
            $key = empty($this->_cacheKey) ? md5($sql . var_export($values, true)) : $this->_cacheKey;
            if ($this->_cacheTime > 0) {
                $cacheHandle->set($key, $return, $this->_cacheTime);
            } else {
                $cacheHandle->delete($key);
            }
        }
        $this->_cacheKey = '';
        $this->_cacheTime = null;
        $this->_reset();
        if ($this->_isMysql() && true == Z::config()->getTraceStatus()) {
            if (preg_match('/SELECT /ims', $sql)) {
                try {
                    $trace['runtime'] = (Z::microtime() - $startTime) . 'ms';
                    $trace['time'] = date('Y-m-d H:i:s');
                    $sth = $pdo->prepare("EXPLAIN " . $sql);
                    $sql = str_replace("\n", ' ', $sql);
                    $arr = $sth->execute($values) ? $sth->fetch(PDO::FETCH_ASSOC) : [];
                    $this->_traceRes = $trace + $arr + ['Values' => implode(',', $values), 'SQL' => $sql];
                    if (true == $this->getTrace()) {
                        $this->trace();
                    }
                } catch (\Exception $e) {
                }
            }
        }
        return $return;
    }
    private function _checkPrefixIdentifier($str)
    {
        $prefix = $this->getTablePrefix();
        $identifier = $this->getTablePrefixSqlIdentifier();
        return $identifier ? str_replace($identifier, $prefix, $str) : $str;
    }
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }
    public function setTablePrefix($tablePrefix)
    {
        $this->tablePrefix = $tablePrefix;
        return $this;
    }
    public function getTablePrefixSqlIdentifier()
    {
        return $this->tablePrefixSqlIdentifier;
    }
    public function setTablePrefixSqlIdentifier($tablePrefixSqlIdentifier)
    {
        $this->tablePrefixSqlIdentifier = $tablePrefixSqlIdentifier;
        return $this;
    }
    private function _isWriteType($sql)
    {
        if (!preg_match(
            '/^\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD DATA|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\s+/i',
            $sql
        )) {
            return false;
        }
        return true;
    }
    private function _isWriteRowsType($sql)
    {
        if (!preg_match('/^\s*"?(INSERT|UPDATE|DELETE|REPLACE)\s+/i', $sql)) {
            return false;
        }
        return true;
    }
    private function _isWriteInsertType($sql)
    {
        if (!preg_match('/^\s*"?(INSERT|REPLACE)\s+/i', $sql)) {
            return false;
        }
        return true;
    }
    /**
     * 数据库连接是否处于锁定状态
     * @return bool
     */
    public function isLocked()
    {
        return $this->_locked;
    }
    private function _isExplain56Type($sql)
    {
        if (!preg_match('/^\s*"?(SELECT|INSERT|UPDATE|DELETE|REPLACE)\s+/i', $sql)) {
            return false;
        }
        return true;
    }
    private function _isExplainType($sql)
    {
        if (!preg_match('/^\s*"?(SELECT)\s+/i', $sql)) {
            return false;
        }
        return true;
    }
    public function getSlowQueryTime()
    {
        return $this->slowQueryTime;
    }
    public function setSlowQueryTime($slowQueryTime)
    {
        $this->slowQueryTime = $slowQueryTime;
        return $this;
    }
    public function getMinIndexType()
    {
        return $this->minIndexType;
    }
    public function setMinIndexType($minIndexType)
    {
        $this->minIndexType = $minIndexType;
        return $this;
    }
    public function getTrace()
    {
        return $this->trace;
    }
    public function setTrace($trace)
    {
        $this->trace = $trace;
        return $this;
    }
    public function trace()
    {
        if (!!$this->_traceRes) {
            Z::log(null, false)->mysql($this->_traceRes, $this->getDriverType());
        }
    }
}
/**
 * Class Zls_Controller
 * @method before($method, $controllerShort, $controller, $args) before($method, $controllerShort, $controller, $args)
 * @method after($method, $args, $contents) after($method, $args, $contents)
 */
abstract class Zls_Controller
{
}
abstract class Zls_Model
{
}
abstract class Zls_Business
{
}
abstract class Zls_Task
{
    protected $debug = false;
    protected $debugError = false;
    /**
     * Zls_Task constructor.
     */
    public function __construct()
    {
        Z::throwIf(!Z::isCli(), 500, 'Task only in cli mode', 'ERROR');
    }
    public function _execute(\Zls_CliArgs $args)
    {
        $this->debug = $args->get('debug');
        $this->debugError = $args->get('debug-error');
        $startTime = Z::microtime();
        $class = get_class($this);
        if ($this->debugError) {
            $_startTime = date('Y-m-d H:i:s.') . substr($startTime . '', strlen($startTime . '') - 3);
            $error = $this->execute($args);
            if ($error) {
                $this->_log('Task [ ' . $class . ' ] execute failed , started at [ ' . $_startTime . ' ], use time ' . (Z::microtime() - $startTime) . ' ms , exited with error : [ ' . $error . ' ]');
                $this->_log('', false);
            }
        } else {
            $this->_log('Task [ ' . $class . ' ] start');
            $this->execute($args);
            $this->_log('Task [ ' . $class . ' ] end , use time ' . (Z::microtime() - $startTime) . ' ms');
            $this->_log('', false);
        }
    }
    abstract public function execute(\Zls_CliArgs $args);
    public function _log($msg, $time = true)
    {
        if ($this->debug || $this->debugError) {
            $nowTime = '' . Z::microtime();
            echo ($time ? date('[Y-m-d H:i:s.' . substr(
                            $nowTime,
                            strlen($nowTime) - 3
                        ) . ']') . ' [PID:' . sprintf(
                        '%- 5d',
                        getmypid()
                    ) . '] ' : '') . $msg . "\n";
        }
    }
    /**
     * @param $pid
     * @return bool|false|int
     */
    final public function pidIsExists($pid)
    {
        if (PATH_SEPARATOR == ':') {
            return trim(Z::command("ps ax | awk '{ print $1 }' | grep -e \"^{$pid}$\""), "\n") == $pid;
        } else {
            return preg_match("/\t?\s?$pid\t?\s?/", Z::command('tasklist /NH /FI "PID eq ' . $pid . '"'));
        }
    }
}
class Zls_Router_PathInfo extends Zls_Router
{
    private $isPathinfo;
    public function __construct($isPathinfo = true)
    {
        parent::__construct();
        $this->isPathinfo = $isPathinfo;
    }
    /**
     * @param null $uri
     * @return \Zls_Route
     */
    public function find($uri = null)
    {
        $config = Zls::getConfig();
        if (is_null($uri)) {
            $uri = $config->getRequest()->getPathInfo();
        }
        $uri = trim($uri, '/') ?: z::get('s');
        $_hmvcModule = $config->getCurrentDomainHmvcModuleNname();
        if (empty($uri) && empty($_hmvcModule)) {
            return $this->route->setFound(false);
        } else {
            if ($uriRewriter = $config->getUriRewriter()) {
                $uri = $uriRewriter->rewrite($uri);
            }
        }
        $_info = explode('/', $uri);
        $hmvcModule = current($_info);
        if (!$_hmvcModule) {
            if ($config->hmvcIsDomainOnly($hmvcModule)) {
                $hmvcModule = '';
            }
        } else {
            $hmvcModule = $_hmvcModule;
        }
        $hmvcModuleDirName = \Zls::checkHmvc($hmvcModule, false);
        if (!$_hmvcModule && $hmvcModuleDirName && !$config->hmvcIsDomainOnly($hmvcModule)) {
            $uri = ltrim(substr($uri, strlen($hmvcModule)), '/');
        }
        $controller = $config->getDefaultController();
        $method = $config->getDefaultMethod();
        $subfix = $config->getMethodUriSubfix();
        if ($uri) {
            if ($subfix) {
                $methodPathArr = explode($subfix, $uri);
                if (Z::strEndsWith($uri, $subfix)) {
                    if (stripos($methodPathArr[0], '/') !== false) {
                        $controller = str_replace('/', '_', dirname($uri));
                        $method = basename($methodPathArr[0]);
                    } else {
                        $method = basename($methodPathArr[0]);
                    }
                } else {
                    $controller = str_replace('/', '_', $uri);
                }
            } else {
                $methodPathArr = explode('/', $uri);
                if (count($methodPathArr) > 1) {
                    $method = array_pop($methodPathArr);
                    $controller = implode('_', $methodPathArr);
                } else {
                    $controller = $uri;
                }
            }
        }
        $controller = $config->getControllerDirName() . '_' . $controller;
        $methodAndParameters = explode($config->getMethodParametersDelimiter(), $method);
        $method = $config->getMethodPrefix() . current($methodAndParameters);
        array_shift($methodAndParameters);
        $parameters = $methodAndParameters;
        $hmvcModule = $hmvcModuleDirName ? $hmvcModule : '';
        return $this->route->setHmvcModuleName($hmvcModule)->setController($controller)->setMethod($method)->setArgs($parameters)->setFound(true);
    }
    public function url($action = '', $getData = [], $opt = ['subfix' => true, 'ishmvc' => false])
    {
        $config = Z::config();
        $isPathinfo = $config->getRequest()->getPathInfo() !== null;
        $MethodUriSubfix = $config->getMethodUriSubfix();
        $SubfixStatus = $isPathinfo ? Z::arrayGet($opt, 'subfix', false) : false;
        $isHmvc = Z::arrayGet($opt, 'ishmvc', false);
        if ($SubfixStatus === true && !Z::strEndsWith($action, $MethodUriSubfix)) {
            $action = $action . $MethodUriSubfix;
        }
        if ($isHmvc === true) {
            $hmvcModules = $config->getHmvcModules();
            $hmvcDirName = !!Z::arrayGet(
                $hmvcModules,
                $config->getRoute()->getHmvcModuleName(),
                null
            ) ? $config->getRoute()->getHmvcModuleName() : '';
            $action = $hmvcDirName . '/' . $action;
        }
        $hmvcModuleName = $config->getCurrentDomainHmvcModuleNname();
        if ($hmvcModuleName && $config->hmvcIsDomainOnly($hmvcModuleName)) {
            $action = preg_replace('|^' . $hmvcModuleName . '/?|', '/', $action);
        }
        $root = Z::strBeginsWith($action, '/');
        $index = $config->getIsRewrite() ? '' : ($root ? '/' . ZLS_INDEX_NAME : ZLS_INDEX_NAME . '/');
        if ($isPathinfo) {
            $url = $index . $action;
        } else {
            $url = $root ? $index . '?s=' . ltrim($action, '/') : '/' . $index . '?s=' . $action;
        }
        $url = rtrim($url, '/');
        $url = $index ? $url : ($action ? $url : $url . '/');
        if (!empty($getData)) {
            $url = $url . ($isPathinfo ? '?' : '&');
            foreach ($getData as $k => $v) {
                $url .= $k . '=' . urlencode($v) . '&';
            }
            $url = rtrim($url, '&');
        }
        if ($isPathinfo && $requestUri = Z::server('REQUEST_URI')) {
            $requestUri = Z::tap(explode($config->getRequest()->getPathInfo() ?: '/', $requestUri), function ($v) {
                return Z::arrayGet($v, 0);
            });
            $url = Z::strBeginsWith($url, $requestUri) ? $url : $requestUri . $url;
        }
        return $url;
    }
}
abstract class Zls_Task_Single extends Zls_Task
{
    /**
     * @param Zls_CliArgs $args
     */
    public function _execute(\Zls_CliArgs $args)
    {
        $this->debug = $args->get('debug');
        $class = get_class($this);
        $startTime = Z::microtime();
        $this->_log('Single Task [ ' . $class . ' ] start');
        $lockFilePath = $args->get('pid');
        if (!$lockFilePath) {
            $tempDirPath = Z::config()->getStorageDirPath();
            $key = md5(
                Z::config()->getApplicationDir() .
                Z::config()->getClassesDirName() . '/'
                . Z::config()->getTaskDirName() . '/'
                . str_replace('_', '/', get_class($this)) . '.php'
            );
            $lockFilePath = Z::realPathMkdir($tempDirPath . 'taskSingle') . '/' . $key . '.pid';
        }
        if (file_exists($lockFilePath)) {
            $pid = file_get_contents($lockFilePath);
            if ($this->pidIsExists($pid)) {
                $this->_log('Single Task [ ' . $class . ' ] is running with pid ' . $pid . ' , now exiting...');
                $this->_log('Single Task [ ' . $class . ' ] end , use time ' . (Z::microtime() - $startTime) . ' ms');
                $this->_log('', false);
                return;
            }
        }
        Z::throwIf(file_put_contents($lockFilePath, getmypid()) === false, 500, 'can not create file : [ ' . $lockFilePath . ' ]', 'ERROR');
        $this->_log('update pid file [ ' . $lockFilePath . ' ]');
        $this->execute($args);
        @unlink($lockFilePath);
        $this->_log('clean pid file [ ' . $lockFilePath . ' ]');
        $this->_log('Single Task [ ' . $class . ' ] end , use time ' . (Z::microtime() - $startTime) . ' ms');
        $this->_log('', false);
    }
}
abstract class Zls_Task_Multiple extends Zls_Task
{
    public function _execute(\Zls_CliArgs $args)
    {
        $this->debug = $args->get('debug');
        $class = get_class($this);
        $startTime = Z::microtime();
        $this->_log('Multiple Task [ ' . $class . ' ] start');
        $lockFilePath = $args->get('pid');
        if (!$lockFilePath) {
            $tempDirPath = Z::config()->getStorageDirPath();
            $key = md5(Z::config()->getApplicationDir() .
                Z::config()->getClassesDirName() . '/'
                . Z::config()->getTaskDirName() . '/'
                . str_replace('_', '/', get_class($this)) . '.php');
            $lockFilePath = Z::realPathMkdir($tempDirPath . 'taskMultiple') . '/' . $key . '.pid';
        }
        $alivedPids = [];
        if (file_exists($lockFilePath)) {
            $count = 0;
            $pids = explode("\n", file_get_contents($lockFilePath));
            foreach ($pids as $pid) {
                if ($pid = (int)$pid) {
                    if ($this->pidIsExists($pid)) {
                        $alivedPids[] = $pid;
                        if (++$count > $this->getMaxCount() - 1) {
                            $this->_log('Multiple Task [ ' . $class . ' ] reach max count : ' . $this->getMaxCount() . ' , now exiting...');
                            $this->_log('Multiple Task [ ' . $class . ' ] end , use time ' . (Z::microtime() - $startTime) . ' ms');
                            $this->_log('', false);
                            return;
                        }
                    }
                }
            }
        }
        $alivedPids[] = getmypid();
        Z::throwIf(file_put_contents($lockFilePath, implode("\n", $alivedPids)) === false, 500, 'can not create file : [ ' . $lockFilePath . ' ]', 'ERROR');
        $this->_log('update pid file [ ' . $lockFilePath . ' ]');
        $this->execute($args);
        $this->_log('clean pid file [ ' . $lockFilePath . ' ]');
        $this->_log('Multiple Task [ ' . $class . ' ] end , use time ' . (Z::microtime() - $startTime) . ' ms');
        $this->_log('', false);
    }
    abstract protected function getMaxCount();
}
/**
 * @property Zls_Route $route
 */
abstract class Zls_Router
{
    protected $route;
    public function __construct()
    {
        $this->route = new \Zls_Route();
    }
    public function getType()
    {
        return get_called_class();
    }
    /**
     * @return \Zls_Route
     */
    abstract public function find();
    abstract public function url();
    public function &route()
    {
        return $this->route;
    }
}
abstract class Zls_Exception extends \Exception
{
    protected $errorMessage;
    protected $errorCode;
    protected $errorFile;
    protected $errorLine;
    protected $errorType;
    protected $trace;
    protected $httpStatusLine = 'HTTP/1.0 500 Internal Server Error';
    protected $exceptionName = 'Zls_Exception';
    public function __construct(
        $errorMessage = '',
        $errorCode = 0,
        $errorType = 'Exception',
        $errorFile = '',
        $errorLine = '0'
    ) {
        parent::__construct($errorMessage, $errorCode);
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->errorType = $errorType;
        $this->errorFile = Z::realPath($errorFile);
        $this->errorLine = $errorLine;
        $this->trace = debug_backtrace(false);
        if (in_array($errorCode, [500, 404])) {
            z::header($errorCode === 404 ? 'HTTP/1.1 404 Not Found' : 'HTTP/1.1 500 Internal Server Error');
        }
    }
    public function getErrorCode()
    {
        return $this->errorCode ? $this->errorCode : $this->getCode();
    }
    public function getTraceHtmlString()
    {
        return $this->getTraceString(false);
    }
    /**
     * @param $isCli
     * @return string
     */
    private function getTraceString($isCli)
    {
        $trace = array_reverse($this->trace);
        $str = $isCli ? "[ Debug Backtrace ]\n" : '<div style="padding:10px">[ Debug Backtrace ]<br/>';
        if (empty($trace)) {
            return '';
        }
        $i = 1;
        foreach ($trace as $e) {
            $file = Z::safePath(Z::arrayGet($e, 'file'));
            $line = Z::arrayGet($e, 'line');
            $func = (!empty($e['class']) ? "{$e['class']}{$e['type']}{$e['function']}()" : "{$e['function']}()");
            $str .= "" . ($i++) . ".{$func} " . ($line ? "[ line:{$line} {$file} ]" : '') . ($isCli ? "\n" : '<br/>');
        }
        $str .= $isCli ? "\n" : '</div>';
        return $str;
    }
    public function setHttpHeader()
    {
        if (!Z::isCli()) {
            Z::header($this->httpStatusLine);
        }
        return $this;
    }
    /**
     * @return mixed|string
     * @throws Exception
     */
    public function __toString()
    {
        return $this->render(false, true);
    }
    /**
     * 输出异常信息
     * @param bool $isJson
     * @param bool $return
     * @return mixed|string
     * @throws Exception
     */
    public function render($isJson = false, $return = false)
    {
        $isCli = Z::isCli() && !Z::isSwoole(true);
        $string = str_replace('</body>', $this->getTraceString($isCli) . '</body>', $this->renderHtml());
        if ($isJson) {
            $string = $this->renderJson();
        } elseif ($isCli) {
            $string = $this->renderCli();
        }
        if (!$return) {
            z::finish($string);
        }
        return !$return ? z::finish($string) : $string;
    }
    public function renderHtml()
    {
        $run = z::debug(false);
        return '<html><meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0"><body style="line-height:30px;padding:0;margin:0;background:#0C8611;color:whitesmoke;font-family:\'Courier New\',monospace;font-size:18px;">'
            . '<div style="padding:10px;background:#104411;color:#4CAF50;font-size:25px;font-weight:bold;">' . $this->exceptionName . ' - [ ' . $this->getErrorType() . ' ] </div>'
            . '<div style="padding:10px;color:yellow;">'
            . '<strong>Environment: </strong>
' . $this->getEnvironment() . '<br/>'
            . '<strong>Line: </strong>' . $this->getErrorLine() . ' [ ' . $this->getErrorFile(true) . ' ]<br/>'
            . '<strong>Message: </strong>' . htmlspecialchars($this->getErrorMessage()) . '</br>'
            . '<strong>Time: </strong>' . date('Y/m/d H:i:s T') . '</br>'
            . '<strong>WasteTime: </strong>' . $run['runtime'] . '</br>'
            . '<strong>Memory: </strong>' . $run['memory'] . '</div>'
            . '</body></html>';
    }
    public function getErrorType()
    {
        return $this->errorType2string($this->errorCode);
    }
    public function errorType2string($errorType)
    {
        $value = $errorType;
        $levelNames = [
            E_ERROR           => 'ERROR',
            E_WARNING         => 'WARNING',
            E_PARSE           => 'PARSE',
            E_NOTICE          => 'NOTICE',
            E_CORE_ERROR      => 'CORE_ERROR',
            E_CORE_WARNING    => 'CORE_WARNING',
            E_COMPILE_ERROR   => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR      => 'USER_ERROR',
            E_USER_WARNING    => 'USER_WARNING',
            E_USER_NOTICE     => 'USER_NOTICE',
        ];
        if (defined('E_STRICT')) {
            $levelNames[E_STRICT] = 'STRICT';
        }
        if (defined('E_DEPRECATED')) {
            $levelNames[E_DEPRECATED] = 'DEPRECATED';
        }
        if (defined('E_USER_DEPRECATED')) {
            $levelNames[E_USER_DEPRECATED] = 'USER_DEPRECATED';
        }
        if (defined('E_RECOVERABLE_ERROR')) {
            $levelNames[E_RECOVERABLE_ERROR] = 'RECOVERABLE_ERROR';
        }
        $levels = [];
        if (($value & E_ALL) == E_ALL) {
            $levels[] = 'E_ALL';
            $value &= ~E_ALL;
        }
        foreach ($levelNames as $level => $name) {
            if (($value & $level) == $level) {
                $levels[] = $name;
            }
        }
        if (empty($levelNames[$this->errorCode])) {
            return $this->errorType ? $this->errorType : 'General Error';
        }
        return implode(' | ', $levels);
    }
    /**
     * @return array|mixed|null|string
     */
    public function getEnvironment()
    {
        return Z::config()->getEnvironment();
    }
    public function getErrorLine()
    {
        return $this->errorLine ? $this->errorLine : ($this->errorFile ? $this->errorLine : $this->getLine());
    }
    /**
     * @param bool $safePath
     * @return string
     */
    public function getErrorFile($safePath = false)
    {
        $file = $this->errorFile ? $this->errorFile : $this->getFile();
        return $safePath ? Z::safePath($file) : $file;
    }
    public function getErrorMessage()
    {
        return $this->errorMessage ? $this->errorMessage : $this->getMessage();
    }
    public function renderJson()
    {
        $render = \Zls::getConfig()->getExceptionJsonRender();
        if (is_callable($render)) {
            return $render($this);
        }
        return '';
    }
    /**
     * @return string
     */
    public function renderCli()
    {
        $run = z::debug(false);
        return "$this->exceptionName [ " . $this->getErrorType() . ' ]' . PHP_EOL
            . 'Environment: ' . $this->getEnvironment() . PHP_EOL
            . 'Line: ' . $this->getErrorLine() . ". " . $this->getErrorFile() . PHP_EOL
            . 'Message: ' . $this->getErrorMessage() . PHP_EOL
            . 'Time: ' . date('Y/m/d H:i:s T') . PHP_EOL
            . 'WasteTime: ' . $run['runtime'] . PHP_EOL
            . 'Memory: ' . $run['memory'] . PHP_EOL
            . "Trace: " . $this->getTraceCliString() . PHP_EOL;
    }
    /**
     * @return string
     */
    public function getTraceCliString()
    {
        return $this->getTraceString(true);
    }
}
abstract class Zls_Session implements \SessionHandlerInterface
{
    protected $config;
    /**
     * Zls_Session constructor.
     * @param $configFileName
     */
    public function __construct($configFileName)
    {
        if (is_array($configFileName)) {
            $this->config = $configFileName;
        } else {
            $this->config = Z::config($configFileName);
        }
    }
    abstract public function init();
    abstract public function swooleInit($sessionId);
    abstract public function swooleWrite($sessionId, $sessionData);
    abstract public function swooleRead($sessionId);
    abstract public function swooleDestroy($sessionId);
    abstract public function swooleGc($maxlifetime);
    //
    //
    //
    //
    //
}
class Zls_Exception_404 extends Zls_Exception
{
    protected $exceptionName = 'Zls_Exception_404';
    protected $httpStatusLine = 'HTTP/1.0 404 Not Found';
    public function __construct(
        $errorMessage = '',
        $errorCode = 404,
        $errorType = 'Exception',
        $errorFile = '',
        $errorLine = '0'
    ) {
        parent::__construct($errorMessage, $errorCode, $errorType = 'Exception', $errorFile, $errorLine);
    }
}
class Zls_Exception_500 extends Zls_Exception
{
    protected $exceptionName = 'Zls_Exception_500';
    protected $httpStatusLine = 'HTTP/1.0 500 Internal Server Error';
    public function __construct(
        $errorMessage = '',
        $errorCode = 500,
        $errorType = 'Exception',
        $errorFile = '',
        $errorLine = '0'
    ) {
        parent::__construct($errorMessage, $errorCode, $errorType, $errorFile, $errorLine);
    }
}
class Zls_Exception_Database extends Zls_Exception
{
    protected $exceptionName = 'Zls_Exception_Database';
    protected $httpStatusLine = 'HTTP/1.0 500 Internal Server Error';
    public function __construct(
        $errorMessage = '',
        $errorCode = 500,
        $errorType = 'Exception',
        $errorFile = '',
        $errorLine = '0'
    ) {
        parent::__construct($errorMessage, $errorCode, $errorType, $errorFile, $errorLine);
    }
}
class Zls_Request_Default implements Zls_Request
{
    private $pathInfo;
    private $queryString;
    public function __construct()
    {
        if (!$this->pathInfo = Z::arrayGet($_SERVER, 'PATH_INFO', Z::arrayGet($_SERVER, 'REDIRECT_PATH_INFO'))) {
            if ($requestUri = Z::arrayGet($_SERVER, 'REQUEST_URI', '')) {
                $REQUEST_URI = Z::arrayGet($_SERVER, 'REQUEST_URI', '');
                if (Z::strBeginsWith($REQUEST_URI, '//')) {
                    $REQUEST_URI = ltrim($REQUEST_URI, '/');
                }
                $this->pathInfo = parse_url($REQUEST_URI, PHP_URL_PATH);
            }
        }
        if (in_array($this->pathInfo, ['/favicon.ico'])) {
            Z::finish();
        }
        $this->queryString = Z::arrayGet($_SERVER, 'QUERY_STRING', '');
    }
    public function getPathInfo()
    {
        return $this->pathInfo;
    }
    public function setPathInfo($pathInfo)
    {
        $this->pathInfo = $pathInfo;
        return $this;
    }
    public function getQueryString()
    {
        return $this->queryString;
    }
    public function setQueryString($queryString)
    {
        $this->queryString = $queryString;
        return $this;
    }
}
class Zls_View
{
    private static $vars = [];
    public function add($key, $value = [])
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if (!Z::arrayKeyExists($k, self::$vars)) {
                    self::$vars[$k] = $v;
                }
            }
        } else {
            if (!Z::arrayKeyExists($key, self::$vars)) {
                self::$vars[$key] = $value;
            }
        }
        return $this;
    }
    public function set($key, $value = [])
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::$vars[$k] = $v;
            }
        } else {
            self::$vars[$key] = $value;
        }
        return $this;
    }
    /**
     * 加载一个视图
     * @param string $viewName 视图名称
     * @param array  $data     视图中可以使用的数据
     * @param bool   $return   是否返回视图内容
     * @return string
     */
    public function load($viewName, $data = [], $return = false)
    {
        $config = Z::config();
        $path = $config->getApplicationDir() . $config->getViewsDirName() . '/' . $viewName . '.php';
        $hmvcModules = $config->getHmvcModules();
        $hmvcDirName = Z::arrayGet($hmvcModules, $config->getRoute()->getHmvcModuleName(), '');
        if ($hmvcDirName) {
            $hmvcPath = Z::realPath($config->getPrimaryApplicationDir() . $config->getHmvcDirName() . '/' . $hmvcDirName);
            $trace = debug_backtrace();
            $calledIsInHmvc = false;
            $appPath = Z::realPath($config->getApplicationDir());
            foreach ($trace as $t) {
                $filepath = Z::arrayGet($t, 'file', '');
                if (!empty($filepath)) {
                    $filepath = Z::realPath($filepath);
                    $checkList = ['load', 'runWeb', 'message', 'redirect'];
                    $function = Z::arrayGet($t, 'function', '');
                    if (($filepath && in_array($function, $checkList) && strpos(
                                $filepath,
                                $appPath
                            ) === 0 && strpos($filepath, $hmvcPath) === 0) || $function == 'handle') {
                        $calledIsInHmvc = true;
                        break;
                    } elseif (!in_array($function, $checkList)) {
                        break;
                    }
                }
            }
            if (!$calledIsInHmvc) {
                $path = $config->getPrimaryApplicationDir() . $config->getViewsDirName() . '/' . $viewName . '.php';
            }
        }
        return $this->loadRaw($path, $data, $return);
    }
    /**
     * @param       $path
     * @param array $data
     * @param bool  $return
     * @return string
     */
    public function loadRaw($path, $data = [], $return = false)
    {
        Z::throwIf(!file_exists($path), 500, 'view file : [ ' . $path . ' ] not found', 'ERROR');
        $data = array_merge(self::$vars, $data);
        if (!empty($data)) {
            extract($data);
        }
        if ($return) {
            @ob_start();
            include $path;
            $html = @ob_get_clean();
            return $html;
        } else {
            include $path;
            return '';
        }
    }
    /**
     * 加载主项目的视图
     * @param string $viewName 主项目视图名称
     * @param array  $data     视图中可以使用的数据
     * @param bool   $return   是否返回视图内容
     * @return string
     */
    public function loadParent($viewName, $data = [], $return = false)
    {
        $config = Z::config();
        $path = $config->getPrimaryApplicationDir() . $config->getViewsDirName() . '/' . $viewName . '.php';
        return $this->loadRaw($path, $data, $return);
    }
}
class Zls_CliArgs
{
    private $args;
    public function __construct()
    {
        $args = Z::getOpt();
        $this->args = empty($args) ? [] : $args;
    }
    public function get($key = null, $default = null)
    {
        if (empty($key)) {
            return $this->args;
        }
        return Z::arrayGet($this->args, $key, $default);
    }
}
class Zls_Route
{
    private $type;
    private $found = false;
    private $controller;
    private $method;
    private $args;
    private $hmvcModuleName;
    public function __construct()
    {
        $this->args = [];
    }
    public function getType()
    {
        return $this->type;
    }
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }
    public function getHmvcModuleName()
    {
        return $this->hmvcModuleName;
    }
    public function setHmvcModuleName($hmvcModuleName)
    {
        $this->hmvcModuleName = $hmvcModuleName;
        return $this;
    }
    public function found()
    {
        return $this->found;
    }
    public function setFound($found)
    {
        $this->found = $found;
        return $this;
    }
    public function getControllerShort()
    {
        return preg_replace('/^' . Z::config()->getControllerDirName() . '_/', '', $this->getController());
    }
    public function getController()
    {
        return $this->controller;
    }
    public function setController($controller)
    {
        $this->controller = $controller;
        return $this;
    }
    public function getMethodShort()
    {
        return preg_replace('/^' . Z::config()->getMethodPrefix() . '/', '', $this->getMethod());
    }
    public function getMethod()
    {
        return $this->method;
    }
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }
    public function getArgs()
    {
        return $this->args;
    }
    public function setArgs(array $args)
    {
        $this->args = $args;
        return $this;
    }
}
class Zls_SeparationRouter extends Zls_Route
{
    /**
     * @param $route
     * @param $isHmvcModule
     * @return bool|string
     */
    public function find($route, $isHmvcModule)
    {
        $arg = explode('_', $route);
        $hmvcModule = array_shift($arg);
        if ($hmvcModule !== $isHmvcModule) {
            return false;
        }
        $document = implode('/', $arg);
        $appPath = ZLS_APP_PATH . '../';
        $path = $appPath . 'router/' . $hmvcModule . '.json';
        $config = Z::config();
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            Z::throwIf(empty($data), 404, 'invalid file format or conte file : [ ' . Z::safePath($path) . ' ]');
            $defaultMethod = Z::arrayGet($data, 'default');
            if (!$document || $document === $config->getDefaultController()) {
                $document = $defaultMethod;
            }
            $routerMatch = [];
            $router = Z::arrayGet($data, 'routerList');
            $viewPath = '';
            $routerData = $beforeData = [];
            $document = strtolower($document);
            $static = Z::arrayGet($data, 'static');
            if (!!$router) {
                foreach ($router as $_router => $value) {
                    if (!isset($value['view']) && !isset($value['viewFile'])) {
                        continue;
                    } elseif (preg_match('/^' . str_replace('/', '\/', $_router) . '$/', $document, $routerMatch)) {
                        $viewPath = Z::arrayGet($value, 'view') ?: Z::arrayGet($value, 'viewFile') . '/' . $document;
                        $routerData = $value;
                        break;
                    } elseif (!!$defaultMethod && preg_match(
                            '/^' . str_replace('/', '\/', $_router) . '$/',
                            $document . '/' . $defaultMethod,
                            $routerMatch
                        )) {
                        $_viewPath = Z::arrayGet($value, 'view') ?: Z::arrayGet($value, 'viewFile') . '/' . $document . '/' . $defaultMethod;
                        if (is_file($appPath . $_viewPath)) {
                            $viewPath = $_viewPath;
                            $routerData = $value;
                            break;
                        }
                    }
                }
            }
            if (!$viewPath) {
                return false;
            }
            if (is_dir($view = $appPath . $viewPath)) {
                $view = $view . '/' . $defaultMethod;
            }
            Z::throwIf(!file_exists($view), 404, 'view file : [ ' . Z::safePath($view) . ' ] not found');
            $pathPrefix = explode('/', $viewPath);
            $document = array_pop($pathPrefix);
            $pathPrefix = array_shift($pathPrefix);
            if ($before = Z::arrayGet($data, 'before')) {
                $rule = explode(':', $before);
                $before = Z::business(Z::arrayGet($rule, 0));
                if ($beforeMethod = Z::arrayGet($rule, 1)) {
                    $beforeData['globalData'] = $before->$beforeMethod(
                        $routerMatch,
                        $document,
                        $pathPrefix,
                        $hmvcModule
                    );
                }
            }
            if ($before = Z::arrayGet($routerData, 'before')) {
                $rule = explode(':', $before);
                $before = Z::business(Z::arrayGet($rule, 0));
                if ($beforeMethod = Z::arrayGet($rule, 1)) {
                    $beforeData['data'] = $before->$beforeMethod(
                        $routerMatch,
                        $document,
                        $pathPrefix,
                        $hmvcModule
                    );
                }
            }
            if ($static === true) {
                $html = \file_get_contents($view);
            } else {
                $html = Z::view()->loadRaw($view, ['beforeData' => $beforeData], true);
            }
            if ($after = Z::arrayGet($data, 'after')) {
                $rule = explode(':', $after);
                $after = Z::business(Z::arrayGet($rule, 0));
                if ($afterMethod = Z::arrayGet($rule, 1)) {
                    $html = $after->$afterMethod($html, $routerMatch, $document, $pathPrefix, $hmvcModule);
                }
            }
            if ($after = Z::arrayGet($routerData, 'after')) {
                $rule = explode(':', $after);
                $after = Z::business(Z::arrayGet($rule, 0));
                if ($afterMethod = Z::arrayGet($rule, 1)) {
                    $content = $after->$afterMethod($html, $routerMatch, $document, $pathPrefix, $hmvcModule, $document);
                    if ($content) {
                        $html = $content;
                    }
                }
            }
            return $html;
        }
        return false;
    }
}
/**
 * @property Zls_Exception_Handle $exceptionHandle
 * @method self setHmvcModules(array $hmvcs)
 * @method self setEnvironment(string $environment)
 * @method self setShowError(boolean $showError)
 * @method self setTraceStatus($e)
 * @method self setApiDocToken(string $token)
 * @method self setIsRewrite(boolean $isRewrite)
 * @method self setSeparationRouter(boolean $separationRouter)
 * @method self setDefaultController(string $defaultController)
 * @method self setDefaultMethod($e)
 * @method self setMethodPrefix($e)
 * @method self setMethodUriSubfix($e)
 * @method self setMethodParametersDelimiter($e)
 * @method self setExceptionHandle($e)
 * @method self setOutputJsonRender($e)
 * @method self setLogsSubDirNameFormat($e)
 * @method self setCommands($e)
 * @method self setHmvcDirName($e)
 * @method string getBeanDirName()
 * @method string getExceptionLevel()
 * @method string getApplicationDir()
 * @method string getClassesDirName()
 * @method string getControllerDirName()
 * @method string getCookiePrefix()
 * @method string getCacheConfig()
 * @method array getHmvcModules()
 * @method \Zls_Session getSessionHandle()
 * @method string getTaskDirName()
 * @method string getPrimaryApplicationDir()
 * @method string getMethodPrefix()
 * @method string getBusinessDirName()
 * @method string getDaoDirName()
 * @method string getModelDirName()
 * @method string getHmvcDirName()
 * @method string getApiDocToken()
 * @method string getFunctionsDirName()
 * @method string getDefaultController()
 * @method string getDefaultMethod()
 * @method string getLibraryDirName()
 * @method string getMethodUriSubfix()
 * @method string getConfigDirName()
 * @method array getMethodCacheConfig()
 * @method boolean getExceptionControl()
 * @method \Zls_Maintain_Handle_Default getMaintainModeHandle()
 * @method array getCommands()
 * @method boolean getIsRewrite()
 */
class Zls_Config
{
    private static $alias = [];
    private $applicationDir = '';
    private $primaryApplicationDir = '';
    private $indexDir = '';
    private $commands = [];
    private $classesDirName = 'classes';
    private $hmvcDirName = 'hmvc';
    private $libraryDirName = 'library';
    private $functionsDirName = 'functions';
    private $storageDirPath = '';
    private $viewsDirName = 'views';
    private $configDirName = 'config';
    private $controllerDirName = 'Controller';
    private $businessDirName = 'Business';
    private $daoDirName = 'Dao';
    private $beanDirName = 'Bean';
    private $modelDirName = 'Model';
    private $taskDirName = 'Task';
    private $defaultController = 'Index';
    private $defaultMethod = 'index';
    private $methodPrefix = 'z_';
    private $methodUriSubfix = '.go';
    private $methodParametersDelimiter = '-';
    private $logsSubDirNameFormat = 'Y-m-d/H';
    private $exceptionLevel = '';
    private $exceptionControl = true;
    private $cookiePrefix = '';
    private $backendServerIpWhitelist = [];
    private $isRewrite = true;
    private $request;
    private $showError;
    private $traceStatus = false;
    private $routersContainer = [];
    private $packageMasterContainer = [];
    private $packageContainer = [];
    private $loggerWriters = [];
    private $logsMaxDay = 10;
    private $uriRewriter;
    private $exceptionHandle;
    private $route;
    private $environment = 'production';
    private $hmvcModules = [];
    private $isMaintainMode;
    private $maintainIpWhitelist;
    private $maintainModeHandle;
    private $databseConfig;
    private $cacheHandles = [];
    private $cacheConfig;
    private $sessionConfig;
    private $sessionHandle;
    private $hvmcDomain;
    private $methodCacheConfig;
    private $dataCheckRules;
    private $outputJsonRender;
    private $exceptionJsonRender;
    private $zMethods = [];
    private $encryptKey;
    private $apiDocToken = '';
    private $exceptionMemoryReserveSize = 256000;
    private $separationRouter = false;
    private $traceStatusCallBack = null;
    private $clientIpConditions = [
        'source' => ['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'],
        'check'  => ['HTTP_X_FORWARDED_FOR'],
    ];
    private $hmvcDomains = [
        'enable'  => false,
        'domains' => [],
    ];
    /**
     * @return array
     */
    public function getAlias()
    {
        return self::$alias;
    }
    public function getMaintainIpWhitelist()
    {
        return $this->getSysConfig($this->maintainIpWhitelist, 'ipWhitelist', []);
    }
    /**
     * @param array $maintainIpWhitelist
     * @return $this
     */
    public function setMaintainIpWhitelist(array $maintainIpWhitelist)
    {
        $this->maintainIpWhitelist = $maintainIpWhitelist;
        return $this;
    }
    public function getSysConfig($value, $key, $default = '')
    {
        if (is_null($value)) {
            $value = z::config()->find('zls') ? Z::config('zls.' . $key) : $default;
        }
        return $value;
    }
    /**
     * 按照包的顺序查找配置文件
     * @param string $filename
     * @param string $ext
     * @return string
     */
    public function find($filename, $ext = '.php')
    {
        foreach ($this->getPackages() as $packagePath) {
            $path = $packagePath . $this->getConfigDirName() . '/';
            $filePath = $path . $this->getEnvironment() . '/' . $filename . $ext;
            $fileDefaultPath = $path . 'default/' . $filename . $ext;
            if (file_exists($filePath)) {
                return $filePath;
            } elseif (file_exists($fileDefaultPath)) {
                return $fileDefaultPath;
            }
        }
        return '';
    }
    public function getPackages()
    {
        return array_merge($this->packageMasterContainer, $this->packageContainer);
    }
    public function getEnvironment()
    {
        if (empty($this->environment)) {
            $this->environment = ($env = (($cliEnv = Z::getOpt('env')) ? $cliEnv : Z::arrayGet(
                $_SERVER,
                'ENVIRONMENT'
            ))) ? $env : 'production';//'development'
        }
        return $this->environment;
    }
    public function getShowError()
    {
        return $this->getSysConfig($this->showError, 'showError');
    }
    /**
     * 设置别名
     * @param array $alias
     * @return $this
     */
    public function setAlias($alias)
    {
        self::$alias = $alias;
        return $this;
    }
    public function __get($name)
    {
        return $this->$name;
    }
    public function __set($name, $value)
    {
        $this->$name = $value;
        return $this;
    }
    public function setExceptionControl($exceptionControl = true)
    {
        $this->exceptionControl = $exceptionControl;
        return $this;
    }
    public function getCurrentDomainHmvcModuleNname()
    {
        if (!$this->hmvcDomains['enable']) {
            return false;
        } elseif (!is_null($this->hvmcDomain)) {
            return $this->hvmcDomain;
        }
        $_domain = Z::server('http_host');
        $domain = explode('.', $_domain);
        $length = count($domain);
        $topDomain = '';
        if ($length >= 2) {
            $topDomain = $domain[$length - 2] . '.' . $domain[$length - 1];
        }
        foreach ($this->hmvcDomains['domains'] as $prefix => $hvmc) {
            $hvmcDomain = ($hvmc['isFullDomain'] ? $prefix : ($prefix . '.' . $topDomain));
            if ((z::arrayGet($hvmc, 'isRegEx') === true)
                && (preg_match('/^' . $hvmcDomain . '$/', $_domain))) {
                $this->hvmcDomain = $hvmc['enable'] ? $hvmc['hmvcModuleName'] : false;
            } elseif ($hvmcDomain == $_domain) {
                $this->hvmcDomain = $hvmc['enable'] ? $hvmc['hmvcModuleName'] : false;
            }
            if (!is_null($this->hvmcDomain)) {
                return $this->hvmcDomain;
            }
        }
        return '';
    }
    public function hmvcIsDomainOnly($hmvcModuleName)
    {
        if (!$hmvcModuleName || !$this->hmvcDomains['enable']) {
            return false;
        }
        foreach ($this->hmvcDomains['domains'] as $hvmc) {
            if ($hmvcModuleName == $hvmc['hmvcModuleName']) {
                return $hvmc['domainOnly'];
            }
            return false;
        }
        return false;
    }
    public function setHmvcDomains($hmvcDomains)
    {
        if (is_string($hmvcDomains)) {
            $this->hmvcDomains = Z::config($hmvcDomains, false);
        } elseif (is_array($hmvcDomains)) {
            $this->hmvcDomains = $hmvcDomains;
        }
        return $this;
    }
    public function getEncryptKey()
    {
        $key = $this->getEnvironment();
        if (!empty($this->encryptKey[$key])) {
            return $this->encryptKey[$key];
        } elseif (!empty($this->encryptKey['default'])) {
            return $this->encryptKey['default'];
        }
        return '73zls';
    }
    public function setEncryptKey($encryptKey)
    {
        $encryptFile = Z::config()->find($encryptKey);
        if (!!$encryptFile) {
            $encryptKey = Z::config($encryptKey, false);
        }
        if (is_array($encryptKey)) {
            $this->encryptKey = $encryptKey;
        } else {
            $this->encryptKey = [
                'default' => $encryptKey,
            ];
        }
        return $this;
    }
    /**
     * 扩展核心
     * @param              $methodName
     * @param string|array $method
     * @return $this
     */
    public function setZMethods($methodName, $method = null)
    {
        if (is_array($methodName)) {
            $this->zMethods = array_merge($this->zMethods, $methodName);
        } else {
            $this->zMethods[$methodName] = $method;
        }
        return $this;
    }
    public function getExceptionJsonRender()
    {
        if (!$this->exceptionJsonRender) {
            $this->exceptionJsonRender = function (\Exception $e) {
                $run = Z::debug(false);
                /**
                 * @var \Zls_Exception $e
                 */
                $json['environment'] = $e->getEnvironment();
                $json['file'] = $e->getErrorFile();
                $json['line'] = $e->getErrorLine();
                $json['msg'] = $e->getErrorMessage();
                $json['type'] = $e->getErrorType();
                $json['code'] = 0;
                $json['errorCode'] = $e->getErrorCode();
                $json['time'] = date('Y/m/d H:i:s T');
                $json['wasteTime'] = $run['runtime'];
                $json['memory'] = $run['memory'];
                $json['trace'] = array_filter(explode("\n", $e->getTraceCliString()));
                return @json_encode($json);
            };
        }
        return $this->exceptionJsonRender;
    }
    public function getOutputJsonRender()
    {
        z::header('Content-Type: application/json; charset=UTF-8');
        if (empty($this->outputJsonRender)) {
            $this->outputJsonRender = function () {
                $args = func_get_args();
                if (is_array($code = Z::arrayGet($args, 0, ''))) {
                    $args = $code;
                    $code = $args[0];
                }
                $message = Z::arrayGet($args, 1, '');
                $data = Z::arrayGet($args, 2, '');
                $die = Z::arrayGet($args, 3, false);
                $json = json_encode(
                    ['code' => $code, 'msg' => $message, 'data' => $data],
                    JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES
                );
                if ($die) {
                    Z::finish($json);
                }
                return $json;
            };
        }
        return $this->outputJsonRender;
    }
    /**
     * @param $dataCheckRules
     * @return $this
     */
    public function setDataCheckRules($dataCheckRules)
    {
        $this->dataCheckRules = is_array($dataCheckRules) ? $dataCheckRules : Z::config($dataCheckRules, false);
        return $this;
    }
    /**
     * @param $methodCacheConfig
     * @return $this
     */
    public function setMethodCacheConfig($methodCacheConfig)
    {
        $this->methodCacheConfig = is_array($methodCacheConfig) ? $methodCacheConfig : Z::config(
            $methodCacheConfig,
            false
        );
        return $this;
    }
    /**
     * @param string $key
     * @return mixed
     */
    public function getCacheHandle($key = '')
    {
        $fileCacheClass = 'Zls_Cache_File';
        if (empty($this->cacheConfig)) {
            $this->cacheConfig = Z::config('cache') ?: [
                'default_type' => 'file',
                'drivers'      => [
                    'file' => [
                        'class'  => $fileCacheClass,
                        'config' => Z::config()->getStorageDirPath() . 'cache/',
                    ],
                ],
            ];
        }
        if (is_array($key)) {
            return z::factory($key['class'], false, false, [$key['config']]);
        } else {
            $key = $key ? $key : $this->cacheConfig['default_type'];
            Z::throwIf(!Z::arrayKeyExists("drivers.$key", $this->cacheConfig), 500, 'unknown cache type [ ' . $key . ' ]', 'ERROR');
            $config = $this->cacheConfig['drivers'][$key]['config'];
            if (!$className = Z::arrayGet($this->cacheConfig, 'drivers.' . $key . '.class')) {
                // 没有缓存类默认文件缓存
                $className = $fileCacheClass;
            }
            if (!Z::arrayKeyExists($key, $this->cacheHandles)) {
                $this->cacheHandles[$key] = z::factory($className, false, false, [$config]);
            }
            return $this->cacheHandles[$key];
        }
    }
    public function getStorageDirPath()
    {
        return empty($this->storageDirPath) ? $this->getPrimaryApplicationDir() . 'storage/' : $this->storageDirPath;
    }
    public function setStorageDirPath($storageDirPath)
    {
        $this->storageDirPath = Z::realPath($storageDirPath, true);
        return $this;
    }
    public function setCacheConfig($cacheConfig)
    {
        $this->cacheHandles = [];
        if (is_string($cacheConfig)) {
            $this->cacheConfig = Z::config($cacheConfig, false);
        } elseif (is_array($cacheConfig)) {
            $this->cacheConfig = $cacheConfig;
        }
        return $this;
    }
    /**
     * 设置session托管
     * @param $sessionHandle
     * @return $this
     */
    public function setSessionHandle($sessionHandle)
    {
        if ($sessionHandle instanceof Zls_Session) {
            $this->sessionHandle = $sessionHandle;
        } else {
            $this->sessionHandle = Z::config($sessionHandle, false);
        }
        return $this;
    }
    public function getSessionConfig()
    {
        if (empty($this->sessionConfig)) {
            $this->sessionConfig = [
                'autostart'         => false,
                'cookie_path'       => '/',
                'cookie_domain'     => Z::arrayGet(explode(':', Z::server('HTTP_HOST')), 0, Z::server('HTTP_HOST')),
                'session_name'      => 'ZLS',
                'lifetime'          => 86400,
                'session_save_path' => null,
            ];
        }
        return $this->sessionConfig;
    }
    /**
     * @param $sessionConfig
     * @return $this
     */
    public function setSessionConfig($sessionConfig)
    {
        if (is_array($sessionConfig)) {
            $this->sessionConfig = $sessionConfig;
        } else {
            $this->sessionConfig = Z::config($sessionConfig, false);
        }
        return $this;
    }
    public function getDatabaseConfig($group = null)
    {
        if (empty($group)) {
            return $this->databseConfig;
        } else {
            return Z::arrayKeyExists($group, $this->databseConfig) ? $this->databseConfig[$group] : [];
        }
    }
    public function setDatabaseConfig($databseConfig)
    {
        $this->databseConfig = is_array($databseConfig) ? $databseConfig : Z::config($databseConfig);
        return $this;
    }
    public function setMaintainModeHandle(Zls_Maintain_Handle $maintainModeHandle)
    {
        $this->maintainModeHandle = $maintainModeHandle;
        return $this;
    }
    public function getIsMaintainMode()
    {
        return $this->getSysConfig($this->isMaintainMode, 'maintainMode');
    }
    /**
     * @return Zls_Uri_Rewriter
     */
    public function getUriRewriter()
    {
        if (!$this->uriRewriter) {
            $this->uriRewriter = new \Zls_Uri_Rewriter_Default();
        }
        return $this->uriRewriter;
    }
    public function setUriRewriter(Zls_Uri_Rewriter $uriRewriter)
    {
        $this->uriRewriter = $uriRewriter;
        return $this;
    }
    /**
     * 如果服务器是ngix之类代理转发请求到后端apache运行的PHP
     * 那么这里应该设置信任的nginx所在服务器的ip<br>
     * nginx里面应该设置 X_FORWARDED_FOR server变量来表示真实的客户端IP
     * 不然通过Z::clientIp()是获取不到真实的客户端IP的
     * @param array $backendServerIpWhitelist
     * @return \Zls_Config
     */
    public function setBackendServerIpWhitelist(array $backendServerIpWhitelist)
    {
        $this->backendServerIpWhitelist = $backendServerIpWhitelist;
        return $this;
    }
    public function getLogsSubDirNameFormat()
    {
        if (!$this->logsSubDirNameFormat) {
            $this->logsSubDirNameFormat = 'Y-m-d/H';
        }
        return $this->logsSubDirNameFormat;
    }
    /**
     * @param array $funciontsFileNameArray
     * @return $this
     */
    public function addAutoloadFunctions(array $funciontsFileNameArray)
    {
        foreach ($funciontsFileNameArray as $functionsFileName) {
            Z::functions($functionsFileName);
        }
        return $this;
    }
    /**
     * @return \Zls_Route
     */
    public function getRoute()
    {
        return empty($this->route) ? new \Zls_Route() : $this->route;
    }
    /**
     * 设置错误级别
     * @param $exceptionLevel
     * @return $this
     */
    public function setExceptionLevel($exceptionLevel)
    {
        $this->exceptionLevel = $exceptionLevel;
        return $this;
    }
    public function getIndexDir()
    {
        if (empty($this->indexDir)) {
            $this->indexDir = ZLS_PATH;
        }
        return $this->indexDir;
    }
    public function setIndexDir($indexDir)
    {
        $this->indexDir = Z::realPath($indexDir) . '/';
        return $this;
    }
    public function setLoggerWriters(Zls_Logger $loggerWriters)
    {
        $this->loggerWriters = $loggerWriters;
        return $this;
    }
    public function addMasterPackages(array $packagesPath)
    {
        foreach ($packagesPath as $packagePath) {
            $this->addMasterPackage($packagePath);
        }
        return $this;
    }
    public function addMasterPackage($packagePath)
    {
        $packagePath = realPath($packagePath) . '/';
        if (!in_array($packagePath, $this->packageMasterContainer)) {
            array_push($this->packageMasterContainer, $packagePath);
            if (file_exists($library = $packagePath . $this->getLibraryDirName() . '/')) {
                array_push($this->packageMasterContainer, $library);
            }
        }
        return $this;
    }
    public function addPackages(array $packagesPath)
    {
        foreach ($packagesPath as $packagePath) {
            $this->addPackage($packagePath);
        }
        return $this;
    }
    public function addPackage($packagePath)
    {
        $packagePath = Z::realPath($packagePath) . '/';
        if (!in_array($packagePath, $this->packageContainer)) {
            array_push($this->packageContainer, $packagePath);
            if (file_exists($library = $packagePath . $this->getLibraryDirName() . '/')) {
                array_push($this->packageContainer, $library);
            }
        }
        return $this;
    }
    /**
     * @param $method
     * @param $args
     * @return string|boolean
     */
    public function __call($method, $args)
    {
        if (Z::strBeginsWith($method, 'get')) {
            $argName = lcfirst(str_replace('get', '', $method));
            return $this->$argName;
        } elseif (Z::strBeginsWith($method, 'set')) {
            $argName = lcfirst(str_replace('set', '', $method));
            $this->$argName = count($args) === 1 ? $args[0] : $args;
            return $this;
        }
        Z::throwIf(true, 500, 'Call to undefined method Zls_Config::' . $method . '()');
        return false;
    }
    /**
     * 加载项目目录下的bootstrap.php配置
     */
    public function bootstrap()
    {
        if (file_exists($bootstrap = $this->getApplicationDir() . 'bootstrap.php')) {
            if (!Z::isSwoole()) {
                Z::includeOnce($bootstrap);
            } else {
                Z::swooleBootstrap($this->getApplicationDir());
            }
        }
    }
    public function setApplicationDir($applicationDir)
    {
        $this->applicationDir = Z::realPath($applicationDir, true);
        $this->setPrimaryApplicationDir($this->applicationDir);
        return $this;
    }
    public function setPrimaryApplicationDir($primaryApplicationDir = '')
    {
        if (empty($this->primaryApplicationDir)) {
            $this->primaryApplicationDir = Z::realPath($primaryApplicationDir ?: $this->applicationDir, true);
        }
        return $this;
    }
    public function composer()
    {
        if (file_exists($composer = ZLS_APP_PATH . '../vendor/autoload.php')) {
            Z::includeOnce($composer);
        }
        return $this;
    }
    /**
     * @return Zls_Request
     */
    public function getRequest()
    {
        if (!$this->request) {
            $this->request = new \Zls_Request_Default();
        }
        return $this->request;
    }
    public function setRequest(Zls_Request $request)
    {
        $this->request = $request;
        return $this;
    }
    public function addRouter($router)
    {
        if (is_string($router)) {
            $router = Z::factory($router, true);
        }
        array_unshift($this->routersContainer, $router);
        return $this;
    }
    public function getRouters()
    {
        if (!$this->routersContainer && !ZLS_RUN_MODE_PLUGIN) {
            array_unshift($this->routersContainer, Z::factory('Zls_Router_PathInfo', true));
        }
        return $this->routersContainer;
    }
    /**
     * Zls_Logger
     * @param $loggerWriter
     * @return $this
     */
    public function addLoggerWriter($loggerWriter)
    {
        $this->loggerWriters[] = $loggerWriter;
        return $this;
    }
    public function setClientIpConditions(array $source, array $check)
    {
        if ($source) {
            $this->clientIpConditions['source'] = $source;
        }
        if ($check) {
            $this->clientIpConditions['check'] = $check;
        }
        return $this;
    }
    public function getSeparationRouter($controller, $hmvcModule)
    {
        if (!$this->separationRouter) {
            return false;
        }
        static $router;
        if (!$router) {
            $router = new \Zls_SeparationRouter();
        }
        if ($hmvcModule) {
            $controller = $hmvcModule . '_' . $controller;
        }
        return $router->find(str_replace('Controller_', '', $controller), $hmvcModule);
    }
}
class Zls_Logger_Dispatcher
{
    private static $instance;
    private static $memReverse;
    public static function initialize()
    {
        if (empty(self::$instance)) {
            self::setMemReverse();
            self::$instance = new self();
            Z::isPluginMode() ? ini_set('display_errors', true) : ini_set('display_errors', false);
            set_exception_handler([self::$instance, 'handleException']);
            set_error_handler([self::$instance, 'handleError']);
            register_shutdown_function([self::$instance, 'handleFatal']);
        }
        return self::$instance;
    }
    public static function setMemReverse()
    {
        self::$memReverse = str_repeat("x", Zls::getConfig()->getExceptionMemoryReserveSize());
    }
    /**
     * @param $exception
     * @throws Exception
     */
    final public function handleException($exception)
    {
        if (is_subclass_of($exception, 'Zls_Exception')) {
            $this->dispatch($exception);
        } else {
            $this->dispatch(new \Zls_Exception_500(
                $exception->getMessage(),
                $exception->getCode(),
                get_class($exception),
                $exception->getFile(),
                $exception->getLine()
            ));
        }
    }
    /**
     * 异常
     * todo 继承 \Zls_Exception
     * @param Zls_Exception $exception
     * @param bool          $result
     * @return mixed|string
     * @throws Exception
     */
    final public function dispatch(\Zls_Exception $exception, $result = false)
    {
        $error = '';
        $config = Z::config();
        ini_set('display_errors', true);
        $loggerWriters = $config->getLoggerWriters();
        foreach ($loggerWriters as $loggerWriter) {
            $loggerWriter->write($exception);
        }
        $handle = $config->getExceptionHandle();
        if ($config->getShowError() || $handle) {
            if ($handle instanceof \Zls_Exception_Handle) {
                $error = $handle->handle($exception);
            } else {
                $error = $exception->render(Z::isAjax(), true);
            }
        } elseif (Z::isCli() && !Z::isSwoole()) {
            $error = $exception->render();
        } else {
            $path = [
                $config->getApplicationDir() . $config->getViewsDirName() . '/error/' . $exception->getErrorCode() . '.php',
                $config->getPrimaryApplicationDir() . $config->getViewsDirName() . $exception->getErrorCode() . '.php',
            ];
            if (file_exists($file = $path[0]) || file_exists($file = $path[1])) {
                $error = Z::view()->loadRaw($file, [], true);
            }
        }
        if (!$result) {
            Z::finish($error);
        }
        return $error;
    }
    /**
     * @param $code
     * @param $message
     * @param $file
     * @param $line
     * @throws Exception
     */
    final public function handleError($code, $message, $file, $line)
    {
        if (0 == error_reporting()) {
            return;
        }
        $this->dispatch(new \Zls_Exception_500($message, $code, 'General Error', $file, $line));
    }
    final public function handleFatal()
    {
        if (0 == error_reporting()) {
            return;
        }
        $lastError = error_get_last();
        $fatalError = [1, 256, 64, 16, 4, 4096];
        if (!Z::arrayKeyExists("type", $lastError) || !in_array($lastError["type"], $fatalError)) {
            return;
        }
        self::$memReverse = null;
        if (!Z::isSwoole()) {
            $this->dispatch(new \Zls_Exception_500(
                $lastError['message'],
                $lastError['type'],
                'Fatal Error',
                $lastError['file'],
                $lastError['line']
            ));
        } else {
            $error = $this->dispatch(new \Zls_Exception_500(
                $lastError['message'],
                $lastError['type'],
                'Fatal Error',
                $lastError['file'],
                $lastError['line']
            ), true);
            if (Z::isSwoole(true)) {
                $response = Z::di()->makeShared('SwooleResponse');
                $response->write($error);
                $response->end();
            } else {
                echo $error;
            }
        }
    }
}
class Zls_Maintain_Handle_Default implements Zls_Maintain_Handle
{
    public function handle()
    {
        if (!Z::isCli()) {
            Z::header('Content-type: text/html;charset=utf-8');
        }
        return '<center><h2>server is under maintenance</h2><h3>服务器维护中</h3>' . date('Y/m/d H:i:s e') . '</center>';
    }
}
class Zls_Uri_Rewriter_Default implements Zls_Uri_Rewriter
{
    public function rewrite($uri)
    {
        return $uri;
    }
}
class Zls_Exception_Handle_Default implements Zls_Exception_Handle
{
    public function handle(\Zls_Exception $exception)
    {
        $exception->render(Z::isAjax());
    }
}
class Zls_Database_SlowQuery_Handle_Default implements Zls_Database_SlowQuery_Handle
{
    public function handle($sql, $value, $explainString, $time, $trace)
    {
        $content = 'SQL : ' . $sql . PHP_EOL
            . 'value : ' . $value . PHP_EOL
            . 'explain : ' . $explainString . PHP_EOL
            . 'usingtime : ' . $time . 'ms' . PHP_EOL
            . 'time : ' . date('Y-m-d H:i:s');
        if ($trace) {
            $content = 'file : ' . $trace['file'] . PHP_EOL
                . 'line : ' . $trace['line'] . PHP_EOL
                . 'class : ' . $trace['class'] . PHP_EOL
                . 'function : ' . $trace['function'] . PHP_EOL
                . $content;
        }
        Z::log($content . PHP_EOL, 'slowQueryProduction');
    }
}
class Zls_Database_Index_Handle_Default implements Zls_Database_Index_Handle
{
    public function handle($sql, $value, $explainString, $time, $trace)
    {
        $content = 'SQL : ' . $sql . PHP_EOL
            . 'value : ' . $value . PHP_EOL
            . 'explain : ' . $explainString . PHP_EOL
            . 'usingtime : ' . $time . 'ms' . PHP_EOL
            . 'time : ' . date('Y-m-d H:i:s');
        if ($trace) {
            $content = 'file : ' . $trace['file'] . PHP_EOL
                . 'line : ' . $trace['line'] . PHP_EOL
                . 'class : ' . $trace['class'] . PHP_EOL
                . 'function : ' . $trace['function'] . PHP_EOL
                . $content;
        }
        Z::log($content . PHP_EOL, 'indexProduction');
    }
}
class Zls_Cache_File implements Zls_Cache
{
    private $_cacheDirPath;
    public function __construct($cacheDirPath = '')
    {
        $cacheDirPath = empty($cacheDirPath) ? Z::config()->getStorageDirPath() . 'cache/' : $cacheDirPath;
        $this->_cacheDirPath = Z::realPath($cacheDirPath) . '/';
        if (!is_dir($this->_cacheDirPath)) {
            mkdir($this->_cacheDirPath, 0700, true);
        }
        Z::throwIf(!is_writable($this->_cacheDirPath), 500, 'cache dir [ ' . Z::safePath($this->_cacheDirPath) . ' ] not writable', 'ERROR');
    }
    public function clean()
    {
        return Z::rmdir($this->_cacheDirPath, false);
    }
    public function get($key)
    {
        if (empty($key)) {
            return null;
        }
        $_key = $this->_hashKey($key);
        $filePath = $this->_hashKeyPath($_key) . $_key;
        if (file_exists($filePath)) {
            $cacheData = file_get_contents($filePath);
            $userData = $this->unpack($cacheData);
            if (!is_null($userData)) {
                return $userData;
            } else {
                $this->delete($key);
            }
        }
        return null;
    }
    private function _hashKey($key)
    {
        return md5($key);
    }
    private function _hashKeyPath($key)
    {
        $key = md5($key);
        $len = strlen($key);
        return $this->_cacheDirPath . $key{$len - 1} . $key{$len - 2} . $key{$len - 3} . '/';
    }
    private function unpack($cacheData)
    {
        $cacheData = @unserialize($cacheData);
        if (is_array($cacheData) && Z::arrayKeyExists('userData', $cacheData) && Z::arrayKeyExists(
                'expireTime',
                $cacheData
            )) {
            $expireTime = $cacheData['expireTime'];
            $userData = $cacheData['userData'];
            if ($expireTime == 0) {
                return $userData;
            }
            return $expireTime > time() ? $userData : null;
        } else {
            return null;
        }
    }
    public function delete($key)
    {
        if (empty($key)) {
            return false;
        }
        $key = $this->_hashKey($key);
        $filePath = $this->_hashKeyPath($key) . $key;
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
    }
    public function set($key, $value, $cacheTime = 0)
    {
        if (empty($key)) {
            return false;
        }
        $key = $this->_hashKey($key);
        $cacheDir = $this->_hashKeyPath($key);
        $filePath = $cacheDir . $key;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }
        $cacheData = $this->pack($value, $cacheTime);
        if (empty($cacheData)) {
            return false;
        }
        return file_put_contents($filePath, $cacheData, LOCK_EX);
    }
    private function pack($userData, $cacheTime)
    {
        $cacheTime = (int)$cacheTime;
        return @serialize(
            [
                'userData'   => $userData,
                'expireTime' => ($cacheTime == 0 ? 0 : time() + $cacheTime),
            ]
        );
    }
    public function &instance($key = null, $isRead = true)
    {
        return $this;
    }
    public function reset()
    {
        return $this;
    }
}
class Zls_Trace
{
    public static function instance()
    {
        static $instance;
        if (!$instance) {
            $instance = new self();
        }
        return $instance;
    }
    /**
     * @param        $data
     * @param string $type
     */
    public function mysql($data, $type = 'mysql')
    {
        $content = '';
        foreach ($data as $key => $item) {
            $content = "{$key}:{$item}" . PHP_EOL . $content;
        }
        $this->output($content, $type);
    }
    /**
     * @param       $content
     * @param       $type
     * @param array $debug
     */
    public function output($content, $type, $debug = [])
    {
        $fn = function ($content) {
            $_content = '';
            if (is_array($content)) {
                foreach ($content as $key => $value) {
                    try {
                        $value = is_string($value) ? $value : var_export($value, true);
                    } catch (\Exception $e) {
                        $value = is_string($value) ? $value : print_r($value, true);
                    }
                    $_content .= $key . ' : ' . $value . PHP_EOL;
                }
            } else {
                $_content = print_r($content, true);
            }
            return $_content;
        };
        $debug = $fn($debug);
        $prefix = str_repeat('=', 25) . (new \DateTime())->format('Y-m-d H:i:s u') . str_repeat('=', 25);
        if (is_bool($content)) {
            $content = var_export($content, true);
        } elseif (!is_string($content)) {
            $content = $fn($content);
        }
        $content = $prefix . PHP_EOL . $debug . $content;
        $callBack = \Z::config()->getTraceStatusCallBack();
        if ($callBack instanceof Closure) {
            $callBack($content, $type);
        } else {
            if (!file_exists($saveFile = $this->saveDirPath($type))) {
                $content = '<?php defined("IN_ZLS") or die();?>' . PHP_EOL . $content;
                $this->clear($saveFile);
            }
            file_put_contents($saveFile, $content . PHP_EOL, LOCK_EX | FILE_APPEND);
        }
    }
    /**
     * @param $type
     * @return string
     */
    public function saveDirPath($type)
    {
        $saveDirPath = Z::config()->getStorageDirPath() . $type . '/';
        if (!is_dir($saveDirPath)) {
            @mkdir($saveDirPath, 0700, true);
        }
        return $saveDirPath . date('Y-m-d') . '.log';
    }
    public function clear($saveFile)
    {
        $logsMaxDay = Z::config()->getLogsMaxDay();
        $etime = time();
        $stime = $etime - ($logsMaxDay * 86400);
        $datearr = [];
        while ($stime <= $etime) {
            $datearr[] = date('Y-m-d', $etime);
            $etime = $etime - 3600 * 24;
        }
        $dir = pathinfo($saveFile, PATHINFO_DIRNAME);
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file != "." && $file != "..") {
                    if (!in_array(pathinfo($file, PATHINFO_FILENAME), $datearr, true)) {
                        @unlink($dir . '/' . $file);
                    }
                }
            }
            closedir($dh);
        }
    }
    /**
     * @param array  $data
     * @param string $type
     */
    public function log(array $data, $type = 'trace')
    {
        $arg = '';
        foreach ($data['args'] as $key => $v) {
            $arg .= '------[arg_' . $key . ']------'
                . PHP_EOL
                . var_export($v, true)
                . PHP_EOL;
        }
        $debug = \Z::debug(null, false, true);
        $this->output(
            vsprintf(
                "traceType : %s\ntime : %s\nruntime : %s\nmemory : %s\npath : %s\nline : %u\nargs : \n%s\n\n",
                [
                    'log',
                    date('Y-m-d H:i:s'),
                    $debug['runtime'] . 's',
                    $debug['memory'],
                    \Z::safePath($data['file']),
                    $data['line'],
                    $arg,
                ]
            ),
            $type
        );
    }
}
