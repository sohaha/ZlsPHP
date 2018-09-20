<?php
$config = Zls::getConfig();
z::header('Content-Type: text/html; charset=UTF-8');
z::header('X-Powered-By: Zls');
//}
//白名单ip
$isWhiteList = Z::isWhiteIp(Z::clientIp([]));
