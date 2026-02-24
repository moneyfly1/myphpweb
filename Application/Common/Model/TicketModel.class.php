<?php
namespace Common\Model;
use Common\Model\BaseModel;

class TicketModel extends BaseModel {

    protected $_auto = array(
        array('created_at', 'time', 1, 'function'),
        array('updated_at', 'time', 3, 'function'),
    );

    public function addData($data) {
        $data['ticket_no'] = 'TK' . date('YmdHis') . mt_rand(100, 999);
        foreach ($data as $k => $v) {
            if (is_string($v)) $data[$k] = trim($v);
        }
        return $this->add($data);
    }

    public function getData($map = false) {
        if (!empty($map)) {
            return $this->where($map)->find();
        }
        return $this->order('id desc')->select();
    }

    public function getAllData($map = false, $order = 'id desc', $limit = 0) {
        $query = $this->where($map ?: array())->order($order);
        if ($limit) $query = $query->limit($limit);
        return $query->select();
    }

    public function getByUser($userId) {
        return $this->where(array('user_id' => $userId))->order('id desc')->select();
    }
}
