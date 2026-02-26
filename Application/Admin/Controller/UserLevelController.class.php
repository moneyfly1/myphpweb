<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class UserLevelController extends AdminBaseController {
    private function _ok($msg, $url='') {
        if (IS_AJAX) { $this->ajaxReturn(array('code'=>0,'msg'=>$msg)); }
        else { $this->success($msg, $url); }
    }
    private function _fail($msg) {
        if (IS_AJAX) { $this->ajaxReturn(array('code'=>1,'msg'=>$msg)); }
        else { $this->error($msg); }
    }

    public function index() {
        $list = D('UserLevel')->getData();
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['status_text'] = $v['is_active'] ? '启用' : '<span style="color:red">禁用</span>';
                $list[$k]['created_fmt'] = $v['created_at'] ? date('Y-m-d H:i', $v['created_at']) : '-';
            }
        }
        $this->assign('list', $list ?: array());
        $this->display();
    }

    public function add() {
        if (IS_POST) {
            $data = I('post.');
            $data['level_order'] = intval($data['level_order']);
            $data['min_consumption'] = floatval($data['min_consumption']);
            $data['discount_rate'] = floatval($data['discount_rate']);
            $data['device_limit_bonus'] = intval($data['device_limit_bonus']);
            $data['is_active'] = isset($data['is_active']) ? intval($data['is_active']) : 1;
            $res = D('UserLevel')->addData($data);
            if ($res) {
                $this->_ok('添加成功', U('Admin/UserLevel/index'));
            } else {
                $this->_fail('添加失败');
            }
        }
        $this->display();
    }

    public function edit() {
        if (IS_POST) {
            $temp = I('post.');
            $data = $temp;
            unset($data['id']);
            $data['level_order'] = intval($data['level_order']);
            $data['min_consumption'] = floatval($data['min_consumption']);
            $data['discount_rate'] = floatval($data['discount_rate']);
            $data['device_limit_bonus'] = intval($data['device_limit_bonus']);
            $data['is_active'] = isset($data['is_active']) ? intval($data['is_active']) : 0;
            $result = D('UserLevel')->where(array('id' => $temp['id']))->save($data);
            if ($result !== false) {
                $this->_ok('修改成功', U('Admin/UserLevel/index'));
            } else {
                $this->_fail('修改失败');
            }
        } else {
            $id = I('get.id', 0, 'intval');
            $data = D('UserLevel')->getData(array('id' => $id));
            $this->assign('data', $data);
            $this->display();
        }
    }

    public function del() {
        $id = I('get.id', 0, 'intval');
        $result = D('UserLevel')->where(array('id' => $id))->delete();
        if ($result) {
            $this->_ok('删除成功', U('Admin/UserLevel/index'));
        } else {
            $this->_fail('删除失败');
        }
    }
}
