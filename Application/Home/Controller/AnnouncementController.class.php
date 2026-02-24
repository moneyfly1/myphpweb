<?php
namespace Home\Controller;
use Think\Controller;

class AnnouncementController extends Controller {
    public function index() {
        $list = D('Announcement')->getActiveAnnouncements();
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['start_time_fmt'] = date('Y-m-d', $v['start_time']);
                $types = array(0 => '通知', 1 => '维护', 2 => '促销', 3 => '紧急');
                $list[$k]['type_text'] = isset($types[$v['type']]) ? $types[$v['type']] : '通知';
            }
        }
        $this->assign('list', $list ?: array());
        $this->display();
    }

    public function detail() {
        $id = I('get.id', 0, 'intval');
        $data = D('Announcement')->getData(array('id' => $id, 'is_active' => 1));
        if (!$data) $this->error('公告不存在');
        $types = array(0 => '通知', 1 => '维护', 2 => '促销', 3 => '紧急');
        $data['type_text'] = isset($types[$data['type']]) ? $types[$data['type']] : '通知';
        $data['start_time_fmt'] = date('Y-m-d H:i', $data['start_time']);
        $this->assign('data', $data);
        $this->display();
    }
}
