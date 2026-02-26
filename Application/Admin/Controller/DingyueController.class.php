<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
set_time_limit(0);
/**
 * 后台首页控制器
 */
class DingyueController extends AdminBaseController{

	private function _ok($msg, $url='') {
		if (IS_AJAX) { $this->ajaxReturn(array('code'=>0,'msg'=>$msg)); }
		else { $this->success($msg, $url); }
	}
	private function _fail($msg) {
		if (IS_AJAX) { $this->ajaxReturn(array('code'=>1,'msg'=>$msg)); }
		else { $this->error($msg); }
	}

	/**
	 * 列表
	 */
	/**
	 * 进入用户后台
	 */
	public function loginAsUser(){
		$id = I('get.id');
		if(!$id){
			$this->_fail('参数错误');
		}

		// 获取订阅信息
		$dingyue = D('ShortDingyue')->getData(['id'=>$id]);
		if(!$dingyue){
			$this->_fail('订阅不存在');
		}

		// 根据QQ号查找用户
		$user = D('User')->getData(['username'=>$dingyue['qq']]);
		if(!$user){
			$this->_fail('用户不存在');
		}
		
		// 生成一次性 token 并写入缓存（5 分钟有效），前台校验后即失效
		$token = md5($user['username'] . $user['password'] . time() . mt_rand());
		S('auto_login_' . $token, $user['username'], 300);

		$loginUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/login?auto=1&username=' . urlencode($user['username']) . '&token=' . $token;

		header('Location: ' . $loginUrl);
		exit();
	}
	
	/**
	 * 重置用户订阅地址
	 */
	public function resetSubscription(){
		$id = I('get.id');
		if(!$id){
			$this->_fail('参数错误');
		}
		// 获取订阅信息
		$dingyue = D('ShortDingyue')->getData(['id'=>$id]);
		if(!$dingyue){
			$this->_fail('订阅不存在');
		}

        // 获取用户信息
        $user = D('User')->getData(['username'=>$dingyue['qq']]);
        $user_id = $user ? $user['id'] : 0;
        $qq = $dingyue['qq']; // 新增

		// 生成新的16位随机订阅地址
		$newData = [
			'mobileshorturl' => generate_secure_random(16),
			'clashshorturl' => generate_secure_random(16)
		];

        // 写入历史表
        M('ShortDingyueHistory')->add([
            'user_id' => $user_id,
            'qq' => $qq, // 新增
            'old_url' => $dingyue['mobileshorturl'] . ' | ' . $dingyue['clashshorturl'],
            'new_url' => $newData['mobileshorturl'] . ' | ' . $newData['clashshorturl'],
            'change_type' => 'reset',
            'change_time' => time()
        ]);
        // 写入操作日志
        M('UserActionLog')->add([
            'user_id' => $user_id,
            'action' => 'reset_subscription',
            'detail' => '重置订阅地址，原地址：' . $dingyue['mobileshorturl'] . ' | ' . $dingyue['clashshorturl'] . '，新地址：' . $newData['mobileshorturl'] . ' | ' . $newData['clashshorturl'],
            'action_time' => time()
        ]);

		// 更新数据库
		$res = D('ShortDingyue')->editData(['id'=>$id], $newData);
		if($res){
			// 清空设备日志
			M('DeviceLog')->where(['dingyue_id' => $id])->delete();
			// 设备数归零并清空允许设备列表
			$reset_data = ['drivers' => 0];
			// 检查表结构，如果allowed_devices字段存在，则清空允许设备列表
			$table_fields = M('ShortDingyue')->getDbFields();
			$has_allowed_devices = in_array('allowed_devices', $table_fields);
			if ($has_allowed_devices) {
				$reset_data['allowed_devices'] = '[]';
			}
			D('ShortDingyue')->editData(['id' => $id], $reset_data);
			$this->_ok('订阅地址重置成功', U('Admin/Dingyue/list'));
		}else{
			$this->_fail('重置失败');
		}
	}

