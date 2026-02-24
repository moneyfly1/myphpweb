<?php
namespace Home\Controller;
use Think\Controller;

/**
 * 首页控制器 - 优化重构版
 * 
 * 主要优化：
 * 1. 合并了多个配置生成方法为统一的模板方法
 * 2. 简化了 short() 方法的条件分支
 * 3. 提取了公共的日志记录逻辑
 * 4. 清理了冗余的测试代码
 */
class IndexController extends Controller
{
    // ==================== 常量定义 ====================

    /**
     * 订阅软件UA匹配模式
     */
    private static $SUBSCRIPTION_PATTERNS = [
        // Clash系列
        '/ClashforWindows/i',
        '/ClashMetaForAndroid/i',
        '/ClashMeta/i',
        '/clash-verge/i',
        '/clash\.meta/i',
        '/FlClash/i',
        '/flclash/i',
        // iOS
        '/Shadowrocket/i',
        '/Quantumult/i',
        '/Surge/i',
        '/Loon/i',
        '/Stash/i',
        '/Sparkle/i',
        // Android
        '/V2rayNG/i',
        '/SagerNet/i',
        '/Matsuri/i',
        '/AnXray/i',
        // Windows
        '/v2rayN/i',
        // 通用
        '/subconverter/i',
        '/subscription/i',
        '/proxy/i',
        '/vpn/i'
    ];

    /**
     * 浏览器/机器人UA匹配模式（不计入设备数）
     */
    private static $BROWSER_PATTERNS = [
        '/DingTalkBot/i',
        '/Go-http-client/i',
        '/HttpClient/i',
        '/curl/i',
        '/wget/i',
        '/python-requests/i',
        '/Java/i',
        '/okhttp/i',
        '/Scrapy/i',
        '/Bot/i',
        '/Spider/i'
    ];

    /**
     * Clash Meta Android UA匹配模式
     */
    private static $CLASH_ANDROID_PATTERN = '/ClashMetaForAndroid|clash\.meta.*Android|Clash.*Android.*Meta|clash\.meta/i';

    // ==================== 主要公开方法 ====================

    /**
     * 统一 layout 示例页（访问 /welcome）
     */
    public function welcome()
    {
        $this->display();
    }

