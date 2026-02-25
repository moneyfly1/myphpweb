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
    		return;
    	}
	if (IS_POST) {
		$data = I('post.');
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
				$this->ajaxReturn(array('status'=>1, 'msg'=>'登录成功', 'url'=>'/'));
			}else{
				$this->ajaxReturn(array('status'=>0, 'msg'=>'账户未激活，请联系管理员'));
			}

		}else{
			$this->ajaxReturn(array('status'=>0, 'msg'=>'账号或密码错误'));
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

    /**
     * 发送邮箱验证码（注册用）
     */
    public function sendVerifyCode(){
        if(!IS_POST){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'请求方式错误'));
            return;
        }
        $username = I('post.user', '', 'trim');
        if(!is_numeric($username) || strlen($username) < 5 || strlen($username) > 16){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'请输入正确的QQ号码'));
            return;
        }
        // 检查是否已注册
        $exists = M('user')->where(['username'=>$username])->find();
        if($exists){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'该QQ号已注册，请直接登录'));
            return;
        }
        // 60秒内不能重复发送
        $cooldownKey = 'verify_cooldown_' . $username;
        if(S($cooldownKey)){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'发送太频繁，请稍后再试'));
            return;
        }
        // 生成6位验证码
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        // 存入缓存，5分钟有效
        S('verify_code_' . $username, $code, 300);
        // 设置60秒冷却
        S($cooldownKey, 1, 60);
        // 发送邮件
        $result = send_verify_code_email($username . '@qq.com', $username, $code, '注册');
        if($result){
            $this->ajaxReturn(array('status'=>1, 'msg'=>'验证码已发送到 ' . $username . '@qq.com'));
        }else{
            $this->ajaxReturn(array('status'=>0, 'msg'=>'验证码发送失败，请稍后重试'));
        }
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
		$username = $data['user'];
		$password = $data['pwd'];
		$verifyCode = isset($data['verify_code']) ? trim($data['verify_code']) : '';
		if(!is_numeric($username) || strlen($password)<8){
			if(IS_AJAX){
				$this->ajaxReturn(array('status'=>0, 'msg'=>'账号或密码填写有误，账号必须为QQ号码,密码不少于8位'));
			}else{
				$this->error('账号或密码填写有误，账号必须为QQ号码,密码不少于8位');
			}
			return;
		}
		// 验证邮箱验证码
		if(empty($verifyCode) || !preg_match('/^\d{6}$/', $verifyCode)){
			$this->ajaxReturn(array('status'=>0, 'msg'=>'请输入6位数字验证码'));
			return;
		}
		$cachedCode = S('verify_code_' . $username);
		if(!$cachedCode || $cachedCode !== $verifyCode){
			$this->ajaxReturn(array('status'=>0, 'msg'=>'验证码错误或已过期，请重新获取'));
			return;
		}
		// 验证通过，删除缓存
		S('verify_code_' . $username, null);
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
			$saveData['activation'] = 1; // 验证码已验证邮箱，直接激活
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
					if(IS_AJAX){
						$this->ajaxReturn(array('status'=>1, 'msg'=>'恭喜！注册成功，请登录', 'url'=>'/login'));
					}else{
						$this->success('恭喜！注册成功，请登录','/login');
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

    /**
     * 发送密码重置验证码
     */
    public function sendResetCode(){
        if(!IS_POST){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'请求方式错误'));
            return;
        }
        $username = I('post.user', '', 'trim');
        if(!is_numeric($username) || strlen($username) < 5 || strlen($username) > 16){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'请输入正确的QQ号码'));
            return;
        }
        // 检查用户是否存在
        $user = M('user')->where(['username'=>$username])->find();
        if(!$user){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'该账号不存在'));
            return;
        }
        // 60秒内不能重复发送
        $cooldownKey = 'reset_cooldown_' . $username;
        if(S($cooldownKey)){
            $this->ajaxReturn(array('status'=>0, 'msg'=>'发送太频繁，请稍后再试'));
            return;
        }
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        S('reset_code_' . $username, $code, 300);
        S($cooldownKey, 1, 60);
        $result = send_verify_code_email($username . '@qq.com', $username, $code, '密码重置');
        if($result){
            $this->ajaxReturn(array('status'=>1, 'msg'=>'验证码已发送到 ' . $username . '@qq.com'));
        }else{
            $this->ajaxReturn(array('status'=>0, 'msg'=>'验证码发送失败，请稍后重试'));
        }
    }

    public function getpass(){
    	if(check_user_login()){
    		if(IS_AJAX){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'您已登录，如需重置密码，请稍等将自动跳转', 'url'=>'/resetpass'));
    		}else{
    			$this->error('您已登录，如需重置密码，请稍等将自动跳转','/resetpass');
    		}
    		return;
    	}
    	if(IS_POST){
    		$username = I('post.user', '', 'trim');
    		$verifyCode = I('post.verify_code', '', 'trim');
    		$newPassword = I('post.new_password');
    		$confirmPassword = I('post.confirm_password');

    		if(!is_numeric($username)){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'请输入正确的QQ号码'));
    			return;
    		}
    		if(empty($verifyCode) || !preg_match('/^\d{6}$/', $verifyCode)){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'请输入6位数字验证码'));
    			return;
    		}
    		if(strlen($newPassword) < 8){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'新密码不能少于8位'));
    			return;
    		}
    		if($newPassword !== $confirmPassword){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'两次输入的密码不一致'));
    			return;
    		}
    		// 验证验证码
    		$cachedCode = S('reset_code_' . $username);
    		if(!$cachedCode || $cachedCode !== $verifyCode){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'验证码错误或已过期，请重新获取'));
    			return;
    		}
    		// 验证用户存在
    		$user = M('user')->where(['username'=>$username])->find();
    		if(!$user){
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'该账号不存在'));
    			return;
    		}
    		// 重置密码
    		S('reset_code_' . $username, null);
    		$newHash = secure_password_hash($newPassword);
    		$res = M('user')->where(['username'=>$username])->save(['password'=>$newHash]);
    		if($res !== false){
    			$this->ajaxReturn(array('status'=>1, 'msg'=>'密码重置成功，请登录', 'url'=>'/login'));
    		}else{
    			$this->ajaxReturn(array('status'=>0, 'msg'=>'密码重置失败，请重试'));
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
