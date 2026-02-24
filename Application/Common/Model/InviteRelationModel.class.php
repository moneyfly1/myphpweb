<?php
namespace Common\Model;
use Common\Model\BaseModel;

class InviteRelationModel extends BaseModel {

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

    public function getByInviter($inviterId) {
        return $this->where(array('inviter_id' => $inviterId))->order('id desc')->select();
    }
}
