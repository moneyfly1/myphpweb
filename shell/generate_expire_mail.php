#!/usr/bin/env php
<?php
/**
 * 到期提醒邮件生成脚本
 * 查找即将到期（7天内）和已到期的订阅用户，生成提醒邮件并加入发送队列
 *
 * 用法: php generate_expire_mail.php
 * 建议通过 cron 每天执行一次
 */

// 加载引导文件（定义常量、加载 .env、加载 function.php）
require_once __DIR__ . '/bootstrap.php';

// 加载邮件模板类和邮件队列类
require_once COMMON_PATH . 'Common/EmailTemplate.class.php';
require_once APP_PATH . 'Common/Common/EmailQueue.class.php';

// ---------- 数据库连接 ----------
$host     = env('DB_HOST');
$dbname   = env('DB_NAME');
$username = env('DB_USER');
$password = env('DB_PASSWORD');
$port     = env('DB_PORT', '3306');
$prefix   = env('DB_PREFIX', '');

if (empty($host) || empty($dbname) || empty($username) || empty($password)) {
    fwrite(STDERR, "[错误] 数据库配置不完整，请检查 .env 中的 DB_HOST/DB_NAME/DB_USER/DB_PASSWORD\n");
    exit(1);
}

$mysqli = new mysqli($host, $username, $password, $dbname, intval($port));
if ($mysqli->connect_error) {
    fwrite(STDERR, "[错误] 数据库连接失败: " . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

// ---------- 表名 ----------
$subTable   = $prefix . 'short_dingyue';
$queueTable = $prefix . 'email_queue';

// ---------- 时间计算 ----------
$today        = date('Y-m-d');               // 今天
$sevenDaysOut = date('Y-m-d', strtotime('+7 days')); // 7天后

// ---------- 查询即将到期 + 已到期用户（status=1） ----------
// 即将到期: endtime >= 今天 AND endtime <= 7天后
// 已到期:   endtime < 今天
$sql = "SELECT qq, endtime FROM `{$subTable}`
        WHERE status = 1
          AND (
              (endtime >= '{$today}' AND endtime <= '{$sevenDaysOut}')
              OR endtime < '{$today}'
          )";

$result = $mysqli->query($sql);
if (!$result) {
    fwrite(STDERR, "[错误] 查询失败: " . $mysqli->error . "\n");
    $mysqli->close();
    exit(1);
}

if ($result->num_rows === 0) {
    echo "[" . date('Y-m-d H:i:s') . "] 没有需要提醒的用户\n";
    $mysqli->close();
    exit(0);
}

// ---------- 遍历用户，生成邮件并加入队列 ----------
$queue       = new EmailQueue();
$countTotal  = 0;
$countAdded  = 0;
$countSkipped = 0;
$countFailed = 0;
$cutoff24h   = time() - 86400; // 24小时前的时间戳

while ($row = $result->fetch_assoc()) {
    $qq      = trim($row['qq']);
    $endtime = trim($row['endtime']); // DATE 字符串，如 '2026-03-01'

    if (empty($qq) || empty($endtime)) {
        continue;
    }

    $countTotal++;
    $email          = $qq . '@qq.com';
    $expireTimestamp = strtotime($endtime);
    $isExpired      = ($endtime < $today);
    $subject        = $isExpired ? '订阅已到期' : '订阅即将到期';

    // ---------- 去重：24小时内同邮箱同类型不重复入队 ----------
    $checkSql = sprintf(
        "SELECT COUNT(*) AS cnt FROM `%s` WHERE to_email = '%s' AND type = 'expiration' AND created_at > %d",
        $queueTable,
        $mysqli->real_escape_string($email),
        $cutoff24h
    );
    $checkResult = $mysqli->query($checkSql);
    if ($checkResult) {
        $checkRow = $checkResult->fetch_assoc();
        if (intval($checkRow['cnt']) > 0) {
            $countSkipped++;
            echo "  [跳过] {$email} — 24小时内已有到期提醒在队列中\n";
            continue;
        }
    }

    // ---------- 生成邮件内容 ----------
    $body = EmailTemplate::getExpirationTemplate($qq, $expireTimestamp, $isExpired);

    // ---------- 加入队列 ----------
    $ok = $queue->addToQueue($email, $subject, $body, 'expiration', 2);
    if ($ok) {
        $countAdded++;
        $tag = $isExpired ? '已到期' : '即将到期';
        echo "  [入队] {$email} (到期日: {$endtime}, {$tag})\n";
    } else {
        $countFailed++;
        fwrite(STDERR, "  [失败] {$email} 加入队列失败\n");
    }
}

$mysqli->close();

// ---------- 输出汇总 ----------
echo "\n========== 到期提醒邮件生成汇总 ==========\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n";
echo "扫描用户: {$countTotal}\n";
echo "成功入队: {$countAdded}\n";
echo "重复跳过: {$countSkipped}\n";
echo "入队失败: {$countFailed}\n";
echo "==========================================\n";
