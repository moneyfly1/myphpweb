#!/usr/bin/env php
<?php
/**
 * 邮件队列处理脚本
 * 用于定时处理邮件队列中的邮件
 * 
 * 使用方法：
 * 1. 守护进程模式：php process_email_queue.php daemon
 * 2. 单次处理模式：php process_email_queue.php process
 * 3. 显示队列状态：php process_email_queue.php status
 * 4. 清理已发送邮件：php process_email_queue.php clean
 */

// 设置脚本执行时间限制
set_time_limit(0);
ini_set('memory_limit', '256M');

// 获取脚本所在目录（脚本位于 shell/ 子目录，项目根目录在上一级）
$scriptDir = dirname(dirname(__FILE__));

// 加载引导文件（定义常量、加载.env、加载function.php）
require_once __DIR__ . '/bootstrap.php';

// 邮件队列处理类
class EmailQueueProcessor
{

    private $isRunning = false;
    private $pidFile;
    private $logFile;
    private $mysqli;
    private $prefix = 'yg_';

    public function __construct()
    {
        global $scriptDir;
        $this->pidFile = $scriptDir . '/Application/Runtime/email_queue.pid';
        $this->logFile = $scriptDir . '/Application/Runtime/Logs/email_queue.log';

        // 确保日志目录存在
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // 连接数据库
        $this->connectDatabase();
    }

