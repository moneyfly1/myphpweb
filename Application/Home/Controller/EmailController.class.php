<?php
namespace Home\Controller;
use Think\Controller;

class EmailController extends Controller {
    public function _initialize() {
        // 验证是否为AJAX请求
        if(!IS_AJAX){
            $this->error('非法请求');
            exit;
        }
    }

    public function send(){
        //检查请求方法
        if(!IS_POST){
            $this->ajaxReturn(['code' => 1, 'msg' => '请求方式错误']);
            return;
        }

        // 获取并验证参数
        $data = I('post.');
        if(empty($data['qq']) || empty($data['mobileUrl']) || empty($data['clashUrl'])){
            $this->ajaxReturn(['code' => 1, 'msg' => '参数错误']);
            return;
        }

        // 验证订阅链接合法性
        $short = M('short_dingyue')->where(array(
            '_complex' => array(
                '_logic' => 'OR',
                'mobileshorturl' => $data['mobileUrl'],
                'clashshorturl' => $data['clashUrl']
            )
        ))->find();
        // var_dump($short);die;
        if(!$short){
            $this->ajaxReturn(['code' => 1, 'msg' => '无效的订阅地址']);
            return;
        }
        
		$mobileUrl = 'https://'.$_SERVER['HTTP_HOST'].'/'.$short['mobileshorturl'];
		$clashUrl = 'https://'.$_SERVER['HTTP_HOST'].'/'.$short['clashshorturl'];
		$result = send_subscription_email($data['qq'].'@qq.com', $data['qq'], $mobileUrl, $clashUrl, $short['endtime']);
		if ($result) {
            $this->ajaxReturn(['code' => 0, 'msg' => '发送成功']);
		}else{
			$this->ajaxReturn(['code' => 1, 'msg' => '发送失败']);
		}
    }
}
