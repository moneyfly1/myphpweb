<?php
/**
 * 定时任务管理控制器
 * 管理和执行系统定时任务（邮件队列、日志清理、用户同步、到期提醒等）
 */
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class CronController extends AdminBaseController {

    /** 调度配置文件路径 */
    private function scheduleFile() {
        return RUNTIME_PATH . 'cron_schedule.json';
    }

    /** 读取自定义调度配置 */
    private function getScheduleConfig() {
        $file = $this->scheduleFile();
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) return $data;
        }
        // 默认配置
        return array(
            'email_queue'   => array('cron' => '*/1 * * * *', 'label' => '每1分钟'),
            'clean_email'   => array('cron' => '0 3 * * *',   'label' => '每天凌晨3点'),
            'log_rotate'    => array('cron' => '0 2 * * *',   'label' => '每天凌晨2点'),
            'log_clean'     => array('cron' => '5 2 * * *',   'label' => '每天凌晨2:05'),
            'expire_remind' => array('cron' => '0 9 * * *',   'label' => '每天上午9点'),
            'sync_user'     => array('cron' => '0 4 * * 1',   'label' => '每周一凌晨4点'),
        );
    }

    /** 保存调度配置 */
    private function saveScheduleConfig($config) {
        file_put_contents($this->scheduleFile(), json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** 任务定义 */
    private function getTaskList() {
        $root = dirname(APP_PATH);
        $php  = PHP_BINARY ?: '/usr/bin/php';
        $schedule = $this->getScheduleConfig();
        return array(
            'email_queue' => array(
                'name'     => '处理邮件队列',
                'desc'     => '发送待发邮件，支持重试和失败处理',
                'cmd'      => $php . ' ' . $root . '/shell/process_email_queue.php process',
                'schedule' => $schedule['email_queue']['label'],
                'cron'     => $schedule['email_queue']['cron'],
            ),
            'clean_email' => array(
                'name'     => '清理邮件队列',
                'desc'     => '删除7天前已发送的邮件记录',
                'cmd'      => $php . ' ' . $root . '/shell/process_email_queue.php clean',
                'schedule' => $schedule['clean_email']['label'],
                'cron'     => $schedule['clean_email']['cron'],
            ),
            'log_rotate' => array(
                'name'     => '日志轮转',
                'desc'     => '压缩超过10MB的日志文件',
                'cmd'      => $php . ' ' . $root . '/shell/log_manager.php rotate',
                'schedule' => $schedule['log_rotate']['label'],
                'cron'     => $schedule['log_rotate']['cron'],
            ),
            'log_clean' => array(
                'name'     => '日志清理',
                'desc'     => '删除30天前的旧日志',
                'cmd'      => $php . ' ' . $root . '/shell/log_manager.php clean',
                'schedule' => $schedule['log_clean']['label'],
                'cron'     => $schedule['log_clean']['cron'],
            ),
            'expire_remind' => array(
                'name'     => '到期提醒',
                'desc'     => '为7天内到期的用户生成提醒邮件',
                'cmd'      => $php . ' ' . $root . '/shell/generate_expire_mail.php',
                'schedule' => $schedule['expire_remind']['label'],
                'cron'     => $schedule['expire_remind']['cron'],
            ),
            'sync_user' => array(
                'name'     => '用户同步',
                'desc'     => '清理无订阅记录的孤立用户',
                'cmd'      => $php . ' ' . $root . '/shell/sync_user_with_short.php',
                'schedule' => $schedule['sync_user']['label'],
                'cron'     => $schedule['sync_user']['cron'],
            ),
        );
    }

    /** 日志文件路径 */
    private function logFile() {
        $dir = RUNTIME_PATH . 'Logs/cron/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir . 'cron_' . date('Ymd') . '.log';
    }

    /** 任务列表页 */
    public function index() {
        $tasks = $this->getTaskList();
        $logFile = $this->logFile();
        $logs = array();
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if ($data) $logs[$data['task']] = $data;
            }
        }
        // 检查邮件守护进程状态
        $root = dirname(APP_PATH);
        $pidFile = $root . '/Application/Runtime/email_queue.pid';
        $daemonRunning = false;
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && file_exists("/proc/{$pid}")) {
                $daemonRunning = true;
            }
        }
        $this->assign('tasks', $tasks);
        $this->assign('logs', $logs);
        $this->assign('daemonRunning', $daemonRunning);
        $this->assign('scheduleConfig', $this->getScheduleConfig());
        $this->display();
    }

    /** 手动执行任务（AJAX） */
    public function run() {
        if (!IS_AJAX) $this->error('非法请求');
        $taskId = I('post.task_id', '');
        $tasks  = $this->getTaskList();
        if (!isset($tasks[$taskId])) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '任务不存在'));
        }
        $task = $tasks[$taskId];
        $cmd  = $task['cmd'] . ' 2>&1';

        $startTime = microtime(true);
        $output = array();
        $retval = 0;
        exec($cmd, $output, $retval);
        $duration = round(microtime(true) - $startTime, 2);
        $outputStr = implode("\n", $output);

        $record = array(
            'task'     => $taskId,
            'name'     => $task['name'],
            'time'     => date('Y-m-d H:i:s'),
            'duration' => $duration . 's',
            'status'   => $retval === 0 ? 'success' : 'failed',
            'output'   => mb_substr($outputStr, 0, 2000),
            'operator' => $_SESSION['admin']['username'],
        );
        file_put_contents($this->logFile(), json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

        $this->ajaxReturn(array(
            'code'     => $retval === 0 ? 0 : 1,
            'msg'      => $retval === 0 ? '执行成功' : '执行失败',
            'data'     => $record,
        ));
    }

    /** 保存调度设置（AJAX） */
    public function saveSchedule() {
        if (!IS_AJAX) $this->error('非法请求');
        $config = array();
        $tasks = array('email_queue','clean_email','log_rotate','log_clean','expire_remind','sync_user');
        foreach ($tasks as $tid) {
            $cron  = I('post.' . $tid . '_cron', '');
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

    /** 启动/停止邮件守护进程（AJAX） */
    public function toggleDaemon() {
        if (!IS_AJAX) $this->error('非法请求');
        $root = dirname(APP_PATH);
        $pidFile = $root . '/Application/Runtime/email_queue.pid';
        $php = PHP_BINARY ?: '/usr/bin/php';
        $script = $root . '/shell/process_email_queue.php';

        // 检查是否在运行
        $running = false;
        $pid = 0;
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && file_exists("/proc/{$pid}")) {
                $running = true;
            }
        }

        if ($running) {
            // 停止
            posix_kill((int)$pid, SIGTERM);
            sleep(1);
            @unlink($pidFile);
            $this->ajaxReturn(array('code' => 0, 'msg' => '守护进程已停止', 'data' => array('running' => false)));
        } else {
            // 启动守护进程（后台运行）
            $logFile = RUNTIME_PATH . 'Logs/email_daemon.log';
            $cmd = "nohup {$php} {$script} daemon >> {$logFile} 2>&1 &";
            exec($cmd);
            sleep(1);
            // 验证是否启动成功
            $started = false;
            if (file_exists($pidFile)) {
                $newPid = trim(file_get_contents($pidFile));
                if ($newPid && file_exists("/proc/{$newPid}")) {
                    $started = true;
                }
            }
            $this->ajaxReturn(array(
                'code' => $started ? 0 : 1,
                'msg'  => $started ? '守护进程已启动' : '启动失败，请检查日志',
                'data' => array('running' => $started),
            ));
        }
    }

    /** 查看任务日志（AJAX） */
    public function logs() {
        $taskId = I('get.task_id', '');
        $days   = I('get.days', 7, 'intval');
        $result = array();
        for ($i = 0; $i < $days; $i++) {
            $date = date('Ymd', strtotime("-{$i} days"));
            $file = RUNTIME_PATH . 'Logs/cron/cron_' . $date . '.log';
            if (!file_exists($file)) continue;
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if ($data && (empty($taskId) || $data['task'] === $taskId)) {
                    $result[] = $data;
                }
            }
        }
        usort($result, function($a, $b) { return strcmp($b['time'], $a['time']); });
        $this->ajaxReturn(array('code' => 0, 'data' => array_slice($result, 0, 100)));
    }

    /** 生成 crontab 配置（AJAX） */
    public function crontab() {
        $root  = dirname(APP_PATH);
        $php   = PHP_BINARY ?: '/usr/bin/php';
        $schedule = $this->getScheduleConfig();
        $tasks = $this->getTaskList();
        $lines = array("# === 订阅系统定时任务 ===");
        $cmdMap = array(
            'email_queue'   => $root . '/shell/process_email_queue.php process',
            'clean_email'   => $root . '/shell/process_email_queue.php clean',
            'log_rotate'    => $root . '/shell/log_manager.php rotate',
            'log_clean'     => $root . '/shell/log_manager.php clean',
            'expire_remind' => $root . '/shell/generate_expire_mail.php',
            'sync_user'     => $root . '/shell/sync_user_with_short.php',
        );
        foreach ($schedule as $tid => $cfg) {
            if (isset($cmdMap[$tid])) {
                $lines[] = "{$cfg['cron']} {$php} {$cmdMap[$tid]} >> /dev/null 2>&1";
            }
        }
        $this->ajaxReturn(array('code' => 0, 'data' => implode("\n", $lines)));
    }
}
