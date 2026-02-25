<?php
// sync_user_with_short.php
// 对比yg_user和yg_short_dingyue表，删除不在yg_short_dingyue中的用户账号信息

// 从 .env 读取数据库配置
$envFile = dirname(dirname(__FILE__)) . '/.env';
if (!file_exists($envFile)) {
    echo ".env 文件不存在\n";
    exit(1);
}
$envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($envLines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    list($key, $val) = explode('=', $line, 2);
    $env[trim($key)] = trim($val);
}

$dbHost = isset($env['DB_HOST']) ? $env['DB_HOST'] : '127.0.0.1';
$dbName = isset($env['DB_NAME']) ? $env['DB_NAME'] : '';
$dbUser = isset($env['DB_USER']) ? $env['DB_USER'] : '';
$dbPass = isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : '';
$dbPort = isset($env['DB_PORT']) ? $env['DB_PORT'] : '3306';
$dbPrefix = isset($env['DB_PREFIX']) ? $env['DB_PREFIX'] : 'yg_';

try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. 获取short_dingyue所有qq
    $shortQq = [];
    $stmt = $pdo->query("SELECT qq FROM {$dbPrefix}short_dingyue");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $shortQq[] = $row['qq'];
    }

    // 2. 获取admin所有username
    $stmt = $pdo->query("SELECT id, username FROM {$dbPrefix}admin");
    $deleteCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['id'];
        $username = $row['username'];
        if (!in_array($username, $shortQq)) {
            // 3. 删除不在short表中的用户
            $pdo->exec("DELETE FROM {$dbPrefix}admin WHERE id = $id");
            echo "已删除用户：$username (id=$id)\n";
            $deleteCount++;
        }
    }
    echo "同步完成，共删除 $deleteCount 个用户。\n";

} catch (PDOException $e) {
    echo "数据库连接失败：" . $e->getMessage() . "\n";
    exit(1);
}
