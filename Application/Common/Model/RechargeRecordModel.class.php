<?php
namespace Common\Model;
use Think\Model;

class RechargeRecordModel extends Model {
    protected $tableName = 'recharge_record';

    // Change user balance with transaction record
    public function changeBalance($userId, $amount, $type, $remark = '', $orderId = null, $operator = '') {
        $user = M('user')->where(array('id' => $userId))->find();
        if (!$user) return false;
        $balanceBefore = floatval($user['balance']);
        $balanceAfter = $balanceBefore + floatval($amount);
        if ($balanceAfter < 0) return false; // Prevent negative balance
        // Start transaction
        $this->startTrans();
        try {
            // Update user balance
            M('user')->where(array('id' => $userId))->save(array('balance' => $balanceAfter));
            // Record the change
            $this->add(array(
                'user_id' => $userId,
                'username' => $user['username'],
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'order_id' => $orderId,
                'remark' => $remark,
                'operator' => $operator,
                'created_at' => time(),
            ));
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    public function getRecords($userId, $limit = 20) {
        return $this->where(array('user_id' => $userId))->order('id desc')->limit($limit)->select();
    }
}
