<?php
/**
 * 定时任务管理控制器
 * 管理和执行系统定时任务（邮件队列、日志清理、用户同步、到期提醒等）
 */
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class CronController extends AdminBaseController {

    /** 任务定义：id => [名称, 描述, 命令, 建议周期] */
    private function getTaskList() {
        $root = dirname(dirname(dirname(dirname(__DIR__))));
        $php  = PHP_BINARY ?: '/usr/bin/php';
        return array(
            'email_queue' => array(
                'name'     => '处理邮件队列',
                'desc'     => '发送待发邮件，支持重试和失败处理',
                'cmd'      => $php . ' ' . $root . '/shell/process_email_queue.php process',
                'schedule' => '每5分钟',
            ),
            'clean_email' => array(
                'name'     => '清理邮件队列',
                'desc'     => '删除7天前已发送的邮件记录',
                'cmd'      => $php . ' ' . $root . '/shell/process_email_queue.php clean',
                'schedule' => '每天凌晨3点',
            ),
            'log_rotate' => array(
                'name'     => '日志轮转',
                'desc'     => '压缩超过10MB的日志文件',
                'cmd'      => $php . ' ' . $root . '/shell/log_manager.php rotate',
                'schedule' => '每天凌晨2点',
            ),
            'log_clean' => array(
                'name'     => '日志清理',
                'desc'     => '删除30天前的旧日志',
                'cmd'      => $php . ' ' . $root . '/shell/log_manager.php clean',
                'schedule' => '每天凌晨2点',
            ),
            'expire_remind' => array(
                'name'     => '到期提醒',
                'desc'     => '为7天内到期的用户生成提醒邮件',
                'cmd'      => $php . ' ' . $root . '/shell/generate_expire_mail.php',
                'schedule' => '每天上午9点',
            ),
            'sync_user' => array(
                'name'     => '用户同步',
                'desc'     => '清理无订阅记录的孤立用户',
                'cmd'      => $php . ' ' . $root . '/shell/sync_user_with_short.php',
                'schedule' => '每周一凌晨4点',
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
        // 读取最近执行记录
        $logFile = $this->logFile();
        $logs = array();
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if ($data) $logs[$data['task']] = $data;
            }
        }
        $this->assign('tasks', $tasks);
        $this->assign('logs', $logs);
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

        // 记录日志
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
        // 按时间倒序
        usort($result, function($a, $b) { return strcmp($b['time'], $a['time']); });
        $this->ajaxReturn(array('code' => 0, 'data' => array_slice($result, 0, 100)));
    }

    /** 生成 crontab 配置（AJAX） */
    public function crontab() {
        $root  = dirname(dirname(dirname(dirname(__DIR__))));
        $php   = PHP_BINARY ?: '/usr/bin/php';
        $lines = array(
            "# === 订阅系统定时任务 ===",
            "*/5 * * * * {$php} {$root}/shell/process_email_queue.php process >> /dev/null 2>&1",
            "0 3 * * * {$php} {$root}/shell/process_email_queue.php clean >> /dev/null 2>&1",
            "0 2 * * * {$php} {$root}/shell/log_manager.php rotate >> /dev/null 2>&1",
            "5 2 * * * {$php} {$root}/shell/log_manager.php clean >> /dev/null 2>&1",
            "0 9 * * * {$php} {$root}/shell/generate_expire_mail.php >> /dev/null 2>&1",
            "0 4 * * 1 {$php} {$root}/shell/sync_user_with_short.php >> /dev/null 2>&1",
        );
        $this->ajaxReturn(array('code' => 0, 'data' => implode("\n", $lines)));
    }
}
