<?php
namespace Home\Controller;
use Think\Controller;

class BalanceController extends Controller {

    public function index() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        $userId = $_SESSION['users']['id'];
        $user = M('user')->where(array('id' => $userId))->find();
        $records = D('RechargeRecord')->getRecords($userId, 20);
        if ($records) {
            foreach ($records as $k => $v) {
                $records[$k]['create_time_fmt'] = date('Y-m-d H:i:s', $v['created_at']);
                $records[$k]['amount_fmt'] = ($v['amount'] >= 0 ? '+' : '') . number_format($v['amount'], 2);
            }
        }
        $this->assign('balance', number_format(floatval($user['balance']), 2));
        $this->assign('records', $records ?: array());
        $this->display();
    }

    /**
     * 充值页面
     */
    public function recharge() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        $userId = $_SESSION['users']['id'];
        $user = M('user')->where(array('id' => $userId))->find();
        $this->assign('balance', number_format(floatval($user['balance']), 2));
        $this->display();
    }

    /**
     * 发起充值支付
     */
    public function pay() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }

        $amount = floatval(I('get.amount', 0));
        if ($amount < 1 || $amount > 10000) {
            $this->error('充值金额需在1-10000元之间');
        }
        $amount = round($amount, 2);

        // 生成充值订单号
        $orderNo = 'RC' . date('YmdHis') . mt_rand(1000, 9999);

        // 检查是否已有未支付的充值订单
        $orderModel = D('order');
        $existing = $orderModel->where(array(
            'user_name' => $_SESSION['users']['username'],
            'pay_method' => 'recharge',
            'status' => 0,
        ))->find();
        if ($existing) {
            // 复用已有订单
            $orderNo = $existing['order_no'];
            // 更新金额
            $orderModel->where(array('id' => $existing['id']))->save(array(
                'total_amount' => $amount,
                'create_time' => date('Y-m-d H:i:s'),
            ));
        } else {
            // 创建充值订单
            $orderModel->add(array(
                'user_name' => $_SESSION['users']['username'],
                'plan_id' => 0,
                'order_no' => $orderNo,
                'total_amount' => $amount,
                'days' => 0,
                'status' => 0,
                'pay_method' => 'recharge',
                'create_time' => date('Y-m-d H:i:s'),
            ));
        }

        // 获取支付宝配置
        $alipayConfig = D('paysite')->where(array(
            'pay_type' => 'zfb',
            'status' => 1,
        ))->find();

        if (!$alipayConfig) {
            $this->error('支付配置错误，请联系管理员');
        }

        // 调用支付宝生成二维码
        $planInfo = array(
            'name' => '余额充值',
            'num' => 0,
            'price' => $amount,
        );
        $alipayResult = $this->alipayPay($orderNo, $planInfo, $alipayConfig);

        if ($alipayResult && $alipayResult['status'] === 'success') {
            $this->assign('list', array(
                'status' => 'success',
                'path' => $alipayResult['path'],
                'qr_code' => $alipayResult['qr_code'],
                'order_no' => $orderNo,
                'price' => $amount,
            ));
            $this->display('rechargePay');
        } else {
            error_log('Balance recharge: QR generation failed ' . json_encode($alipayResult));
            $this->error('支付二维码生成失败，请稍后重试');
        }
    }

    /**
     * 充值支付宝异步回调
     */
    public function notify() {
        $input = file_get_contents("php://input");
        parse_str($input, $data);

        if (empty($data['out_trade_no']) || empty($data['trade_no']) || empty($data['sign'])) {
            echo "failure";
            return;
        }
        if ($data['trade_status'] != 'TRADE_SUCCESS') {
            echo "failure";
            return;
        }

        $orderModel = D('order');
        $order = $orderModel->where(array(
            'order_no' => $data['out_trade_no'],
            'status' => 0,
            'pay_method' => 'recharge',
        ))->lock(true)->find();

        if (!$order) {
            echo "success";
            return;
        }

        // 更新订单状态
        $orderModel->where(array('order_no' => $data['out_trade_no']))->save(array(
            'status' => 1,
            'pay_time' => date('Y-m-d H:i:s', strtotime(urldecode($data['gmt_payment']))),
            'pay_no' => $data['trade_no'],
        ));

        // 充值到用户余额
        $user = M('user')->where(array('username' => $order['user_name']))->find();
        if ($user) {
            D('RechargeRecord')->changeBalance(
                $user['id'],
                floatval($order['total_amount']),
                1, // type 1=充值
                '支付宝在线充值 订单:' . $order['order_no'],
                $order['id']
            );
        }

        echo "success";
    }

    /**
     * 查询充值订单支付状态 (前端轮询)
     */
    public function checkStatus() {
        $orderNo = I('get.order_no', '', 'trim');
        if (empty($orderNo) || !preg_match('/^[a-zA-Z0-9_-]+$/', $orderNo)) {
            echo json_encode(array('paid' => false));
            return;
        }
        $order = D('order')->where(array(
            'order_no' => $orderNo,
            'pay_method' => 'recharge',
            'status' => 1,
        ))->find();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('paid' => $order ? true : false));
    }

    /**
     * 调用支付宝当面付生成二维码 (复用OrderController的逻辑)
     */
    private function alipayPay($orderNo, $planInfo, $alipayConfig) {
        require_once './Vendor/Alipay/f2fpay/model/builder/AlipayTradePrecreateContentBuilder.php';
        require_once './Vendor/Alipay/f2fpay/service/AlipayTradeService.php';

        $subject = mb_substr('余额充值 ¥' . $planInfo['price'], 0, 256);
        $totalAmount = number_format($planInfo['price'], 2, '.', '');
        $body = '账户余额充值 ¥' . $planInfo['price'];

        $extendParams = new \ExtendParams();
        $extendParamsArr = $extendParams->getExtendParams();

        $qrPayRequestBuilder = new \AlipayTradePrecreateContentBuilder();
        $qrPayRequestBuilder->setOutTradeNo($orderNo);
        $qrPayRequestBuilder->setTotalAmount($totalAmount);
        $qrPayRequestBuilder->setTimeExpress("5m");
        $qrPayRequestBuilder->setSubject($subject);
        $qrPayRequestBuilder->setBody($body);
        $qrPayRequestBuilder->setUndiscountableAmount("0.01");
        $qrPayRequestBuilder->setExtendParams($extendParamsArr);

        // 使用充值专用的notify_url
        $notifyUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/Balance/notify';

        $parameter = array(
            'app_id' => $alipayConfig['app_id'],
            'alipay_public_key' => $alipayConfig['alipay_public_key'],
            'merchant_private_key' => $alipayConfig['merchant_private_key'],
            'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
            'return_url' => $alipayConfig['return_url'],
            'charset' => 'UTF-8',
            'sign_type' => 'RSA2',
            'MaxQueryRetry' => '10',
            'QueryDuration' => '3',
            'notify_url' => $notifyUrl,
        );

        $qrPay = new \AlipayTradeService($parameter);
        $qrPayResult = $qrPay->qrPay($qrPayRequestBuilder);

        switch ($qrPayResult->getTradeStatus()) {
            case "SUCCESS":
                $response = $qrPayResult->getResponse();
                $qrCodeContent = $response->qr_code;
                return array(
                    'status' => 'success',
                    'path' => $qrPay->create_erweima($qrCodeContent),
                    'qr_code' => $qrCodeContent,
                );
            default:
                error_log('Balance alipayPay failed: ' . json_encode($qrPayResult->getResponse()));
                return null;
        }
    }
}
