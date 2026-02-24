<?php
namespace Common\Model;
use Common\Model\BaseModel;

class UserLevelModel extends BaseModel {
    protected $_auto = array(
        array('created_at', 'time', 1, 'function'),
    );

    public function getData($map = false) {
        if (!empty($map)) return $this->where($map)->find();
        return $this->order('level_order asc')->select();
    }

    public function getAllActive() {
        return $this->where(array('is_active' => 1))->order('level_order asc')->select();
    }

    // Get user's current level based on total_consumption
    public function getUserLevel($totalConsumption) {
        return $this->where(array(
            'is_active' => 1,
            'min_consumption' => array('elt', $totalConsumption),
        ))->order('min_consumption desc')->find();
    }

    // Check if user should be upgraded
    public function checkUpgrade($userId) {
        $user = M('user')->where(array('id' => $userId))->find();
        if (!$user) return false;
        $newLevel = $this->getUserLevel($user['total_consumption']);
        if ($newLevel && $newLevel['id'] != $user['user_level_id']) {
            M('user')->where(array('id' => $userId))->save(array('user_level_id' => $newLevel['id']));
            return $newLevel;
        }
        return false;
    }
}
