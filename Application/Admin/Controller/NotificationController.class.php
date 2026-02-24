<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

/**
 * 通知管理控制器
 */
class NotificationController extends AdminBaseController{

    /**
     * 通知设置页面
     */
    public function index(){
        // 获取当前通知配置
        $config = $this->getNotificationConfig();
        
        $this->assign('config', $config);
        $this->display();
    }
    
    /**
     * 保存通知设置
     */
    public function save(){
        $data = I('post.');
        
        // 验证数据
        if(empty($data['telegram_bot_token']) && empty($data['bark_key']) && empty($data['email_to'])){
            $this->error('至少需要配置一种通知方式');
        }
        
        // 保存配置到文件
        $config = [
            'telegram' => [
                'enabled' => isset($data['telegram_enabled']) ? 1 : 0,
                'bot_token' => trim($data['telegram_bot_token']),
                'chat_id' => trim($data['telegram_chat_id'])
            ],
            'bark' => [
                'enabled' => isset($data['bark_enabled']) ? 1 : 0,
                'key' => trim($data['bark_key']),
                'server' => trim($data['bark_server']) ?: 'https://api.day.app'
            ],
            'email' => [
                'enabled' => isset($data['email_enabled']) ? 1 : 0,
                'to' => trim($data['email_to'])
            ]
        ];
        
        $result = $this->saveNotificationConfig($config);
        
        if($result){
            $this->success('通知设置保存成功', U('Admin/Notification/index'));
        } else {
            $this->error('通知设置保存失败');
        }
    }
    
    /**
     * 测试通知
     */
    public function test(){
        $type = I('get.type', '');
        $config = $this->getNotificationConfig();
        
        $testMessage = '这是一条测试消息，订单通知功能正常工作！';
        
        switch($type){
            case 'telegram':
                if(!$config['telegram']['enabled'] || empty($config['telegram']['bot_token'])){
                    $this->error('Telegram通知未启用或配置不完整');
                }
                $result = $this->sendTelegramNotification($testMessage, $config['telegram']);
                break;
                
            case 'bark':
                if(!$config['bark']['enabled'] || empty($config['bark']['key'])){
                    $this->error('Bark通知未启用或配置不完整');
                }
                $result = $this->sendBarkNotification($testMessage, $config['bark']);
                break;
                
            case 'email':
                if(!$config['email']['enabled'] || empty($config['email']['to'])){
                    $this->error('邮件通知未启用或配置不完整');
                }
                $result = $this->sendEmailNotification($testMessage, $config['email']);
                break;
                
            default:
                $this->error('无效的通知类型');
        }
        
        if($result){
            $this->success('测试通知发送成功');
        } else {
            $this->error('测试通知发送失败，请检查配置');
        }
    }
    
    /**
     * 获取通知配置
     */
    private function getNotificationConfig(){
        $configFile = APP_PATH . 'Common/Conf/notification.php';
        
        if(file_exists($configFile)){
            return include $configFile;
        }
        
        // 默认配置
        return [
            'telegram' => [
                'enabled' => 0,
                'bot_token' => '',
                'chat_id' => ''
            ],
            'bark' => [
                'enabled' => 0,
                'key' => '',
                'server' => 'https://api.day.app'
            ],
            'email' => [
                'enabled' => 0,
                'to' => ''
            ]
        ];
    }
    
    /**
     * 保存通知配置
     */
    private function saveNotificationConfig($config){
        $configFile = APP_PATH . 'Common/Conf/notification.php';
        $configContent = "<?php\nreturn " . var_export($config, true) . ";";
        
        return file_put_contents($configFile, $configContent) !== false;
    }
    
    /**
     * 发送Telegram通知
     */
    private function sendTelegramNotification($message, $config){
        $url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
        
        $data = [
            'chat_id' => $config['chat_id'],
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
    
    /**
     * 发送Bark通知
     */
    private function sendBarkNotification($message, $config){
        $url = rtrim($config['server'], '/') . '/' . $config['key'];
        
        $data = [
            'title' => '订单通知',
            'body' => $message,
            'sound' => 'default'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
    
    /**
     * 发送邮件通知
     */
    private function sendEmailNotification($message, $config){
        if(!function_exists('send_email')){
            return false;
        }
        
        $subject = '订单通知测试 - ' . date('Y-m-d H:i:s');
        $content = '<h3>订单通知测试</h3>';
        $content .= '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
        $content .= '<hr>';
        $content .= '<p style="color: #666; font-size: 12px;">此邮件由系统自动发送，请勿回复。</p>';
        
        $result = send_email($config['to'], $subject, $content);
        
        return $result === true || (is_array($result) && isset($result['status']) && $result['status'] === true);
    }
}