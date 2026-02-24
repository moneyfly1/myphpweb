<?php
// sync_user_with_short.php
// 直接使用代码内置的数据库配置，对比yg_user和yg_short_dingyue表，删除不在yg_short_dingyue中的用户账号信息

// 数据库配置（请根据实际情况修改）
$dbHost = '127.0.0.1';
$dbName = 'demomoneyfly';
$dbUser = 'demomoneyfly';
$dbPass = 'adzwCAzXBM7yGryW';
$dbPort = '3306';
$dbPrefix = 'yg_';

try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. 获取yg_short_dingyue所有qq
    $shortQq = [];
    $stmt = $pdo->query("SELECT qq FROM {$dbPrefix}short_dingyue");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $shortQq[] = $row['qq'];
    }

    // 2. 获取yg_user所有username
    $stmt = $pdo->query("SELECT id, username FROM {$dbPrefix}user");
    $deleteCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['id'];
        $username = $row['username'];
        if (!in_array($username, $shortQq)) {
            // 3. 删除不在short表中的用户
            $pdo->exec("DELETE FROM {$dbPrefix}user WHERE id = $id");
            echo "已删除用户：$username (id=$id)\n";
            $deleteCount++;
        }
    }
    echo "同步完成，共删除 $deleteCount 个用户。\n";

} catch (PDOException $e) {
    echo "数据库连接失败：" . $e->getMessage() . "\n";
    exit(1);
} 