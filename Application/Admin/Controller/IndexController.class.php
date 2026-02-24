<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台首页控制器
 */
class IndexController extends AdminBaseController{

	public function index(){
		try {
			// 记录访问日志
			\Think\Log::write('访问后台首页 - 开始', 'INFO');
			
			// 获取数据统计
			$statistics = $this->getStatistics();
			\Think\Log::write('统计数据获取成功', 'INFO');
			
			// 分配数据到模板
			$this->assign('statistics', $statistics);
			\Think\Log::write('数据分配到模板成功', 'INFO');
			
			// 显示模板
			$this->display();
			\Think\Log::write('模板显示完成', 'INFO');
			
		} catch (\Think\Exception $e) {
			\Think\Log::write('后台首页错误：' . $e->getMessage(), 'ERROR');
			$this->error($e->getMessage());
		} catch (\Exception $e) {
			\Think\Log::write('后台首页系统错误：' . $e->getMessage(), 'ERROR');
			$this->error('系统错误，请查看日志');
		}
	}

	/**
	 * 获取数据统计
	 */
	private function getStatistics() {
		$nowTime = time();
		$sevenDaysLater = $nowTime + (7 * 24 * 60 * 60); // 7天后
		$thirtyDaysLater = $nowTime + (30 * 24 * 60 * 60); // 30天后
		$thirtyDaysAgo = $nowTime - (30 * 24 * 60 * 60); // 30天前
		$sevenDaysAgo = $nowTime - (7 * 24 * 60 * 60); // 7天前
		$todayStart = strtotime(date('Y-m-d 00:00:00'));
		$tomorrowStart = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day')));
		$monthStart = strtotime(date('Y-m-01 00:00:00'));
		$monthEnd = strtotime(date('Y-m-01 00:00:00', strtotime('+1 month')));
		
		// === 核心业务指标 ===
		// 1. 用户相关统计
		$totalUsers = M('User')->count();
		$todayUsers = M('User')->where(['regtime' => ['egt', $todayStart]])->count();
		$yesterdayStart = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
		$yesterdayUsers = M('User')->where(['regtime' => ['between', [$yesterdayStart, $todayStart]]])->count();
		$activeUsers = M('User')->where(['lasttime' => ['egt', $thirtyDaysAgo]])->count(); // 活跃用户：30天内登录
		
		// 2. 订阅相关统计 (按独立用户统计)
		$totalSubscriptions = M('ShortDingyue')->count('DISTINCT qq'); // 总订阅用户
		$activeSubscriptions = M('ShortDingyue')->where(['endtime' => ['gt', $nowTime]])->count('DISTINCT qq'); // 活跃订阅用户
		$expiredSubscriptions = M('ShortDingyue')->where('endtime < ' . $nowTime . ' AND endtime > 0')->count('DISTINCT qq'); // 已过期用户
		$expiringSoon = M('ShortDingyue')->where(['endtime' => ['between', [$nowTime, $sevenDaysLater]]])->count('DISTINCT qq'); // 即将过期用户
		$expiringIn30Days = M('ShortDingyue')->where(['endtime' => ['between', [$sevenDaysLater, $thirtyDaysLater]]])->count('DISTINCT qq'); // 30天内即将过期用户
		
		// 3. 订单和收入统计
        // 今日订单（实际支付）
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));
        $todayOrders = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$todayStart} AND UNIX_TIMESTAMP(pay_time) <= {$todayEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->count();
        $todayPaidOrders = $todayOrders;
        // 今日收入
        $todayRevenue = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$todayStart} AND UNIX_TIMESTAMP(pay_time) <= {$todayEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->sum('total_amount') ?: 0;
        
        // 昨日订单（实际支付）
        $yesterdayStart = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
        $yesterdayEnd = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
        $yesterdayOrders = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$yesterdayStart} AND UNIX_TIMESTAMP(pay_time) <= {$yesterdayEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->count();
        $yesterdayPaidOrders = $yesterdayOrders;
        // 昨日收入
        $yesterdayRevenue = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$yesterdayStart} AND UNIX_TIMESTAMP(pay_time) <= {$yesterdayEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->sum('total_amount') ?: 0;
        
        // 周订单（实际支付）
        $weekStart = strtotime(date('Y-m-d 00:00:00', $sevenDaysAgo));
        $weekEnd = strtotime(date('Y-m-d 23:59:59'));
        $weeklyOrders = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$weekStart} AND UNIX_TIMESTAMP(pay_time) <= {$weekEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->count();
        $weeklyPaidOrders = $weeklyOrders;
        // 周收入
        $weeklyRevenue = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$weekStart} AND UNIX_TIMESTAMP(pay_time) <= {$weekEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->sum('total_amount') ?: 0;
        
        // 月订单（实际支付）
        $monthStart = strtotime(date('Y-m-01 00:00:00'));
        $monthEnd = strtotime(date('Y-m-t 23:59:59'));
        $monthlyOrders = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$monthStart} AND UNIX_TIMESTAMP(pay_time) <= {$monthEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->count();
        $monthlyPaidOrders = $monthlyOrders;
        // 月收入
        $monthlyRevenue = M('order')->where([
            '_string' => "UNIX_TIMESTAMP(pay_time) >= {$monthStart} AND UNIX_TIMESTAMP(pay_time) <= {$monthEnd} AND pay_time IS NOT NULL",
            'status' => 1
        ])->sum('total_amount') ?: 0;
		
		// 4. 待处理事项统计
		$pendingOrders = M('order')->where(['status' => 0])->count(); // 待支付订单
		$failedOrders = M('order')->where(['status' => 2])->count(); // 失败订单
		
		// 5. 套餐销售统计（按支付时间统计最近30天）
		$levelStats = M('order')
			->field('plan_id, COUNT(*) as sales_count, SUM(total_amount) as total_revenue')
			->where([
				'status' => 1,
				'pay_time' => ['between', [date('Y-m-d 00:00:00', $thirtyDaysAgo), date('Y-m-d 23:59:59')]]
			])
			->group('plan_id')
			->order('sales_count desc')
			->limit(5)
			->select();
		
		// 获取套餐名称
		foreach ($levelStats as $k => $v) {
			$level = M('level')->where(['id' => $v['plan_id']])->find();
			$levelStats[$k]['level_name'] = $level ? $level['name'] : '未知套餐';
			$levelStats[$k]['total_revenue'] = number_format($v['total_revenue'], 2);
		}
		
		// 6. 获取详细列表数据
		$recentPayments = M('order')
			->field('user_name as qq, total_amount as money, pay_time, order_no, plan_id')
			->where([
				'_string' => "pay_time IS NOT NULL AND pay_time != '0000-00-00 00:00:00'",
				'status' => 1
			])
			->order('UNIX_TIMESTAMP(pay_time) desc')
			->limit(10)
			->select();
		
		// 为最近付款添加套餐名称和处理时间格式
        foreach ($recentPayments as $k => $v) {
            $level = M('level')->where(['id' => $v['plan_id']])->find();
            $recentPayments[$k]['level_name'] = $level ? $level['name'] : '未知套餐';
            
            // 将pay_time字符串转换为时间戳
            if (!empty($v['pay_time']) && $v['pay_time'] != '0000-00-00 00:00:00') {
                $timestamp = strtotime($v['pay_time']);
                $recentPayments[$k]['pay_time'] = $timestamp ? $timestamp : 0;
            } else {
                $recentPayments[$k]['pay_time'] = 0;
            }
        }
		
		$expiringUsers = M('ShortDingyue')
			->field('qq, endtime')
			->where(['endtime' => ['between', [$nowTime, $sevenDaysLater]]])
			->order('endtime asc')
			->limit(15)
			->select();
		
		// 为即将过期用户计算剩余天数
		foreach ($expiringUsers as $k => $v) {
			$expiringUsers[$k]['days_left'] = ceil(($v['endtime'] - $nowTime) / (24 * 60 * 60));
		}
		
		// 问题用户：只从所有未到期用户中检查
        $nowTime = time();
        $currentMonth = date('Y-m');
        $monthStart = strtotime($currentMonth . '-01 00:00:00');
        $monthEnd = strtotime($currentMonth . '-' . date('t') . ' 23:59:59');

        $problemUsers = [];
        $allUsers = M('ShortDingyue')->where(['endtime' => ['gt', $nowTime]])->select();

        foreach ($allUsers as $userInfo) {
            $qq = $userInfo['qq'];
            $count = isset($userInfo['count']) ? intval($userInfo['count']) : 0;
            $clashCount = isset($userInfo['clashcount']) ? intval($userInfo['clashcount']) : 0;
            $totalCount = $count + $clashCount;

            // 查找 user_id
            $user = M('user')->where(['username' => $qq])->find();
            $user_id = $user ? $user['id'] : 0;
            $changeCount = 0;
            if ($user_id) {
                $changeCount = M('ShortDingyueHistory')->where([
                    'user_id' => $user_id,
                    'change_time' => ['between', [$monthStart, $monthEnd]]
                ])->count();
            }

            if ($totalCount > 500 || $changeCount > 2) {
                $abnormalReason = '';
                if ($totalCount > 500) {
                    $abnormalReason = '本月订阅次数+clash请求次数：' . $totalCount . '（超过200次）';
                }
                if ($changeCount > 2) {
                    if (!empty($abnormalReason)) {
                        $abnormalReason .= '，';
                    }
                    $abnormalReason .= '本月重置订阅地址次数：' . $changeCount . '（超过2次）';
                }

                $problemUsers[] = [
                    'qq' => $qq,
                    'subscription_count' => $count,
                    'clash_count' => $clashCount,
                    'change_count' => $changeCount,
                    'abnormal_reason' => $abnormalReason,
                    'mobileshorturl' => $userInfo['mobileshorturl'],
                    'clashshorturl' => $userInfo['clashshorturl']
                ];
            }
        }
        
        // 调试信息：记录最终问题用户数
        \Think\Log::write('问题用户检测完成：共发现 ' . count($problemUsers) . ' 个问题用户', 'INFO');
		
		// 7. 计算转化率和增长率
		$conversionRate = $todayOrders > 0 ? round(($todayPaidOrders / $todayOrders) * 100, 2) : 0;
		$userGrowthRate = $yesterdayUsers > 0 ? round((($todayUsers - $yesterdayUsers) / $yesterdayUsers) * 100, 2) : 0;
		$revenueGrowthRate = $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 2) : 0;
		
		return [
			// 基础统计
			'total_users' => $totalUsers,
			'active_users' => $activeUsers,
			'total_subscriptions' => $totalSubscriptions,
			'active_subscriptions' => $activeSubscriptions,
			'expired_subscriptions' => $expiredSubscriptions,
			'expiring_subscriptions' => $expiringSoon,
			'expiring_in_30_days' => $expiringIn30Days,
			
			// 今日数据
			'today_new_users' => $todayUsers,
			'today_orders' => $todayOrders,
			'today_paid_orders' => $todayPaidOrders,
			'today_revenue' => number_format($todayRevenue, 2),
			
			// 昨日对比
			'yesterday_users' => $yesterdayUsers,
			'yesterday_orders' => $yesterdayOrders,
			'yesterday_paid_orders' => $yesterdayPaidOrders,
			'yesterday_revenue' => number_format($yesterdayRevenue, 2),
			
			// 周度数据
			'weekly_orders' => $weeklyOrders,
			'weekly_paid_orders' => $weeklyPaidOrders,
			'weekly_revenue' => number_format($weeklyRevenue, 2),
			
			// 月度数据
			'monthly_orders' => $monthlyOrders,
			'monthly_paid_orders' => $monthlyPaidOrders,
			'monthly_revenue' => number_format($monthlyRevenue, 2),
			
			// 待处理事项
			'pending_orders' => $pendingOrders,
			'failed_orders' => $failedOrders,
			'problem_users' => count($problemUsers),
			
			// 增长率
			'conversion_rate' => $conversionRate,
			'user_growth_rate' => $userGrowthRate,
			'revenue_growth_rate' => $revenueGrowthRate,
			
			// 详细列表
			'recent_payments' => $recentPayments,
			'expiring_users' => $expiringUsers,
			'problem_users_list' => $problemUsers,
			'top_packages' => $levelStats
		];
	}

	public function elements(){
		$this->display();
	}

	public function welcome(){
		$this->display();
	}

}
