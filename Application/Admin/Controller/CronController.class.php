<?php
/**
 * 定时任务管理控制器
 * 通过 PHP 直接调用任务类，不依赖 exec() / posix / nohup
 */
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class CronController extends AdminBaseController
{

    /** 调度配置文件路径 */
    private function scheduleFile()
    {
        return RUNTIME_PATH . 'cron_schedule.json';
    }

    /** 读取调度配置 */
    private function getScheduleConfig()
    {
        $file = $this->scheduleFile();
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data))
                return $data;
        }
        return array(
            'email_queue' => array('cron' => '*/1 * * * *', 'label' => '每1分钟'),
            'clean_email' => array('cron' => '0 3 * * *', 'label' => '每天凌晨3点'),
            'log_rotate' => array('cron' => '0 2 * * *', 'label' => '每天凌晨2点'),
            'log_clean' => array('cron' => '5 2 * * *', 'label' => '每天凌晨2:05'),
            'expire_remind' => array('cron' => '0 9 * * *', 'label' => '每天上午9点'),
            'sync_user' => array('cron' => '0 4 * * 1', 'label' => '每周一凌晨4点'),
        );
    }

    /** 保存调度配置 */
    private function saveScheduleConfig($config)
    {
        file_put_contents($this->scheduleFile(), json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** 任务定义 */
    private function getTaskList()
    {
        $schedule = $this->getScheduleConfig();
        return array(
            'email_queue' => array(
                'name' => '处理邮件队列',
                'desc' => '发送待发邮件，支持重试和失败处理',
                'schedule' => $schedule['email_queue']['label'],
                'cron' => $schedule['email_queue']['cron'],
                'icon' => 'fa-envelope',
            ),
            'clean_email' => array(
                'name' => '清理邮件队列',
                'desc' => '删除7天前已发送的邮件记录',
                'schedule' => $schedule['clean_email']['label'],
                'cron' => $schedule['clean_email']['cron'],
                'icon' => 'fa-eraser',
            ),
            'log_rotate' => array(
                'name' => '日志轮转',
                'desc' => '压缩超过10MB的日志文件',
                'schedule' => $schedule['log_rotate']['label'],
                'cron' => $schedule['log_rotate']['cron'],
                'icon' => 'fa-archive',
            ),
            'log_clean' => array(
                'name' => '日志清理',
                'desc' => '删除30天前的旧日志',
                'schedule' => $schedule['log_clean']['label'],
                'cron' => $schedule['log_clean']['cron'],
                'icon' => 'fa-trash',
            ),
            'expire_remind' => array(
                'name' => '到期提醒',
                'desc' => '为7天内到期的用户生成提醒邮件',
                'schedule' => $schedule['expire_remind']['label'],
                'cron' => $schedule['expire_remind']['cron'],
                'icon' => 'fa-bell',
            ),
            'sync_user' => array(
                'name' => '用户同步',
                'desc' => '清理无订阅记录的孤立用户',
                'schedule' => $schedule['sync_user']['label'],
                'cron' => $schedule['sync_user']['cron'],
                'icon' => 'fa-users',
            ),
        );
    }

    /** 日志文件路径 */
    private function logFile()
    {
        $dir = RUNTIME_PATH . 'Logs/cron/';
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        return $dir . 'cron_' . date('Ymd') . '.log';
    }

    /** 直接执行任务（PHP 内调用，不依赖 exec） */
    private function executeTask($taskId)
    {
        $startTime = microtime(true);
        $result = array('code' => 1, 'msg' => '未知任务');

        try {
            switch ($taskId) {
                case 'email_queue':
                    $result = $this->doEmailQueue();
                    break;
                case 'clean_email':
                    $result = $this->doCleanEmail();
                    break;
                case 'log_rotate':
                    $result = $this->doLogRotate();
                    break;
                case 'log_clean':
                    $result = $this->doLogClean();
                    break;
                case 'expire_remind':
                    $result = $this->doExpireRemind();
                    break;
                case 'sync_user':
                    $result = $this->doSyncUser();
                    break;
                default:
                    return array('code' => 1, 'msg' => "未知任务: {$taskId}");
            }
        } catch (\Exception $e) {
            $result = array('code' => 1, 'msg' => '异常: ' . $e->getMessage());
        }

        $duration = round(microtime(true) - $startTime, 2);
        $result['duration'] = $duration . 's';
        return $result;
    }

    // ==================== 各任务实现 ====================

    private function doEmailQueue()
    {
        try {
            require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
            $queue = new \EmailQueue();
            $emails = $queue->getPendingEmails(10);

            // 可能是由于环境丢失或者是无法连接，加一个明确的验证
            if (!is_array($emails)) {
                return array('code' => 1, 'msg' => '获取待发邮件失败，可能数据库配置异常。');
            }

            $success = 0;
            $fail = 0;
            foreach ($emails as $email) {
                $queue->markAsProcessing($email['id']);
                $ok = send_mail_direct($email['to_email'], $email['subject'], $email['body']);
                if ($ok) {
                    $queue->markAsSent($email['id']);
                    $success++;
                } else {
                    $queue->markAsFailed($email['id'], '发送失败');
                    $fail++;
                }
                usleep(100000); // 防刷间隔
            }
            return array('code' => 0, 'msg' => "成功{$success}, 失败{$fail}, 共" . count($emails) . "封");
        } catch (\Exception $e) {
            return array('code' => 1, 'msg' => '致命错误: ' . $e->getMessage());
        } catch (\Error $err) {
            return array('code' => 1, 'msg' => '代码错误: ' . $err->getMessage());
        }
    }

    private function doCleanEmail()
    {
        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        $queue = new \EmailQueue();
        $cleaned = $queue->cleanSentEmails(7);
        return array('code' => 0, 'msg' => "清理{$cleaned}条记录");
    }

    private function doLogRotate()
    {
        require_once dirname(APP_PATH) . '/shell/log_manager.php';
        ob_start();
        $manager = new \LogManager();
        $rotated = $manager->rotateLogs();
        ob_end_clean();
        return array('code' => 0, 'msg' => "轮转{$rotated}个文件");
    }

    private function doLogClean()
    {
        require_once dirname(APP_PATH) . '/shell/log_manager.php';
        ob_start();
        $manager = new \LogManager();
        $cleaned = $manager->cleanOldLogs();
        ob_end_clean();
        return array('code' => 0, 'msg' => "清理{$cleaned}个文件");
    }

    private function doExpireRemind()
    {
        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        require_once APP_PATH . 'Common/Common/EmailTemplate.class.php';
        $queue = new \EmailQueue();
        $now = time();
        $sevenDaysLater = $now + 7 * 86400;
        $prefix = C('DB_PREFIX');
        $users = M()->query("SELECT qq, endtime FROM {$prefix}short_dingyue WHERE status = 1 AND endtime <= {$sevenDaysLater}");
        if (empty($users)) {
            return array('code' => 0, 'msg' => '无需提醒');
        }
        $queued = 0;
        $skipped = 0;
        $oneDayAgo = $now - 86400;
        foreach ($users as $user) {
            $qq = $user['qq'];
            $endtime = intval($user['endtime']);
            $email = $qq . '@qq.com';
            $recentCheck = M('email_queue')->where(array(
                'to_email' => $email,
                'type' => 'expiration',
                'created_at' => array('gt', $oneDayAgo)
            ))->count();
            if ($recentCheck > 0) {
                $skipped++;
                continue;
            }
            $isExpired = ($endtime <= $now);
            $body = \EmailTemplate::getExpirationTemplate($qq, $endtime, $isExpired);
            $subject = $isExpired ? '您的订阅已到期' : '您的订阅即将到期';
            $queue->addToQueue($email, $subject, $body, 'expiration', 3);
            $queued++;
        }
        return array('code' => 0, 'msg' => "入队{$queued}, 跳过{$skipped}");
    }

    private function doSyncUser()
    {
        $prefix = C('DB_PREFIX');
        $qqRows = M()->query("SELECT qq FROM {$prefix}short_dingyue");
        $qqList = array();
        foreach ($qqRows as $row) {
            $qqList[] = "'" . addslashes($row['qq']) . "'";
        }
        if (empty($qqList)) {
            return array('code' => 1, 'msg' => 'short_dingyue 表为空，跳过同步');
        }
        $qqIn = implode(',', $qqList);
        $deleted = M()->execute("DELETE FROM {$prefix}user WHERE username NOT IN ({$qqIn})");
        return array('code' => 0, 'msg' => "删除{$deleted}个孤立用户");
    }

    // ==================== 页面和 AJAX 接口 ====================

    /**
     * 检查邮件守护进程是否运行
     */
    private function checkDaemonStatus()
    {
        // 查找 process_email_queue.php 进程
        $output = array();
        exec("ps -ef | grep process_email_queue.php | grep -v grep", $output);
        return count($output) > 0;
    }

    /** 任务列表页 */
    public function index()
    {
        $tasks = $this->getTaskList();
        $logFile = $this->logFile();
        $logs = array();
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if ($data)
                    $logs[$data['task']] = $data;
            }
        }
        $this->assign('tasks', $tasks);
        $this->assign('logs', $logs);
        $this->assign('scheduleConfig', $this->getScheduleConfig());
        $this->assign('daemonRunning', $this->checkDaemonStatus());
        $this->display();
    }

    /** 切换邮件守护进程状态（AJAX） */
    public function toggleDaemon()
    {
        if (!IS_AJAX)
            $this->error('非法请求');
        $isRunning = $this->checkDaemonStatus();
        $scriptPath = dirname(APP_PATH) . '/shell/process_email_queue.php';

        if ($isRunning) {
            // 停止进程
            exec("pkill -f process_email_queue.php");
            // 等待一秒以确保停止
            sleep(1);
            $runningNow = $this->checkDaemonStatus();
            if (!$runningNow) {
                $this->ajaxReturn(array('code' => 0, 'msg' => '守护进程已停止', 'data' => array('running' => false)));
            } else {
                $this->ajaxReturn(array('code' => 1, 'msg' => '停止失败，请手动检查进程'));
            }
        } else {
            // 启动进程
            if (!file_exists($scriptPath)) {
                $this->ajaxReturn(array('code' => 1, 'msg' => '守护脚本文件丢失'));
            }

            // 使用 nohup 后台运行脚本，守护模式
            $shPath = dirname(APP_PATH) . '/shell/process_email_queue.sh';
            $logPath = RUNTIME_PATH . 'Logs/daemon_error.log';
            if (!is_dir(dirname($logPath))) {
                @mkdir(dirname($logPath), 0755, true);
            }

            // 确保脚本有可执行权限
            if (file_exists($shPath)) {
                @chmod($shPath, 0755);
                exec("nohup bash {$shPath} > /dev/null 2>> {$logPath} &");
            } else {
                exec("nohup php {$scriptPath} daemon > /dev/null 2>> {$logPath} &");
            }

            sleep(1);
            $runningNow = $this->checkDaemonStatus();

            // 如果拉起失败，尝试读取错误日志返回前台
            if (!$runningNow) {
                $errMsg = '进程启动后立即退出，可能没有执行权限或环境异常';
                if (file_exists($logPath)) {
                    $errLog = file_get_contents($logPath);
                    if ($errLog) {
                        $errMsg .= " - [日志]: " . substr($errLog, -100);
                    }
                }
                $this->ajaxReturn(array('code' => 1, 'msg' => $errMsg, 'data' => array('running' => false)));
            }

            $this->ajaxReturn(array('code' => 0, 'msg' => '守护进程已成功启动', 'data' => array('running' => true)));
        }
    }

    /** 手动执行任务（AJAX） */
    public function run()
    {
        if (!IS_AJAX)
            $this->error('非法请求');
        $taskId = I('post.task_id', '');
        $tasks = $this->getTaskList();
        if (!isset($tasks[$taskId])) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '任务不存在'));
        }

        set_time_limit(120);
        $result = $this->executeTask($taskId);

        $record = array(
            'task' => $taskId,
            'name' => $tasks[$taskId]['name'],
            'time' => date('Y-m-d H:i:s'),
            'duration' => $result['duration'],
            'status' => $result['code'] === 0 ? 'success' : 'failed',
            'output' => $result['msg'],
            'operator' => session('admin.username'),
        );
        file_put_contents($this->logFile(), json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

        $this->ajaxReturn(array(
            'code' => $result['code'],
            'msg' => $result['code'] === 0 ? '执行成功' : '执行失败: ' . $result['msg'],
            'data' => $record,
        ));
    }

    /** 保存调度设置（AJAX） */
    public function saveSchedule()
    {
        if (!IS_AJAX)
            $this->error('非法请求');
        $config = array();
        $taskIds = array('email_queue', 'clean_email', 'log_rotate', 'log_clean', 'expire_remind', 'sync_user');
        foreach ($taskIds as $tid) {
            $cron = I('post.' . $tid . '_cron', '');
            $label = I('post.' . $tid . '_label', '');
            if ($cron && $label) {
                $config[$tid] = array('cron' => $cron, 'label' => $label);
            }
        }
        if (empty($config)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '配置为空'));
        }
        $this->saveScheduleConfig($config);
        $this->ajaxReturn(array('code' => 0, 'msg' => '保存成功'));
    }

    /** 查看任务日志（AJAX） */
    public function logs()
    {
        $taskId = I('get.task_id', '');
        $days = I('get.days', 7, 'intval');
        $result = array();
        for ($i = 0; $i < $days; $i++) {
            $date = date('Ymd', strtotime("-{$i} days"));
            $file = RUNTIME_PATH . 'Logs/cron/cron_' . $date . '.log';
            if (!file_exists($file))
                continue;
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if ($data && (empty($taskId) || $data['task'] === $taskId)) {
                    $result[] = $data;
                }
            }
        }
        usort($result, function ($a, $b) {
            return strcmp($b['time'], $a['time']);
        });
        $this->ajaxReturn(array('code' => 0, 'data' => array_slice($result, 0, 100)));
    }

    /** 生成 crontab 配置（AJAX） */
    public function crontab()
    {
        $secret = env('CRON_SECRET', '');
        $domain = env('SITE_DOMAIN', 'dy.moneyfly.club');
        $schedule = $this->getScheduleConfig();
        $lines = array("# === 订阅系统定时任务（通过 curl 调用 Web 接口） ===");
        foreach ($schedule as $tid => $cfg) {
            $url = "https://{$domain}/?s=/Home/Cron/run&secret={$secret}&task={$tid}";
            $lines[] = "{$cfg['cron']} curl -s \"{$url}\" > /dev/null 2>&1";
        }
        $lines[] = "";
        $lines[] = "# 或者一次性执行所有常用任务：";
        $lines[] = "*/5 * * * * curl -s \"https://{$domain}/?s=/Home/Cron/runAll&secret={$secret}\" > /dev/null 2>&1";
        $this->ajaxReturn(array('code' => 0, 'data' => implode("\n", $lines)));
    }

    /** 隐形执行接口（适用于 WebCron 触发或者前台 Ajax 轮询触发），免鉴权且只在发送失败或大批量时输出日志 */
    public function heartbeat()
    {
        // 允许较长执行时间，免权
        set_time_limit(120);
        $result = $this->executeTask('email_queue');

        // 仅在真的有邮件需要发送或者发送产生错误时写入日志
        if (strpos($result['msg'], '成功0, 失败0') === false) {
            $record = array(
                'task' => 'email_queue',
                'name' => '处理邮件队列 (Heartbeat)',
                'time' => date('Y-m-d H:i:s'),
                'duration' => $result['duration'],
                'status' => $result['code'] === 0 ? 'success' : 'failed',
                'output' => $result['msg'],
                'operator' => 'system_heartbeat',
            );
            file_put_contents($this->logFile(), json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        }

        $this->ajaxReturn(array('code' => 0, 'msg' => 'beat'));
    }
}
