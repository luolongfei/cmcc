<?php
/**
 * 用户配置
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2018/7/28
 * @time 17:40
 */

return [
    'users' => [
        [ // mom
            'name' => '老妈',
            'sendKey' => 'xxx', // 微信推送通道
            'SSOCookie' => 'xxx', // 令牌
            'smsCityCookie' => 11, // 城市代码
            'cstamp' => 1532404831157, // 登录时间
            'userAgent' => 'Mozilla/5.0 (Linux; Android 5.1; m2 Build/LMY47D; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/44.0.2403.147 Mobile Safari/537.36/3.4.4 scmcc.mobile' // 客户端
        ],
        [ // me
            'name' => '罗叔叔',
            'sendKey' => 'xxx', // 微信推送通道
            'SSOCookie' => 'xxx', // 令牌
            'smsCityCookie' => 27, // 城市代码
            'cstamp' => 1532673072100, // 登录时间
            'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15G77/3.4.4 scmcc.mobile' // 客户端
        ],
        [ // father
            'name' => '罗大爷',
            'sendKey' => 'xxx', // 微信推送通道
            'SSOCookie' => 'xxx', // 令牌
            'smsCityCookie' => 27, // 城市代码
            'cstamp' => 1532771740688, // 登录时间
            'userAgent' => 'Mozilla/5.0 (Linux; Android 7.0; HUAWEI MLA-AL10 Build/HUAWEIMLA-AL10; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/56.0.2924.87 Mobile Safari/537.36/3.4.4 scmcc.mobile' // 客户端
        ],
    ],
    'errorReportSendKey' => 'xxx',
    'imei' => 'xxx', // 在获取用户流量情况时，服务器会效验此项，只要服务器上imei与此项匹配就可通过验证，不会效验imei所属用户，故就不单独为每个用户定义了
];