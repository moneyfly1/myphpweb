<?php
/**
 * 邮件队列管理控制器
 * 用于后台管理邮件队列
 */
namespace Admin\Controller;
use Think\Controller;
use Common\Controller\AdminBaseController;

class EmailQueueController extends AdminBaseController {
    
    private $emailQueue;
    private $emailQueuePath;
    
    public function _initialize() {
        parent::_initialize();
        // 统一引入邮件队列类
        $this->emailQueuePath = dirname(dirname(dirname(__FILE__))) . '/Common/Common/EmailQueue.class.php';
        require_once $this->emailQueuePath;
        $this->emailQueue = new \EmailQueue();
    }
    
    /**
     * 邮件队列列表
     */
    public function index() {
        // 获取筛选条件
        $status = I('get.status', '');
        $emailType = I('get.email_type', '');
        $page = I('get.p', 1);
        $pageSize = 20;
        
        // 构建查询条件
        $where = [];
        if (!empty($status)) {
            $where['status'] = $status;
        }
        if (!empty($emailType)) {
            $where['type'] = $emailType;
        }
        
        // 获取邮件列表
        $emails = M('email_queue')
            ->where($where)
            ->order('created_at DESC')
            ->page($page, $pageSize)
            ->select();
        
        // 获取总数
        $total = M('email_queue')->where($where)->count();
        
        // 分页
        $Page = new \Think\Page($total, $pageSize);
        $show = $Page->show();
        
        // 获取队列统计
        $stats = $this->emailQueue->getQueueStats();
        
        $this->assign('emails', $emails);
        $this->assign('stats', $stats);
        $this->assign('page', $show);
        $this->assign('status', $status);
        $this->assign('emailType', $emailType);
        
        $this->display();
    }
    
    /**
     * 重新发送邮件
     */
    public function resend() {
        $id = I('post.id', 0);
        
        if (empty($id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        
        // 检查邮件是否存在
        $email = M('email_queue')->where(['id' => $id])->find();
        if (empty($email)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '邮件不存在']);
        }
        
        // 重置状态为待发送
        $result = M('email_queue')->where(['id' => $id])->save([
            'status' => 'pending',
            'retry_count' => 0,
            'error_message' => '',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->ajaxReturn([
            'status' => $result !== false ? 1 : 0, 
            'msg' => $result !== false ? '重新发送成功' : '操作失败'
        ]);
    }
    
    /**
     * 删除邮件
     */
    public function delete() {
        $id = I('post.id', 0);
        
        if (empty($id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        
        $result = M('email_queue')->where(['id' => $id])->delete();
        
        $this->ajaxReturn([
            'status' => $result ? 1 : 0, 
            'msg' => $result ? '删除成功' : '删除失败'
        ]);
    }
    
    /**
     * 批量删除邮件
     */
    public function batchDelete() {
        $ids = I('post.ids', '');
        
        if (empty($ids)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要删除的邮件']);
        }
        
        $idArray = explode(',', $ids);
        $result = M('email_queue')->where(['id' => ['in', $idArray]])->delete();
        
        $this->ajaxReturn([
            'status' => $result ? 1 : 0, 
            'msg' => $result ? '批量删除成功' : '批量删除失败'
        ]);
    }
    
    /**
     * 清理已发送和已失败的邮件
     */
    public function cleanup() {
        $days = I('post.days', 7);
        
        if ($days <= 0) {
            $this->ajaxReturn(['status' => 0, 'msg' => '保留天数必须大于0']);
        }
        
        $count = $this->emailQueue->cleanupSentEmails($days);
        
        $this->ajaxReturn([
            'status' => 1, 
            'msg' => $count > 0 ? "清理完成！删除了 {$count} 条{$days}天前的已发送和已失败邮件记录" : "没有找到需要清理的邮件记录"
        ]);
    }
    
    /**
     * 手动处理队列
     */
    public function processQueue() {
        // 引入邮件发送函数
        require_once dirname(dirname(dirname(__FILE__))) . '/Common/Common/function.php';
        
        // 获取待发送邮件
        $emails = $this->emailQueue->getPendingEmails(5); // 一次处理5封
        
        if (empty($emails)) {
            $this->ajaxReturn(['status' => 1, 'msg' => '没有待处理的邮件']);
        }
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($emails as $email) {
            try {
                // 标记为处理中
                $this->emailQueue->markAsProcessing($email['id']);
                
                // 发送邮件
                $result = send_mail_direct($email['to_email'], $email['subject'], $email['body']);
                
                if ($result) {
                    $this->emailQueue->markAsSent($email['id']);
                    $successCount++;
                } else {
                    $this->emailQueue->markAsFailed($email['id'], '邮件发送失败');
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->emailQueue->markAsFailed($email['id'], $e->getMessage());
                $failCount++;
            }
        }
        
        $this->ajaxReturn([
            'status' => 1, 
            'msg' => "处理完成：成功 {$successCount} 封，失败 {$failCount} 封"
        ]);
    }
    
    /**
     * 获取队列统计信息
     */
    public function getStats() {
        $stats = $this->emailQueue->getQueueStats();
        $this->ajaxReturn(['status' => 1, 'data' => $stats]);
    }
    
    /**
     * 邮件详情
     */
    public function detail() {
        $id = I('get.id', 0);
        
        if (empty($id)) {
            $this->error('参数错误');
        }
        
        $email = M('email_queue')->where(['id' => $id])->find();
        
        if (empty($email)) {
            $this->error('邮件不存在');
        }
        
        $this->assign('email', $email);
        $this->display();
    }
    
    /**
     * 测试邮件发送
     */
    public function testEmail() {
        if (IS_POST) {
            $email = I('post.email', '');
            $subject = I('post.subject', '测试邮件');
            $content = I('post.content', '这是一封测试邮件');
            
            if (empty($email)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入邮箱地址']);
            }
            
            // 引入邮件发送函数
            require_once dirname(dirname(dirname(__FILE__))) . '/Common/Common/function.php';
            
            // 直接发送测试邮件
            $result = send_mail_direct($email, $subject, $content);
            
            $this->ajaxReturn([
                'status' => $result ? 1 : 0, 
                'msg' => $result ? '测试邮件发送成功' : '测试邮件发送失败'
            ]);
        } else {
            $this->display();
        }
    }
    
    /**
     * 邮件配置检查
     */
    public function checkConfig() {
        // 从.env文件读取配置
        $smtpHost = env('EMAIL_SMTP') ?: env('MAIL_HOST');
        $smtpPort = env('EMAIL_PORT') ?: env('MAIL_PORT');
        $smtpUser = env('EMAIL_USERNAME') ?: env('MAIL_USER');
        $smtpPass = env('EMAIL_PASSWORD') ?: env('MAIL_PASS');
        $smtpSecure = env('EMAIL_SMTP_SECURE') ?: env('MAIL_SECURE');
        $fromName = env('EMAIL_FROM_NAME') ?: '订阅服务';
        
        $config = [
            'EMAIL_SMTP' => $smtpHost,
            'EMAIL_PORT' => $smtpPort,
            'EMAIL_USERNAME' => $smtpUser,
            'EMAIL_PASSWORD' => $smtpPass ? '已配置' : '未配置',
            'EMAIL_SMTP_SECURE' => $smtpSecure,
            'EMAIL_FROM_NAME' => $fromName,
            'config_source' => '.env文件'
        ];
        
        // 检查配置完整性
        $isComplete = !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass);
        
        $this->ajaxReturn([
            'status' => 1,
            'config' => $config,
            'isComplete' => $isComplete
        ]);
    }
}