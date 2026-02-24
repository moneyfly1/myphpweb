<?php
namespace Common\Controller;
use Common\Controller\BaseController;
/**
 * admin 基类控制器
 */
class AdminBaseController extends BaseController{
	/**
	 * 初始化方法
	 */
	public function _initialize(){
		parent::_initialize();
		$auth=new \Think\Auth();
		$rule_name=MODULE_NAME.'/'.CONTROLLER_NAME.'/'.ACTION_NAME;

        // if(!check_login()){
        //     redirect('/admin.php/user/login');die;
        // }
		// p($_SESSION);die;
		if ($rule_name != 'Admin/User/login') {
			// 检查用户是否登录
			if(!isset($_SESSION['user']['id']) || empty($_SESSION['user']['id'])){
				redirect('/admin.php/User/login');
			}
			
			$superAdminIds = C('SUPER_ADMIN_IDS');
			if (!is_array($superAdminIds)) $superAdminIds = array(88);
			if (!in_array($_SESSION['user']['id'], $superAdminIds)) {
				// 如果是customerDetail方法，使用detail方法的权限规则
				if($rule_name == 'Admin/Dingyue/customerDetail'){
					$rule_name = 'Admin/Dingyue/detail';
				}
				$result=$auth->check($rule_name,$_SESSION['user']['id']);
				if(!$result){
					redirect('/admin.php/User/login');
				}
			}
		}
		

		// 分配菜单数据
		$nav_data=D('AdminNav')->getTreeData('level','order_number,id');
		// var_dump($nav_data);die;
		$assign=array(
			'nav_data'=>$nav_data
			);
		$this->assign($assign);
	}




}

