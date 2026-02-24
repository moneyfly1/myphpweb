<?php
namespace Home\Controller;
use Think\Controller;

set_time_limit(0);
ignore_user_abort(true);

/**
 * 定时任务控制器
 */
class CronController extends Controller
{
    /**
     * 处理邮件队列
     * 可由 Cron Job 定期调用：* * * * * curl http://domain.com/index.php/Home/Cron/processEmailQueue
     */
    public function processEmailQueue()
    {
        $startTime = microtime(true);
        \Think\Log::record('邮件队列处理开始...', 'INFO');

        require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
        $stats = (new \EmailQueue())->processQueue(20);

        $duration = round(microtime(true) - $startTime, 4);
        $logMessage = "邮件队列处理完成。耗时: {$duration} 秒。";

        if (is_array($stats)) {
            $logMessage .= " 已处理-{$stats['processed']}, 成功-{$stats['sent']}, 失败-{$stats['failed']}";
        } else {
            $logMessage .= " 处理了 {$stats} 封邮件。";
        }

        \Think\Log::record($logMessage, 'INFO');
        echo $logMessage;
    }
}