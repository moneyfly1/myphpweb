<?php
namespace Common\Model;
use Common\Model\BaseModel;

class CouponModel extends BaseModel {
    protected $_auto = array(
        array('created_at', 'time', 1, 'function'),
    );

    public function getData($map = false) {
        if (!empty($map)) return $this->where($map)->find();
        return $this->order('id desc')->select();
    }

    public function getAllData($map = false) {
        return $this->where($map ?: array())->order('id desc')->select();
    }

    /**
     * 验证优惠券是否可用
     * @param string $code 优惠码
     * @param int $userId 用户ID
     * @param float $amount 订单金额
     * @param int $packageId 套餐ID
     * @return array
     */
    public function validateCoupon($code, $userId, $amount, $packageId = 0) {
        $coupon = $this->where(array('code' => $code, 'is_active' => 1))->find();
        if (!$coupon) return array('code' => 1, 'msg' => '优惠券不存在或已失效');
        $now = time();
        if ($coupon['valid_from'] > $now) return array('code' => 1, 'msg' => '优惠券尚未生效');
        if ($coupon['valid_until'] < $now) return array('code' => 1, 'msg' => '优惠券已过期');
        if ($coupon['total_quantity'] > 0 && $coupon['used_quantity'] >= $coupon['total_quantity']) {
            return array('code' => 1, 'msg' => '优惠券已被领完');
        }
        if ($amount < $coupon['min_amount']) {
            return array('code' => 1, 'msg' => '订单金额不满足最低消费 ¥' . $coupon['min_amount']);
        }
        // 检查每人使用次数限制
        $userUsed = M('coupon_usage')->where(array('coupon_id' => $coupon['id'], 'user_id' => $userId))->count();
        if ($userUsed >= $coupon['max_uses_per_user']) {
            return array('code' => 1, 'msg' => '您已达到该优惠券使用上限');
        }
        // 检查适用套餐
        if (!empty($coupon['applicable_packages'])) {
            $pkgs = explode(',', $coupon['applicable_packages']);
            if (!in_array($packageId, $pkgs)) {
                return array('code' => 1, 'msg' => '该优惠券不适用于此套餐');
            }
        }
        // 计算折扣金额
        $discount = 0;
        if ($coupon['type'] == 1) { // 百分比折扣
            $discount = $amount * $coupon['discount_value'] / 100;
            if ($coupon['max_discount'] > 0 && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } else { // 固定金额
            $discount = $coupon['discount_value'];
        }
        $discount = min($discount, $amount);
        return array('code' => 0, 'msg' => '验证成功', 'data' => array(
            'coupon' => $coupon,
            'discount' => round($discount, 2),
        ));
    }

    /**
     * 记录优惠券使用
     * @param int $couponId 优惠券ID
     * @param int $userId 用户ID
     * @param int $orderId 订单ID
     * @param float $discountAmount 折扣金额
     */
    public function useCoupon($couponId, $userId, $orderId, $discountAmount) {
        M('coupon_usage')->add(array(
            'coupon_id' => $couponId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
            'used_at' => time(),
        ));
        $this->where(array('id' => $couponId))->setInc('used_quantity');
    }
}
