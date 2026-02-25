#!/usr/bin/env php
<?php
/**
 * Shell 脚本引导文件
 * 在 CLI 模式下定义 ThinkPHP 常量并加载 .env，使 function.php 可正常工作
 *
 * 用法：在 shell 脚本开头 require_once __DIR__ . '/bootstrap.php';
 */

// 项目根目录
$_rootDir = dirname(__DIR__);

// 定义 ThinkPHP 需要的常量（仅在未定义时）
if (!defined('APP_PATH'))     define('APP_PATH',     $_rootDir . '/Application/');
if (!defined('COMMON_PATH'))  define('COMMON_PATH',  $_rootDir . '/Application/Common/');
if (!defined('RUNTIME_PATH')) define('RUNTIME_PATH',  $_rootDir . '/Application/Runtime/');
if (!defined('VENDOR_PATH'))  define('VENDOR_PATH',   $_rootDir . '/ThinkPHP/Library/Vendor/');

// 确保 Runtime/Logs 目录存在
$_logDir = RUNTIME_PATH . 'Logs';
if (!is_dir($_logDir)) @mkdir($_logDir, 0755, true);

// 加载 .env
$_envFile = $_rootDir . '/.env';
if (is_file($_envFile)) {
    $lines = file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim(trim($value), '"\'');
            $_ENV[$key] = $value;
            if (function_exists('putenv')) putenv("$key=$value");
        }
    }
}

// 加载公共函数
require_once COMMON_PATH . 'Common/function.php';
