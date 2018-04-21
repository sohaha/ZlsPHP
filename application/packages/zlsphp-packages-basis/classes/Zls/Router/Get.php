<?php
namespace Zls\Router;
use Z;
use Zls;
/**
 * Zls_Router_Get
 * @author      影浅-Seekwe
 * @email       seekwe@gmail.com
 *              Date:        16/2/3
 *              Time:        12:09
 */
class Get extends \Zls_Router
{
    private $controllerKey, $methodKey, $moduleKey;
    public function __construct($config = [])
    {
        parent::__construct();
        $this->controllerKey = z::arrayGet($config, 'controllerKey', 'c');
        $this->methodKey = z::arrayGet($config, 'methodKey', 'a');
        $this->moduleKey = z::arrayGet($config, 'moduleKey', 'm');
    }
    public function find($uri = '')
    {
        $config = Z::config();
        $query = $config->getRequest()->getQueryString();
        $_hmvcModule = $config->getCurrentDomainHmvcModuleNname();
        if (count($config->getRouters()) > 1 && ($config->getRequest()->getPathInfo() === '/' || (!$query && !$_hmvcModule))) {
            return $this->route->setFound(false);
        }
        parse_str($query, $get);
        $controller = Z::arrayGet($get, $this->controllerKey, '');
        $method = Z::arrayGet($get, $this->methodKey, '');
        $hmvcModule = Z::arrayGet($get, $this->moduleKey, '');
        if (!$_hmvcModule) {
            if ($config->hmvcIsDomainOnly($hmvcModule)) {
                $hmvcModule = '';
            }
        } else {
            //-当前域名绑定了hmvc模块
            $hmvcModule = $_hmvcModule;
        }
        $hmvcModuleDirName = Zls::checkHmvc($hmvcModule, false);
        if ($controller) {
            $controller = $config->getControllerDirName() . '_' . $controller;
        }
        if ($method) {
            $method = $config->getMethodPrefix() . $method;
        }
        $hmvcModule = $hmvcModuleDirName ? $hmvcModule : '';
        if ($html = $config->getSeparationRouter($controller, $hmvcModule)) {
            return $html;
        }
        return $this->route->setHmvcModuleName($hmvcModule)
            ->setController($controller)
            ->setMethod($method)
            ->setFound($hmvcModuleDirName || $controller);
    }
    public function url($url = '')
    {
        return $url;
    }
}