	public function list(){
		// 处理搜索请求 - 支持POST和GET参数
		$search_keyword = '';
		if (IS_POST) {
			$search = I('post.');
			$search_keyword = $search['search'];
		} else {
			// 处理GET参数中的qq搜索
			$qq_param = I('get.qq');
			if (!empty($qq_param)) {
				$search_keyword = $qq_param;
			}
		}
		
		if (!empty($search_keyword)) {
			// 执行搜索
			$data['data'][0] = D('ShortDingyue')->getOrData($search_keyword);
			// 如果主表没查到，尝试查历史表
			if (empty($data['data'][0])) {
				$history = M('ShortDingyueHistory')->where([
					'_string' => "old_url like '%{$search_keyword}%' or new_url like '%{$search_keyword}%'"
				])->order('change_time desc')->find();
				if ($history) {
					// 通过user_id查找最新订阅
					$user_id = $history['user_id'];
					$user = M('user')->where(['id'=>$user_id])->find();
					if ($user) {
						$data['data'][0] = D('ShortDingyue')->getData(['qq'=>$user['username']]);
						$data['data'][0]['history_match'] = $history; // 标记是历史查询
					}
				}
			}
			// var_dump($data);die;

			$data['data'][0]['mobileshorturl'] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$data['data'][0]['mobileshorturl'];
			$data['data'][0]['clashshorturl'] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$data['data'][0]['clashshorturl'];
			$data['data'][0]['search'] = $search_keyword;
			// 确保搜索关键词能在模板中正确显示
			$data[0]['search'] = $search_keyword;
			if($data['data'][0]['endtime']>0){
			    ob_start();
			    qrcode("sub://".base64_encode($data['data'][0]['mobileshorturl'])."#".urlencode("有效期至：".date('Y-m-d H:i:s',$data['data'][0]['endtime'])));
			    $data['data'][0]['qrcodeUrl'] = base64_encode(ob_get_contents());
			    ob_end_clean();
			}else{
			    ob_start();
			    qrcode("sub://".base64_encode($data['data'][0]['mobileshorturl'])."#".urlencode('订阅已失效'));
			    $data['data'][0]['qrcodeUrl'] = base64_encode(ob_get_contents());
			    ob_end_clean();
			}
			
			$data['data'][0]['addtime'] = date('Y-m-d',$data['data'][0]['addtime']);
			$data['data'][0]['endtime'] = date('Y-m-d',$data['data'][0]['endtime']);
			if($data['data'][0]['ispush']==1) {
			    $data['data'][0]['ispush'] = '已发送';
			}else{
			    $data['data'][0]['ispush'] = "<font style='color:red'>未发送</font>";
			}
			if($data['data'][0]['status']==1) {
			    $data['data'][0]['status'] = '启用';
			}else{
			    $data['data'][0]['status'] = "<font style='color:red'>禁用</font>";
			}
		}else{
			$request = I('get.');
			$orderWhitelist = ['addtime', 'endtime', 'id', 'qq'];
			$typeWhitelist = ['asc', 'desc'];
			$orderField = isset($request['order']) && in_array($request['order'], $orderWhitelist) ? $request['order'] : 'addtime';
			$orderType = isset($request['type']) && in_array(strtolower($request['type']), $typeWhitelist) ? strtolower($request['type']) : 'desc';
			$order = $orderField . ' ' . $orderType;
			$this->assign('order', $orderField);
			$this->assign('type', $orderType);
			// 构造分页参数
			$pageParams = [
				'order' => $orderField,
				'type' => $orderType
			];
			if (!empty($search_keyword)) {
				$pageParams['search'] = $search_keyword;
			}
			$map = [];
			if (!empty($search_keyword)) {
				$map['qq'] = ['like', "%$search_keyword%"];
			}
			$model = M('ShortDingyue');
			$count = $model->where($map)->count();
			$page = new \Org\Nx\Page($count, 10, $pageParams);
			$list = $model->where($map)->order($order)->limit($page->firstRow.','.$page->listRows)->select();
			$data = array(
				'data' => $list,
				'page' => $page->show()
			);
			foreach ($data['data'] as $k => $v) {
			    
    			$data['data'][$k]['mobileshorturl'] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$v['mobileshorturl'];
    			$data['data'][$k]['clashshorturl'] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$v['clashshorturl'];
				$data['data'][$k]['addtime'] = date('Y-m-d',$v['addtime']);
				if($data['data'][$k]['endtime']>0){
				    $data['data'][$k]['endtime'] = date('Y-m-d',$v['endtime']);
				    ob_start();
				    qrcode("sub://".base64_encode($data['data'][$k]['mobileshorturl'])."#".urlencode("有效期至：".date('Y-m-d H:i:s',$v['endtime'])));
    			    $data['data'][$k]['qrcodeUrl'] = base64_encode(ob_get_contents());
    			    ob_end_clean();
				}else{
				    $data['data'][$k]['endtime'] = '';
				    ob_start();
				    qrcode("sub://".base64_encode($data['data'][$k]['mobileshorturl'])."#".urlencode('订阅已失效'));
    			    $data['data'][$k]['qrcodeUrl'] = base64_encode(ob_get_contents());
    			    ob_end_clean();
				}

    			if($data['data'][$k]['ispush']==1) {
    			    $data['data'][$k]['ispush'] = '已发送';
    			}else{
    			    $data['data'][$k]['ispush'] = "<font style='color:red'>未发送</font>";
    			}
    			if($data['data'][$k]['status']==1) {
    			    $data['data'][$k]['status'] = '启用';
    			}else{
    			    $data['data'][$k]['status'] = "<font style='color:red'>禁用</font>";
    			}
			}
			if($orderType=='asc'){
				$this->assign('ordertype','desc');
			}else{
				$this->assign('ordertype','asc');
			}
		}
		$this->assign($data);
		$this->display();
	}

