<?php
namespace Common\Model;
use Think\Model;

class NodeMgrModel extends Model {
    protected $tableName = 'node';

    public function getData($map = false) {
        if (!empty($map)) return $this->where($map)->find();
        return $this->order('sort_order asc, id asc')->select();
    }

    public function getVisibleNodes() {
        return $this->where(array('is_visible' => 1))->order('sort_order asc')->select();
    }

    public function updateHealth($id, $latency, $status) {
        return $this->where(array('id' => $id))->save(array(
            'latency' => $latency,
            'status' => $status,
            'last_test' => time(),
            'updated_at' => time(),
        ));
    }
}
