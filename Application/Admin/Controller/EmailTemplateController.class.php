<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class EmailTemplateController extends AdminBaseController {

    public function index() {
        $list = D('EmailTemplateDb')->getAllTemplates();
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['status_text'] = $v['is_active'] ? '启用' : '<span style="color:red">禁用</span>';
                $list[$k]['updated_fmt'] = $v['updated_at'] ? date('Y-m-d H:i', $v['updated_at']) : '-';
            }
        }
        $this->assign('list', $list ?: array());
        $this->display();
    }

    public function edit() {
        if (IS_POST) {
            $temp = I('post.');
            $data = array(
                'subject' => $temp['subject'],
                'content' => $temp['content'],
                'is_active' => isset($temp['is_active']) ? intval($temp['is_active']) : 0,
                'updated_at' => time(),
            );
            $result = D('EmailTemplateDb')->where(array('id' => $temp['id']))->save($data);
            if ($result !== false) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功','url'=>U('Admin/EmailTemplate/index')));
                } else {
                    $this->success('修改成功', U('Admin/EmailTemplate/index'));
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
            $data = D('EmailTemplateDb')->where(array('id' => $id))->find();
            $this->assign('data', $data);
            $this->display();
        }
    }

    public function preview() {
        if (!IS_AJAX) $this->error('非法请求');
        $name = I('post.name', '', 'trim');
        $sampleVars = array(
            'username' => '测试用户',
            'email' => 'test@example.com',
            'site_name' => '订阅服务',
            'site_url' => 'https://' . $_SERVER['HTTP_HOST'],
            'activation_link' => 'https://' . $_SERVER['HTTP_HOST'] . '/active/reg/sample',
            'expire_date' => date('Y-m-d', strtotime('+30 days')),
            'amount' => '99.00',
            'order_no' => 'ORD' . date('YmdHis'),
            'code' => 'ABCD1234',
            'content' => '这是一条测试内容',
        );
        $result = D('EmailTemplateDb')->renderTemplate($name, $sampleVars);
        if ($result) {
            $this->ajaxReturn(array('code' => 0, 'data' => $result));
        } else {
            $this->ajaxReturn(array('code' => 1, 'msg' => '模板不存在或已禁用'));
        }
    }
}
