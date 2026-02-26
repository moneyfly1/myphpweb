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
     * 处理邮件队列（主入口）
     */
    public function run() {
        $this->checkSecret();
        set_time_limit(60);
        ignore_user_abort(true);

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

        $this->ajaxReturn(array(
            'code' => 0,
            'msg'  => "处理完成: 成功{$success}, 失败{$fail}",
            'data' => array(
                'success' => $success,
                'fail'    => $fail,
                'total'   => count($emails),
            )
        ));
    }

    /**
     * 兼容旧URL
     */
    public function processEmailQueue() {
        $this->run();
    }

    /**
     * 队列状态
     */
    public function status() {
        $this->checkSecret();

        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        $queue = new \EmailQueue();
        $stats = $queue->getQueueStats();

        $this->ajaxReturn(array('code' => 0, 'data' => $stats));
    }
}
