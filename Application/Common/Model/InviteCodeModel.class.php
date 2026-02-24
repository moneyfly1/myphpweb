<?php
namespace Common\Model;
use Common\Model\BaseModel;

class InviteCodeModel extends BaseModel {

    protected $_auto = array(
        array('created_at', 'time', 1, 'function'),
    );

    public function getData($map = false) {
        if (!empty($map)) {
            return $this->where($map)->find();
        }
        return $this->order('id desc')->select();
    }

    public function getAllData($map = false) {
        return $this->where($map ?: array())->order('id desc')->select();
    }

    public function getByUser($userId) {
        return $this->where(array('user_id' => $userId))->order('id desc')->select();
    }

    public function generateCode($userId, $maxUses = 5, $rewardType = 'balance', $inviterReward = 0, $inviteeReward = 0) {
        $code = strtoupper(substr(md5(uniqid($userId, true)), 0, 8));
        $data = array(
            'code'           => $code,
            'user_id'        => $userId,
            'max_uses'       => $maxUses,
            'reward_type'    => $rewardType,
            'inviter_reward' => $inviterReward,
            'invitee_reward' => $inviteeReward,
            'is_active'      => 1,
            'used_count'     => 0,
        );
        $id = $this->addData($data);
        return $id ? $code : false;
    }

    public function useCode($code, $inviteeId) {
        $invite = $this->where(array('code' => $code, 'is_active' => 1))->find();
        if (!$invite) return array('code' => 1, 'msg' => '邀请码无效');
        if ($invite['max_uses'] > 0 && $invite['used_count'] >= $invite['max_uses']) {
            return array('code' => 1, 'msg' => '邀请码已用完');
        }
        if ($invite['expires_at'] && $invite['expires_at'] < time()) {
            return array('code' => 1, 'msg' => '邀请码已过期');
        }
        if ($invite['user_id'] == $inviteeId) {
            return array('code' => 1, 'msg' => '不能使用自己的邀请码');
        }
        $this->where(array('id' => $invite['id']))->setInc('used_count');
        M('user')->where(array('id' => $inviteeId))->save(array(
            'invited_by'       => $invite['user_id'],
            'invite_code_used' => $code,
        ));
        D('InviteRelation')->addData(array(
            'invite_code_id'      => $invite['id'],
            'inviter_id'          => $invite['user_id'],
            'invitee_id'          => $inviteeId,
            'inviter_reward_amount' => $invite['inviter_reward'],
            'invitee_reward_amount' => $invite['invitee_reward'],
        ));
        return array('code' => 0, 'msg' => '邀请码使用成功', 'data' => $invite);
    }
}
