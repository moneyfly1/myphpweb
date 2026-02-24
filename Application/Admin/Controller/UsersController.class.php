<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
set_time_limit(0);
/**
 * 前台用户控制器
 * 管理用户的增删改查、批量操作、邮件发送等功能
 */
class UsersController extends AdminBaseController {
	/**
	 * 用户列表（支持搜索和排序）
	 */
	public function list() {
		if (IS_POST) {
			$search = I('post.');
			$data['data'] = D('User')->getAllData(['username' => $search['search']]);
			// 如果没有结果，确保 $data['data'] 是数组，以避免后续报错
			if (!is_array($data['data'])) $data['data'] = [];
			// 记录搜索关键词，便于模板显示
			if (isset($search['search'])) $data['data'][0]['search'] = $search['search'];
			foreach ($data['data'] as $k => $v) {
				$data['data'][$k]['regtime'] = isset($v['regtime']) ? date('Y-m-d H:i:s', $v['regtime']) : '';
				$data['data'][$k]['lasttime'] = (isset($v['lasttime']) && $v['lasttime'] > 0) ? date('Y-m-d H:i:s', $v['lasttime']) : '';
				$data['data'][$k]['status'] = (isset($v['status']) && $v['status'] == 1) ? '启用' : "<font style='color:red'>禁用</font>";
				$data['data'][$k]['activation'] = (isset($v['activation']) && $v['activation'] == 1) ? '已激活' : "<font style='color:red'>未激活</font>";
			}
		} else {
			$request = I('get.');
			$orderWhitelist = ['regtime', 'id', 'lasttime'];
			$typeWhitelist = ['asc', 'desc'];
			$orderField = isset($request['order']) && in_array($request['order'], $orderWhitelist) ? $request['order'] : 'regtime';
			$orderType = isset($request['type']) && in_array(strtolower($request['type']), $typeWhitelist) ? strtolower($request['type']) : 'desc';
			$order = $orderField . ' ' . $orderType;
			$request['order'] = $orderField;
			$request['type'] = $orderType;
			// 使用 ShortDingyue 的 getPage 来分页 User 表数据（兼容原逻辑）
			$data = D('ShortDingyue')->getPage(M('User'), '', $order);
			if (!is_array($data) || !isset($data['data'])) $data = ['data' => []];
			foreach ($data['data'] as $k => $v) {
				$data['data'][$k]['regtime'] = isset($v['regtime']) ? date('Y-m-d H:i:s', $v['regtime']) : '';
				$data['data'][$k]['lasttime'] = (isset($v['lasttime']) && $v['lasttime'] > 0) ? date('Y-m-d H:i:s', $v['lasttime']) : '';
				$data['data'][$k]['status'] = (isset($v['status']) && $v['status'] == 1) ? '启用' : "<font style='color:red'>禁用</font>";
				$data['data'][$k]['activation'] = (isset($v['activation']) && $v['activation'] == 1) ? '已激活' : "<font style='color:red'>未激活</font>";
			}
			$this->assign('ordertype', $request['type'] == 'asc' ? 'desc' : 'asc');
		}
		$this->assign($data);
		$this->display();
	}

	/**
	 * 添加用户
	 */
	public function add() {
		if (IS_POST) {
			$data = I('post.');
			unset($data['file']); // 移除上传文件字段
			$check = D('User')->getData(['username' => $data['username']]);
			if ($check) {
				$this->error('账户已存在');
			} else {
				$data['regtime'] = time();
				$data['activation'] = '1';
				$data['lasttime'] = isset($data['lasttime']) ? strtotime($data['lasttime']) : 0;
				$data['password'] = secure_password_hash($data['password']);
				$res = D('User')->addData($data);
				if ($res) {
					// 获取最大设备数，默认为5
					$maxDevices = isset($data['max_devices']) && is_numeric($data['max_devices']) ? intval($data['max_devices']) : 5;
					// 确保最大设备数在合理范围内
					$maxDevices = max(1, min(50, $maxDevices));
					
					$shortData = [
						'qq' => $data['username'],
						'mobileshorturl' => generate_secure_random(16),
						'clashshorturl' => generate_secure_random(16),
						'addtime' => time(),
						'endtime' => time() + 31536000, // 有效期一年
						'setdrivers' => $maxDevices // 设置最大设备数
					];
					$shortres = M('ShortDingyue')->add($shortData);
					if ($shortres) {
						$this->success('创建用户及短链成功');
					} else {
						$this->error('添加失败');
					}
				}
			}
		}
		$this->display();
	}

