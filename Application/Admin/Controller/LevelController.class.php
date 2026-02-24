<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
/**
 * 后台首页控制器
 */
class LevelController extends AdminBaseController{



public function index() {
       	

        $model = D('level');
        $list = $model->order('id asc')->select();
foreach ($list as $k => $v) {
			
    			if($list[$k]['status']==1) {
    			    $list[$k]['status'] = '启用';
    			}else{
    			    $list[$k]['status'] = "<font style='color:red'>禁用</font>";
    			}
    			
			}
		
        $this->assign('list', $list);
        $this->display();
    }

    /**
     * 添加、编辑操作
     */
    public function add() {
       if(IS_POST){
			$data=I('post.');
			unset($data['file']);



    		$res = D('level')->add($data);

			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>1,'msg'=>'添加成功','url'=>U('Admin/Level/index')));
			} else {
				$this->success('添加成功', U('Admin/Level/index'));
			}
		}
		$this->display();
    }


public function del(){
		$id = I('get.id','int');
		$result = D('level')->where(['id' => $id])->delete();

		if ($result) {
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>1,'msg'=>'删除成功','url'=>U('Admin/Level/index')));
			} else {
				$this->success('删除成功', U('Admin/Level/index'));
			}
		}else{
			if(IS_AJAX) {
				$this->ajaxReturn(array('status'=>0,'msg'=>'删除失败'));
			} else {
				$this->error('删除失败');
			}
		}
	}
    /**
     * 添加、编辑操作
     */
   public function edit() {
    if (IS_POST) {
        $temp = I('post.');
        $data = $temp;
        unset($data['id']);

        $result = D('level')->where(['id' => $temp['id']])->save($data);
        if ($result) {
            if(IS_AJAX) {
                $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功','url'=>U('Admin/Level/index')));
            } else {
                $this->success('修改成功', U('Admin/Level/index'));
            }
        } else {
            if(IS_AJAX) {
                $this->ajaxReturn(array('status'=>0,'msg'=>'修改失败'));
            } else {
                $this->error('修改失败');
            }
        }
    } else {
        $id = I('get.id', 'int');

        $data = D('level')->find($id);

        $this->assign('data', $data);
        $this->display();
    }
}










}
