<?php
namespace Home\Controller;
use Think\Controller;

class OrderController extends Controller
{

    public function tc()
    {
        if (!check_user_login()) {
            $this->error('请登录后操作', '/login', 0);
        }

        $qq = $_SESSION['users']['username'];
        $m = M('ShortDingyue');
        $data = $m->where(['qq' => $qq])->find();

        if ($data) {
            $data['ms'] = $data['mobileshorturl'];
            $data['cs'] = $data['clashshorturl'];
            $data['mobileshorturl'] = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $data['mobileshorturl'];
            $data['clashshorturl'] = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $data['clashshorturl'];

            if (floor(($data['endtime'] - time()) / 86400) < 0) {
                $data['endtime'] = 0;
                $data['jsdate'] = '订阅已失效';
            } else {
                $data['jsdate'] = '有效期至：' . date('Y-m-d H:i:s', $data['endtime']);
                $data['endtime'] = floor(($data['endtime'] - time()) / 86400);
            }

            $data['qrcodeUrl'] = "sub://" . base64_encode($data['mobileshorturl']) . "#" . urlencode($data['jsdate']);
            $this->assign('data', $data);
        }

        $model = D('level');
        $list = $model->where('status = 1')->order('id asc')->select();
        $this->assign('list', $list);

        // 获取用户余额和VIP等级信息
        $userId = $_SESSION['users']['id'];
        $userInfo = M('user')->where(array('id' => $userId))->find();
        $this->assign('userBalance', floatval($userInfo['balance']));

        $vipDiscount = 1.00; // 默认无折扣(1.00=原价, 0.80=8折)
        $vipLevelName = '';
        if (!empty($userInfo['user_level_id'])) {
            $levelInfo = D('UserLevel')->where(array('id' => $userInfo['user_level_id']))->find();
            if ($levelInfo && floatval($levelInfo['discount_rate']) > 0) {
                $vipDiscount = floatval($levelInfo['discount_rate']);
                $vipLevelName = $levelInfo['level_name'];
            }
        }
        $this->assign('vipDiscount', $vipDiscount);
        $this->assign('vipLevelName', $vipLevelName);

        $this->display();
    }

