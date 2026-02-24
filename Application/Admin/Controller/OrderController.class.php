<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台首页控制器
 */
class OrderController extends AdminBaseController{
    
    
    
    
    public function paysite() {
       	

        $model = D('paysite');
        $list = $model->order('id asc')->select();

		
        $this->assign('list', $list);
        $this->display();
    }
       public function payedit() {
    if (IS_POST) {
        $temp = I('post.');
        $data = $temp;
        unset($data['id']);
        
        $result = D('paysite')->where(['id' => $temp['id']])->save($data);
        if ($result) {
            $this->success('修改成功', U('Admin/Order/paysite'));
        } else {
            $this->error('修改失败');
        }
    } else {
        $id = I('get.id', 'int');
        
        $data = D('paysite')->find($id); 
        
        $this->assign('data', $data);
        $this->display();
    }
}
    
    
    
    
    
// PHP控制器代码需要添加分页参数处理
public function index() {
    $model = D('order');
    $search = I('get.search', ''); // 修改：使用GET方式获取搜索关键词
    
    // 设置查询条件
    $where = array();
    if (!empty($search)) {
        // 修改：支持多字段搜索，移除不存在的user_id字段
        $where['_logic'] = 'or';
        $where['user_name'] = array('like', "%$search%");
        $where['order_no'] = array('like', "%$search%");
    }

    $date = I('get.date', '');
    if ($date === 'today') {
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $tomorrowStart = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day')));
        $where['create_time'] = array('between', array($todayStart, $tomorrowStart));
    }

    // 分页设置
    $count = $model->where($where)->count(); // 满足条件的总记录数
    $Page = new \Think\Page($count, 10); // 每页显示10条
    
    // 添加搜索参数到分页
    if (!empty($search)) {
        $Page->parameter['search'] = $search;
    }
    
    $Page->setConfig('prev', '上一页');
    $Page->setConfig('next', '下一页');
    $Page->setConfig('theme', '%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END%');
    
    // 获取分页数据
    $list = $model->where($where)
                 ->order('id desc')
                 ->limit($Page->firstRow.','.$Page->listRows)
                 ->select();
    
    // 处理数据
    foreach ($list as $k => $v) {
        $result = D('level')->where(array('id' => $v['plan_id']))->find();
        $list[$k]['tc'] = $result ? $result['name'] : '';
        
        // 格式化时间显示
        if (!empty($v['create_time'])) {
            $list[$k]['create_time'] = date('Y-m-d H:i:s', strtotime($v['create_time']));
        } else {
            $list[$k]['create_time'] = '';
        }
        
        if (!empty($v['pay_time'])) {
            $list[$k]['pay_time'] = date('Y-m-d H:i:s', strtotime($v['pay_time']));
        } else {
            $list[$k]['pay_time'] = '';
        }
        
        if($v['status'] == 0) {
            $list[$k]['status'] = '待支付';
        } elseif($v['status'] == 2) {
            $list[$k]['status'] = '已取消';
        } else {
            $list[$k]['status'] = "<font style='color:red'>已支付</font>";
        }
    }
    
    // 分配变量到模板
    $this->assign('list', $list);
    $this->assign('page', $Page->show());
    $this->assign('search', $search);
    $this->assign('totalCount', $count);
    
    $this->display();
}

/**
 * 从订单编号解析下单日期
 * 订单编号格式：YYYYMMDDHHMMSS + 4位随机数
 * 例如：202506212356456985 表示 2025年6月21日23时56分45秒
 * @param string $orderNo 订单编号
 * @return array 包含日期信息的数组
 */
public function parseOrderDate($orderNo) {
    if (strlen($orderNo) < 14) {
        return ['error' => '订单编号格式不正确'];
    }
    
    // 提取时间部分（前14位）
    $timePart = substr($orderNo, 0, 14);
    
    // 解析各个时间组件
    $year = substr($timePart, 0, 4);
    $month = substr($timePart, 4, 2);
    $day = substr($timePart, 6, 2);
    $hour = substr($timePart, 8, 2);
    $minute = substr($timePart, 10, 2);
    $second = substr($timePart, 12, 2);
    
    // 验证日期有效性
    if (!checkdate($month, $day, $year)) {
        return ['error' => '订单编号中的日期无效'];
    }
    
    // 格式化日期
    $orderDate = "$year-$month-$day";
    $orderDateTime = "$year-$month-$day $hour:$minute:$second";
    
    return [
        'order_no' => $orderNo,
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'hour' => $hour,
        'minute' => $minute,
        'second' => $second,
        'order_date' => $orderDate,
        'order_datetime' => $orderDateTime,
        'timestamp' => strtotime($orderDateTime)
    ];
}

/**
 * 根据订单编号查询订单信息并判断支付状态
 * @param string $orderNo 订单编号
 * @return array 订单信息
 */
public function getOrderInfoByNo($orderNo = '') {
    if (empty($orderNo)) {
        $orderNo = I('get.order_no', '');
    }
    
    if (empty($orderNo)) {
        $this->ajaxReturn(['code' => 0, 'msg' => '请提供订单编号']);
        return;
    }
    
    // 解析订单日期
    $dateInfo = $this->parseOrderDate($orderNo);
    
    // 查询订单信息
    $order = M('order')->where(['order_no' => $orderNo])->find();
    
    if (!$order) {
        $result = [
            'code' => 0,
            'msg' => '订单不存在',
            'data' => [
                'order_no' => $orderNo,
                'date_info' => $dateInfo,
                'exists' => false
            ]
        ];
    } else {
        // 获取套餐信息
        $plan = M('level')->where(['id' => $order['plan_id']])->find();
        
        // 判断支付状态
        $statusText = '';
        $isPaid = false;
        switch ($order['status']) {
            case 0:
                $statusText = '待支付';
                break;
            case 1:
                $statusText = '已支付';
                $isPaid = true;
                break;
            case 2:
                $statusText = '已取消';
                break;
            default:
                $statusText = '未知状态';
        }
        
        $result = [
            'code' => 1,
            'msg' => '查询成功',
            'data' => [
                'order_no' => $orderNo,
                'date_info' => $dateInfo,
                'exists' => true,
                'order_info' => [
                    'id' => $order['id'],
                    'user_name' => $order['user_name'],
                    'plan_id' => $order['plan_id'],
                    'plan_name' => $plan ? $plan['name'] : '未知套餐',
                    'total_amount' => $order['total_amount'],
                    'status' => $order['status'],
                    'status_text' => $statusText,
                    'is_paid' => $isPaid,
                    'create_time' => $order['create_time'],
                    'pay_time' => $order['pay_time'],
                    'trade_no' => isset($order['trade_no']) ? $order['trade_no'] : ''
                ]
            ]
        ];
    }
    
    // 如果是AJAX请求，返回JSON
    if (IS_AJAX) {
        $this->ajaxReturn($result);
    } else {
        // 否则分配到模板
        $this->assign('result', $result);
        $this->display();
    }
}

}