	/**
	 * 编辑用户
	 */
	public function edit() {
		if (IS_POST) {
			$temp = I('post.');
			$data = $temp;
			unset($data['id']);
			if (!empty($data['password'])) {
				$data['password'] = secure_password_hash($data['password']);
			} else {
				unset($data['password']);
			}
			$result = D('User')->editData(['id' => $temp['id']], $data);
			if ($result) {
				$this->success('修改成功');
			} else {
				$this->error('修改失败');
			}
		} else {
			$id = I('get.id', 'int');
			$data = D('User')->getData(['id' => $id]);
			if (!$data) $this->error('用户不存在');
			if (!isset($data['status']) || $data['status'] === null) $data['status'] = 1;
			if (!isset($data['activation']) || $data['activation'] === null) $data['activation'] = 0;
			if ($data['lasttime'] > 0) $data['lasttime'] = date('Y-m-d H:i:s', $data['lasttime']);
			$data['status'] = intval($data['status']);
			$data['activation'] = intval($data['activation']);
			$this->assign('data', $data);
			$this->display();
		}
	}

	/**
	 * 删除用户及相关数据
	 */
	public function del() {
		$id = I('get.id', 'int');
		$user = D('User')->getData(['id' => $id]);
		if (!$user) $this->error('用户不存在', U('Users/list'));
		$qq = $user['username'];
		$userResult = D('User')->deleteData(['id' => $id]); // 删除用户表
		M('ShortDingyue')->where(['qq' => $qq])->delete(); // 删除订阅表
		M('order')->where(['user_name' => $qq])->delete(); // 删除订单表
		M('DeviceLog')->where(['qq' => $qq])->delete(); // 删除设备日志
		$emailQueueDb = M('email_queue');
		$emailQueueDb->where(['user_name' => $qq])->delete(); // 邮件队列
		$emailQueueDb->where(['qq' => $qq])->delete();
		M('short_dingyue_history')->where(['qq' => $qq])->delete(); // 历史订阅表
		M('dingyue')->where(['qq' => $qq])->delete(); // yg_dingyue表
		M('auth_group_access')->where(['uid' => $id])->delete(); // 用户组关联
		M('user_action_log')->where(['qq' => $qq])->delete(); // 用户操作日志
		if ($userResult) {
			$url = U('Users/list');
			echo "<script>alert('删除成功（用户、订阅、订单等数据已同步清理）'); window.location.href='{$url}';</script>";
			exit();
		} else {
			$url = U('Users/list');
			echo "<script>alert('删除失败'); window.location.href='{$url}';</script>";
			exit();
		}
	}

	/**
	 * 发送订阅邮件
	 */
	public function sendmail() {
		$id = I('get.id', 'int');
		$data = D('ShortDingyue')->getData(['id' => $id]);
		$mobileUrl = "https://" . $_SERVER['HTTP_HOST'] . '/' . $data['mobileshorturl'];
		$clashUrl = "https://" . $_SERVER['HTTP_HOST'] . '/' . $data['clashshorturl'];
		$result = send_subscription_email($data['qq'] . '@qq.com', $data['qq'], $mobileUrl, $clashUrl, $data['endtime']);
		if ($result) {
			$temp['ispush'] = 1;
			D('ShortDingyue')->editData(['id' => $id], $temp);
			$this->success('发送成功');
		} else {
			$this->error('发送失败');
		}
	}

	/**
	 * 批量删除订阅
	 */
	public function allDel() {
		$data = I('post.');
		$map['id'] = ['in', $data['id']];
		$result = D('ShortDingyue')->deleteData($map);
		if ($result) {
			die('删除成功');
		} else {
			die('删除失败');
		}
	}

