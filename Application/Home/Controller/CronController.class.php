<?php
/**
 * 公开定时任务入口（无需登录，通过 secret 验证）
 *
 * 用法（宝塔定时任务或外部 cron 服务）：
 *   每1分钟: curl "https://dy.moneyfly.club/?s=/Home/Cron/run&secret=你的CRON_SECRET"
 *   查状态:  curl "https://dy.moneyfly.club/?s=/Home/Cron/status&secret=你的CRON_SECRET"
 *
 * .env 中配置: CRON_SECRET=moneyfly2026cron
 */
namespace Home\Controller;
use Think\Controller;

class CronController extends Controller {

    private function checkSecret() {
        $secret = I('get.secret', '');
        $expected = env('CRON_SECRET', '');
        if (empty($expected) || $secret !== $expected) {
            header('HTTP/1.1 403 Forbidden');
            exit('Forbidden');
        }
    }

    /**
     * 统一定时任务入口
     * URL: /?s=/Home/Cron/run&secret=xxx&task=email_queue
     */
    public function run() {
        $this->checkSecret();
        set_time_limit(120);
        ignore_user_abort(true);

        $task = I('get.task', 'email_queue');

        switch ($task) {
            case 'email_queue':
                $result = $this->taskEmailQueue();
                break;
            case 'clean_email':
                $result = $this->taskCleanEmail();
                break;
            case 'log_rotate':
                $result = $this->taskLogRotate();
                break;
            case 'log_clean':
                $result = $this->taskLogClean();
                break;
            case 'expire_remind':
                $result = $this->taskExpireRemind();
                break;
            case 'sync_user':
                $result = $this->taskSyncUser();
                break;
            default:
                $this->ajaxReturn(array('code' => 1, 'msg' => "未知任务: {$task}"));
                return;
        }

        $result['task'] = $task;
        $this->ajaxReturn($result);
    }

    /**
     * 批量执行常用任务组合
     * URL: /?s=/Home/Cron/runAll&secret=xxx
     */
    public function runAll() {
        $this->checkSecret();
        set_time_limit(180);
        ignore_user_abort(true);

        $results = array();
        $results['email_queue'] = $this->taskEmailQueue();
        $results['clean_email'] = $this->taskCleanEmail();
        $results['expire_remind'] = $this->taskExpireRemind();

        $this->ajaxReturn(array('code' => 0, 'msg' => '批量执行完成', 'data' => $results));
    }

    /**
     * 兼容旧URL
     */
    public function processEmailQueue() {
        $this->run();
    }

    // ==================== 各任务实现 ====================

    /**
     * 处理邮件队列
     */
    private function taskEmailQueue() {
        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        $queue = new \EmailQueue();

        $emails = $queue->getPendingEmails(10);
        $success = 0;
        $fail = 0;

        foreach ($emails as $email) {
            $queue->markAsProcessing($email['id']);
            $result = send_mail_direct(
                $email['to_email'],
                $email['subject'],
                $email['body']
            );
            if ($result) {
                $queue->markAsSent($email['id']);
                $success++;
            } else {
                $queue->markAsFailed($email['id'], '发送失败');
                $fail++;
            }
            usleep(100000);
        }

        return array(
            'code' => 0,
            'msg'  => "处理完成: 成功{$success}, 失败{$fail}",
            'data' => array('success' => $success, 'fail' => $fail, 'total' => count($emails))
        );
    }

    /**
     * 清理旧邮件记录（7天）
     */
    private function taskCleanEmail() {
        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        $queue = new \EmailQueue();
        $cleaned = $queue->cleanSentEmails(7);

        return array(
            'code' => 0,
            'msg'  => "清理完成: {$cleaned} 条记录",
            'data' => array('cleaned' => $cleaned)
        );
    }

    /**
     * 日志轮转
     */
    private function taskLogRotate() {
        require_once dirname(APP_PATH) . '/shell/log_manager.php';
        ob_start();
        $manager = new \LogManager();
        $rotated = $manager->rotateLogs();
        ob_end_clean();

        return array(
            'code' => 0,
            'msg'  => "日志轮转完成: {$rotated} 个文件",
            'data' => array('rotated' => $rotated)
        );
    }

