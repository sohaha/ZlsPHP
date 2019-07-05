<?php
$config = Zls::getConfig();
Z::header('Content-Type: text/html; charset=UTF-8');
Z::header('X-Powered-By: Zls');

// 白名单ip
$isWhiteList = Z::isWhiteIp(Z::clientIp([]));

// 开启CROS
// Z::header('Access-Control-Allow-Origin: ' . Z::server('HTTP_ORIGIN'));
// Z::header('Access-Control-Allow-Credentials: true');
// Z::header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// Z::header('Access-Control-Allow-Headers:' . Z::server('HTTP_ACCESS_CONTROL_REQUEST_HEADERS'));
// if (Z::isOptions()) {
//     Z::end(Z::server('HTTP_ORIGIN'));
// }
