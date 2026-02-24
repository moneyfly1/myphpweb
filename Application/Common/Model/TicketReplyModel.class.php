<?php
namespace Common\Model;
use Common\Model\BaseModel;

class TicketReplyModel extends BaseModel {

    protected $_auto = array(
        array('created_at', 'time', 1, 'function'),
    );

    public function getData($map = false) {
        if (!empty($map)) {
            return $this->where($map)->order('id asc')->select();
        }
        return $this->order('id asc')->select();
    }

    public function addData($data) {
        foreach ($data as $k => $v) {
            if (is_string($v)) $data[$k] = trim($v);
        }
        return $this->add($data);
    }
}