    /**
     * AJAX验证优惠券
     */
    public function checkCoupon()
    {
        if (!isset($_SESSION['users']['id'])) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '请先登录'));
        }
        $couponCode = I('post.coupon_code', '', 'trim');
        $planId = intval(I('post.plan_id', 0));
        $amount = floatval(I('post.amount', 0));

        if (empty($couponCode)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '请输入优惠码'));
        }
        if ($planId <= 0 || $amount <= 0) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '请先选择套餐'));
        }

        $userId = $_SESSION['users']['id'];
        $result = D('Coupon')->validateCoupon($couponCode, $userId, $amount, $planId);
        $this->ajaxReturn($result);
    }

    public function notify()
    {
        $input = file_get_contents("php://input");
        parse_str($input, $data);

        if (empty($data['out_trade_no']) || empty($data['trade_no']) || empty($data['sign'])) {
            error_log("缺少必要参数");
            echo "failure";
            return;
        }

        if (($data['trade_status']) != 'TRADE_SUCCESS') {
            error_log("订单不存在");
            echo "failure";
            return;
        }

        $orderModel = D('order');
        $order = $orderModel->where([
            'order_no' => $data['out_trade_no'],
            'status' => 0
        ])->lock(true)->find();

        if (!$order) {
            error_log("订单不存在或状态非0");
            echo "success";
            return;
        }

        // 更新订单信息
        $updateData = [
            'status' => 1,
            'pay_time' => date('Y-m-d H:i:s', strtotime(urldecode($data['gmt_payment']))),
            'pay_no' => $data['trade_no']
        ];

        $result = $orderModel->where(['order_no' => $data['out_trade_no']])->save($updateData);

        if ($result === false) {
            error_log("订单更新失败: " . $orderModel->getDbError());
            echo "failure";
            return;
        }

        $addSeconds = $order['days'] * 86400;

        try {
            // 查询记录，如果不存在则创建
            // 使用M方法确保表名正确映射
            $record = M('ShortDingyue')
                ->where(['qq' => $order['user_name']])
                ->find();

            if (!$record) {
                // 如果订阅记录不存在，创建新记录
                error_log('订阅记录不存在，创建新记录: ' . $order['user_name']);
                $newRecord = [
                    'qq' => $order['user_name'],
                    'endtime' => 0,
                    'setdrivers' => 5,
                    'mobileshorturl' => '',
                    'clashshorturl' => ''
                ];
                $recordId = M('ShortDingyue')->add($newRecord);
                if (!$recordId) {
                    error_log('创建订阅记录失败: ' . M('ShortDingyue')->getDbError());
                    echo "success"; // 返回success避免重复回调，但记录错误
                    return;
                }
                // 重新查询记录
                $record = M('ShortDingyue')
                    ->where(['qq' => $order['user_name']])
                    ->find();
                if (!$record) {
                    error_log('创建记录后查询失败: ' . $order['user_name']);
                    echo "success"; // 返回success避免重复回调
                    return;
                }
            }

            // 计算新的到期时间
            $utcNow = (new \DateTime('now', new \DateTimeZone('UTC')))->getTimestamp();
            if (($record['endtime']) == 0 || $record['endtime'] <= $utcNow) {
                $newEndTime = $utcNow + $addSeconds;
            } else {
                $newEndTime = $record['endtime'] + $addSeconds;
            }

            // 获取套餐信息以更新设备数量限制
            $levelModel = D('level');
            $levelInfo = $levelModel->where(['id' => $order['plan_id']])->find();

            // 准备更新数据
            $updateData = ['endtime' => $newEndTime];

            // 如果找到套餐信息，同时更新设备数量限制
            if ($levelInfo && isset($levelInfo['setdrivers'])) {
                $updateData['setdrivers'] = $levelInfo['setdrivers'];
                error_log('更新设备数量限制: ' . $levelInfo['setdrivers'] . ' (套餐ID: ' . $order['plan_id'] . ')');
            }

            // 更新订阅时间和设备数量限制
            $result = M('ShortDingyue')
                ->where(['qq' => $order['user_name']])
                ->save($updateData);

            // 处理结果
            if ($result === false) {
                error_log('数据库更新失败: ' . M('ShortDingyue')->getDbError());
                error_log('更新数据: ' . json_encode($updateData, JSON_UNESCAPED_UNICODE));
                error_log('用户: ' . $order['user_name']);
                // 返回success避免支付宝重复回调，但记录错误日志
                echo "success";
                return;
            }
            
            // 记录成功日志
            error_log('订阅更新成功 - 用户: ' . $order['user_name'] . ', 新到期时间: ' . date('Y-m-d H:i:s', $newEndTime) . ', 设备限制: ' . (isset($updateData['setdrivers']) ? $updateData['setdrivers'] : '未更新'));

            // 更新用户累计消费并检查VIP升级
            $payUserId = M('user')->where(array('username' => $order['user_name']))->getField('id');
            if ($payUserId) {
                M('user')->where(array('id' => $payUserId))->setInc('total_consumption', $order['total_amount']);
                D('UserLevel')->checkUpgrade($payUserId);
            }
            // 记录优惠券使用
            if (!empty($order['coupon_code'])) {
                $coupon = D('Coupon')->where(array('code' => $order['coupon_code']))->find();
                if ($coupon) {
                    D('Coupon')->useCoupon($coupon['id'], $payUserId, $order['id'], $order['discount_amount']);
                }
            }

            // 无论是否发生变更，都发送通知
            error_log('数据库更新结果: ' . ($result === 0 ? '无变更' : '已更新'));

            // 发送通知
            $config = $this->getNotificationConfig();
            error_log('通知配置: ' . json_encode($config, JSON_UNESCAPED_UNICODE));

            // 构建格式化的通知消息
            $formattedMessage = $this->buildFormattedNotificationMessage($order, $data);
            error_log('通知消息: ' . $formattedMessage);

            // 发送Telegram通知
            if ($config['telegram']['enabled']) {
                $telegramResult = $this->sendTelegramNotification($formattedMessage['telegram'], $config['telegram']);
                error_log('Telegram通知结果: ' . ($telegramResult ? '成功' : '失败'));
            } else {
                error_log('Telegram通知已禁用');
            }

            // 发送Bark通知
            if ($config['bark']['enabled']) {
                $barkResult = $this->sendBarkNotification($formattedMessage['bark'], $config['bark']);
                error_log('Bark通知结果: ' . ($barkResult ? '成功' : '失败'));
            } else {
                error_log('Bark通知已禁用');
            }

            // 发送邮件通知
            if ($config['email']['enabled']) {
                $userEmailResult = $this->sendOrderEmailNotification($order, $data, $config['email'], true);
                error_log('用户邮件发送结果: ' . ($userEmailResult ? '成功' : '失败') . ' - 用户: ' . $order['user_name']);

                $adminEmailResult = $this->sendOrderEmailNotification($order, $data, $config['email'], false);
                error_log('管理员邮件发送结果: ' . ($adminEmailResult ? '成功' : '失败') . ' - 收件人: ' . $config['email']['to']);
            } else {
                error_log('邮件通知已禁用');
            }

            echo "success";

        } catch (\Exception $e) {
            $orderNoCtx = isset($data['out_trade_no']) ? $data['out_trade_no'] : 'unknown';
            error_log('支付回调异常 order_no: ' . $orderNoCtx . ' - ' . $e->getMessage());
            error_log('异常堆栈: ' . $e->getTraceAsString());
            echo "success";
        }
    }

    public function payReturn()
    {
        // 获取所有输入数据
        $input = file_get_contents("php://input");

        // 尝试解析JSON输入（如果是JSON格式）
        $data = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $logContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $order_no = I('get.order_no', '', 'trim');
            if ($order_no === '' || strlen($order_no) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $order_no)) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['paid' => 'false', 'msg' => 'invalid order_no']);
                return;
            }

            $o = D('order')->where([
                'order_no' => $order_no,
                'status' => 1
            ])->find();

            if ($o) {
                echo json_encode(['paid' => 'true']);
            } else {
                echo json_encode(['paid' => 'false']);
            }
        }
    }


    public function qx()
    {
        if (!isset($_SESSION['users']['username'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 1, 'msg' => '请先登录']);
            return;
        }

        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        $order_no = isset($data['order_no']) ? trim($data['order_no']) : '';

        if ($order_no === '' || strlen($order_no) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $order_no)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 1, 'msg' => '订单号无效']);
            return;
        }

        $order = D('order')->where(['order_no' => $order_no])->find();
        if (!$order) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 1, 'msg' => '订单不存在']);
            return;
        }
        if ($order['user_name'] !== $_SESSION['users']['username']) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 1, 'msg' => '无权操作该订单']);
            return;
        }
        if ($order['status'] != 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 1, 'msg' => '订单状态不允许取消']);
            return;
        }

        D('order')->where(['order_no' => $order_no])->setField('status', 2);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'msg' => '已取消']);
    }


    public function pay()
    {
        if (!isset($_SESSION['users']['username'])) {
            $this->error('请先登录', '/user/login');
        }

        $planId = intval(I('get.plan', 0));
        $paymentMethod = I('get.method', '', 'trim');
        $orderNo = I('get.order_no', '', 'trim');
        $couponCode = I('get.coupon_code', '', 'trim');

        $allowedMethods = ['支付宝', 'alipay', 'balance'];
        if (!in_array($paymentMethod, $allowedMethods)) {
            $this->error('不支持的支付方式');
        }

        if (!$orderNo) {
            $this->error('订单不存在');
        }

        // 优化：合并数据库查询，减少查询次数
        $orderModel = D('order');
        $dd = $orderModel->where(['order_no' => $orderNo])->find();

        $model = D('level');
        $plan = $model->where([
            'id' => $planId,
            'status' => 1
        ])->find();

        if (!$plan) {
            $this->error('套餐不存在或已下架');
        }

        // --- 计算折扣后金额 ---
        $userId = $_SESSION['users']['id'];
        $originalPrice = floatval($plan['price']);
        $finalAmount = $originalPrice;
        $discountAmount = 0;
        $couponDiscount = 0;
        $vipDiscountRate = 1.00; // 1.00=原价, 0.80=8折

        // VIP等级折扣
        $userInfo = M('user')->where(array('id' => $userId))->find();
        if (!empty($userInfo['user_level_id'])) {
            $userLevel = D('UserLevel')->where(array('id' => $userInfo['user_level_id']))->find();
            if ($userLevel && floatval($userLevel['discount_rate']) > 0 && floatval($userLevel['discount_rate']) < 1.00) {
                $vipDiscountRate = floatval($userLevel['discount_rate']);
            }
        }
        $finalAmount = round($originalPrice * $vipDiscountRate, 2);

        // 优惠券折扣
        if (!empty($couponCode)) {
            $couponResult = D('Coupon')->validateCoupon($couponCode, $userId, $finalAmount, $planId);
            if ($couponResult['code'] == 0) {
                $couponDiscount = floatval($couponResult['data']['discount']);
                $finalAmount = round($finalAmount - $couponDiscount, 2);
            } else {
                $this->error($couponResult['msg']);
            }
        }

        $discountAmount = round($originalPrice - $finalAmount, 2);
        if ($finalAmount < 0.01) {
            $finalAmount = 0.01;
        }

        if ($dd) {
            if ($dd['status'] != 0) {
                $this->error('订单已处理，无法重复支付');
            }
        } else {
            // 创建新订单
            $orderData = [
                'user_name' => $_SESSION['users']['username'],
                'plan_id' => $plan['id'],
                'order_no' => $orderNo,
                'total_amount' => $finalAmount,
                'discount_amount' => $discountAmount,
                'coupon_code' => $couponCode,
                'days' => $plan['num'],
                'status' => 0,
                'pay_method' => $paymentMethod,
                'create_time' => date('Y-m-d H:i:s')
            ];

            if (!$orderModel->add($orderData)) {
                $this->error('订单创建失败');
            }
        }

        // --- 余额支付 ---
        if ($paymentMethod === 'balance') {
            $order = $orderModel->where(['order_no' => $orderNo])->find();
            $result = $this->processBalancePayment($order, $plan);
            if ($result === true) {
                $this->redirect('/tc?pay_success=1');
            } else {
                $this->error($result);
            }
            return;
        }

        // --- 支付宝支付 ---
        $alipayConfig = D('paysite')->where([
            'pay_type' => 'zfb',
            'status' => 1
        ])->find();

        if (!$alipayConfig) {
            $this->error('支付宝配置错误');
        }

        // 用折扣后的价格覆盖plan price用于支付宝下单
        $planForPay = $plan;
        $planForPay['price'] = $finalAmount;

        $alipayResult = $this->alipayPay($orderNo, $planForPay, $alipayConfig);

        if ($alipayResult && $alipayResult['status'] === 'success') {
            $alipayResult['order_no'] = $orderNo;
            $alipayResult['price'] = $finalAmount;
            // 检查二维码是否生成成功（应该是Base64格式）
            if (empty($alipayResult['path']) || strpos($alipayResult['path'], 'data:image') !== 0) {
                error_log('二维码生成失败，path: ' . var_export($alipayResult['path'], true));
                $alipayResult['path'] = false; // 设置为false，模板会显示错误提示
            }
            $this->assign('list', $alipayResult);
            $this->display();
        } else {
            error_log('Order pay: 二维码生成失败 ' . json_encode($alipayResult));
            $this->error('二维码生成失败，请稍后重试');
        }
    }

    /**
     * 余额支付处理
     * @param array $order 订单信息
     * @param array $plan 套餐信息
     * @return true|string 成功返回true，失败返回错误信息
     */
    private function processBalancePayment($order, $plan)
    {
        $userId = $_SESSION['users']['id'];
        $userInfo = M('user')->where(array('id' => $userId))->find();
        if (!$userInfo) {
            return '用户不存在';
        }

        $amount = floatval($order['total_amount']);
        if (floatval($userInfo['balance']) < $amount) {
            return '余额不足，当前余额 ¥' . number_format($userInfo['balance'], 2);
        }

        // 扣除余额 (type: 2=消费)
        $balanceResult = D('RechargeRecord')->changeBalance(
            $userId,
            -$amount,
            2,
            '余额支付订单: ' . $order['order_no'],
            $order['id']
        );

        if (!$balanceResult) {
            return '余额扣除失败，请重试';
        }

        // 更新订单状态为已支付
        $orderModel = D('order');
        $orderModel->where(array('id' => $order['id']))->save(array(
            'status' => 1,
            'pay_time' => date('Y-m-d H:i:s'),
            'pay_no' => 'BALANCE_' . $order['order_no'],
        ));

        // 开通套餐服务
        $granted = $this->grantService($order);
        if (!$granted) {
            // 服务开通失败，退还余额
            D('RechargeRecord')->changeBalance(
                $userId,
                $amount,
                3, // type 3=退款
                '余额支付服务开通失败自动退款: ' . $order['order_no'],
                $order['id']
            );
            // 回滚订单状态
            $orderModel->where(array('id' => $order['id']))->save(array('status' => 0, 'pay_no' => ''));
            return '服务开通失败，余额已退还，请重试或联系管理员';
        }

        // 更新用户累计消费并检查VIP升级
        M('user')->where(array('id' => $userId))->setInc('total_consumption', $amount);
        D('UserLevel')->checkUpgrade($userId);

        // 记录优惠券使用
        if (!empty($order['coupon_code'])) {
            $coupon = D('Coupon')->where(array('code' => $order['coupon_code']))->find();
            if ($coupon) {
                D('Coupon')->useCoupon($coupon['id'], $userId, $order['id'], $order['discount_amount']);
            }
        }

        return true;
    }

    private function alipayPay($orderNo, $planInfo, $alipayConfig)
    {
        require_once './Vendor/Alipay/f2fpay/model/builder/AlipayTradePrecreateContentBuilder.php';
        require_once './Vendor/Alipay/f2fpay/service/AlipayTradeService.php';

        $qrcode = null;
        $outTradeNo = $orderNo;
        $subject = mb_substr($planInfo['name'] . '套餐（' . $planInfo['num'] . '天）', 0, 256);
        $totalAmount = number_format($planInfo['price'], 2, '.', '');
        $undiscountableAmount = "0.01";
        $body = mb_substr('有效期：' . $planInfo['num'] . '天 | 价格：¥' . $planInfo['price'], 0, 128);

        // 扩展参数（可选）
        $extendParams = new \ExtendParams();
        $extendParamsArr = $extendParams->getExtendParams();
        $timeExpress = "5m";

        // 创建请求builder，设置请求参数
        $qrPayRequestBuilder = new \AlipayTradePrecreateContentBuilder();
        $qrPayRequestBuilder->setOutTradeNo($outTradeNo);
        $qrPayRequestBuilder->setTotalAmount($totalAmount);
        $qrPayRequestBuilder->setTimeExpress($timeExpress);
        $qrPayRequestBuilder->setSubject($subject);
        $qrPayRequestBuilder->setBody($body);
        $qrPayRequestBuilder->setUndiscountableAmount($undiscountableAmount);
        $qrPayRequestBuilder->setExtendParams($extendParamsArr);

        // 构造参数
        $parameter = [
            'app_id' => $alipayConfig['app_id'],
            'alipay_public_key' => $alipayConfig['alipay_public_key'],
            'merchant_private_key' => $alipayConfig['merchant_private_key'],
            'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
            'return_url' => $alipayConfig['return_url'],
            'charset' => 'UTF-8',
            'sign_type' => 'RSA2',
            'MaxQueryRetry' => '10',
            'QueryDuration' => '3',
            'notify_url' => $alipayConfig['notify_url'],
        ];

        // 调用qrPay方法获取当面付应答
        $qrPay = new \AlipayTradeService($parameter);
        $qrPayResult = $qrPay->qrPay($qrPayRequestBuilder);

        // 根据状态值进行业务处理
        switch ($qrPayResult->getTradeStatus()) {
            case "SUCCESS":
                $response = $qrPayResult->getResponse();
                // 保存二维码原始内容，用于生成支付宝app跳转链接
                $qrCodeContent = $response->qr_code;
                $qrcode = [
                    'status' => 'success',
                    'path' => $qrPay->create_erweima($qrCodeContent),
                    'qr_code' => $qrCodeContent, // 保存原始二维码内容，用于跳转支付宝app
                ];
                break;
            case "FAILED":
                error_log('alipayPay FAILED: ' . json_encode($qrPayResult->getResponse()));
                break;
            case "UNKNOWN":
                error_log('alipayPay UNKNOWN: ' . json_encode($qrPayResult->getResponse()));
                break;
            default:
                error_log('alipayPay unexpected status');
                break;
        }
        return $qrcode;
    }

    public function alipayReturn()
    {
        $alipayConfig = D('paysite')->where([
            'pay_type' => 'zfb',
            'status' => 1
        ])->find();

        if (!$alipayConfig) {
            $this->error('支付宝配置错误');
        }

        vendor('Alipay.AlipayNotify');

        $alipayNotify = new \AlipayNotify([
            'app_id' => $alipayConfig['app_id'],
            'merchant_private_key' => $alipayConfig['merchant_private_key'],
            'alipay_public_key' => $alipayConfig['alipay_public_key'],
            'sign_type' => 'RSA2'
        ]);

        $verifyResult = $alipayNotify->verifyReturn();

        if ($verifyResult) {
            $outTradeNo = I('get.out_trade_no', '', 'trim');
            $tradeNo = I('get.trade_no', '', 'trim');
            if ($outTradeNo !== '' && $tradeNo !== '') {
                $this->handlePayment($outTradeNo, $tradeNo);
            }
            $this->success('支付成功');
        } else {
            $this->error('支付验证失败');
        }
    }

    public function alipayNotify()
    {
        $alipayConfig = D('paysite')->where([
            'pay_type' => 'zfb',
            'status' => 1
        ])->find();

        if (!$alipayConfig) {
            echo "fail";
            exit;
        }

        vendor('Alipay.AlipayNotify');

        $alipayNotify = new \AlipayNotify([
            'app_id' => $alipayConfig['app_id'],
            'merchant_private_key' => $alipayConfig['merchant_private_key'],
            'alipay_public_key' => $alipayConfig['alipay_public_key'],
            'sign_type' => 'RSA2'
        ]);

        $verifyResult = $alipayNotify->verifyNotify();

        if ($verifyResult) {
            $outTradeNo = isset($data['out_trade_no']) ? trim($data['out_trade_no']) : '';
            $tradeNo = isset($data['trade_no']) ? trim($data['trade_no']) : '';
            if ($outTradeNo !== '' && $tradeNo !== '') {
                $this->handlePayment($outTradeNo, $tradeNo);
            }
            echo "success";
        } else {
            echo "fail";
        }
    }

    private function handlePayment($orderNo, $tradeNo)
    {
        error_log('handlePayment 开始 - 订单号: ' . $orderNo . ', 交易号: ' . $tradeNo);
        
        $order = M('order')->where(['order_no' => $orderNo])->find();
        if (!$order) {
            error_log('handlePayment: 订单不存在 - 订单号: ' . $orderNo);
            return;
        }
        
        if ($order['status'] != 0) {
            error_log('handlePayment: 订单已处理 - 订单号: ' . $orderNo . ', 当前状态: ' . $order['status']);
            return;
        }
        
        error_log('handlePayment: 找到订单 - 订单号: ' . $orderNo . ', 用户: ' . $order['user_name'] . ', 套餐ID: ' . $order['plan_id'] . ', 天数: ' . $order['days']);
        
        // 更新订单状态
        $orderUpdateResult = M('order')->where(['id' => $order['id']])->save([
            'status' => 1,
            'pay_time' => date('Y-m-d H:i:s'),
            'trade_no' => $tradeNo
        ]);
        
        if ($orderUpdateResult === false) {
            error_log('handlePayment: 订单更新失败 - 订单号: ' . $orderNo . ', 错误: ' . M('order')->getDbError());
            return;
        }
        
        error_log('handlePayment: 订单更新成功，开始开通套餐 - 订单号: ' . $orderNo . ', 用户: ' . $order['user_name']);
        
        // 开通套餐服务
        $this->grantService($order);
        
        error_log('handlePayment 完成 - 订单号: ' . $orderNo);
    }

    /**
     * 开通套餐服务
     * @param array $order 订单信息
     * @return bool 成功返回true，失败返回false
     */
    private function grantService($order)
    {
        error_log('grantService 开始 - 用户: ' . $order['user_name'] . ', 订单号: ' . $order['order_no']);

        $subscription = M('ShortDingyue')->where(['qq' => $order['user_name']])->find();

        if (!$subscription) {
            // 订阅记录不存在，尝试创建
            error_log('grantService: 订阅记录不存在，创建新记录: ' . $order['user_name']);
            $newRecord = [
                'qq' => $order['user_name'],
                'endtime' => 0,
                'setdrivers' => 5,
                'mobileshorturl' => '',
                'clashshorturl' => ''
            ];
            $recordId = M('ShortDingyue')->add($newRecord);
            if (!$recordId) {
                error_log('grantService 错误：创建订阅记录失败: ' . M('ShortDingyue')->getDbError());
                return false;
            }
            $subscription = M('ShortDingyue')->where(['qq' => $order['user_name']])->find();
            if (!$subscription) {
                error_log('grantService 错误：创建后查询失败: ' . $order['user_name']);
                return false;
            }
        }

        $utcNow = (new \DateTime('now', new \DateTimeZone('UTC')))->getTimestamp();
        $addSeconds = $order['days'] * 86400;

        if ($subscription['endtime'] == 0 || $subscription['endtime'] <= $utcNow) {
            $newEndTime = $utcNow + $addSeconds;
        } else {
            $newEndTime = $subscription['endtime'] + $addSeconds;
        }

        $level = M('level')->where(['id' => $order['plan_id']])->find();
        $setdrivers = $level && isset($level['setdrivers']) ? intval($level['setdrivers']) : 5;

        $result = M('ShortDingyue')->where(['qq' => $order['user_name']])->save([
            'endtime' => $newEndTime,
            'setdrivers' => $setdrivers
        ]);

        if ($result === false) {
            error_log('grantService 错误：更新订阅失败 - 用户: ' . $order['user_name'] . ', 错误: ' . M('ShortDingyue')->getDbError());
            return false;
        }

        error_log('grantService 成功 - 用户: ' . $order['user_name'] . ', 到期: ' . date('Y-m-d H:i:s', $newEndTime) . ', 设备: ' . $setdrivers);
        return true;
    }

    private function getNotificationConfig()
    {
        $configFile = APP_PATH . 'Common/Conf/notification.php';

        if (file_exists($configFile)) {
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
     * 发送Telegram通知
     */
    private function sendTelegramNotification($message, $config)
    {
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
    private function sendBarkNotification($message, $config)
    {
        $url = rtrim($config['server'], '/') . '/' . $config['key'];

        $data = [
            'title' => '🎉 新订单支付成功',
            'body' => $message,
            'sound' => 'default',
            'icon' => 'https://api.qrserver.com/v1/create-qr-code/?size=50x50&data=success'
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


    private function senddEmailNotification($testMessage, $config)
    {



        if (!function_exists('send_email')) {
            // 可添加日志记录：error_log('send_email function not found');
            return false;
        }

        // 构建邮件主题（建议根据业务需求补充订单时间等动态信息）
        $subject = '订单通知 - ' . date('Y-m-d H:i:s');

        $content = '<h3>订单通知测试</h3>';
        $content .= '<p>' . nl2br(htmlspecialchars($testMessage)) . '</p>';
        $content .= '<hr>';
        $content .= '<p style="color: #666; font-size: 12px;">此邮件由系统自动发送，请勿回复。</p>';
        // 发送邮件并处理结果
        $result = send_email($config, $subject, $content);

        // 统一结果判断逻辑
        if ($result === true) {
            return true;
        }

        // 兼容不同返回格式（根据实际send_email实现调整）
        if (is_array($result) && isset($result['status'])) {
            return $result['status'] === true;
        }


        return false;
    }

    /**
     * 发送订单邮件通知（包含详细订单信息和订阅地址）
     * @param array $order 订单信息
     * @param array $paymentData 支付数据
     * @param array $emailConfig 邮件配置
     * @param bool $sendToUser 是否发送给用户（true=用户，false=管理员）
     */
    private function sendOrderEmailNotification($order, $paymentData, $emailConfig, $sendToUser = true)
    {
        try {
            // 引入邮件发送函数
            if (!function_exists('send_order_email')) {
                require_once APP_PATH . 'Common/Common/function.php';
            }

            // 校验邮件发送函数是否存在
            if (!function_exists('send_order_email')) {
                error_log('send_order_email function not found');
                return false;
            }

            // 获取套餐信息
            $plan = M('level')->where(['id' => $order['plan_id']])->find();
            if (!$plan) {
                error_log('Plan not found for order: ' . $order['order_no']);
                return false;
            }

            // 构建邮件配置
            if ($sendToUser) {
                // 发送给用户
                $config = array_merge($emailConfig, [
                    'username' => $order['user_name'],
                    'email' => $order['user_name'] . '@qq.com' // 发送给用户的QQ邮箱
                ]);
                $recipientType = '用户';
            } else {
                // 发送给管理员
                $config = array_merge($emailConfig, [
                    'username' => $order['user_name'],
                    'email' => $emailConfig['to'] // 发送给配置的默认邮箱
                ]);
                $recipientType = '管理员';
            }

            // 记录邮件发送信息
            error_log('准备发送邮件 - 类型: ' . $recipientType . ', 用户: ' . $order['user_name'] . ', 邮箱: ' . $config['email']);

            // 发送订单邮件
            $result = send_order_email(
                $config,
                $order['order_no'],
                $plan['name'],
                $plan['price'],
                $order['days'] . '天',
                '已支付',
                false, // 不使用队列，直接发送
                !$sendToUser // 如果是发送给用户，则isAdmin=false；如果是发送给管理员，则isAdmin=true
            );

            // 记录发送结果
            if ($result) {
                error_log('Order email sent successfully to: ' . $config['email']);
            } else {
                error_log('Failed to send order email to: ' . $config['email']);
            }

            return $result;

        } catch (Exception $e) {
            error_log('Error sending order email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 发送邮件通知
     */
    private function sendEmailNotification($orderNo, $plan, $config)
    {
        // 参数有效性校验
        if (empty($orderNo) || !is_array($plan) || empty($plan['name']) || empty($plan['price']) || empty($plan['num'])) {
            // 可添加日志记录：error_log('Invalid parameters for email notification');
            return false;
        }

        // 引入邮件发送函数
        if (!function_exists('send_order_email')) {
            require_once APP_PATH . 'Common/Common/function.php';
        }

        // 校验邮件发送函数是否存在
        if (!function_exists('send_order_email')) {
            error_log('send_order_email function not found');
            return false;
        }

        // 使用新的订单邮件模板，明确使用队列异步发送
        $result = send_order_email($config, $orderNo, $plan['name'], $plan['price'], $plan['num'] . '天', '已支付', true);

        // 统一结果判断逻辑
        if ($result === true) {
            return true;
        }

        // 兼容不同返回格式（根据实际send_email实现调整）
        if (is_array($result) && isset($result['status'])) {
            return $result['status'] === true;
        }


        return false;
    }

    /**
     * 异步发送邮件通知（不阻塞主流程）
     */
    private function sendEmailNotificationAsync($orderNo, $plan, $config)
    {
        // 使用队列异步发送，不阻塞主流程
        if (function_exists('send_order_email')) {
            // 确保使用队列模式
            send_order_email($config, $orderNo, $plan['name'], $plan['price'], $plan['num'] . '天', '已支付', true);
        }
    }

    /**
     * 构建格式化的通知消息
     * @param array $order 订单信息
     * @param array $paymentData 支付数据
     * @return string 格式化的消息
     */
    private function buildFormattedNotificationMessage($order, $paymentData)
    {
        // 获取套餐信息
        $plan = M('level')->where(['id' => $order['plan_id']])->find();
        $planName = $plan ? $plan['name'] : '未知套餐';

        // 获取用户订阅信息
        $subscription = M('ShortDingyue')->where(['qq' => $order['user_name']])->find();
        $expireDate = '';
        if ($subscription && $subscription['endtime'] > 0) {
            $expireDate = date('Y年m月d日 H:i:s', $subscription['endtime']);
        }

        // 构建Telegram格式消息（支持HTML）
        $telegramMessage = "🎉 <b>新订单支付成功</b>\n\n";
        $telegramMessage .= "👤 <b>用户账号：</b>" . htmlspecialchars($order['user_name']) . "\n";
        $telegramMessage .= "📋 <b>订单编号：</b><code>" . htmlspecialchars($paymentData['trade_no']) . "</code>\n";
        $telegramMessage .= "📦 <b>套餐名称：</b>" . htmlspecialchars($planName) . "\n";
        $telegramMessage .= "💰 <b>订单金额：</b>¥" . htmlspecialchars($paymentData['total_amount']) . "\n";
        $telegramMessage .= "⏱️ <b>服务时长：</b>" . $order['days'] . "天\n";
        $telegramMessage .= "🕐 <b>支付时间：</b>" . date('Y年m月d日 H:i:s', strtotime(urldecode($paymentData['gmt_payment']))) . "\n";

        if ($expireDate) {
            $telegramMessage .= "📅 <b>到期时间：</b>" . $expireDate . "\n";
        }

        $telegramMessage .= "\n✅ <b>服务已自动开通</b>\n";
        $telegramMessage .= "📧 <b>邮件通知：</b>已发送给用户和管理员\n";
        $telegramMessage .= "🔗 <b>订阅地址：</b>用户邮件中已包含详细地址和二维码";

        // 构建Bark格式消息（纯文本，更简洁）
        $barkMessage = "🎉 新订单支付成功\n\n";
        $barkMessage .= "用户：" . $order['user_name'] . "\n";
        $barkMessage .= "套餐：" . $planName . "\n";
        $barkMessage .= "金额：¥" . $paymentData['total_amount'] . "\n";
        $barkMessage .= "时长：" . $order['days'] . "天\n";
        $barkMessage .= "订单：" . $paymentData['trade_no'] . "\n";
        $barkMessage .= "时间：" . date('m-d H:i', strtotime(urldecode($paymentData['gmt_payment'])));

        if ($expireDate) {
            $barkMessage .= "\n到期：" . date('m-d H:i', strtotime($expireDate));
        }

        // 返回包含两种格式的消息数组
        return [
            'telegram' => $telegramMessage,
            'bark' => $barkMessage
        ];
    }

}