	/**
	 * 批量推送订阅邮件
	 */
	public function allPush() {
		$data = I('post.');
		$map['id'] = ['in', $data['id']];
		$result = D('ShortDingyue')->getAllData($map);
		foreach ($result as $k => $v) {
			$temp = [];
			$mobileUrl = "https://" . $_SERVER['HTTP_HOST'] . '/' . $v['mobileshorturl'];
			$clashUrl = "https://" . $_SERVER['HTTP_HOST'] . '/' . $v['clashshorturl'];
			$emailResult = send_subscription_email($v['qq'] . '@qq.com', $v['qq'], $mobileUrl, $clashUrl, $v['endtime']);
			if ($emailResult) {
				$temp['ispush'] = 1;
				D('ShortDingyue')->editData(['id' => $v['id']], $temp);
			}
		}
		die('发送成功');
	}

	/**
	 * 批量禁用用户
	 */
	public function allDisable() {
		$data = I('post.');
		$map['id'] = ['in', $data['id']];
		$result = D('User')->getAllData($map);
		foreach ($result as $k => $v) {
			$temp['status'] = 0;
			D('User')->editData(['id' => $v['id']], $temp);
		}
		die('禁用成功');
	}

	/**
	 * 批量启用用户
	 */
	public function allEnable() {
		$data = I('post.');
		$map['id'] = ['in', $data['id']];
		$result = D('User')->getAllData($map);
		foreach ($result as $k => $v) {
			$temp['status'] = 1;
			D('User')->editData(['id' => $v['id']], $temp);
		}
		die('启用成功');
	}

	/**
	 * 编辑订阅到期时间
	 */
	public function editTime() {
		$data = I('post.');
		$temp['endtime'] = strtotime($data['endtime']);
		D('ShortDingyue')->editData(['id' => $data['id']], $temp);
	}

	/**
	 * 即将过期用户列表
	 */
	public function expiring() {
		$request = I('get.');
		$orderWhitelist = ['endtime', 'id', 'qq'];
		$typeWhitelist = ['asc', 'desc'];
		$orderField = isset($request['order']) && in_array($request['order'], $orderWhitelist) ? $request['order'] : 'endtime';
		$orderType = isset($request['type']) && in_array(strtolower($request['type']), $typeWhitelist) ? strtolower($request['type']) : 'asc';
		$order = $orderField . ' ' . $orderType;
		$request['order'] = $orderField;
		$request['type'] = $orderType;
		$currentTime = time();
		$sevenDaysLater = $currentTime + (7 * 24 * 60 * 60);
		$where = "endtime > {$currentTime} AND endtime <= {$sevenDaysLater}";
		$data = D('ShortDingyue')->getPage(M('ShortDingyue'), $where, $order);
		if (!is_array($data) || !isset($data['data'])) $data = ['data' => []];
		foreach ($data['data'] as $k => $v) {
			$data['data'][$k]['endtime_formatted'] = isset($v['endtime']) ? date('Y-m-d H:i:s', $v['endtime']) : '';
			$data['data'][$k]['days_left'] = isset($v['endtime']) ? ceil(($v['endtime'] - $currentTime) / (24 * 60 * 60)) : 0;
			$userInfo = D('User')->getData(['username' => $v['qq']]);
			$data['data'][$k]['user_status'] = $userInfo ? ($userInfo['status'] == 1 ? '启用' : '禁用') : '未知';
			$data['data'][$k]['user_activation'] = $userInfo ? ($userInfo['activation'] == 1 ? '已激活' : '未激活') : '未知';
		}
		$this->assign('ordertype', $request['type'] == 'asc' ? 'desc' : 'asc');
		$this->assign($data);
		$this->display();
	}

	/**
	 * 批量发送即将过期提醒邮件
	 */
	public function batchExpireReminder() {
		$data = I('post.');
		$map['id'] = ['in', $data['id']];
		$result = D('ShortDingyue')->getAllData($map);
		$successCount = 0;
		$failCount = 0;
		$logFile = dirname(dirname(dirname(__DIR__))) . '/Runtime/Logs/batch_expire_reminder.log';
		file_put_contents($logFile, "\n==== 批量发送开始: " . date('Y-m-d H:i:s') . " ====" . PHP_EOL, FILE_APPEND);
		foreach ($result as $k => $v) {
			$mobileUrl = "https://" . $_SERVER['HTTP_HOST'] . '/' . $v['mobileshorturl'];
			$clashUrl = "https://" . $_SERVER['HTTP_HOST'] . '/' . $v['clashshorturl'];
			$endtime = is_numeric($v['endtime']) ? intval($v['endtime']) : strtotime($v['endtime']);
			file_put_contents($logFile, "[{$k}] qq={$v['qq']} endtime={$v['endtime']} (ts={$endtime}) ", FILE_APPEND);
			$emailResult = send_expiration_email($v['qq'] . '@qq.com', $v['qq'], $endtime, false, true);
			file_put_contents($logFile, "send_expiration_email结果: " . var_export($emailResult, true) . PHP_EOL, FILE_APPEND);
			if ($emailResult) {
				$successCount++;
				$temp['last_reminder'] = time();
				D('ShortDingyue')->editData(['id' => $v['id']], $temp);
			} else {
				$failCount++;
			}
		}
		file_put_contents($logFile, "==== 批量发送结束: 成功: {$successCount} 失败: {$failCount} ====" . PHP_EOL, FILE_APPEND);
		die("发送完成！成功：{$successCount}，失败：{$failCount}");
	}