	public function add(){
		if(IS_POST){
			$data=I('post.');
			if(!$data['upexcle']){
				unset($data['file']);
				$data['addtime'] = time();
				$data['endtime'] = strtotime($data['endtime']);
				// 自动生成16位随机订阅地址
				$data['mobileshorturl'] = generate_secure_random(16);
				$data['clashshorturl'] = generate_secure_random(16);
				// 默认最大设备数为5
				if (!isset($data['setdrivers']) || !$data['setdrivers']) {
					$data['setdrivers'] = 5;
				}
				$res = D('ShortDingyue')->addData($data);
				if ($res) {
					$this->_ok('添加成功', U('Admin/Dingyue/list'));
				}else{
					$this->_fail('添加失败');
				}
			}else{
				$list=import_excel('/www/wwwroot/proxy.icandoit.ml'.$data['upexcle'][0]);

				foreach ($list as $k => $v) {
					$data = [];
					$check = D('ShortDingyue')->getData(['qq'=>$v['2']]);
					if ($check) {
						if (substr($v['0'],-2)=='xr') {
							$data['mobileurl'] = $v['0'];
							$data['mobileshorturl'] = $v['1'];
						}else{
							$data['clashurl'] = $v['0'];
							$data['clashshorturl'] = $v['1'];
						}
						D('ShortDingyue')->editData(['qq'=>$v['2']],$data);
					}else{
						if (substr($v['0'],-2)=='xr') {
							$data['mobileurl'] = $v['0'];
							$data['mobileshorturl'] = $v['1'];
						}else{
							$data['clashurl'] = $v['0'];
							$data['clashshorturl'] = $v['1'];
						}
						$data['qq'] = $v['2'];
						$data['addtime'] = time();
						D('ShortDingyue')->addData($data);
					}
				}
			}
		}
		$this->display();
	}


	public function edit(){
		if (IS_POST) {
			$temp = I('post.');
			$data = $temp;
			unset($data['id']);
			$data['endtime'] = strtotime($data['endtime']);
			$result = D('ShortDingyue')->editData(['id'=>$temp['id']],$data);
			if ($result) {
				$this->_ok('修改成功', U('Admin/Dingyue/list'));
			}else{
				$this->_fail('修改失败');
			}
		}else{
			$id = I('get.id','int');
			$data = D('ShortDingyue')->getData(['id'=>$id]);
			if($data['endtime']>0){
				    $data['endtime'] = date('Y-m-d',$data['endtime']);
				}
			$this->assign('data',$data);
			$this->display();
		}
	}

