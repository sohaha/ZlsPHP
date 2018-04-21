<?php
$config = Zls::getConfig();
z::header('Content-Type: text/html; charset=UTF-8');
z::header('X-Powered-By: Zls');
z::header("Access-Control-Allow-Origin: " . z::server('HTTP_ORIGIN'));
z::header("Access-Control-Allow-Credentials: true");
z::header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
z::header('Access-Control-Allow-Headers:Authorization,x-requested-with,content-type');
if((isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS')){
    z::finish();
}
//白名单ip
$isWhiteList = Z::isWhiteIp(Z::clientIp([]));
