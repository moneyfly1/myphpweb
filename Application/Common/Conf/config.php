<?php
return array(
//*************************************附加设置***********************************
    'SHOW_PAGE_TRACE'        => env('SHOW_PAGE_TRACE', false),                           // 是否显示调试面板
    'URL_CASE_INSENSITIVE'   => env('URL_CASE_INSENSITIVE', false),                           // url区分大小写
    'TAGLIB_BUILD_IN'        => 'Cx,Common\Tag\My',              // 加载自定义标签
    'LOAD_EXT_CONFIG'        => 'db,alipay,oauth',               // 加载网站设置文件
    'TMPL_PARSE_STRING'      => array(                           // 定义常用路径
        '__OSS__'            => OSS_URL,
        '__PUBLIC__'         => '/Public',
        '__HOME_CSS__'       => trim(TMPL_PATH,'.').'Home/Public/css',
        '__HOME_JS__'        => trim(TMPL_PATH,'.').'Home/Public/js',
        '__HOME_IMAGES__'    => trim(TMPL_PATH,'.').'Home/Public/images',
        '__ADMIN_CSS__'      => '/Public/admin/css',
        '__ADMIN_JS__'       => '/Public/admin/js',
        '__ADMIN_IMAGES__'   => '/Public/admin/images',
        '__ADMIN_ACEADMIN__' => '/Public/statics/aceadmin',
        '__PUBLIC_CSS__'     => trim(TMPL_PATH,'.').'Public/css',
        '__PUBLIC_JS__'      => trim(TMPL_PATH,'.').'Public/js',
        '__PUBLIC_IMAGES__'  => trim(TMPL_PATH,'.').'Public/images',
        '__USER_CSS__'       => trim(TMPL_PATH,'.').'User/Public/css',
        '__USER_JS__'        => trim(TMPL_PATH,'.').'User/Public/js',
        '__USER_IMAGES__'    => trim(TMPL_PATH,'.').'User/Public/images',
        '__APP_CSS__'        => trim(TMPL_PATH,'.').'App/Public/css',
        '__APP_JS__'         => trim(TMPL_PATH,'.').'App/Public/js',
        '__APP_IMAGES__'     => trim(TMPL_PATH,'.').'App/Public/images'
    ),
//***********************************URL设置**************************************
    'MODULE_ALLOW_LIST'      => array('Admin'), //允许访问列表
    'URL_HTML_SUFFIX'        => '',  // URL伪静态后缀设置
    // 'URL_MODEL'              => 1,  //启用rewrite
//***********************************SESSION设置**********************************
    'SESSION_OPTIONS'        => array(
        'name'               => env('SESSION_NAME', 'BJYADMIN'),//设置session名
        'expire'             => env('SESSION_EXPIRE', 24*3600*15), //SESSION保存15天
        'use_trans_sid'      => 1,//跨页传递
        'use_only_cookies'   => 0,//是否只开启基于cookies的session的会话方式
    ),
//***********************************页面设置**************************************
    'TMPL_EXCEPTION_FILE'    => APP_DEBUG ? THINK_PATH.'Tpl/think_exception.tpl' : './Template/default/Home/Public/404.html',
    // 'TMPL_ACTION_ERROR'      => 'Public/dispatch_jump.tpl', // 默认错误跳转对应的模板文件
    // 'TMPL_ACTION_SUCCESS'    => 'Public/dispatch_jump.tpl', // 默认成功跳转对应的模板文件
//***********************************auth设置**********************************
    'AUTH_CONFIG'            => array(
            'AUTH_USER'      => 'users'                         //用户信息表
        ),
    // 超级管理员用户 ID 列表（跳过权限校验），可配置多个
    'SUPER_ADMIN_IDS'         => array(88),
//***********************************邮件服务器**********************************
    'EMAIL_FROM_NAME'        => env('EMAIL_FROM_NAME', 'noreply@example.com'),   // 发件人
    'EMAIL_SMTP'             => env('EMAIL_SMTP', 'smtp.example.com'),   // smtp
    'EMAIL_USERNAME'         => env('EMAIL_USERNAME', 'username@example.com'),   // 账号
    'EMAIL_PASSWORD'         => env('EMAIL_PASSWORD', ''),   // 密码  注意: 163和QQ邮箱是授权码；不是登录的密码
    'EMAIL_SMTP_SECURE'      => env('EMAIL_SMTP_SECURE', 'ssl'),   // 链接方式 如果使用QQ邮箱；需要把此项改为  ssl
    'EMAIL_PORT'             => env('EMAIL_PORT', '465'), // 端口 如果使用QQ邮箱；需要把此项改为  465
    'MAIL_HOST'              => env('MAIL_HOST', 'smtp.example.com'),
    'MAIL_PORT'              => env('MAIL_PORT', 465),
    'MAIL_SECURE'            => env('MAIL_SECURE', 'ssl'),
    'MAIL_USER'              => env('MAIL_USER', 'username@example.com'),
    'MAIL_PASS'              => env('MAIL_PASS', ''),
//***********************************缓存设置**********************************
    'DATA_CACHE_TIME'        => env('DATA_CACHE_TIME', 1800),        // 数据缓存有效期s
    'DATA_CACHE_PREFIX'      => env('DATA_CACHE_PREFIX', 'mem_'),      // 缓存前缀
    'DATA_CACHE_TYPE'        => env('CACHE_TYPE', 'Memcached'), // 数据缓存类型,
    'MEMCACHED_SERVER'       => env('MEMCACHED_SERVER', '127.0.0.1'), // 服务器ip
    'ALIOSS_CONFIG'          => array(
        'KEY_ID'             => env('ALIOSS_KEY_ID', ''), // 阿里云oss key_id
        'KEY_SECRET'         => env('ALIOSS_KEY_SECRET', ''), // 阿里云oss key_secret
        'END_POINT'          => env('ALIOSS_END_POINT', ''), // 阿里云oss endpoint
        'BUCKET'             => env('ALIOSS_BUCKET', '')  // bucken 名称
        ),
    'NEED_UPLOAD_OSS'        => array( // 需要上传的目录
        '/Upload/avatar',
        '/Upload/cover',
        '/Upload/image/webuploader',
        '/Upload/video',
        )
);
