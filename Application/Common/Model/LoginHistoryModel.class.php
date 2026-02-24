<?php
namespace Common\Model;
use Think\Model;

class LoginHistoryModel extends Model {
    protected $tableName = 'login_history';

    public function addRecord($userId, $ipAddress, $userAgent, $status = 1, $failureReason = '') {
        $deviceType = 'PC';
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            $deviceType = preg_match('/iPad|Tablet/i', $userAgent) ? 'Tablet' : 'Mobile';
        }
        return $this->add(array(
            'user_id' => $userId,
            'login_time' => time(),
            'ip_address' => $ipAddress,
            'user_agent' => mb_substr($userAgent, 0, 500),
            'device_type' => $deviceType,
            'login_status' => $status,
            'failure_reason' => $failureReason,
        ));
    }

    public function getByUser($userId, $limit = 20) {
        return $this->where(array('user_id' => $userId))->order('id desc')->limit($limit)->select();
    }

    public function getRecent($limit = 50) {
        return $this->order('id desc')->limit($limit)->select();
    }
}