	public function del(){
		$id = I('get.id','int');
		$dingyue = D('ShortDingyue')->getData(['id'=>$id]);
		if (!$dingyue) {
			$this->ajaxReturn(['status'=>0, 'info'=>'订阅记录不存在']);
		}
		$qq = $dingyue['qq'];
		// 删除设备日志
		M('DeviceLog')->where(['dingyue_id'=>$id,'qq'=>$qq])->delete();
		// 2. 删除订阅表
		$result = D('ShortDingyue')->deleteData(['id'=>$id]);
		// 3. 同步删除用户、订单、邮件队列
		if ($qq) {
			M('User')->where(['username' => $qq])->delete();
			M('order')->where(['user_name' => $qq])->delete();
			if (M('email_queue', '', true)) {
				M('email_queue')->where(['user_name' => $qq])->delete();
				M('email_queue')->where(['qq' => $qq])->delete();
			}
		}
		if ($result) {
			write_action_log('delete_user', "管理员删除了用户{$qq}", $_SESSION['admin']['username'] ?? '');
			$this->ajaxReturn(['status'=>1, 'info'=>'该账号下所有订阅、邮件、用户信息、日志、设备记录等信息已全部清除']);
		}else{
			$this->ajaxReturn(['status'=>0, 'info'=>'删除失败']);
		}
	}

	public function sendmail(){
		$id = I('get.id','int');
		$data = D('ShortDingyue')->getData(['id'=>$id]);
// 		var_dump($data);die;
		$mobileUrl = "https://".$_SERVER['HTTP_HOST'].'/'.$data['mobileshorturl'];
		$clashUrl = "https://".$_SERVER['HTTP_HOST'].'/'.$data['clashshorturl'];
		$result = send_subscription_email($data['qq'].'@qq.com', $data['qq'], $mobileUrl, $clashUrl, $data['endtime']);
		if ($result) {
		    $temp['ispush'] = 1;
		    D('ShortDingyue')->editData(['id'=>$id],$temp);
			$this->_ok('发送成功');
		}else{
			$this->_fail('发送失败');
		}
	}

	public function allDel(){
		$data = I('post.');
		$map['id'] = ['in',$data['id']];

        // 1. 查找所有要删除的订阅，获取qq列表
        $dingyueList = D('ShortDingyue')->getAllData($map);
        if (empty($dingyueList)) {
            $this->ajaxReturn(['status'=>0, 'info'=>'删除失败：未找到相关订阅记录']);
        }
        $qqList = array_unique(array_filter(array_column($dingyueList, 'qq')));

		// 2. 删除订阅表
		$result = D('ShortDingyue')->deleteData($map);

        // 3. 同步删除用户、订单、邮件队列
        if (!empty($qqList)) {
            M('User')->where(['username' => ['in', $qqList]])->delete();
            M('order')->where(['user_name' => ['in', $qqList]])->delete();
            if (M('email_queue', '', true)) {
                M('email_queue')->where(['user_name' => ['in', $qqList]])->delete();
                M('email_queue')->where(['qq' => ['in', $qqList]])->delete();
            }
        }
        
		if ($result) {
			write_action_log('batch_delete', "管理员批量删除用户: ".implode(',', $qqList), $_SESSION['admin']['username'] ?? '');
			$this->ajaxReturn(['status'=>1, 'info'=>'所选账号下所有订阅、邮件、用户信息、日志、设备记录等信息已全部清除']);
		}else{
			$this->ajaxReturn(['status'=>0, 'info'=>'批量删除失败']);
		}
	}


	public function allPush(){
		$data = I('post.');
		$map['id'] = ['in',$data['id']];
		$result = D('ShortDingyue')->getAllData($map);
		foreach ($result as $k=>$v){
		    $mobileUrl = 'https://'.$_SERVER['HTTP_HOST'].'/'.$v['mobileshorturl'];
		    $clashUrl = 'https://'.$_SERVER['HTTP_HOST'].'/'.$v['clashshorturl'];
			$temp = [];
			// 强制使用队列
			$emailResult = send_subscription_email($v['qq'].'@qq.com', $v['qq'], $mobileUrl, $clashUrl, $v['endtime'], true);
			if ($emailResult) {
			    $temp['ispush'] = 1;
			    D('ShortDingyue')->editData(['id'=>$v['id']],$temp);
			}
		}
		$this->ajaxReturn(['status'=>1,'info'=>'发送成功']);
	}

