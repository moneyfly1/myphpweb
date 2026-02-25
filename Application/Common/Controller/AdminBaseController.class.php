<?php
namespace Common\Controller;
use Common\Controller\BaseController;
/**
 * admin 基类控制器
 */
class AdminBaseController extends BaseController
{
	/**
	 * 初始化方法
	 */
	public function _initialize()
	{
		parent::_initialize();
		$rule_name = MODULE_NAME . '/' . CONTROLLER_NAME . '/' . ACTION_NAME;

		// 登录页不需要任何权限检查和数据加载
		if ($rule_name == 'Admin/Admin/login') {
			return;
		}

		// 检查是否登录
		if (!isset($_SESSION['admin']['id']) || empty($_SESSION['admin']['id'])) {
			if (IS_AJAX) {
				$this->ajaxReturn(array('status' => 0, 'msg' => '请先登录'));
			} else {
				$this->display('Admin:login');
				exit;
			}
			return;
		}

		// 非超级管理员需要权限校验
		$superAdminIds = C('SUPER_ADMIN_IDS');
		if (!is_array($superAdminIds))
			$superAdminIds = array(88);
		if (!in_array($_SESSION['admin']['id'], $superAdminIds)) {
			$auth = new \Think\Auth();
			// 如果是customerDetail方法，使用detail方法的权限规则
			if ($rule_name == 'Admin/Dingyue/customerDetail') {
				$rule_name = 'Admin/Dingyue/detail';
			}
			$result = $auth->check($rule_name, $_SESSION['admin']['id']);
			if (!$result) {
				if (IS_AJAX) {
					$this->ajaxReturn(array('status' => 0, 'msg' => '您没有权限访问'));
				} else {
					$this->display('Admin:login');
					exit;
				}
				return;
			}
		}

		// 分配菜单数据
		try {
			$nav_data = D('AdminNav')->getTreeData('level', 'order_number,id');
		} catch (\Exception $e) {
			$nav_data = array();
		}
		$this->assign(array('nav_data' => $nav_data));
	}




}

