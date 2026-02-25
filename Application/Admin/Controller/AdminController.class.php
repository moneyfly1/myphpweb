<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台首页控制器
 */
class AdminController extends AdminBaseController
{

	/**
	 * 用户列表
	 */
	public function index()
	{
		$word = I('get.word', '');
		if (empty($word)) {
			$map = array();
		} else {
			$map = array(
				'username' => $word
			);
		}
		$assign = D('Admin')->getAdminPage($map, 'register_time desc');
		$this->assign($assign);
		$this->display();
	}

	public function login()
	{
		if (IS_POST) {
			$data = I('post.');
			$username = $data['username'];
			$password = $data['password'];

			// 首先根据用户名查找管理员用户
			$get = M('admin')->where(['username' => $username])->find();

			// 验证密码（支持新旧密码格式）
			if ($get && verify_password($password, $get['password'])) {
				// 如果密码需要重新哈希（从MD5升级），则更新密码
				if (check_password_needs_rehash($get['password'])) {
					$new_hash = secure_password_hash($password);
					M('admin')->where(['id' => $get['id']])->save(['password' => $new_hash]);
				}
			} else {
				$get = false; // 密码验证失败
			}
			if ($get) {
				// 正确写入 $_SESSION['admin']，与 AdminBaseController 权限校验一致
				$_SESSION['admin'] = array(
					'id' => $get['id'],
					'username' => $get['username'],
					'avatar' => $get['avatar']
				);
				if (IS_AJAX) {
					$this->ajaxReturn(array('status' => 1, 'msg' => '登录成功', 'url' => '/admin.php?s=/Index/index'));
				} else {
					$this->success('登录成功', '/admin.php?s=/Index/index');
				}
			} else {
				if (IS_AJAX) {
					$this->ajaxReturn(array('status' => 0, 'msg' => '账号或密码错误'));
				} else {
					$this->error('账号或密码错误');
				}
			}
		} else {
			$data = isset($_SESSION['admin']['username']) ? $_SESSION['admin']['username'] . '已登录' : '未登录';
			$assign = array(
				'data' => $data
			);
			$this->assign($assign);
			$this->display();
		}
	}

	public function logout()
	{
		// 清除管理员 session
		$_SESSION['admin'] = null;
		unset($_SESSION['admin']);
		redirect('/admin.php?s=/Admin/login');
	}



}
