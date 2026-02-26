<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台菜单管理
 */
class NavController extends AdminBaseController{
    private function _ok($msg, $url='') {
        if (IS_AJAX) { $this->ajaxReturn(array('code'=>0,'msg'=>$msg)); }
        else { $this->success($msg, $url); }
    }
    private function _fail($msg) {
        if (IS_AJAX) { $this->ajaxReturn(array('code'=>1,'msg'=>$msg)); }
        else { $this->error($msg); }
    }
	/**
	 * 菜单列表
	 */
	public function index(){
		$data=D('AdminNav')->getTreeData('tree','order_number,id');
		$assign=array(
			'data'=>$data
			);
		$this->assign($assign);
		$this->display();
	}

	/**
	 * 添加菜单
	 */
	public function add(){
		$data=I('post.');
		unset($data['id']);
		$result=D('AdminNav')->addData($data);
		if ($result) {
			$this->_ok('添加成功',U('Admin/Nav/index'));
		}else{
			$this->_fail('添加失败');
		}
	}

	/**
	 * 修改菜单
	 */
	public function edit(){
		$data=I('post.');
		$map=array(
			'id'=>$data['id']
			);
		$result=D('AdminNav')->editData($map,$data);
		if ($result) {
			$this->_ok('修改成功',U('Admin/Nav/index'));
		}else{
			$this->_fail('修改失败');
		}
	}

	/**
	 * 删除菜单
	 */
	public function delete(){
		$id=I('get.id');
		$map=array(
			'id'=>$id
			);
		$result=D('AdminNav')->deleteData($map);
		if($result){
			$this->_ok('删除成功',U('Admin/Nav/index'));
		}else{
			$this->_fail('请先删除子菜单');
		}
	}

	/**
	 * 菜单排序
	 */
	public function order(){
		$data=I('post.');
		$result=D('AdminNav')->orderData($data);
		if ($result) {
			$this->_ok('排序成功',U('Admin/Nav/index'));
		}else{
			$this->_fail('排序失败');
		}
	}


}
