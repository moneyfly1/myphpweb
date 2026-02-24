<?php
namespace Home\Controller;
use Think\Controller;
class HelpController extends Controller {
    public function index(){
        if(!check_user_login()){
            $this->error('请先登录','/login');
        }
        
        // 获取用户信息
      
         $username = $_SESSION['users']['username'];
       
        $this->assign('username', $username);
        $this->display();
    }
}