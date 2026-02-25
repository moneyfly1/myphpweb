<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台首页控制器 - 综合仪表盘
 */
class IndexController extends AdminBaseController{

	public function index(){
		// 用户统计
		try {
			$totalUsers = M('user')->count();
			$activeUsers = M('user')->where(array('status' => 1))->count();
		} catch (\Exception $e) {
			$totalUsers = 0;
			$activeUsers = 0;
		}

		// 订阅统计
		try {
			$totalSubs = M('short_dingyue')->count();
			$activeSubs = M('short_dingyue')->where(array('endtime' => array('egt', time())))->count();
			$expiringSoon = M('short_dingyue')->where(array(
				'endtime' => array('between', array(time(), time() + 7*86400))
			))->count();
		} catch (\Exception $e) {
			$totalSubs = 0;
			$activeSubs = 0;
			$expiringSoon = 0;
		}

		// 订单统计
		try {
			$totalOrders = M('order')->count();
			$paidOrders = M('order')->where(array('status' => 1))->count();
			$todayOrders = M('order')->where(array(
				'create_time' => array('egt', date('Y-m-d 00:00:00'))
			))->count();
			$monthRevenue = M('order')->where(array(
				'status' => 1,
				'create_time' => array('egt', date('Y-m-01 00:00:00'))
			))->sum('total_amount') ?: 0;
		} catch (\Exception $e) {
			$totalOrders = 0;
			$paidOrders = 0;
			$todayOrders = 0;
			$monthRevenue = 0;
		}

		// 工单统计
		try {
			$pendingTickets = M('ticket')->where(array('status' => array('in', '0,1')))->count();
		} catch (\Exception $e) {
			$pendingTickets = 0;
		}

		// 设备统计
		try {
			$totalDevices = M('device_log')->count();
			$dayAgo = time() - 86400;
			$activeDevices24h = M('device_log')->where(array('last_seen' => array('egt', $dayAgo)))->count();
		} catch (\Exception $e) {
			$totalDevices = 0;
			$activeDevices24h = 0;
		}

		// 邮件队列
		try {
			$pendingEmails = M('email_queue')->where(array('status' => array('in', '0,1')))->count();
		} catch (\Exception $e) {
			$pendingEmails = 0;
		}

		// 最近订单 (last 10)
		try {
			$recentOrders = M('order')->order('id desc')->limit(10)->select();
		} catch (\Exception $e) {
			$recentOrders = array();
		}

		// 最近工单 (last 5)
		try {
			$recentTickets = M('ticket')->alias('t')
				->join('LEFT JOIN __USER__ u ON t.user_id = u.id')
				->field('t.*, u.username')
				->order('t.id desc')->limit(5)->select();
		} catch (\Exception $e) {
			$recentTickets = array();
		}

		// 节点统计
		try {
			$totalNodes = M('node')->count();
			$onlineNodes = M('node')->where(array('status' => 1))->count();
		} catch (\Exception $e) {
			$totalNodes = 0;
			$onlineNodes = 0;
		}

		// 系统信息
		try {
			$mysqlVer = M()->query("SELECT VERSION() as v");
			$systemInfo = array(
				'php_version' => PHP_VERSION,
				'mysql_version' => $mysqlVer[0]['v'],
				'server_os' => php_uname('s') . ' ' . php_uname('r'),
				'server_time' => date('Y-m-d H:i:s'),
				'disk_free' => round(disk_free_space('/') / 1073741824, 2) . ' GB',
				'disk_total' => round(disk_total_space('/') / 1073741824, 2) . ' GB',
			);
		} catch (\Exception $e) {
			$systemInfo = array(
				'php_version' => PHP_VERSION,
				'mysql_version' => '-',
				'server_os' => php_uname('s') . ' ' . php_uname('r'),
				'server_time' => date('Y-m-d H:i:s'),
				'disk_free' => '-',
				'disk_total' => '-',
			);
		}

		$this->assign('totalUsers', $totalUsers);
		$this->assign('activeUsers', $activeUsers);
		$this->assign('totalSubs', $totalSubs);
		$this->assign('activeSubs', $activeSubs);
		$this->assign('expiringSoon', $expiringSoon);
		$this->assign('totalOrders', $totalOrders);
		$this->assign('paidOrders', $paidOrders);
		$this->assign('todayOrders', $todayOrders);
		$this->assign('monthRevenue', $monthRevenue);
		$this->assign('pendingTickets', $pendingTickets);
		$this->assign('totalDevices', $totalDevices);
		$this->assign('activeDevices24h', $activeDevices24h);
		$this->assign('pendingEmails', $pendingEmails);
		$this->assign('recentOrders', $recentOrders ?: array());
		$this->assign('recentTickets', $recentTickets ?: array());
		$this->assign('totalNodes', $totalNodes);
		$this->assign('onlineNodes', $onlineNodes);
		$this->assign('systemInfo', $systemInfo);
		$this->display();
	}

	public function elements(){
		$this->display();
	}

	public function welcome(){
		$this->display();
	}

}
