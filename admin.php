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

// 应用入口文件

// 检测PHP环境
if(version_compare(PHP_VERSION,'5.3.0','<'))  die('require PHP > 5.3.0 !');

// 开启调试模式 建议开发阶段开启 部署阶段设为 false
define('APP_DEBUG', false);

define('BIND_MODULE','Admin');

// 定义应用目录
define('APP_PATH','./Application/');

// 自动清理过期的 Runtime 缓存（当配置文件更新后自动重建）
$_runtimeFile = APP_PATH . 'Runtime/common~runtime.php';
$_configFile  = APP_PATH . 'Admin/Conf/config.php';
$_commonConf  = APP_PATH . 'Common/Conf/config.php';
if (is_file($_runtimeFile)) {
    $rtime = filemtime($_runtimeFile);
    if ((is_file($_configFile) && filemtime($_configFile) > $rtime)
        || (is_file($_commonConf) && filemtime($_commonConf) > $rtime)) {
        // 配置文件比缓存新，清除 Runtime 让框架重建
        array_map('unlink', glob(APP_PATH . 'Runtime/common~runtime.php'));
        array_map('unlink', glob(APP_PATH . 'Runtime/Admin~runtime.php'));
        // 清除模板缓存
        array_map('unlink', glob(APP_PATH . 'Runtime/Cache/Admin/*.php'));
    }
}

// 引入ThinkPHP入口文件
require './ThinkPHP/ThinkPHP.php';

// 亲^_^ 后面不需要任何代码了 就是如此简单