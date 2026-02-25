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

// 在 ThinkPHP 启动前加载 .env，确保 Runtime 缓存模式下 env() 也能读到正确的值
// （编译后的 runtime 文件中 __DIR__ 会指向 Runtime 目录，导致 loadEnv() 找不到 .env）
$_envFile = __DIR__ . '/.env';
if (is_file($_envFile)) {
    $lines = file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');
            $_ENV[$key] = $value;
            if (function_exists('putenv')) putenv("$key=$value");
        }
    }
}

// 自动清理过期的 Runtime 缓存（当配置文件内容变化后自动重建）
$_runtimeFile = APP_PATH . 'Runtime/common~runtime.php';
$_hashFile = APP_PATH . 'Runtime/.config_hash';
$_configFiles = array(
    APP_PATH . 'Admin/Conf/config.php',
    APP_PATH . 'Common/Conf/config.php',
    APP_PATH . 'Common/Conf/db.php',
    APP_PATH . 'Common/Common/function.php',
    __DIR__ . '/.env',
);
$_currentHash = '';
foreach ($_configFiles as $_cf) {
    if (is_file($_cf)) $_currentHash .= md5_file($_cf);
}
$_currentHash = md5($_currentHash);
$_oldHash = is_file($_hashFile) ? trim(file_get_contents($_hashFile)) : '';
if ($_currentHash !== $_oldHash) {
    @unlink(APP_PATH . 'Runtime/common~runtime.php');
    @unlink(APP_PATH . 'Runtime/Admin~runtime.php');
    // 清除所有模板缓存
    foreach (glob(APP_PATH . 'Runtime/Cache/Admin/*.php') ?: array() as $_f) @unlink($_f);
    foreach (glob(APP_PATH . 'Runtime/Cache/*.php') ?: array() as $_f) @unlink($_f);
    foreach (glob(APP_PATH . 'Runtime/Data/*.php') ?: array() as $_f) @unlink($_f);
    @mkdir(APP_PATH . 'Runtime', 0777, true);
    @file_put_contents($_hashFile, $_currentHash);
}

// 引入ThinkPHP入口文件
require './ThinkPHP/ThinkPHP.php';

// 亲^_^ 后面不需要任何代码了 就是如此简单