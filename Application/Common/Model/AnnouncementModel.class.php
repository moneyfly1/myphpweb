<?php
namespace Common\Model;
use Common\Model\BaseModel;

class AnnouncementModel extends BaseModel {

    protected $_auto = array(
        array('created_at', 'time', 1, 'function'),
        array('updated_at', 'time', 3, 'function'),
    );

    public function getData($map = false) {
        if (!empty($map)) {
            return $this->where($map)->find();
        }
        return $this->order('sort_order asc, id desc')->select();
    }

    public function getAllData($map = false) {
        if (!empty($map)) {
            return $this->where($map)->order('sort_order asc, id desc')->select();
        }
        return $this->order('sort_order asc, id desc')->select();
    }

    public function getActiveAnnouncements() {
        $now = time();
        return $this->where(array(
            'is_active' => 1,
            'start_time' => array('elt', $now),
            'end_time' => array('egt', $now),
        ))->order('sort_order asc, id desc')->select();
    }

    public function getPopupAnnouncements() {
        $now = time();
        return $this->where(array(
            'is_active' => 1,
            'is_popup' => 1,
            'start_time' => array('elt', $now),
            'end_time' => array('egt', $now),
        ))->order('sort_order asc, id desc')->select();
    }
}