	/**
	 * 查看用户操作日志
	 */
	public function userLogs() {
		$userId = I('get.id', 'int');
		if (!$userId) {
			$this->error('用户ID不能为空');
		}

		// 获取用户信息
		$user = M('User')->where(['id' => $userId])->find();
		if (!$user) {
			$this->error('用户不存在');
		}

		// 确保用户数据完整性 - 只对真正缺失的字段设置默认值
		if (!isset($user['regtime']) || $user['regtime'] === null) {
			$user['regtime'] = 0;
		}
		if (!isset($user['lasttime']) || $user['lasttime'] === null) {
			$user['lasttime'] = 0;
		}
		if (!isset($user['status']) || $user['status'] === null) {
			$user['status'] = 0;
		}
		if (!isset($user['activation']) || $user['activation'] === null) {
			$user['activation'] = 0;
		}
		
		// 预处理显示数据
		$user['regtime_display'] = ($user['regtime'] && $user['regtime'] > 0) ? date('Y-m-d H:i:s', $user['regtime']) : '未知';
		$user['lasttime_display'] = ($user['lasttime'] && $user['lasttime'] > 0) ? date('Y-m-d H:i:s', $user['lasttime']) : '从未登录';
		$user['status_display'] = ($user['status'] == 1) ? '启用' : '禁用';
		$user['activation_display'] = ($user['activation'] == 1) ? '已激活' : '未激活';
		

		// 获取用户操作日志
		$actionLogs = M('UserActionLog')->where(['user_id' => $userId])->order('action_time desc')->select();
		if (!$actionLogs) {
			$actionLogs = [];
		}
		
		// 获取订阅历史记录
		$subscriptionHistory = M('ShortDingyueHistory')->where(['user_id' => $userId])->order('change_time desc')->select();
		if (!$subscriptionHistory) {
			$subscriptionHistory = [];
		}
		
		// 获取设备日志
		$shortDingyue = M('ShortDingyue')->where(['qq' => $user['username']])->find();
		$deviceLogs = [];
		if ($shortDingyue) {
			$deviceLogs = M('DeviceLog')->where(['dingyue_id' => $shortDingyue['id']])->order('last_seen desc')->select();
			if (!$deviceLogs) {
				$deviceLogs = [];
			}
		}

		// 格式化时间
		foreach ($actionLogs as &$log) {
			if (isset($log['action_time']) && $log['action_time'] > 0) {
				$log['action_time_formatted'] = date('Y-m-d H:i:s', $log['action_time']);
			} else {
				$log['action_time_formatted'] = '未知时间';
			}
		}
		foreach ($subscriptionHistory as &$history) {
			if (isset($history['change_time']) && $history['change_time'] > 0) {
				$history['change_time_formatted'] = date('Y-m-d H:i:s', $history['change_time']);
			} else {
				$history['change_time_formatted'] = '未知时间';
			}
		}
		foreach ($deviceLogs as &$device) {
			if (isset($device['last_seen']) && $device['last_seen'] > 0) {
				$device['last_seen_formatted'] = date('Y-m-d H:i:s', $device['last_seen']);
			} else {
				$device['last_seen_formatted'] = '未知时间';
			}
		}

		$this->assign('user', $user);
		$this->assign('actionLogs', $actionLogs);
		$this->assign('subscriptionHistory', $subscriptionHistory);
		$this->assign('deviceLogs', $deviceLogs);
		$this->display();
	}
}