<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台首页控制器
 */
class UserController extends AdminBaseController{

	/**
	 * 用户列表
	 */
	public function index(){
		$word=I('get.word','');
		if (empty($word)) {
			$map=array();
		}else{
			$map=array(
				'username'=>$word
				);
		}
		$assign=D('Users')->getAdminPage($map,'register_time desc');
		$this->assign($assign);
		$this->display();
	}

	public function login(){
		if (IS_POST) {
			$data = I('post.');
			$username = $data['username'];
			$password = $data['password'];

			// 首先根据用户名查找管理员用户
			$get = M('users')->where(['username'=>$username])->find();
			
			// 验证密码（支持新旧密码格式）
			if ($get && verify_password($password, $get['password'])) {
				// 如果密码需要重新哈希（从MD5升级），则更新密码
				if (check_password_needs_rehash($get['password'])) {
					$new_hash = secure_password_hash($password);
					M('users')->where(['id'=>$get['id']])->save(['password'=>$new_hash]);
				}
			} else {
				$get = false; // 密码验证失败
			}
			if ($get) {
				$_SESSION['user']=array(
                    'id'=>$get['id'],
                    'username'=>$get['username'],
                    'avatar'=>$get['avatar']
                );
				$this->success('登录成功',U('/'));
			}else{
				$this->error('账号或密码错误');
			}
		}else{
			$data=check_login() ? $_SESSION['user']['username'].'已登录' : '未登录';
            $assign=array(
                'data'=>$data
                );
            $this->assign($assign);
            $this->display();
		}
	}

	public function logout(){
		session('user',null);
		$this->success('退出成功、前往登录页面',U('Admin/User/login'));
	}



}
