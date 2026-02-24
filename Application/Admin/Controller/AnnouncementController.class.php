<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class AnnouncementController extends AdminBaseController {

    public function index() {
        $list = D('Announcement')->getData();
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['start_time_fmt'] = $v['start_time'] ? date('Y-m-d H:i', $v['start_time']) : '-';
                $list[$k]['end_time_fmt'] = $v['end_time'] ? date('Y-m-d H:i', $v['end_time']) : '-';
                $list[$k]['type_text'] = $this->getTypeText($v['type']);
                $list[$k]['status_text'] = $v['is_active'] ? '启用' : '<span style="color:red">禁用</span>';
            }
        }
        $this->assign('list', $list ?: array());
        $this->display();
    }

    public function add() {
        if (IS_POST) {
            $data = I('post.');
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $data['is_active'] = isset($data['is_active']) ? intval($data['is_active']) : 1;
            $data['is_popup'] = isset($data['is_popup']) ? intval($data['is_popup']) : 0;
            $data['sort_order'] = intval($data['sort_order']);
            $data['type'] = intval($data['type']);
            $res = D('Announcement')->addData($data);
            if ($res) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>1,'msg'=>'添加成功','url'=>U('Admin/Announcement/index')));
                } else {
                    $this->success('添加成功', U('Admin/Announcement/index'));
                }
            } else {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>0,'msg'=>'添加失败'));
                } else {
                    $this->error('添加失败');
                }
            }
        }
        $this->display();
    }

    public function edit() {
        if (IS_POST) {
            $temp = I('post.');
            $data = $temp;
            unset($data['id']);
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $data['is_active'] = isset($data['is_active']) ? intval($data['is_active']) : 0;
            $data['is_popup'] = isset($data['is_popup']) ? intval($data['is_popup']) : 0;
            $data['sort_order'] = intval($data['sort_order']);
            $data['type'] = intval($data['type']);
            $result = D('Announcement')->where(array('id' => $temp['id']))->save($data);
            if ($result !== false) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功','url'=>U('Admin/Announcement/index')));
                } else {
                    $this->success('修改成功', U('Admin/Announcement/index'));
                }
            } else {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>0,'msg'=>'修改失败'));
                } else {
                    $this->error('修改失败');
                }
            }
        } else {
            $id = I('get.id', 0, 'intval');
            $data = D('Announcement')->getData(array('id' => $id));
            if ($data) {
                $data['start_time_str'] = $data['start_time'] ? date('Y-m-d\TH:i', $data['start_time']) : '';
                $data['end_time_str'] = $data['end_time'] ? date('Y-m-d\TH:i', $data['end_time']) : '';
            }
            $this->assign('data', $data);
            $this->display();
        }
    }

    public function del() {
        $id = I('get.id', 0, 'intval');
        $result = D('Announcement')->where(array('id' => $id))->delete();
        if ($result) {
            if(IS_AJAX) {
                $this->ajaxReturn(array('status'=>1,'msg'=>'删除成功','url'=>U('Admin/Announcement/index')));
            } else {
                $this->success('删除成功', U('Admin/Announcement/index'));
            }
        } else {
            if(IS_AJAX) {
                $this->ajaxReturn(array('status'=>0,'msg'=>'删除失败'));
            } else {
                $this->error('删除失败');
            }
        }
    }

    public function toggle() {
        if (!IS_AJAX) $this->error('非法请求');
        $id = I('post.id', 0, 'intval');
        $ann = D('Announcement')->where(array('id' => $id))->find();
        if (!$ann) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '公告不存在'));
        }
        $newStatus = $ann['is_active'] ? 0 : 1;
        D('Announcement')->where(array('id' => $id))->save(array('is_active' => $newStatus));
        $this->ajaxReturn(array('code' => 0, 'msg' => $newStatus ? '已启用' : '已禁用'));
    }

    private function getTypeText($type) {
        $types = array(0 => '通知', 1 => '维护', 2 => '促销', 3 => '紧急');
        return isset($types[$type]) ? $types[$type] : '未知';
    }
}
