<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class CouponController extends AdminBaseController {

    public function index() {
        $list = D('Coupon')->getData();
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['valid_from_fmt'] = $v['valid_from'] ? date('Y-m-d H:i', $v['valid_from']) : '-';
                $list[$k]['valid_until_fmt'] = $v['valid_until'] ? date('Y-m-d H:i', $v['valid_until']) : '-';
                $list[$k]['type_text'] = $v['type'] == 1 ? '百分比折扣' : '固定金额';
                $list[$k]['discount_fmt'] = $v['type'] == 1 ? $v['discount_value'] . '%' : '¥' . $v['discount_value'];
                $list[$k]['quantity_fmt'] = $v['used_quantity'] . '/' . ($v['total_quantity'] > 0 ? $v['total_quantity'] : '不限');
                $list[$k]['status_text'] = $v['is_active'] ? '<span style="color:#52c41a">启用</span>' : '<span style="color:#f5222d">禁用</span>';
            }
        }
        $this->assign('list', $list ?: array());
        $this->display();
    }

    public function add() {
        if (IS_POST) {
            $data = I('post.');
            if (empty($data['code'])) {
                $data['code'] = strtoupper(substr(md5(uniqid()), 0, 8));
            }
            $data['code'] = strtoupper(trim($data['code']));
            $data['valid_from'] = strtotime($data['valid_from']);
            $data['valid_until'] = strtotime($data['valid_until']);
            $data['type'] = intval($data['type']);
            $data['discount_value'] = floatval($data['discount_value']);
            $data['min_amount'] = floatval($data['min_amount']);
            $data['max_discount'] = floatval($data['max_discount']);
            $data['total_quantity'] = intval($data['total_quantity']);
            $data['max_uses_per_user'] = intval($data['max_uses_per_user']);
            $data['is_active'] = isset($data['is_active']) ? intval($data['is_active']) : 1;
            $data['used_quantity'] = 0;
            // 检查优惠码是否重复
            $exists = D('Coupon')->where(array('code' => $data['code']))->find();
            if ($exists) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>0,'msg'=>'优惠码已存在，请更换'));
                } else {
                    $this->error('优惠码已存在，请更换');
                }
                return;
            }
            $res = D('Coupon')->addData($data);
            if ($res) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>1,'msg'=>'添加成功','url'=>U('Admin/Coupon/index')));
                } else {
                    $this->success('添加成功', U('Admin/Coupon/index'));
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
            $data['valid_from'] = strtotime($data['valid_from']);
            $data['valid_until'] = strtotime($data['valid_until']);
            $data['type'] = intval($data['type']);
            $data['discount_value'] = floatval($data['discount_value']);
            $data['min_amount'] = floatval($data['min_amount']);
            $data['max_discount'] = floatval($data['max_discount']);
            $data['total_quantity'] = intval($data['total_quantity']);
            $data['max_uses_per_user'] = intval($data['max_uses_per_user']);
            $data['is_active'] = isset($data['is_active']) ? intval($data['is_active']) : 0;
            $result = D('Coupon')->where(array('id' => $temp['id']))->save($data);
            if ($result !== false) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功','url'=>U('Admin/Coupon/index')));
                } else {
                    $this->success('修改成功', U('Admin/Coupon/index'));
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
            $data = D('Coupon')->getData(array('id' => $id));
            if ($data) {
                $data['valid_from_str'] = $data['valid_from'] ? date('Y-m-d\TH:i', $data['valid_from']) : '';
                $data['valid_until_str'] = $data['valid_until'] ? date('Y-m-d\TH:i', $data['valid_until']) : '';
            }
            $this->assign('data', $data);
            $this->display();
        }
    }

    public function del() {
        $id = I('get.id', 0, 'intval');
        $result = D('Coupon')->where(array('id' => $id))->delete();
        if ($result) {
            if(IS_AJAX) {
                $this->ajaxReturn(array('status'=>1,'msg'=>'删除成功','url'=>U('Admin/Coupon/index')));
            } else {
                $this->success('删除成功', U('Admin/Coupon/index'));
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
        $coupon = D('Coupon')->where(array('id' => $id))->find();
        if (!$coupon) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '优惠券不存在'));
        }
        $newStatus = $coupon['is_active'] ? 0 : 1;
        D('Coupon')->where(array('id' => $id))->save(array('is_active' => $newStatus));
        $this->ajaxReturn(array('code' => 0, 'msg' => $newStatus ? '已启用' : '已禁用'));
    }
}