    /**
     * 日志清理
     */
    private function taskLogClean() {
        require_once dirname(APP_PATH) . '/shell/log_manager.php';
        ob_start();
        $manager = new \LogManager();
        $cleaned = $manager->cleanOldLogs();
        ob_end_clean();

        return array(
            'code' => 0,
            'msg'  => "日志清理完成: {$cleaned} 个文件",
            'data' => array('cleaned' => $cleaned)
        );
    }

    /**
     * 到期提醒：扫描即将到期/已到期用户，生成提醒邮件
     */
    private function taskExpireRemind() {
        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        require_once APP_PATH . 'Common/Common/EmailTemplate.class.php';

        $queue = new \EmailQueue();
        $now = time();
        $sevenDaysLater = $now + 7 * 86400;
        $prefix = C('DB_PREFIX');

        // 查询7天内到期或已到期、状态为1的订阅
        $sql = "SELECT qq, endtime FROM {$prefix}short_dingyue "
             . "WHERE status = 1 AND endtime <= {$sevenDaysLater}";
        $users = M()->query($sql);

        if (empty($users)) {
            return array('code' => 0, 'msg' => '无需提醒', 'data' => array('queued' => 0, 'skipped' => 0));
        }

        $queued = 0;
        $skipped = 0;
        $oneDayAgo = $now - 86400;

        foreach ($users as $user) {
            $qq = $user['qq'];
            $endtime = intval($user['endtime']);
            $email = $qq . '@qq.com';

            // 24小时内已入队则跳过
            $recentCheck = M('email_queue')->where(array(
                'to_email' => $email,
                'type'     => 'expiration',
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

        return array(
            'code' => 0,
            'msg'  => "提醒完成: 入队{$queued}, 跳过{$skipped}",
            'data' => array('queued' => $queued, 'skipped' => $skipped)
        );
    }

    /**
     * 同步用户：删除不在 short_dingyue 中的 user 记录
     */
    private function taskSyncUser() {
        $prefix = C('DB_PREFIX');

        // 获取 short_dingyue 所有 QQ
        $qqRows = M()->query("SELECT qq FROM {$prefix}short_dingyue");
        $qqList = array();
        foreach ($qqRows as $row) {
            $qqList[] = "'" . addslashes($row['qq']) . "'";
        }

        $deleted = 0;
        if (!empty($qqList)) {
            $qqIn = implode(',', $qqList);
            $deleted = M()->execute("DELETE FROM {$prefix}user WHERE username NOT IN ({$qqIn})");
        } else {
            // short_dingyue 为空时不执行删除，防止误删全部
            return array('code' => 1, 'msg' => 'short_dingyue 表为空，跳过同步', 'data' => array('deleted' => 0));
        }

        return array(
            'code' => 0,
            'msg'  => "同步完成: 删除{$deleted}个用户",
            'data' => array('deleted' => $deleted)
        );
    }

    /**
     * 状态查看：显示所有任务相关统计
     * URL: /?s=/Home/Cron/status&secret=xxx
     */
    public function status() {
        $this->checkSecret();

        $prefix = C('DB_PREFIX');
        $stats = array();

        // 邮件队列统计
        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        $queue = new \EmailQueue();
        $stats['email_queue'] = $queue->getQueueStats();

        // 到期提醒统计
        $now = time();
        $sevenDaysLater = $now + 7 * 86400;
        $expiring = M()->query("SELECT COUNT(*) as cnt FROM {$prefix}short_dingyue WHERE status = 1 AND endtime > {$now} AND endtime <= {$sevenDaysLater}");
        $expired = M()->query("SELECT COUNT(*) as cnt FROM {$prefix}short_dingyue WHERE status = 1 AND endtime <= {$now}");
        $stats['expire_remind'] = array(
            'expiring_7days' => intval($expiring[0]['cnt']),
            'already_expired' => intval($expired[0]['cnt'])
        );

        // 用户同步统计
        $totalUsers = M()->query("SELECT COUNT(*) as cnt FROM {$prefix}user");
        $totalDingyue = M()->query("SELECT COUNT(*) as cnt FROM {$prefix}short_dingyue");
        $stats['sync_user'] = array(
            'user_count' => intval($totalUsers[0]['cnt']),
            'dingyue_count' => intval($totalDingyue[0]['cnt'])
        );

        $this->ajaxReturn(array('code' => 0, 'data' => $stats));
    }
}
