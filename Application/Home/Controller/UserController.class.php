<?php
namespace Home\Controller;
use Think\Controller;
class UserController extends Controller {
    public function login(){
    	// 处理自动登录（从管理员后台跳转，校验一次性 token）
    	if(I('get.auto') == '1' && I('get.username') && I('get.token')){
    		$username = I('get.username', '', 'trim');
    		$token = I('get.token', '', 'trim');
    		if ($username === '' || $token === '' || strlen($token) > 64) {
    			if(IS_AJAX){
    				$this->ajaxReturn(array('status'=>0, 'msg'=>'链接无效'));
    			}else{
    				$this->error('链接无效');
    			}
    			return;
    		}
    		$cacheKey = 'auto_login_' . $token;
    		$storedUsername = S($cacheKey);
    		if ($storedUsername === false || $storedUsername !== $username) {
    			if(IS_AJAX){
    				$this->ajaxReturn(array('status'=>0, 'msg'=>'链接无效或已过期'));
    			}else{
    				$this->error('链接无效或已过期');
    			}
    			return;
    		}
    		S($cacheKey, null); // 一次性使用，立即失效

    		$user = M('user')->where(['username'=>$username])->find();
    		if(!$user){
    			if(IS_AJAX){
    				$this->ajaxReturn(array('status'=>0, 'msg'=>'用户不存在'));
    			}else{
    				$this->error('用户不存在');
    			}
    			return;
    		}
    		if($user['activation'] != 1){
    			if(IS_AJAX){
    				$this->ajaxReturn(array('status'=>0, 'msg'=>'用户账户未激活'));
    			}else{
    				$this->error('用户账户未激活');
    			}
    			return;
    		}
    		$_SESSION['users'] = array(
    			'id'=>$user['id'],
    			'username'=>$user['username']
    		);
    		$loginData['lasttime'] = time();
    		M('user')->where(['id'=>$user['id']])->save($loginData);
    		D('LoginHistory')->addRecord($user['id'], get_client_ip(), $_SERVER['HTTP_USER_AGENT']);
    		if(IS_AJAX){
    			$this->ajaxReturn(array('status'=>1, 'msg'=>'管理员代理登录成功', 'url'=>'/'));
    		}else{
    			$this->success('管理员代理登录成功','/',0);
    		}
    		return;
    	}

    	if(check_user_login()){
    		if(IS_AJAX){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'您已登录，请勿乱操作'));
    		}else{
    			$this->error('您已登录，请勿乱操作');
    		}
    	}
	if (IS_POST) {
		$data = I('post.');
		if (empty($data['verify_code']) || !check_verify($data['verify_code'])) {
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'验证码错误'));
			}else{
				$this->error('验证码错误');
			}
			return;
		}
		$username = $data['user'];
		$password = $data['pwd'];

		// 首先根据用户名查找用户
		$get = M('user')->where(['username'=>$username])->find();

		// 验证密码（支持新旧密码格式）
		if ($get && verify_password($password, $get['password'])) {
			// 如果密码需要重新哈希（从MD5升级），则更新密码
			if (check_password_needs_rehash($get['password'])) {
				$new_hash = secure_password_hash($password);
				M('user')->where(['id'=>$get['id']])->save(['password'=>$new_hash]);
			}
		} else {
			$get = false; // 密码验证失败
		}
		if ($get) {
			if($get['activation']==1){
				$_SESSION['users']=array(
                    'id'=>$get['id'],
                    'username'=>$get['username']
                );
                $loginData['lasttime'] = time();
                M('user')->where(['id'=>$get['id']])->save($loginData);
				D('LoginHistory')->addRecord($get['id'], get_client_ip(), $_SERVER['HTTP_USER_AGENT']);
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>1, 'msg'=>'登录成功', 'url'=>'/'));
				}else{
					$this->success('登录成功','/',0);
				}
			}else{
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>0, 'msg'=>'恭喜注册成功，激活邮件已经发送到你QQ邮箱，请去邮箱点击链接激活账户'));
				}else{
					$this->error('恭喜注册成功，激活邮件已经发送到你QQ邮箱，请去邮箱点击链接激活账户','/login',10);
				}
			}

		}else{
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'账号或密码错误'));
			}else{
				$this->error('账号或密码错误');
			}
		}
	}else{
		$data=check_user_login() ? $_SESSION['users']['username'].'已登录' : '未登录';
            $assign=array(
                'data'=>$data
                );
            $this->assign($assign);
            $this->display();
	}
    }

    /** 输出验证码图片，供登录/注册页使用 */
    public function verify(){
        show_verify();
        exit;
    }

    public function reg(){
    	if(check_user_login()){
    		if(IS_AJAX){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'您已登录，请勿乱操作'));
    		}else{
    			$this->error('您已登录，请勿乱操作');
    		}
    	}
	if (IS_POST) {
		$data = I('post.');
		if (empty($data['verify_code']) || !check_verify($data['verify_code'])) {
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'验证码错误'));
			}else{
				$this->error('验证码错误');
			}
			return;
		}
		$username = $data['user'];
		$password = $data['pwd'];
		if(!is_numeric($username) || strlen($password)<8){
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'账号或密码填写有误，账号必须为QQ号码,密码不少于8位'));
			}else{
				$this->error('账号或密码填写有误，账号必须为QQ号码,密码不少于8位');
			}
		}
		$get = M('user')->where(['username'=>$username])->find();
		if ($get) {
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'账号已存在，请不要再次注册，如果已经激活账户，请直接登录'));
			}else{
				$this->success('账号已存在，请不要再次注册，如果已经激活账户，请直接登录');
			}
		}else{
			$saveData['username'] = $username;
			$saveData['password'] = secure_password_hash($password);
			$saveData['regtime'] = time();
			$saveData['status'] = 1;
			$saveData['activation'] = 0;
			$reg = M('user')->add($saveData);
			if($reg){
				$checkold = M('dingyue')->where(['qq'=>$username])->find();
				$shortData['qq'] = $username;
			$shortData['mobileshorturl'] = generate_secure_random(16);
			$shortData['clashshorturl'] = generate_secure_random(16);
			$shortData['addtime'] = time();
				if($checkold){
					$shortData['endtime'] = '1701964800';//2023-12-08
				}else{
					$shortData['endtime'] = '0';
				}
				$shortres = M('ShortDingyue')->add($shortData);
				if($shortres){
					$keys = authcode($username,'true');
					$activationLink = "https://".$_SERVER['HTTP_HOST']."/active/reg/".$keys;
					send_activation_email($username.'@qq.com', $username, $activationLink, false);
					if(IS_AJAX){
						$this->ajaxReturn(array('status'=>1, 'msg'=>'恭喜！注册并创建订阅地址成功', 'url'=>'/login'));
					}else{
						$this->success('恭喜！注册并创建订阅地址成功','/login');
					}
				}else{
					if(IS_AJAX){
						$this->ajaxReturn(array('status'=>0, 'msg'=>'账户注册成功但订阅地址生成失败，请联系管理员'));
					}else{
						$this->error('账户注册成功但订阅地址生成失败，请联系管理员');
					}
				}

			}else{
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>0, 'msg'=>'注册失败，稍候再试'));
				}else{
					$this->error('注册失败，稍候再试');
				}
			}
		}
	}else{
            $this->display();
	}
    }

    public function active(){
    	$data = I('get.');
    	$check = authcode($data['reg'],'DECODE');
    	if(empty($check)){
    		$this->error('访问失效，请联系管理员');
    	}else{
    		$username = $check;
    		$res = M('user')->where(['username'=>$username])->save(['activation'=>1]);
    		if($res){
    			$this->success('激活成功','/login');
    		}else{
    			$this->error('已经激活成功可以直接登陆,如仍提示激活失败，请联系管理员');
    		}
    	}
    }

    public function getpass(){
    	if(check_user_login()){
    		if(IS_AJAX){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'您已登录，如需重置密码，请稍等将自动跳转', 'url'=>'/resetpass'));
    		}else{
    			$this->error('您已登录，如需重置密码，请稍等将自动跳转','/resetpass');
    		}
    	}
    	if(IS_POST){
    		$username = I('post.user');
    		$keys = authcode($username,'true');
    		$resetLink = "https://".$_SERVER['HTTP_HOST']."/respass/reset/".$keys;

    		// 使用邮件模板发送密码重置邮件，直接发送不走队列
    		$result = send_password_reset_email($username.'@qq.com', $username, $resetLink, false);

    		if($result){
    			if(IS_AJAX){
    				$this->ajaxReturn(array('status'=>1, 'msg'=>'重置链接已发送至邮箱，请自行登录查看', 'url'=>'/login'));
    			}else{
    				$this->success('重置链接已发送至邮箱，请自行登录查看','/login');
    			}
    		}else{
    			if(IS_AJAX){
    				$this->ajaxReturn(array('status'=>0, 'msg'=>'邮件发送失败，请稍后重试'));
    			}else{
    				$this->error('邮件发送失败，请稍后重试');
    			}
    		}
    	}else{
    		$this->display();
    	}

    }

    public function respass(){
    	$data = I('get.');
    	$check = authcode($data['reset'],'DECODE');
	if(IS_POST){
		if(empty($check)){
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'访问失效，可尝试重新生成重置链接，如一直无效，请联系管理员'));
			}else{
				$this->error('访问失效，可尝试重新生成重置链接，如一直无效，请联系管理员');
			}
		}else{
			$username = $check;
			$request = I('post.');
			if ($request['newpassword'] != $request['confirmpassword']) {
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>0, 'msg'=>'两次密码不一致，请重新确认后提交'));
				}else{
					$this->error('两次密码不一致，请重新确认后提交');
				}
			}
			$password = secure_password_hash($request['newpassword']);
			$res = M('user')->where(['username'=>$username])->save(['password'=>$password]);
			if ($res) {
				session('users',null);
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>1, 'msg'=>'密码重置成功，请重新登录账户', 'url'=>'/login'));
				}else{
					$this->success('密码重置成功，请重新登录账户','/login');
				}
			}else{
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>0, 'msg'=>'密码重置失败，请稍候重试！'));
				}else{
					$this->error('密码重置失败，请稍候重试！');
				}
			}
		}
	}else{
		$this->display();
	}
    }

	public function resetpass(){
		if(!check_user_login()){
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'您未登录，如需找回密码，请稍等将自动跳转', 'url'=>'/getpass'));
			}else{
				$this->error('您未登录，如需找回密码，请稍等将自动跳转','/getpass');
			}
		}
		if (IS_POST) {
			$request = I('post.');
			if ($request['newpassword'] != $request['confirmpassword']) {
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>0, 'msg'=>'两次密码不一致，请重新确认后提交'));
				}else{
					$this->error('两次密码不一致，请重新确认后提交');
				}
			}
			$password = secure_password_hash($request['newpassword']);
			$username = $_SESSION['users']['username'];
			$res = M('user')->where(['username'=>$username])->save(['password'=>$password]);
			if ($res) {
				session('users',null);
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>1, 'msg'=>'密码重置成功，请重新登录账户', 'url'=>'/login'));
				}else{
					$this->success('密码重置成功，请重新登录账户','/login');
				}
			}else{
				if(IS_AJAX){
					$this->ajaxReturn(array('status'=>0, 'msg'=>'密码重置失败，请稍候重试！'));
				}else{
					$this->error('密码重置失败，请稍候重试！');
				}
			}
		}else{
			$this->display();
		}

	}

    public function outlogin(){
		session('users',null);
		$this->error('已退出登录','/login',0);
    }
    public function checkDingyue(){
        set_time_limit(0);
        $nowTime = time();
        $ltTime = strtotime('-1 week');
        $agTime = strtotime('+15 days');
        $ltUser = M('ShortDingyue')->field(['qq','endtime'])->where(['endtime'=>['egt',$ltTime],['endtime'=>['lt',$nowTime]]])->select();
        // var_dump($ltUser);die;
        $ltTitle = '订阅过期提醒';
        $ltTotal = 0;

        foreach ($ltUser as $k=>$v) {
	    send_expiration_email($v['qq'].'@qq.com', $v['qq'], $v['endtime'], true);
	    $ltTotal++;
        }
        echo $ltTitle."总条数：".$ltTotal;
        $agUser = M('ShortDingyue')->field(['qq','endtime'])->where(['endtime'=>['elt',$agTime],['endtime'=>['gt',$nowTime]]])->select();
        // var_dump($agUser);die;
        $agTitle = '订阅即将到期提醒';
        $agTotal = 0;
        foreach ($agUser as $k=>$v) {
	    send_expiration_email($v['qq'].'@qq.com', $v['qq'], $v['endtime'], false);
	    $agTotal++;
        }
        echo $agTitle."总条数：".$agTotal;
    }

    public function checkDingyues(){
        set_time_limit(0);
        $nowTime = time();
        $ltTime = strtotime('-1 week');
        $agTime = strtotime('+15 days');
        // $ltUser = M('ShortDingyue')->field(['qq','endtime'])->where(['endtime'=>['egt',$ltTime],['endtime'=>['lt',$nowTime]]])->select();
        // var_dump($ltUser);die;
        $ltTitle = '订阅过期提醒';
        $content = "\r\n<br>您的订阅已于".date('Y-m-d',time())."过期，请联系管理员续费<br>";
		send_email('3219904322@qq.com',$ltTitle,$content);

    }

    public function loginHistory() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        $userId = session('users.id');
        $list = D('LoginHistory')->getByUser($userId, 30);
        foreach ($list as $k => $v) {
            $list[$k]['login_time_fmt'] = date('Y-m-d H:i:s', $v['login_time']);
        }
        $this->assign('list', $list);
        $this->display();
    }

    public function setTheme() {
        if (!check_user_login()) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '请先登录'));
        }
        $theme = I('post.theme', 'default', 'trim');
        $allowed = array('default', 'dark', 'green', 'purple');
        if (!in_array($theme, $allowed)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '无效主题'));
        }
        $userId = session('users.id');
        M('user')->where(array('id' => $userId))->save(array('theme' => $theme));
        session('users.theme', $theme);
        $this->ajaxReturn(array('code' => 0, 'msg' => '主题已更新'));
    }

}