    /**
     * 首页展示
     */
    public function index()
    {
        if (!check_user_login()) {
            $this->error('请登录后操作', '/login', 0);
        }

        $qq = $_SESSION['users']['username'];
        $data = M('ShortDingyue')->where(['qq' => $qq])->find();

        if (!$data) {
            $this->error('致命错误，请联系管理员');
            return;
        }

        // 构建完整URL
        $host = 'https://' . $_SERVER['HTTP_HOST'] . '/';
        $data['ms'] = $data['mobileshorturl'];
        $data['cs'] = $data['clashshorturl'];
        $data['mobileshorturl'] = $host . $data['mobileshorturl'];
        $data['clashshorturl'] = $host . $data['clashshorturl'];

        // 获取用户状态
        $user = M('user')->where(['username' => $data['qq']])->find();
        $isActivated = $user && isset($user['activation']) && $user['activation'] == 1;
        $deviceManagementEnabled = $user && isset($user['device_management_enabled']) && $user['device_management_enabled'] == 1;
        $data['device_management_enabled'] = $deviceManagementEnabled ? 1 : 0;

        // 计算订阅状态
        $statusInfo = $this->calculateSubscriptionStatus($data, $isActivated);
        $data['jsdate'] = $statusInfo['message'];
        $data['endtime'] = $statusInfo['days_left'];
        $data['qrcodeUrl'] = "sub://" . base64_encode($data['mobileshorturl']) . "#" . urlencode($data['jsdate']);

        $this->assign('device_management_enabled_js', $deviceManagementEnabled ? 'true' : 'false');
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 订阅短链处理 - 核心方法（简化版）
     */
    public function short()
    {
        $request = I('get.short');
        $m = M('ShortDingyue');

        // 1. 查询订阅记录
        $data = $m->where([
            '_complex' => [
                'mobileshorturl' => $request,
                'clashshorturl' => $request,
                '_logic' => 'or'
            ]
        ])->find();

        if (!$data) {
            $this->error('订阅不存在');
            return;
        }

        // 2. 判断请求类型
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isClash = ($data['clashshorturl'] == $request);
        $filename = $isClash ? 'clash.yaml' : 'xr';
        $countField = $isClash ? 'clashcount' : 'count';

        // 3. 判断客户端类型
        $isSubscriptionApp = $this->isSubscriptionApp($ua);
        $isBrowser = (!$isSubscriptionApp && preg_match('/(Mozilla|Chrome|Safari|Edge|Firefox)/i', $ua))
            || $this->isBrowserLike($ua);

        // 4. 状态检查
        $user = M('user')->where(['username' => $data['qq']])->find();
        $isActivated = $user && isset($user['activation']) && $user['activation'] == 1;
        $endtime = intval($data['endtime'] ?? 0);
        $status = intval($data['status'] ?? 0);
        $currentDevices = intval($data['drivers'] ?? 0);
        $maxDevices = intval($data['setdrivers'] ?? 0);

        // 5. 根据状态返回对应配置
        $checkResult = $this->checkSubscriptionAccess($data, $isActivated, $endtime, $status, $currentDevices, $maxDevices, $isBrowser, $ua);

        // 增加访问计数
        $m->where(['id' => $data['id']])->setInc($countField, 1);

        // 6. 输出配置
        if ($checkResult['allowed']) {
            $this->outputSubscriptionFile('true', $filename);
        } else {
            $this->logReject($data, $checkResult['reason'], $ua);
            $config = $this->generateStatusConfig($checkResult['type'], $isClash, $endtime, $currentDevices, $maxDevices);
            $this->outputConfig($config, $filename, $isClash);
        }
    }

    /**
     * 重置订阅URL
     */
    public function resetUrl()
    {
        if (!check_user_login()) {
            $this->error('请登录后操作', '/login');
            return;
        }

        $qq = $_SESSION['users']['username'];
        $old = D('ShortDingyue')->getData(['qq' => $qq]);

        $newData = [
            'mobileshorturl' => generate_secure_random(16),
            'clashshorturl' => generate_secure_random(16)
        ];

        // 记录历史
        $user = M('user')->where(['username' => $qq])->find();
        $userId = $user ? $user['id'] : 0;

        M('ShortDingyueHistory')->add([
            'user_id' => $userId,
            'old_url' => $old['mobileshorturl'] . ' | ' . $old['clashshorturl'],
            'new_url' => $newData['mobileshorturl'] . ' | ' . $newData['clashshorturl'],
            'change_type' => 'user_reset',
            'change_time' => time()
        ]);

        M('UserActionLog')->add([
            'user_id' => $userId,
            'action' => 'user_reset_subscription',
            'detail' => "用户自助重置订阅地址",
            'action_time' => time()
        ]);

        $res = D('ShortDingyue')->editData(['qq' => $qq], $newData);

        if ($res) {
            // 清空设备记录
            M('DeviceLog')->where(['dingyue_id' => $old['id']])->delete();

            $resetData = ['drivers' => 0];
            $tableFields = M('ShortDingyue')->getDbFields();
            if (in_array('allowed_devices', $tableFields)) {
                $resetData['allowed_devices'] = '[]';
            }
            D('ShortDingyue')->editData(['id' => $old['id']], $resetData);

            write_action_log('reset_subscription', "用户{$qq}重置了订阅地址", $qq);
            $this->success('重置成功');
        } else {
            $this->error('重置失败，请联系管理员');
        }
    }

    /**
     * 获取设备列表
     */
    public function getDeviceList()
    {
        if (!check_user_login()) {
            $this->ajaxReturn(['code' => 1, 'msg' => '请先登录']);
            return;
        }

        $dingyueId = I('post.dingyue_id', 0, 'intval');
        $qq = I('post.qq', '', 'trim');
        $currentUserQq = $_SESSION['users']['username'];

        // 权限验证
        if (!$dingyueId || $qq !== $currentUserQq) {
            $this->ajaxReturn(['code' => 1, 'msg' => '参数无效或权限不足']);
            return;
        }

        $subscription = M('ShortDingyue')->where(['id' => $dingyueId, 'qq' => $qq])->find();
        if (!$subscription) {
            $this->ajaxReturn(['code' => 1, 'msg' => '订阅记录不存在']);
            return;
        }

        try {
            // 获取设备列表
            $devices = $this->getUniqueDevices($dingyueId, $qq, $subscription);

            $tableFields = M('ShortDingyue')->getDbFields();
            $hasAllowedDevices = in_array('allowed_devices', $tableFields);

            $currentDevices = 0;
            if ($hasAllowedDevices && !empty($subscription['allowed_devices'])) {
                $allowedDevices = json_decode($subscription['allowed_devices'], true) ?: [];
                $currentDevices = count($allowedDevices);
            } else {
                $currentDevices = intval($subscription['drivers'] ?? 0);
            }

            $this->ajaxReturn([
                'code' => 0,
                'msg' => '获取成功',
                'data' => $devices,
                'current_devices' => $currentDevices,
                'max_devices' => $subscription['setdrivers']
            ]);
        } catch (\Exception $e) {
            error_log("getDeviceList error: " . $e->getMessage());
            $this->ajaxReturn(['code' => 1, 'msg' => '获取失败']);
        }
    }

    /**
     * 移除设备
     */
    public function removeDevice()
    {
        if (!check_user_login()) {
            $this->ajaxReturn(['code' => 1, 'msg' => '请先登录']);
            return;
        }

        $fingerprint = I('post.fingerprint', '', 'trim');
        $dingyueId = I('post.dingyue_id', 0, 'intval');
        $qq = I('post.qq', '', 'trim');
        $currentUserQq = $_SESSION['users']['username'];

        // 验证
        if (!$fingerprint || !$dingyueId || $qq !== $currentUserQq) {
            $this->ajaxReturn(['code' => 1, 'msg' => '参数无效或权限不足']);
            return;
        }

        $subscription = M('ShortDingyue')->where(['id' => $dingyueId, 'qq' => $qq])->find();
        if (!$subscription) {
            $this->ajaxReturn(['code' => 1, 'msg' => '订阅记录不存在']);
            return;
        }

        try {
            M()->startTrans();

            $tableFields = M('ShortDingyue')->getDbFields();
            $hasAllowedDevices = in_array('allowed_devices', $tableFields);

            $allowedDevices = [];
            if ($hasAllowedDevices && !empty($subscription['allowed_devices'])) {
                $allowedDevices = json_decode($subscription['allowed_devices'], true) ?: [];
            }

            if (!in_array($fingerprint, $allowedDevices)) {
                M()->rollback();
                $this->ajaxReturn(['code' => 1, 'msg' => '设备不在允许列表中']);
                return;
            }

            // 删除设备记录
            M('DeviceLog')->where([
                'dingyue_id' => $dingyueId,
                'qq' => $qq,
                'fingerprint' => $fingerprint
            ])->delete();

            // 更新允许列表
            $allowedDevices = array_values(array_diff($allowedDevices, [$fingerprint]));
            $updateData = ['drivers' => count($allowedDevices)];
            if ($hasAllowedDevices) {
                $updateData['allowed_devices'] = json_encode($allowedDevices);
            }
            M('ShortDingyue')->where(['id' => $dingyueId])->save($updateData);

            M()->commit();

            $this->logDeviceAction('remove_device', $qq, $dingyueId, $fingerprint);
            $this->ajaxReturn(['code' => 0, 'msg' => '设备移除成功']);
        } catch (\Exception $e) {
            M()->rollback();
            $this->ajaxReturn(['code' => 1, 'msg' => '移除失败']);
        }
    }

    /**
     * 发送邮件
     */
    public function sendMail()
    {
        $qq = I('post.qq');
        $mobileUrl = I('post.mobileUrl');
        $clashUrl = I('post.clashUrl');
        $mailUser = I('post.mailUser');
        $mailPass = I('post.mailPass');

        if (!$qq || !$mobileUrl || !$clashUrl || !$mailUser || !$mailPass) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数不完整']);
            return;
        }

        $to = $qq . '@qq.com';
        $subject = '您的订阅信息';
        $body = "手机短链：{$mobileUrl}<br>Clash短链：{$clashUrl}";

        vendor('PHPMailer.PHPMailerAutoload');
        $mail = new \PHPMailer();
        $mail->isSMTP();
        $mail->Host = I('post.mailHost', 'smtp.qq.com');
        $mail->SMTPAuth = true;
        $mail->Username = $mailUser;
        $mail->Password = $mailPass;
        $mail->SMTPSecure = I('post.mailSecure', true) ? 'ssl' : '';
        $mail->Port = I('post.mailPort', 465);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($mailUser, '订阅系统');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        if ($mail->send()) {
            $this->ajaxReturn(['status' => 1, 'msg' => '发送成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '发送失败: ' . $mail->ErrorInfo]);
        }
    }

    /**
     * 清理旧设备记录（管理员功能）
     */
    public function cleanOldDevices()
    {
        if (!check_user_login()) {
            $this->error('请登录后操作');
            return;
        }

        $qq = $_SESSION['users']['username'];
        $user = M('user')->where(['username' => $qq])->find();

        if (!$user || $user['id'] != 1) {
            $this->error('权限不足');
            return;
        }

        try {
            $deviceCount = M('DeviceLog')->count();
            M('DeviceLog')->where('1=1')->delete();

            $resetData = ['drivers' => 0];
            $tableFields = M('ShortDingyue')->getDbFields();
            if (in_array('allowed_devices', $tableFields)) {
                $resetData['allowed_devices'] = '[]';
            }
            M('ShortDingyue')->where('1=1')->save($resetData);

            $this->logDeviceAction('clean_old_devices', $qq, 0, '', "清理了 {$deviceCount} 条设备记录");
            $this->success("清理完成！删除了 {$deviceCount} 条设备记录");
        } catch (\Exception $e) {
            $this->error('清理失败：' . $e->getMessage());
        }
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 计算订阅状态信息
     */
    private function calculateSubscriptionStatus($data, $isActivated)
    {
        $endtime = intval($data['endtime'] ?? 0);
        $status = intval($data['status'] ?? 0);
        $currentDevices = intval($data['drivers'] ?? 0);
        $maxDevices = intval($data['setdrivers'] ?? 0);

        $isExpired = ($endtime > 0 && $endtime < time()) || !$isActivated || ($status !== 1);
        $isOverlimit = ($currentDevices >= $maxDevices);

        if ($isExpired && $isOverlimit) {
            return ['message' => '订阅已过期且设备超过限制', 'days_left' => 0];
        } elseif ($isExpired) {
            return ['message' => '订阅已过期', 'days_left' => 0];
        } elseif ($isOverlimit) {
            return [
                'message' => "设备超过限制({$currentDevices}/{$maxDevices})",
                'days_left' => $endtime > 0 ? floor(($endtime - time()) / 86400) : 0
            ];
        } elseif ($endtime == 0) {
            return ['message' => '永久有效', 'days_left' => 0];
        } else {
            return [
                'message' => '有效期至：' . date('Y-m-d H:i:s', $endtime),
                'days_left' => floor(($endtime - time()) / 86400)
            ];
        }
    }

    /**
     * 检查订阅访问权限
     */
    private function checkSubscriptionAccess($data, $isActivated, $endtime, $status, $currentDevices, $maxDevices, $isBrowser, $ua)
    {
        // 未激活
        if (!$isActivated) {
            return ['allowed' => false, 'type' => 'expired', 'reason' => 'user_not_activated'];
        }

        // 已过期
        if ($endtime > 0 && $endtime < time()) {
            $type = ($currentDevices >= $maxDevices) ? 'both' : 'expired';
            return ['allowed' => false, 'type' => $type, 'reason' => 'subscription_expired'];
        }

        // 已禁用
        if ($status !== 1) {
            return ['allowed' => false, 'type' => 'expired', 'reason' => 'subscription_disabled'];
        }

        // 浏览器直接放行
        if ($isBrowser) {
            return ['allowed' => true, 'type' => 'normal', 'reason' => ''];
        }

        // 设备限制检查
        if ($currentDevices >= $maxDevices) {
            $ip = md5($_SERVER['REMOTE_ADDR']);
            $fingerprint = $this->generateCrossIpFingerprint($ua, $ip);
            $isAllowed = $this->isDeviceAllowed($data['id'], $data['qq'], $fingerprint, $maxDevices);

            if (!$isAllowed) {
                return ['allowed' => false, 'type' => 'overlimit', 'reason' => 'device_limit_exceeded'];
            }
        }

        // 处理设备记录
        $this->processDeviceAccess($data, $ua);

        return ['allowed' => true, 'type' => 'normal', 'reason' => ''];
    }

    /**
     * 处理设备访问记录
     */
    private function processDeviceAccess($data, $ua)
    {
        $ip = md5($_SERVER['REMOTE_ADDR']);
        $now = time();
        $dingyueId = $data['id'];
        $qq = $data['qq'];

        $deviceResult = $this->smartDeviceRecognition($ua, $ip, $qq, $dingyueId);
        $fingerprint = $this->generateCrossIpFingerprint($ua, $ip);

        if (!$deviceResult['is_existing']) {
            $this->addDeviceToAllowedList($dingyueId, $fingerprint, $data['setdrivers']);
        }

        if ($deviceResult['device_id']) {
            M('DeviceLog')->where(['id' => $deviceResult['device_id']])->save(['last_seen' => $now]);
        }
    }

    /**
     * 生成状态配置（统一方法）
     * @param string $type expired|overlimit|both
     * @param bool $isClash 是否为Clash格式
     */
    private function generateStatusConfig($type, $isClash, $endtime = 0, $currentCount = 0, $maxDevices = 0)
    {
        $expiredDate = $endtime > 0 ? date('Y-m-d H:i:s', $endtime) : '未知';
        $contactInfo = 'QQ3219904322';

        // 构建节点列表
        $nodes = [];

        if ($type === 'expired' || $type === 'both') {
            $nodes[] = ['port' => 50000, 'name' => '⚠️ 订阅已过期'];
            $nodes[] = ['port' => 50001, 'name' => "📅 到期时间：{$expiredDate}"];
            $nodes[] = ['port' => 50002, 'name' => '💰 订阅已过期，请续费'];
        }

        if ($type === 'overlimit' || $type === 'both') {
            $nodes[] = ['port' => 50010, 'name' => "⚠️ 设备超过限制 ({$currentCount}/{$maxDevices})"];
            $nodes[] = ['port' => 50011, 'name' => '📱 请移除部分设备或升级套餐'];
        }

        $nodes[] = ['port' => 50003, 'name' => "📞 请联系管理员 {$contactInfo}"];

        return $isClash ? $this->buildClashYaml($nodes) : $this->buildXrConfig($nodes);
    }

    /**
     * 构建Clash YAML配置
     */
    private function buildClashYaml($nodes)
    {
        $method = 'aes-128-gcm';
        $password = 'ebee5473-ec60-4d24-8b8c-61e15060a7c7';
        $server = '127.0.0.1';

        $proxiesYaml = "";
        $proxyNames = [];

        foreach ($nodes as $node) {
            $proxiesYaml .= "  - name: {$node['name']}\n";
            $proxiesYaml .= "    type: ss\n";
            $proxiesYaml .= "    server: {$server}\n";
            $proxiesYaml .= "    port: {$node['port']}\n";
            $proxiesYaml .= "    cipher: {$method}\n";
            $proxiesYaml .= "    password: {$password}\n";
            $proxyNames[] = "      - {$node['name']}";
        }

        $proxyNamesYaml = implode("\n", $proxyNames);

        return <<<YAML
port: 7890
socks-port: 7891
allow-lan: true
mode: Rule
log-level: info
external-controller: :9090
dns:
  enable: true
  nameserver:
    - 119.29.29.29
    - 223.5.5.5
  fallback:
    - 8.8.8.8
    - 8.8.4.4

proxies:
{$proxiesYaml}
proxy-groups:
  - name: 🚀 节点选择
    type: select
    proxies:
{$proxyNamesYaml}
  - name: 🎯 全球直连
    type: select
    proxies:
      - DIRECT
  - name: 🛑 全球拦截
    type: select
    proxies:
      - REJECT
      - DIRECT
  - name: 🐟 漏网之鱼
    type: select
    proxies:
      - 🚀 节点选择
      - 🎯 全球直连

rules:
  - DOMAIN-SUFFIX,local,🎯 全球直连
  - IP-CIDR,127.0.0.0/8,🎯 全球直连,no-resolve
  - IP-CIDR,192.168.0.0/16,🎯 全球直连,no-resolve
  - GEOIP,CN,🎯 全球直连
  - MATCH,🚀 节点选择
YAML;
    }

    /**
     * 构建XR Base64配置
     */
    private function buildXrConfig($nodes)
    {
        $method = 'aes-128-gcm';
        $password = 'ebee5473-ec60-4d24-8b8c-61e15060a7c7';
        $server = '127.0.0.1';
        $auth = base64_encode($method . ':' . $password);

        $links = [];
        foreach ($nodes as $node) {
            $links[] = "ss://{$auth}@{$server}:{$node['port']}#" . urlencode($node['name']);
        }

        return base64_encode(implode("\n", $links));
    }

    /**
     * 输出订阅文件
     */
    private function outputSubscriptionFile($checkfile, $filename)
    {
        $file = $_SERVER["DOCUMENT_ROOT"] . '/Upload/' . $checkfile . '/' . $filename;
        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Cache-Control: public");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Length:" . filesize($file));
        header("Content-Disposition: attachment; filename=" . $filename);
        readfile($file);
        exit;
    }

    /**
     * 输出配置内容
     */
    private function outputConfig($config, $filename, $isClash)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        if ($isClash) {
            header("Content-Type: application/x-yaml; charset=utf-8");
        } else {
            header("Content-Type: text/plain; charset=utf-8");
        }
        header("Content-Disposition: attachment; filename=" . $filename);
        echo $config;
        exit;
    }

    // ==================== 设备识别方法 ====================

    /**
     * 检查是否为订阅软件
     */
    private function isSubscriptionApp($ua)
    {
        foreach (self::$SUBSCRIPTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $ua)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查是否应该按浏览器处理
     */
    private function isBrowserLike($ua)
    {
        foreach (self::$BROWSER_PATTERNS as $pattern) {
            if (preg_match($pattern, $ua)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查是否为Clash客户端
     */
    private function isClashClient($ua)
    {
        return preg_match('/ClashforWindows|ClashMetaForAndroid|ClashMeta|clash-verge|clash\.meta|FlClash|flclash/i', $ua);
    }

    /**
     * 生成跨IP稳定的设备指纹
     */
    private function generateCrossIpFingerprint($ua, $ip)
    {
        $normalizedUa = function_exists('parse_and_normalize_ua') ? parse_and_normalize_ua($ua) : $ua;

        // 提取硬件特征
        $hwFeatures = [];
        if (preg_match('/\((.*?)\)/', $ua, $matches)) {
            $hwFeatures[] = trim($matches[1]);
        }
        if (preg_match('/(iPhone|iPad|iPod)(\d+,\d+)/i', $ua, $matches)) {
            $hwFeatures[] = $matches[1] . $matches[2];
        }
        if (preg_match('/;\s*([^;]+?)\s*Build/i', $ua, $matches)) {
            $hwFeatures[] = trim($matches[1]);
        }

        $hwFingerprint = !empty($hwFeatures) ? md5(implode('|', $hwFeatures)) : '';

        // 订阅软件不包含IP
        if ($this->isSubscriptionApp($ua)) {
            $baseFingerprint = md5("app_" . preg_replace('/\s+/', '', $normalizedUa));
            return $hwFingerprint ? md5($baseFingerprint . "_" . $hwFingerprint) : $baseFingerprint;
        }

        // 非订阅软件包含IP
        return $hwFingerprint ?: md5(preg_replace('/\s+/', '', $normalizedUa) . "_" . $ip);
    }

    /**
     * 智能设备识别与合并
     */
    private function smartDeviceRecognition($ua, $ip, $qq, $dingyueId)
    {
        $now = time();
        $tableFields = M('DeviceLog')->getDbFields();
        $hasFingerprint = in_array('fingerprint', $tableFields);

        // Clash Meta Android强制合并
        if (preg_match(self::$CLASH_ANDROID_PATTERN, $ua)) {
            $existingDevice = M('DeviceLog')->where([
                'dingyue_id' => $dingyueId,
                'qq' => $qq
            ])->order('last_seen DESC')->select();

            foreach ($existingDevice as $device) {
                if (preg_match(self::$CLASH_ANDROID_PATTERN, $device['ua'])) {
                    $this->updateDeviceRecord($device['id'], $ip, $ua, $now);
                    return ['is_existing' => true, 'device_id' => $device['id']];
                }
            }

            $fingerprint = md5("clash_meta_android_forced_{$qq}_{$dingyueId}");
            $newId = $this->createNewDeviceRecord($fingerprint, $ip, $ua, $qq, $dingyueId, $now);
            return ['is_existing' => false, 'device_id' => $newId];
        }

        // 常规设备处理
        $fingerprint = $this->generateCrossIpFingerprint($ua, $ip);

        if ($hasFingerprint) {
            $exactMatch = M('DeviceLog')->where([
                'dingyue_id' => $dingyueId,
                'qq' => $qq,
                'fingerprint' => $fingerprint
            ])->order('last_seen DESC')->find();

            if ($exactMatch) {
                $this->updateDeviceRecord($exactMatch['id'], $ip, $ua, $now);
                return ['is_existing' => true, 'device_id' => $exactMatch['id']];
            }
        }

        // 创建新设备
        $newId = $this->createNewDeviceRecord($fingerprint, $ip, $ua, $qq, $dingyueId, $now);
        return ['is_existing' => false, 'device_id' => $newId];
    }

    /**
     * 更新设备记录
     */
    private function updateDeviceRecord($deviceId, $newIp, $newUa, $now)
    {
        $tableFields = M('DeviceLog')->getDbFields();
        $hasIpHistory = in_array('ip_history', $tableFields);

        $updateData = [
            'ip' => $newIp,
            'ua' => $newUa,
            'last_seen' => $now
        ];

        if ($hasIpHistory) {
            $device = M('DeviceLog')->where(['id' => $deviceId])->find();
            $ipHistory = json_decode($device['ip_history'] ?? '[]', true);
            if (!in_array($newIp, $ipHistory)) {
                $ipHistory[] = $newIp;
                $ipHistory = array_slice($ipHistory, -10); // 保留最近10个
            }
            $updateData['ip_history'] = json_encode($ipHistory);
        }

        M('DeviceLog')->where(['id' => $deviceId])->save($updateData);
    }

    /**
     * 创建新设备记录
     */
    private function createNewDeviceRecord($fingerprint, $ip, $ua, $qq, $dingyueId, $now)
    {
        $tableFields = M('DeviceLog')->getDbFields();

        $deviceData = [
            'dingyue_id' => $dingyueId,
            'ip' => $ip,
            'ua' => $ua,
            'qq' => $qq,
            'last_seen' => $now
        ];

        if (in_array('fingerprint', $tableFields)) {
            $deviceData['fingerprint'] = $fingerprint;
        }
        if (in_array('ip_history', $tableFields)) {
            $deviceData['ip_history'] = json_encode([$ip]);
        }
        if (in_array('first_seen', $tableFields)) {
            $deviceData['first_seen'] = $now;
        }

        return M('DeviceLog')->add($deviceData);
    }

    /**
     * 检查设备是否在允许列表中
     */
    private function isDeviceAllowed($dingyueId, $qq, $fingerprint, $maxDevices)
    {
        $subscription = M('ShortDingyue')->where(['id' => $dingyueId])->find();
        $allowedDevices = [];

        if ($subscription && !empty($subscription['allowed_devices'])) {
            $allowedDevices = json_decode($subscription['allowed_devices'], true) ?: [];
        }

        // 在允许列表中或列表未满
        return in_array($fingerprint, $allowedDevices) || count($allowedDevices) < $maxDevices;
    }

    /**
     * 添加设备到允许列表
     */
    private function addDeviceToAllowedList($dingyueId, $fingerprint, $maxDevices)
    {
        $subscription = M('ShortDingyue')->where(['id' => $dingyueId])->find();
        $allowedDevices = [];

        if ($subscription && !empty($subscription['allowed_devices'])) {
            $allowedDevices = json_decode($subscription['allowed_devices'], true) ?: [];
        }

        if (!in_array($fingerprint, $allowedDevices) && count($allowedDevices) < $maxDevices) {
            $allowedDevices[] = $fingerprint;
            M('ShortDingyue')->where(['id' => $dingyueId])->save([
                'allowed_devices' => json_encode($allowedDevices),
                'drivers' => count($allowedDevices)
            ]);
            return true;
        }
        return false;
    }

    /**
     * 获取去重后的设备列表
     */
    private function getUniqueDevices($dingyueId, $qq, $subscription)
    {
        $allDevices = M('DeviceLog')->where([
            'dingyue_id' => $dingyueId,
            'qq' => $qq
        ])->order('last_seen DESC')->select();

        $tableFields = M('DeviceLog')->getDbFields();
        $hasFingerprint = in_array('fingerprint', $tableFields);
        $tableFieldsSub = M('ShortDingyue')->getDbFields();
        $hasAllowedDevices = in_array('allowed_devices', $tableFieldsSub);

        $uniqueDevices = [];
        $clashAndroidDevices = [];

        foreach ($allDevices as $device) {
            if (preg_match(self::$CLASH_ANDROID_PATTERN, $device['ua'])) {
                $clashAndroidDevices[] = $device;
                continue;
            }

            $fingerprint = ($hasFingerprint && !empty($device['fingerprint']))
                ? $device['fingerprint']
                : md5($device['ua'] . '|' . $device['ip']);

            if (!isset($uniqueDevices[$fingerprint]) || $device['last_seen'] > $uniqueDevices[$fingerprint]['last_seen']) {
                $uniqueDevices[$fingerprint] = $device;
            }
        }

        // Clash Android只保留最新一个
        if (!empty($clashAndroidDevices)) {
            usort($clashAndroidDevices, function ($a, $b) {
                return $b['last_seen'] - $a['last_seen'];
            });
            $uniqueDevices[md5("clash_meta_android_unified")] = $clashAndroidDevices[0];
        }

        // 过滤允许列表
        $allowedList = [];
        if ($hasAllowedDevices && !empty($subscription['allowed_devices'])) {
            $allowedList = json_decode($subscription['allowed_devices'], true) ?: [];
        }

        $result = [];
        foreach ($uniqueDevices as $device) {
            $fp = ($hasFingerprint && !empty($device['fingerprint']))
                ? $device['fingerprint']
                : md5($device['ua'] . '|' . $device['ip']);

            if (empty($allowedList) || in_array($fp, $allowedList)) {
                // 调用全局函数获取标准化的设备名称
                $normalizedUa = '';
                if (function_exists('parse_and_normalize_ua')) {
                    $normalizedUa = parse_and_normalize_ua($device['ua']);
                } else {
                    $normalizedUa = $this->getSoftwareName($device['ua']);
                }

                $result[] = [
                    'id' => $device['id'],
                    'fingerprint' => $fp,
                    'ua' => $device['ua'],
                    'normalized_ua' => $normalizedUa,  // 前端期望的字段
                    'ip' => $device['ip'],
                    'last_seen' => $device['last_seen'],
                    'device_type' => $this->parseDeviceType($device['ua']),
                    'software_name' => $this->getSoftwareName($device['ua'])
                ];
            }
        }

        usort($result, function ($a, $b) {
            return $b['last_seen'] - $a['last_seen'];
        });

        return $result;
    }

    /**
     * 解析UA并标准化设备类型
     * 使用function.php中的高级设备识别功能
     */
    private function parseDeviceType($ua)
    {
        if (empty($ua)) {
            return 'unknown';
        }

        // 使用function.php中的parse_and_normalize_ua函数进行高级识别
        $normalizedUa = function_exists('parse_and_normalize_ua') ? parse_and_normalize_ua($ua) : $ua;

        // 基于标准化后的UA确定设备类型
        $uaLower = strtolower($normalizedUa);

        // 定义设备类型映射规则（按优先级排序）
        $deviceRules = [
            'ios' => [
                'iphone',
                'ipad',
                'ipod',
                'shadowrocket',
                'quantumult',
                'surge',
                'loon',
                'stash',
                'sparkle'
            ],
            'android' => [
                'clashmetaforandroid',
                'clashforandroid',
                'v2rayng',
                'sagernet',
                'matsuri',
                'anxray',
                'android',
                'android_meta'
            ],
            'windows' => [
                'clashforwindows',
                'v2rayn',
                'flclash',
                'windows',
                'windows_pc'
            ],
            'mac' => [
                'macintosh',
                'mac os',
                'macos',
                'mac',
                'darwin'
            ],
            'linux' => [
                'linux',
                'ubuntu',
                'debian',
                'centos'
            ]
        ];

        // 按优先级检查设备类型
        foreach ($deviceRules as $deviceType => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($uaLower, $keyword) !== false) {
                    return $deviceType;
                }
            }
        }

        return 'unknown';
    }

    /**
     * 获取软件名称和设备详细信息
     * 使用function.php中的高级识别功能
     */
    private function getSoftwareName($ua)
    {
        if (empty($ua)) {
            return '未知设备';
        }

        // 使用function.php中的parse_and_normalize_ua函数进行高级识别
        $normalizedUa = function_exists('parse_and_normalize_ua') ? parse_and_normalize_ua($ua) : $ua;

        // 如果标准化后的UA包含具体设备型号（如iPhone、iPad、Mac等），直接返回
        if (preg_match('/^(iPhone|iPad|iPod|Mac)/', $normalizedUa)) {
            return $normalizedUa;
        }

        $uaLower = strtolower($ua);

        // 定义软件名称映射规则
        $softwareRules = [
            'Clash Meta for Android' => ['clashmetaforandroid', 'clash.meta.for.android', 'android_meta'],
            'Clash for Android' => ['clashforandroid'],
            'Clash for Windows' => ['clashforwindows'],
            'Clash Verge' => ['clash-verge', 'clashverge'],
            'Shadowrocket' => ['shadowrocket'],
            'Quantumult X' => ['quantumult'],
            'Surge' => ['surge'],
            'Loon' => ['loon'],
            'Stash' => ['stash'],
            'Sparkle' => ['sparkle'],
            'V2rayNG' => ['v2rayng'],
            'SagerNet' => ['sagernet'],
            'Matsuri' => ['matsuri'],
            'AnXray' => ['anxray'],
            'v2rayN' => ['v2rayn'],
            'FlClash' => ['flclash']
        ];

        // 按优先级检查软件名称
        foreach ($softwareRules as $softwareName => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($uaLower, $keyword) !== false) {
                    return $softwareName;
                }
            }
        }

        // 如果无法识别具体软件，尝试从标准化后的UA中提取
        if ($normalizedUa !== $ua && $normalizedUa !== 'Unknown') {
            return $normalizedUa;
        }

        // 通用客户端识别
        if (strpos($uaLower, 'clash') !== false) {
            return 'Clash 客户端';
        }
        if (strpos($uaLower, 'v2ray') !== false) {
            return 'V2Ray 客户端';
        }
        if (strpos($uaLower, 'shadowsocks') !== false) {
            return 'Shadowsocks 客户端';
        }

        return '未知软件';
    }

    // ==================== 日志方法 ====================

    /**
     * 记录拒绝访问日志
     */
    private function logReject($data, $reason, $ua)
    {
        $log = [
            'time' => date('Y-m-d H:i:s'),
            'qq' => $data['qq'],
            'dingyue_id' => $data['id'],
            'reason' => $reason,
            'ua' => $ua,
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        file_put_contents(APP_PATH . 'Runtime/Logs/device_reject.log', json_encode($log) . "\n", FILE_APPEND);
    }

    /**
     * 记录设备操作日志
     */
    private function logDeviceAction($action, $qq, $dingyueId, $fingerprint = '', $extra = '')
    {
        $log = [
            'time' => date('Y-m-d H:i:s'),
            'action' => $action,
            'qq' => $qq,
            'dingyue_id' => $dingyueId,
            'fingerprint' => $fingerprint,
            'extra' => $extra
        ];
        file_put_contents(APP_PATH . 'Runtime/Logs/device_management.log', json_encode($log) . "\n", FILE_APPEND);
    }
}