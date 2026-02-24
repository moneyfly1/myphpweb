<?php
namespace Home\Controller;
use Think\Controller;

class InviteController extends Controller {

    /**
     * 我的邀请页面
     */
    public function index() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        $userId = session('users.id');

        // 获取用户的邀请码列表
        $codes = D('InviteCode')->getByUser($userId);

        // 获取邀请记录（我邀请的人）
        $relations = M('InviteRelation')->alias('r')
            ->field('r.*, u.username as invitee_name')
            ->join('LEFT JOIN __USER__ u ON r.invitee_id = u.id')
            ->where(array('r.inviter_id' => $userId))
            ->order('r.id desc')
            ->select();

        $assign = array(
            'codes'     => $codes,
            'relations' => $relations,
        );
        $this->assign($assign);
        $this->display();
    }

    /**
     * 生成邀请码（AJAX）
     */
    public function generate() {
        if (!check_user_login()) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '请先登录'));
        }
        $userId = session('users.id');

        $maxUses       = 5;
        $inviterReward = 5;
        $inviteeReward = 2;

        $code = D('InviteCode')->generateCode($userId, $maxUses, 'balance', $inviterReward, $inviteeReward);
        if ($code) {
            $this->ajaxReturn(array('code' => 0, 'msg' => '生成成功', 'data' => array('code' => $code)));
        } else {
            $this->ajaxReturn(array('code' => 1, 'msg' => '生成失败，请重试'));
        }
    }
}
