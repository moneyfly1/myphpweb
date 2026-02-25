<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// echo 111;die;
// 应用入口文件
// 检测PHP环境
if(version_compare(PHP_VERSION,'5.3.0','<'))  die('require PHP > 5.3.0 !');

// 开启调试模式 建议开发阶段开启 部署阶段设为 false
define('APP_DEBUG', false);

define('BIND_MODULE','Home');

// 定义应用目录
define('APP_PATH','./Application/');

// 自动清理过期的 Runtime 缓存（当配置文件内容变化后自动重建）
$_runtimeFile = APP_PATH . 'Runtime/common~runtime.php';
$_configFiles = array(
    APP_PATH . 'Home/Conf/config.php',
    APP_PATH . 'Common/Conf/config.php',
    APP_PATH . 'Common/Conf/db.php',
);
if (is_file($_runtimeFile)) {
    $_hashFile = APP_PATH . 'Runtime/.config_hash_home';
    $_currentHash = '';
    foreach ($_configFiles as $_cf) {
        if (is_file($_cf)) $_currentHash .= md5_file($_cf);
    }
    $_currentHash = md5($_currentHash);
    $_oldHash = is_file($_hashFile) ? trim(file_get_contents($_hashFile)) : '';
    if ($_currentHash !== $_oldHash) {
        @unlink(APP_PATH . 'Runtime/common~runtime.php');
        @unlink(APP_PATH . 'Runtime/Home~runtime.php');
        array_map('unlink', glob(APP_PATH . 'Runtime/Cache/Home/*.php') ?: array());
        @file_put_contents($_hashFile, $_currentHash);
    }
}

// 引入ThinkPHP入口文件
require './ThinkPHP/ThinkPHP.php';

// 亲^_^ 后面不需要任何代码了 就是如此简单