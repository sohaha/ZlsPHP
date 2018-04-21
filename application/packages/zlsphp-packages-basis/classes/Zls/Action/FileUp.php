<?php
namespace Zls\Action;
/**
 * FileUp
 * @author      影浅-Seekwe
 * @email       seekwe@gmail.com
 * Date:        17/2/3
 * Time:        19:50
 */
use Z;
class FileUp
{
    public $error = ['code' => '', 'info' => ''];
    private $size = 2048, $ext = ['jpg', 'png'], $file_formfield_name = 'file', $type = 'jpg', $save_name, $dir;
    /**
     * 设置表单文件域name名称
     * @param string $field_name
     */
    public function setFormField($field_name)
    {
        $this->file_formfield_name = $field_name;
    }
    /**
     * 设置文件最大大小，单位KB
     * @param int $s
     */
    public function setMaxSize($s)
    {
        $this->size = $s;
    }
    /**
     * 设置允许的文件拓展名列表，数组的形式，
     * 比如：array('jpg','bmp'),不区分大小写
     * @param array $e
     */
    public function setExt(Array $e)
    {
        $this->ext = $e;
    }
    public function saveFile($save_name = null, $dir = null)
    {
        $this->save_name = $save_name;
        $this->dir = $dir;
        $files = z::arrayGet($_FILES, $this->file_formfield_name);
        if (is_null($files)) {
            $this->setError(404, '请先上传文件');
            return false;
        }
        $tmpName = null;
        $newSaveName = null;
        if (is_array(z::arrayGet($files, 'name'))) {
            foreach ($files['name'] as $k => $file) {
                $file = [
                    'name'     => $files['name'][$k],
                    'error'    => $files['error'][$k],
                    'tmp_name' => $files['tmp_name'][$k],
                    'size'     => $files['size'][$k],
                    'type'     => $files['type'][$k],
                ];
                $ext = $this->getFileExt($file) ?: $this->type;
                if ($_tmp_name = $this->checkFile(
                    $file
                )
                ) {
                    $tmpName[] = $_tmp_name;
                } else {
                    return false;
                }
                $newSaveName[] = $save_name ? $save_name . '_' . $k . '.' . $ext : null;
            }
        } else {
            $file = $files;
            $ext = $this->getFileExt($file) ?: $this->type;
            if ($_tmp_name = $this->checkFile($file)) {
                $tmpName = $_tmp_name;
            } else {
                return false;
            }
            $newSaveName = $save_name ? $save_name . '.' . $ext : null;
        }
        return $this->file($tmpName, $newSaveName);
    }
    private function setError($code, $info)
    {
        $this->error['code'] = $code;
        $this->error['error'] = $info;
    }
    public function getFileExt($file)
    {
        $fileExt = pathinfo(z::arrayGet($file, 'name', ''), PATHINFO_EXTENSION);
        return $fileExt ? strtolower($fileExt) : '';
    }
    public function checkFile($file)
    {
        $error_code = $file['error'];
        if ($error_code > 0) {
            $server_error = [
                1 => '文件大小超过了PHP.ini中的文件限制',
                2 => '文件大小超过了浏览器限制',
                3 => '文件部分被上传',
                4 => '没有找到要上传的文件',
                5 => '服务器临时文件夹丢失',
                6 => '文件写入到临时文件夹出错',
            ];
            $this->setError(500, isset($server_error[$error_code]) ? $server_error[$error_code] : '未知错误');
            return false;
        }
        if (!$this->checkExt($file)) {
            return false;
        }
        if (!$this->checkSize($file)) {
            return false;
        }
        return $file;
    }
    private function checkExt($file)
    {
        $ext = $this->ext;
        foreach ($ext as &$v) {
            $v = strtolower($v);
        }
        $fileExt = $this->getFileExt($file);
        if (!in_array($fileExt, $ext)) {
            $this->setError(402, '文件类型错误！只允许：' . implode(',', $ext));
            return false;
        }
        return true;
    }
    private function checkSize($file)
    {
        $max_size = $this->size;
        $size_range = 1024 * $max_size;
        if ($file['size'] > $size_range || !$file['size']) {
            $this->setError(401,
                '文件"' . $file['name'] . '"大小错误！最大：' . ($max_size < 1024 ? $max_size . 'KB' : sprintf('%.1f',
                        $max_size / 1024) . 'MB'));
            return false;
        }
        return true;
    }
    public function file($file, $_save_name)
    {
        $dir = $this->dir;
        if (is_array($_save_name)) {
            $res = [];
            foreach ($_save_name as $k => $v) {
                $save_name = $v;
                $src_file = $file[$k]['tmp_name'];
                if (empty($save_name)) {
                    $file_ext = strtolower(pathinfo($file[$k]['name'], PATHINFO_EXTENSION));
                    $save_name = md5(sha1_file($src_file)) . '.' . $file_ext;
                }
                if (!empty($dir)) {
                    $subfix = $dir{strlen($dir) - 1};
                    $_dir = ($subfix == '/' || $subfix == "\\" ? $dir : $dir . '/');
                    $dir = pathinfo($_dir . $save_name, PATHINFO_DIRNAME);
                } else {
                    $dir = pathinfo($save_name, PATHINFO_DIRNAME);
                }
                $save_name = z::realPathMkdir($dir, true, false, true) . $save_name;
                @move_uploaded_file($src_file, $save_name);
                if (file_exists($save_name)) {
                    $res[] = $save_name;
                } else {
                    $this->setError(501, '移动临时文件到目标文件失败,请检查目标目录是否有写权限');
                    return false;
                }
            }
            return $res;
        } else {
            $src_file = $file['tmp_name'];
            if (empty($_save_name)) {
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $_save_name = md5(sha1_file($src_file)) . '.' . $file_ext;
            }
            if (!empty($dir)) {
                $subfix = $dir{strlen($dir) - 1};
                $_dir = ($subfix == '/' || $subfix == "\\" ? $dir : $dir . '/');
                $dir = pathinfo($_dir . $_save_name, PATHINFO_DIRNAME);
            } else {
                $dir = pathinfo($_save_name, PATHINFO_DIRNAME);
            }
            //$rs = $dir . '/' . $_save_name;
            $_save_name = z::realPathMkdir($dir, true, false, true) . $_save_name;
            @move_uploaded_file($src_file, $_save_name);
            if (file_exists($_save_name)) {
                return $_save_name;//$this->truepath($save_name);
            } else {
                $this->setError(501, '移动临时文件到目标文件失败,请检查目标目录是否有写权限');
                return false;
            }
        }
    }
    public function getError()
    {
        return $this->error;
    }
    public function getErrorMsg()
    {
        return $this->error['error'];
    }
    public function getErrorCode()
    {
        return $this->error['code'];
    }
    public function getFileRawName()
    {
        return strtolower(pathinfo($_FILES[$this->file_formfield_name]['name'], PATHINFO_FILENAME));
    }
    public function getTmpFilePath()
    {
        return $_FILES[$this->file_formfield_name]['tmp_name'];
    }
    private function truepath($path)
    {
        //是linux系统么？
        $unipath = PATH_SEPARATOR == ':';
        //检测一下是否是相对路径，windows下面没有:,linux下面没有/开头
        //如果是相对路径就加上当前工作目录前缀
        if (strpos($path, ':') === false && strlen($path) && $path{0} != '/') {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
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
        //如果是linux这里会导致linux开头的/丢失
        $path = implode(DIRECTORY_SEPARATOR, $absolutes);
        //如果是linux，修复系统前缀
        $path = $unipath ? (strlen($path) && $path{0} != '/' ? '/' . $path : $path) : $path;
        //最后统一分隔符为/，windows兼容/
        $path = str_replace(['/', '\\'], '/', $path);
        return $path;
    }
}
