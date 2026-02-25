<?php
// 用于 shell 脚本调用，生成到期提醒邮件 HTML 内容
// 用法: php generate_expire_mail.php 用户名 到期时间戳 [已到期:0/1]

// 加载引导文件
require_once __DIR__ . '/bootstrap.php';

if (!function_exists('C')) {
    function C($name) {
        if ($name === 'SITE_NAME') {
            return '订阅服务';
        }
        return null;
    }
}

require_once COMMON_PATH . 'Common/EmailTemplate.class.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php generate_expire_mail.php <username> <expire_timestamp> [is_expired]\n");
    exit(1);
}

$username = $argv[1];
$expireTimestamp = intval($argv[2]);
$isExpired = isset($argv[3]) ? (bool)$argv[3] : false;

$html = EmailTemplate::getExpirationTemplate($username, $expireTimestamp, $isExpired);
echo $html; 