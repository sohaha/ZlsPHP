<?php
$config = Zls::getConfig();
Z::header('Content-Type: text/html; charset=UTF-8');
Z::header('X-Powered-By: Zls');
// 开启CROS
//}
// 白名单ip
$isWhiteList = Z::isWhiteIp(Z::clientIp([]));
