<?php
namespace Zls\Action;
use Z;
/**
 * 图片缩放处理
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-03-19 12:57
 */
class Image
{
    private $src;
    private $imageinfo;
    private $image;
    private $imagecopy = 'imagecopyresampled';
    public function __construct($src = '')
    {
        $this->src = $src;
    }
    /**
     * @param string $src
     * @return \Zls\Action\Image
     */
    public function import($src)
    {
        return new self($src);
    }
    public function setImagecopy($fast)
    {
        $this->imagecopy = $fast === true ? 'imagecopyresized' : $fast;
    }
    /**
     * 缩放图片
     * @param int  $width   宽度
     * @param int  $height  高度
     * @param null $percent 倍缩放
     * @return $this
     */
    public function zoom($width = null, $height = null, $percent = null)
    {
        $image = $this->getImage();
        $sWidth = $this->imageinfo['width'];
        $sHeight = $this->imageinfo['height'];
        $newWidth = $newHeight = 0;
        switch (true) {
            case !!$percent:
                $newWidth = $sWidth * $percent;
                $newHeight = $sHeight * $percent;
                break;
            case (!!$width && !!$height):
                $newWidth = $width ? : $width;
                $newHeight = $height ? : $width;
                break;
            case !!$width:
                $scale = $width / $sWidth;
                $newWidth = $width;
                $newHeight = $sHeight * $scale;
                break;
            case !!$height:
                $scale = $height / $sHeight;
                $newWidth = $sWidth * $scale;
                $newHeight = $height;
                break;
            default:
                Z::throwIf(true, 'Exception', 'Image size is invalid');
        }
        $this->copyImage($image, 0, 0, 0, 0, $newWidth, $newHeight, $this->imageinfo['width'], $this->imageinfo['height']);
        return $this;
    }
    /**
     * 打开图片
     */
    public function getImage()
    {
        if (!$this->image) {
            z::throwIf(!$this->src, 500, 'The picture does not exist, please execute first ->import($src)');
            list($width, $height, $type, $attr) = getimagesize($this->src);
            $this->imageinfo = [
                'width' => $width,
                'height' => $height,
                'type' => image_type_to_extension($type, false),
                'typeId' => $type,
                'attr' => $attr,
                'ext' => z::arrayGet(pathinfo($this->src), 'extension'),
            ];
            $fun = $this->imageFn($type);
            z::throwIf(!$type, 500, 'temporarily does not support this file format, please use image processing software to convert the image into GIF, JPG, PNG format');
            $image = $fun($this->src);
            $this->image = $image;
        }
        return $this->image;
    }
    public function imageFn($imagetype = 0, $ext = '', $prefix = 'imagecreatefrom')
    {
        $imageTypeToExtension = $imagetype ? image_type_to_extension($imagetype, false) : false;
        if ($imageTypeToExtension) {
            $imageFn = $prefix . $imageTypeToExtension;
        } elseif ($ext) {
            switch (true) {
                case $ext == 'jpg':
                    $ext = 'jpeg';
                    break;
            }
            $imageFn = $prefix . $ext;
        } else {
            $imageFn = $prefix . 'jpeg';
        }
        return $imageFn;
    }
    public function copyImage($image, $nx, $ny, $sx, $sy, $nw, $nh, $width, $height)
    {
        $newImg = imagecreatetruecolor($nw, $nh);
        $alpha = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
        imagefill($newImg, $nx, $ny, $alpha);
        $imagecopy = $this->imagecopy;
        $imagecopy($newImg, $image, $nx, $ny, $sx, $sy, $nw, $nh, $width, $height);
        imagesavealpha($newImg, true);
        $this->image = $newImg;
        return $newImg;
    }
    /**
     * 保存图片到硬盘
     * @param null   $filename 没有名字表示覆盖
     * @param string $path     保存路径
     * @param null   $type
     * @return mixed
     */
    public function save($filename = null, $path = '', $type = null)
    {
        $fileinfo = pathinfo($this->src);
        $newName = function ($filename = '') use ($fileinfo) {
            $newExt = $filename ? z::arrayGet(pathinfo($filename), 'extension') : '';
            $oldExt = z::arrayGet($fileinfo, 'extension');
            return $newExt ? $filename : $filename . '.' . $oldExt;
        };
        $dirname = z::arrayGet($fileinfo, 'dirname', '');
        switch (true) {
            case (!!$filename && $path):
                $newFile = z::realPathMkdir($path, true) . $newName($filename);
                break;
            case (!!$filename && !$path):
                $newFile = z::realPathMkdir($dirname, true) . $newName($filename);
                break;
            case (!$filename && !!$path):
                $newFile = z::realPathMkdir($path, true) . $newName(z::arrayGet($fileinfo, 'basename'));
                break;
            case $filename === false:
                $newFile = false;
                break;
            default:
                $newFile = $this->src;
        }
        return $this->show($newFile, $type);
    }
    /**
     * 输出图片
     * @param null   $newFile 是否保存文件
     * @param string $type
     * @return mixed
     */
    public function show($newFile = null, $type = '')
    {
        $image = $this->getImage();
        if (!$type) {
            $info = $this->getInfo();
            $newExt = $newFile ? z::arrayGet(pathinfo($newFile), 'extension') : '';
            $type = $newExt ? : $info['type'];
        }
        $type = strtolower($type);
        if (\in_array($type, ['gif', 'png'])) {
            $newWidth = imagesx($image);
            $newHeight = imagesy($image);
            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            $alpha = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
            imagefill($newImg, 0, 0, $alpha);
            imagecopyresampled($newImg, $image, 0, 0, 0, 0, $newWidth, $newHeight, $newWidth, $newHeight);
            imagesavealpha($newImg, true);
            $image = $newImg;
        }
        if ($type === 'jpg') {
            $type = 'JPEG';
        }
        $fun = $this->imageFn(0, $type, 'image');
        if (!$newFile) {
            Z::header('Content-Type: image/' . $this->imageinfo['type']);
        }
        return z::tap($fun($image, $newFile), function () {
            $this->destruct();
        });
    }
    public function getInfo()
    {
        $this->getImage();
        return $this->imageinfo;
    }
    public function destruct()
    {
        if ($this->image) {
            imagedestroy($this->image);
            $this->image = null;
        }
    }
    public function __destruct()
    {
        $this->destruct();
    }
}