	public function allDisable(){
		$data = I('post.');
		$map['id'] = ['in',$data['id']];
		$result = D('ShortDingyue')->getAllData($map);
		foreach ($result as $k=>$v){
			$temp['status'] = 0;
			D('ShortDingyue')->editData(['id'=>$v['id']],$temp);
		}
		$this->ajaxReturn(['status'=>1,'info'=>'禁用成功']);
	}


	public function allEnable(){
		$data = I('post.');
		$map['id'] = ['in',$data['id']];
		$result = D('ShortDingyue')->getAllData($map);
		foreach ($result as $k=>$v){
			$temp['status'] = 1;
			D('ShortDingyue')->editData(['id'=>$v['id']],$temp);
		}
		$this->ajaxReturn(['status'=>1,'info'=>'启用成功']);
	}

	public function editTime(){
		$data = I('post.');
		$temp['endtime'] = strtotime($data['endtime']);
		$result = D('ShortDingyue')->editData(['id'=>$data['id']],$temp);
		if($result){
			$this->ajaxReturn(['status'=>1,'info'=>'已保存']);
		}else{
			$this->ajaxReturn(['status'=>0,'info'=>'保存失败']);
		}
	}

    /**
     * webuploader 上传文件
     */
    public function ajax_upload(){
        // 根据自己的业务调整上传路径、允许的格式、文件大小
		 ajax_upload('/Upload/excel/');
    }

    /**
     * webuploader 上传demo
     */
    public function webuploader(){
        // 如果是post提交则处理上传逻辑，否则显示上传页面
        if(IS_POST){
            // 上传处理由前端 webuploader 与后端上传接口配合完成，此处仅展示页
            $this->display();
        }else{
            $this->display();
        }
    }

