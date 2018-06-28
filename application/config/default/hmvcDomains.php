<?php
/**
 * hvmc模块子域名绑定
 * domains的键是二级开始的域，不包含顶级域名.
 * 比如顶级域名是test.com,这里的domains的键是demo代表demo.test.com
 * 再比如domains的键是i.user代表i.user.test.com
 * domains的键的值hmvcModuleName是要绑定的hmvc的URL模块名称，也就是对应着上面的setHmvcModules()
 * 注册的关联数组中的键名称，比如这里键demo的值hmvcModuleName是Demo，对应的hvmc模块就是上面注册的Demo模块。
 */
return [
    'enable'  => true, //总开关，是否启用
    'domains' => [
        'demo' => [
            'hmvcModuleName' => 'demo', //hvmc模块名称
            'enable'         => true, //单个开关，是否启用
            'domainOnly'     => false, //是否只能通过绑定的域名访问
            'isFullDomain'   => false//绑定完整的域名设置为true；绑定子域名设置为false
        ],
    ],
];
