<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class InviteController extends AdminBaseController {

    /**
     * 邀请码列表
     */
    public function index() {
        $word = I('get.word', '');
        $map = array();
        if (!empty($word)) {
            $map['c.code'] = array('like', '%' . $word . '%');
        }

        $model = M('invite_code')->alias('c')
            ->join('LEFT JOIN yg_user u ON c.user_id = u.id');

        $count = $model->where($map)->count();
        $page = new_page($count, 15);

        $list = M('invite_code')->alias('c')
            ->field('c.*, u.username as creator_name')
            ->join('LEFT JOIN yg_user u ON c.user_id = u.id')
            ->where($map)
            ->order('c.id desc')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        $assign = array(
            'data' => $list,
            'page' => $page->show(),
            'word' => $word,
        );
        $this->assign($assign);
        $this->display();
    }

    /**
     * 邀请关系列表
     */
    public function relations() {
        $model = M('invite_relation')->alias('r')
            ->join('LEFT JOIN yg_user inviter ON r.inviter_id = inviter.id')
            ->join('LEFT JOIN yg_user invitee ON r.invitee_id = invitee.id')
            ->join('LEFT JOIN yg_invite_code c ON r.invite_code_id = c.id');

        $count = $model->count();
        $page = new_page($count, 15);

        $list = M('invite_relation')->alias('r')
            ->field('r.*, inviter.username as inviter_name, invitee.username as invitee_name, c.code as invite_code')
            ->join('LEFT JOIN yg_user inviter ON r.inviter_id = inviter.id')
            ->join('LEFT JOIN yg_user invitee ON r.invitee_id = invitee.id')
            ->join('LEFT JOIN yg_invite_code c ON r.invite_code_id = c.id')
            ->order('r.id desc')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        $assign = array(
            'data' => $list,
            'page' => $page->show(),
        );
        $this->assign($assign);
        $this->display();
    }

    /**
     * 删除邀请码
     */
    public function del() {
        $id = I('get.id', 0, 'intval');
        if (!$id) {
            $this->error('参数错误');
        }
        $result = D('InviteCode')->deleteData(array('id' => $id));
        if ($result) {
            $this->success('删除成功', U('Admin/Invite/index'));
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 切换邀请码状态
     */
    public function toggle() {
        $id = I('post.id', 0, 'intval');
        if (!$id) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '参数错误'));
        }
        $invite = D('InviteCode')->getData(array('id' => $id));
        if (!$invite) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '邀请码不存在'));
        }
        $newStatus = $invite['is_active'] == 1 ? 0 : 1;
        $result = D('InviteCode')->editData(array('id' => $id), array('is_active' => $newStatus));
        if ($result !== false) {
            $this->ajaxReturn(array('code' => 0, 'msg' => '操作成功'));
        } else {
            $this->ajaxReturn(array('code' => 1, 'msg' => '操作失败'));
        }
    }
}