    /**
     * 读取 .env 文件
     */
    private function loadEnv()
    {
        global $scriptDir;
        $envFile = dirname($scriptDir) . '/.env';
        $envVars = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0)
                    continue;
                list($name, $value) = explode('=', $line, 2);
                $envVars[trim($name)] = trim($value);
            }
        }
        return $envVars;
    }

    /**
     * 连接数据库
     */
    private function connectDatabase()
    {
        try {
            // 检查mysqli扩展
            if (!extension_loaded('mysqli')) {
                throw new Exception('mysqli扩展未安装，请安装php_mysqli扩展');
            }

            $envVars = $this->loadEnv();

            $host = env('DB_HOST') ?: (isset($envVars['DB_HOST']) ? $envVars['DB_HOST'] : '127.0.0.1');
            $dbname = env('DB_NAME') ?: (isset($envVars['DB_NAME']) ? $envVars['DB_NAME'] : 'tempadmin');
            $username = env('DB_USER') ?: (isset($envVars['DB_USER']) ? $envVars['DB_USER'] : 'tempadmin');
            $password = env('DB_PASSWORD') ?: (isset($envVars['DB_PASSWORD']) ? $envVars['DB_PASSWORD'] : '');
            $port = env('DB_PORT') ?: (isset($envVars['DB_PORT']) ? $envVars['DB_PORT'] : '3306');
            $prefix = env('DB_PREFIX') ?: (isset($envVars['DB_PREFIX']) ? $envVars['DB_PREFIX'] : 'yg_');

            $this->prefix = $prefix;

            $this->mysqli = new mysqli($host, $username, $password, $dbname, $port);

            if ($this->mysqli->connect_error) {
                throw new Exception('数据库连接失败: ' . $this->mysqli->connect_error);
            }

            $this->mysqli->set_charset('utf8mb4');

            $this->log("数据库连接成功");
        } catch (Exception $e) {
            $this->log("数据库连接失败: " . $e->getMessage());
            exit(1);
        }
    }

    /**
     * 守护进程模式
     */
    public function daemon()
    {
        // 检查是否已经在运行
        if ($this->isProcessRunning()) {
            $this->log("邮件队列处理进程已在运行");
            return;
        }

        // 创建PID文件
        file_put_contents($this->pidFile, getmypid());

        $this->log("邮件队列守护进程启动，PID: " . getmypid());

        // 注册信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
        }

        $this->isRunning = true;

        while ($this->isRunning) {
            try {
                // 自动清理到期超过6个月的用户
                $this->cleanExpiredUsers();
                $this->processQueue();

                // 处理完队列后自动清理7天前已发送邮件
                $cleaned = $this->cleanSentEmails(7);
                $this->log("自动清理了 {$cleaned} 条7天前已发送的邮件记录");

                // 处理信号
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // 休眠10秒
                sleep(10);

            } catch (Exception $e) {
                $this->log("处理队列时发生错误: " . $e->getMessage());
                sleep(60); // 发生错误时休眠更长时间
            }
        }

        $this->cleanup();
    }

    /**
     * 单次处理模式
     */
    public function process()
    {
        $this->log("开始处理邮件队列");
        // 自动清理到期超过6个月的用户
        $this->cleanExpiredUsers();
        $processed = $this->processQueue();
        $this->log("处理完成，共处理 {$processed} 封邮件");
        // 处理完队列后自动清理7天前已发送邮件
        $cleaned = $this->cleanSentEmails(7);
        $this->log("自动清理了 {$cleaned} 条7天前已发送的邮件记录");
        return $processed;
    }

    /**
     * 显示队列状态
     */
    public function status()
    {
        try {
            $stats = $this->getQueueStatistics();

            echo "=== 邮件队列状态 ===\n";
            echo "待发送: {$stats['pending']}\n";
            echo "处理中: {$stats['processing']}\n";
            echo "已发送: {$stats['sent']}\n";
            echo "发送失败: {$stats['failed']}\n";
            echo "总计: {$stats['total']}\n";

            // 检查进程状态
            if ($this->isProcessRunning()) {
                $pid = file_get_contents($this->pidFile);
                echo "守护进程状态: 运行中 (PID: {$pid})\n";
            } else {
                echo "守护进程状态: 未运行\n";
            }

        } catch (Exception $e) {
            echo "获取状态失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 清理已发送和已失败的邮件记录
     */
    public function clean($days = 30)
    {
        try {
            $cleaned = $this->cleanSentEmails($days);
            echo "清理完成，删除了 {$cleaned} 条{$days}天前的已发送和已失败邮件记录\n";
            $this->log("清理了 {$cleaned} 条{$days}天前的已发送和已失败邮件记录");
        } catch (Exception $e) {
            echo "清理失败: " . $e->getMessage() . "\n";
            $this->log("清理失败: " . $e->getMessage());
        }
    }

    /**
     * 处理邮件队列
     */
    private function processQueue()
    {
        try {
            $processed = 0;
            $emails = $this->getPendingEmails(10);

            foreach ($emails as $email) {
                $processed++;

                // 标记为处理中
                $this->markAsProcessing($email['id']);

                // 发送邮件
                $result = $this->sendEmail($email);

                if ($result) {
                    $this->markAsSent($email['id']);
                    $this->log("邮件发送成功: {$email['to_email']}");
                } else {
                    $this->markAsFailed($email['id'], '邮件发送失败');
                    $this->log("邮件发送失败: {$email['to_email']}");
                }

                // 添加小延迟避免过快发送
                usleep(100000); // 0.1秒
            }

            if ($processed > 0) {
                $this->log("处理了 {$processed} 封邮件");
            }

            return $processed;

        } catch (Exception $e) {
            $this->log("处理队列失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取待发送的邮件
     */
    private function getPendingEmails($limit = 10)
    {
        $time = time();
        $sql = "SELECT * FROM {$this->prefix}email_queue
                WHERE status = 'pending'
                AND scheduled_at <= {$time}
                AND retry_count < max_retries
                ORDER BY priority ASC, created_at ASC
                LIMIT {$limit}";

        $result = $this->mysqli->query($sql);
        if (!$result) {
            $this->log("getPendingEmails SQL错误: " . $this->mysqli->error);
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * 标记邮件为处理中
     */
    private function markAsProcessing($id)
    {
        $sql = "UPDATE {$this->prefix}email_queue SET status = 'processing', updated_at = ? WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('ii', $time, $id);
        $time = time();
        $stmt->execute();
        $stmt->close();
    }

    /**
     * 标记邮件发送成功
     */
    private function markAsSent($id)
    {
        $sql = "UPDATE {$this->prefix}email_queue SET status = 'sent', sent_at = ?, updated_at = ? WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('iii', $time, $time, $id);
        $time = time();
        $stmt->execute();
        $stmt->close();
    }

    /**
     * 标记邮件发送失败
     */
    private function markAsFailed($id, $error = '')
    {
        // 先获取当前重试次数
        $sql = "SELECT retry_count, max_retries FROM {$this->prefix}email_queue WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $email = $result->fetch_assoc();
        $stmt->close();

        if (!$email) {
            return false;
        }

        $retryCount = $email['retry_count'] + 1;
        $status = $retryCount >= $email['max_retries'] ? 'failed' : 'pending';
        $time = time();

        if ($status === 'pending') {
            $delay = pow(2, $retryCount) * 60; // 2^n 分钟后重试
            $scheduledAt = $time + $delay;
            $sql = "UPDATE {$this->prefix}email_queue SET status = ?, retry_count = ?, error_message = ?, updated_at = ?, scheduled_at = ? WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('sissii', $status, $retryCount, $error, $time, $scheduledAt, $id);
        } else {
            $sql = "UPDATE {$this->prefix}email_queue SET status = ?, retry_count = ?, error_message = ?, updated_at = ? WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('sisii', $status, $retryCount, $error, $time, $id);
        }

        $stmt->execute();
        $stmt->close();
        return true;
    }

    /**
     * 发送单个邮件
     */
    private function sendEmail($email)
    {
        try {
            // 使用直接发送函数
            return send_mail_direct($email['to_email'], $email['subject'], $email['body']);
        } catch (Exception $e) {
            $this->log('发送邮件失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取队列统计信息
     */
    private function getQueueStatistics()
    {
        $stats = [];

        $sql = "SELECT status, COUNT(*) as count FROM {$this->prefix}email_queue GROUP BY status";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);

        $stats['pending'] = 0;
        $stats['processing'] = 0;
        $stats['sent'] = 0;
        $stats['failed'] = 0;

        foreach ($results as $row) {
            $stats[$row['status']] = $row['count'];
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->prefix}email_queue";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->execute();
        $stats['total'] = $stmt->get_result()->fetch_assoc()['total'];

        $stmt->close();

        return $stats;
    }

    /**
     * 清理已发送和已失败的邮件记录
     */
    private function cleanSentEmails($days = 30)
    {
        $totalAffected = 0;

        if ($days > 0) {
            $cutoffTime = time() - ($days * 24 * 60 * 60);

            // 清理已发送的邮件
            $sql = "DELETE FROM {$this->prefix}email_queue WHERE status = 'sent' AND sent_at < ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('i', $cutoffTime);
            $stmt->execute();
            $sentAffected = $stmt->affected_rows;
            $stmt->close();

            // 清理已失败的邮件
            $sql = "DELETE FROM {$this->prefix}email_queue WHERE status = 'failed' AND updated_at < ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('i', $cutoffTime);
            $stmt->execute();
            $failedAffected = $stmt->affected_rows;
            $stmt->close();

            $totalAffected = $sentAffected + $failedAffected;
            $this->log("清理邮件记录：已发送 {$sentAffected} 条，已失败 {$failedAffected} 条，总计 {$totalAffected} 条");
        } else {
            // 清理所有已发送和已失败的邮件
            $sql = "DELETE FROM {$this->prefix}email_queue WHERE status IN ('sent', 'failed')";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->execute();
            $totalAffected = $stmt->affected_rows;
            $stmt->close();
            $this->log("清理了所有已发送和已失败的邮件记录：{$totalAffected} 条");
        }

        return $totalAffected;
    }

    /**
     * 自动清理到期超过6个月的用户及其所有信息
     */
    private function cleanExpiredUsers()
    {
        $sixMonthsAgo = time() - 6 * 30 * 24 * 60 * 60;

        try {
            // 检查主表是否存在
            if (!$this->tableExists($this->prefix . 'shortdingyue')) {
                $this->log("表 {$this->prefix}shortdingyue 不存在，跳过清理过期用户");
                return 0;
            }

            $sql = "SELECT qq FROM {$this->prefix}shortdingyue WHERE endtime > 0 AND endtime < ?";
            $stmt = $this->mysqli->prepare($sql);
            if ($stmt === false) {
                throw new Exception("准备SQL语句失败: " . $this->mysqli->error);
            }

            $stmt->bind_param('i', $sixMonthsAgo);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $deletedCount = 0;

            foreach ($users as $user) {
                $qq = $user['qq'];

                try {
                    $this->mysqli->begin_transaction();

                    // 删除订阅信息 - 检查每个表是否存在
                    $tables = [
                        $this->prefix . 'shortdingyue' => 'qq',
                        $this->prefix . 'dingyue' => 'qq',
                        $this->prefix . 'short_dingyue_history' => 'qq',
                        $this->prefix . 'user' => 'username'
                    ];

                    foreach ($tables as $table => $field) {
                        if ($this->tableExists($table)) {
                            $sql = "DELETE FROM {$table} WHERE {$field} = ?";
                            $stmt = $this->mysqli->prepare($sql);
                            if ($stmt === false) {
                                throw new Exception("准备删除语句失败: " . $this->mysqli->error);
                            }
                            $stmt->bind_param('s', $qq);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }

                    // 删除邮件队列
                    $email = $qq . "@qq.com";
                    if ($this->tableExists($this->prefix . 'email_queue')) {
                        $sql = "DELETE FROM {$this->prefix}email_queue WHERE to_email = ?";
                        $stmt = $this->mysqli->prepare($sql);
                        if ($stmt === false) {
                            throw new Exception("准备邮件队列删除语句失败: " . $this->mysqli->error);
                        }
                        $stmt->bind_param('s', $email);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // 删除设备日志（如果表存在）
                    if ($this->tableExists($this->prefix . 'device_log')) {
                        $sql = "DELETE FROM {$this->prefix}device_log WHERE qq = ?";
                        $stmt = $this->mysqli->prepare($sql);
                        if ($stmt === false) {
                            throw new Exception("准备设备日志删除语句失败: " . $this->mysqli->error);
                        }
                        $stmt->bind_param('s', $qq);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $this->mysqli->commit();
                    $deletedCount++;
                    $this->log("已清理到期超过6个月的用户及其所有信息: {$qq}");

                } catch (Exception $e) {
                    $this->mysqli->rollback();
                    $this->log("清理用户 {$qq} 失败: " . $e->getMessage());
                }
            }

            if ($deletedCount > 0) {
                $this->log("共清理了 {$deletedCount} 个到期超过6个月的用户及其所有信息");
            }

            return $deletedCount;

        } catch (Exception $e) {
            $this->log("清理过期用户失败: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 检查表是否存在
     */
    private function tableExists($tableName)
    {
        $result = $this->mysqli->query("SHOW TABLES LIKE '{$tableName}'");
        return $result->num_rows > 0;
    }

    /**
     * 检查进程是否在运行
     */
    private function isProcessRunning()
    {
        if (!file_exists($this->pidFile)) {
            return false;
        }

        $pid = file_get_contents($this->pidFile);
        if (!$pid) {
            return false;
        }

        // 检查进程是否存在
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // 在没有posix扩展的情况下，简单检查PID文件
        return true;
    }

    /**
     * 信号处理
     */
    public function signalHandler($signal)
    {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->log("收到停止信号，正在关闭守护进程");
                $this->isRunning = false;
                break;
        }
    }

    /**
     * 清理资源
     */
    private function cleanup()
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
        $this->log("邮件队列守护进程已停止");
    }

    /**
     * 记录日志
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        // 写入日志文件
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        // 输出到控制台
        echo $logMessage;
    }
}

// 主程序
if (php_sapi_name() !== 'cli') {
    die('此脚本只能在命令行模式下运行');
}

$processor = new EmailQueueProcessor();

// 解析命令行参数
$command = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'help';

switch ($command) {
    case 'daemon':
        $processor->daemon();
        break;

    case 'process':
        $processor->process();
        break;

    case 'status':
        $processor->status();
        break;

    case 'clean':
        $days = isset($_SERVER['argv'][2]) ? intval($_SERVER['argv'][2]) : 7;
        $processor->clean($days);
        break;

    case 'help':
    default:
        echo "邮件队列处理脚本\n";
        echo "使用方法：\n";
        echo "  php process_email_queue.php daemon   # 守护进程模式\n";
        echo "  php process_email_queue.php process  # 单次处理模式\n";
        echo "  php process_email_queue.php status   # 显示队列状态\n";
        echo "  php process_email_queue.php clean    # 清理已发送邮件\n";
        echo "  php process_email_queue.php help     # 显示帮助信息\n";
        break;
}