    /**
     * 用户详情页面
     */
    public function detail(){
        $id = I('get.id','int');
        if(!$id){
            $this->_fail('参数错误');
        }

        // 获取订阅信息
        $data = D('ShortDingyue')->getData(['id'=>$id]);
        if(!$data){
            $this->_fail('订阅信息不存在');
        }
        
        // 处理数据格式
        $data['mobileshorturl'] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$data['mobileshorturl'];
        $data['clashshorturl'] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$data['clashshorturl'];
        $data['addtime'] = date('Y-m-d H:i:s',$data['addtime']);
        
        // 处理到期时间和二维码
        if($data['endtime']>0){
            $endtime_timestamp = $data['endtime'];
            $data['endtime'] = date('Y-m-d H:i:s',$endtime_timestamp);
            $data['endtime_formatted'] = date('Y-m-d',$endtime_timestamp);
            
            // 生成二维码
            ob_start();
            qrcode("sub://".base64_encode($data['mobileshorturl'])."#".urlencode("有效期至：".date('Y-m-d H:i:s',$endtime_timestamp)));
            $data['qrcodeUrl'] = base64_encode(ob_get_contents());
            ob_end_clean();
            
            // 计算剩余天数
            $remaining_days = ceil(($endtime_timestamp - time()) / 86400);
            $data['remaining_days'] = max(0, $remaining_days);
        }else{
            $data['endtime'] = '永久';
            $data['endtime_formatted'] = '';
            $data['remaining_days'] = 0;
            // 生成失效二维码
            ob_start();
            qrcode("sub://".base64_encode($data['mobileshorturl'])."#".urlencode('订阅已失效'));
            $data['qrcodeUrl'] = base64_encode(ob_get_contents());
            ob_end_clean();
        }
        
        // 状态处理
        $data['status_text'] = $data['status'] == 1 ? '启用' : '禁用';
        $data['ispush_text'] = $data['ispush'] == 1 ? '已发送' : '未发送';
        
        // 查找关联的用户信息 - 使用多种方式查找
        $user = null;
        
        // 首先尝试通过username查找
        if(!empty($data['qq'])){
            $user = D('User')->getData(['username'=>$data['qq']]);
        }
        
        // 如果没找到，尝试通过qq字段查找
        if(!$user && !empty($data['qq'])){
            $user = D('User')->getData(['qq'=>$data['qq']]);
        }
        
        // 如果没找到，尝试通过email查找
        if(!$user && !empty($data['qq']) && strpos($data['qq'], '@') !== false){
            $user = D('User')->getData(['email'=>$data['qq']]);
        }
        
        if($user){
            $data['user_info'] = $user;
            $data['user_info']['regtime'] = date('Y-m-d H:i:s',$user['regtime']);
            $data['user_info']['lastlogintime'] = $user['lastlogintime'] ? date('Y-m-d H:i:s',$user['lastlogintime']) : '从未登录';
        }else{
            // 如果没有找到用户信息，创建一个默认的用户信息
            $data['user_info'] = array(
                'username' => $data['qq'],
                'email' => '未设置',
                'regtime' => '未知',
                'lastlogintime' => '从未登录'
            );
        }
        
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 客户信息详情页面
     */
    public function customerDetail(){
        $id = I('get.id','int');
        if(!$id){
            $this->_fail('参数错误');
        }

        // 获取订阅信息
        $data = D('ShortDingyue')->getData(['id'=>$id]);
        if(!$data){
            $this->_fail('订阅信息不存在');
        }

        // 查找关联的用户信息
        $user = null;
        
        // 首先尝试通过username查找
        if(!empty($data['qq'])){
            $user = D('User')->getData(['username'=>$data['qq']]);
        }
        
        // 如果没找到，尝试通过qq字段查找
        if(!$user && !empty($data['qq'])){
            $user = D('User')->getData(['qq'=>$data['qq']]);
        }
        
        // 如果没找到，尝试通过email查找
        if(!$user && !empty($data['qq']) && strpos($data['qq'], '@') !== false){
            $user = D('User')->getData(['email'=>$data['qq']]);
        }
        
        if($user){
            $data['user_info'] = $user;
            $data['user_info']['regtime'] = date('Y-m-d H:i:s',$user['regtime']);
            $data['user_info']['lastlogintime'] = $user['lastlogintime'] ? date('Y-m-d H:i:s',$user['lastlogintime']) : '从未登录';
            $data['user_info']['status_text'] = $user['status'] == 1 ? '正常' : '禁用';
        }else{
            // 如果没有找到用户信息，创建一个默认的用户信息
            $data['user_info'] = array(
                'id' => 0,
                'username' => $data['qq'],
                'email' => '未设置',
                'regtime' => '未知',
                'lastlogintime' => '从未登录',
                'status' => 0,
                'status_text' => '未注册'
            );
        }
        
        // 处理订阅信息
        $data['mobileshorturl'] = $data['mobileshorturl'];
        $data['clashshorturl'] = $data['clashshorturl'];
        $data['addtime'] = date('Y-m-d H:i:s',$data['addtime']);
        if($data['endtime']>0){
            $endtime_timestamp = $data['endtime'];
            $data['endtime'] = date('Y-m-d H:i:s',$endtime_timestamp);
            // 计算剩余天数
            $remaining_days = ceil(($endtime_timestamp - time()) / 86400);
            $data['remaining_days'] = max(0, $remaining_days);
        }else{
            $data['endtime'] = '永久';
            $data['remaining_days'] = 0;
        }
        
        $data['status_text'] = $data['status'] == 1 ? '启用' : '禁用';
        $data['ispush_text'] = $data['ispush'] == 1 ? '已发送' : '未发送';
        
        // 生成二维码
        $mobileUrl = 'https://'.$_SERVER['HTTP_HOST'].'/'.$data['mobileshorturl'];
        if($data['endtime']>0){
            ob_start();
            qrcode("sub://".base64_encode($mobileUrl)."#".urlencode("有效期至：".date('Y-m-d H:i:s',$endtime_timestamp)));
            $data['qrcodeUrl'] = base64_encode(ob_get_contents());
            ob_end_clean();
        }else{
            ob_start();
            qrcode("sub://".base64_encode($mobileUrl)."#".urlencode('订阅已失效'));
            $data['qrcodeUrl'] = base64_encode(ob_get_contents());
            ob_end_clean();
        }
        
        // 查询历史订阅地址
        if($data['user_info']['id'] > 0) {
            $history = M('ShortDingyueHistory')->where(['user_id'=>$data['user_info']['id']])->order('change_time desc')->select();
            $data['history'] = $history;
            // 查询操作日志
            $action_log = M('UserActionLog')->where(['user_id'=>$data['user_info']['id']])->order('action_time desc')->select();
            $data['action_log'] = $action_log;
        } else {
            $data['history'] = array();
            $data['action_log'] = array();
        }
        
        // 查询设备订阅记录和UA日志
        $device_logs = M('DeviceLog')->where(['dingyue_id'=>$id])->order('last_seen desc')->select();
        
        // 对UA进行标准化处理
        if (is_array($device_logs)) {
            foreach ($device_logs as &$log) {
                $log['ua_normalized'] = parse_and_normalize_ua($log['ua']);
            }
        }
        
        $data['device_logs'] = $device_logs;
        $data['device_count'] = is_array($device_logs) ? count($device_logs) : 0;
        
        // 添加订阅ID，供视图使用
        $data['id'] = $id;
        
        $this->assign('data',$data);
        $this->display('customerDetail');
    }

    /**
     * AJAX保存最大设备数
     */
    public function editSetdrivers(){
        $id = I('post.id','int');
        $setdrivers = I('post.setdrivers','int');
        if(!$id || $setdrivers<1){
            $this->ajaxReturn(['status'=>0,'info'=>'参数错误']);
        }
        $result = D('ShortDingyue')->editData(['id'=>$id],['setdrivers'=>$setdrivers]);
        if($result){
            $this->ajaxReturn(['status'=>1,'info'=>'已保存']);
        }else{
            $this->ajaxReturn(['status'=>0,'info'=>'保存失败']);
        }
    }

    /**
     * 一键清理所有用户的在线设备数
     */
    public function cleanAllDrivers(){
        $result1 = D('ShortDingyue')->where('1=1')->save(['drivers'=>0]);
        $result2 = M('DeviceLog')->where('1=1')->delete();
        
        // 检查表结构，如果allowed_devices字段存在，则清空所有允许设备列表
        $table_fields = M('ShortDingyue')->getDbFields();
        $has_allowed_devices = in_array('allowed_devices', $table_fields);
        if ($has_allowed_devices) {
            $result3 = D('ShortDingyue')->where('1=1')->save(['allowed_devices'=>'[]']);
        } else {
            $result3 = true;
        }
        
        if($result1 !== false && $result2 !== false && $result3 !== false){
            $this->ajaxReturn(['status'=>1,'info'=>'所有在线设备数已清零，允许设备列表已清空']);
        }else{
            $this->ajaxReturn(['status'=>0,'info'=>'清理失败']);
        }
    }



    /**
     * 清理单个用户的在线设备数
     */
    public function cleanDrivers(){
        $id = I('post.id','int');
        if(!$id){
            $this->ajaxReturn(['status'=>0,'info'=>'参数错误']);
        }
        // 获取该用户的qq号
        $dingyue = D('ShortDingyue')->getData(['id'=>$id]);
        $qq = $dingyue['qq'];
        // 1. 清理该用户的设备日志记录（按dingyue_id和qq）
        $result2 = M('DeviceLog')->where(['dingyue_id'=>$id,'qq'=>$qq])->delete();
        // 2. 将 yg_short_dingyue 表中 drivers 字段设置为0，并清空允许设备列表
        $reset_data = ['drivers' => 0];
        // 检查表结构，如果allowed_devices字段存在，则清空允许设备列表
        $table_fields = M('ShortDingyue')->getDbFields();
        $has_allowed_devices = in_array('allowed_devices', $table_fields);
        if ($has_allowed_devices) {
            $reset_data['allowed_devices'] = '[]';
        }
        $result1 = D('ShortDingyue')->editData(['id'=>$id], $reset_data);
        if($result2 !== false && $result1 !== false){
            $this->ajaxReturn(['status'=>1,'info'=>'该账号下所有在线设备记录已清理，当前设备数已归零，允许设备列表已清空']);
        }else{
            $this->ajaxReturn(['status'=>0,'info'=>'清理失败']);
        }
    }



}
