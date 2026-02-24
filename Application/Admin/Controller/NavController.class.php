<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台菜单管理
 */
class NavController extends AdminBaseController{
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
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>1,'msg'=>'添加成功','url'=>U('Admin/Nav/index')));
			} else {
				$this->success('添加成功',U('Admin/Nav/index'));
			}
		}else{
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>0,'msg'=>'添加失败'));
			} else {
				$this->error('添加失败');
			}
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
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>1,'msg'=>'修改成功','url'=>U('Admin/Nav/index')));
			} else {
				$this->success('修改成功',U('Admin/Nav/index'));
			}
		}else{
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>0,'msg'=>'修改失败'));
			} else {
				$this->error('修改失败');
			}
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
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>1,'msg'=>'删除成功','url'=>U('Admin/Nav/index')));
			} else {
				$this->success('删除成功',U('Admin/Nav/index'));
			}
		}else{
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>0,'msg'=>'请先删除子菜单'));
			} else {
				$this->error('请先删除子菜单');
			}
		}
	}

	/**
	 * 菜单排序
	 */
	public function order(){
		$data=I('post.');
		$result=D('AdminNav')->orderData($data);
		if ($result) {
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>1,'msg'=>'排序成功','url'=>U('Admin/Nav/index')));
			} else {
				$this->success('排序成功',U('Admin/Nav/index'));
			}
		}else{
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>0,'msg'=>'排序失败'));
			} else {
				$this->error('排序失败');
			}
		}
	}


}
