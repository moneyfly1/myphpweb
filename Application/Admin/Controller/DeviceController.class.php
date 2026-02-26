<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class DeviceController extends AdminBaseController {

    public function index() {
        $search = I('get.search', '', 'trim');
        $map = array();
        if ($search) {
            $search = addslashes($search);
            $map['_string'] = "qq LIKE '%{$search}%' OR ip LIKE '%{$search}%' OR ua LIKE '%{$search}%' OR fingerprint LIKE '%{$search}%'";
        }

        $model = M('device_log');
        $count = $model->where($map)->count();
        $page = new \Think\Page($count, 20);
        $list = $model->where($map)
            ->order('last_seen desc')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        // Format data
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['last_seen_fmt'] = $v['last_seen'] ? date('Y-m-d H:i:s', $v['last_seen']) : '-';
                $list[$k]['ip_display'] = $v['ip'] ? (strlen($v['ip']) > 16 ? substr($v['ip'], 0, 16) . '...' : $v['ip']) : '-';
                $list[$k]['client'] = $this->parseUA($v['ua']);
                $ipHistory = json_decode($v['ip_history'], true);
                $list[$k]['ip_count'] = is_array($ipHistory) ? count($ipHistory) : 0;
                $list[$k]['fp_short'] = $v['fingerprint'] ? substr($v['fingerprint'], 0, 12) . '...' : '-';
            }
        }

        $this->assign('list', $list ?: array());
        $this->assign('page', $page->show());
        $this->assign('search', $search);
        $this->assign('total', $count);
        $this->display();
    }

    public function detail() {
        $id = I('get.id', 0, 'intval');
        $device = M('device_log')->where(array('id' => $id))->find();
        if (!$device) $this->error('设备不存在');
        $device['last_seen_fmt'] = $device['last_seen'] ? date('Y-m-d H:i:s', $device['last_seen']) : '-';
        $device['ip_history_arr'] = json_decode($device['ip_history'], true) ?: array();
        $device['client'] = $this->parseUA($device['ua']);

        // Get subscription info
        $dingyue = M('short_dingyue')->where(array('id' => $device['dingyue_id']))->find();
        $this->assign('device', $device);
        $this->assign('dingyue', $dingyue);
        $this->display();
    }

    public function del() {
        $id = I('get.id', 0, 'intval');
        $result = M('device_log')->where(array('id' => $id))->delete();
        if (IS_AJAX) {
            $this->ajaxReturn(array('code' => $result ? 0 : 1, 'msg' => $result ? '删除成功' : '删除失败'));
        }
        if ($result) {
            $this->success('删除成功', U('Admin/Device/index'));
        } else {
            $this->error('删除失败');
        }
    }

    public function batchDel() {
        if (!IS_AJAX) $this->error('非法请求');
        $qq = I('post.qq', '', 'trim');
        if (empty($qq)) $this->ajaxReturn(array('code' => 1, 'msg' => '参数错误'));
        $count = M('device_log')->where(array('qq' => $qq))->delete();
        $this->ajaxReturn(array('code' => 0, 'msg' => '已删除 ' . $count . ' 条记录'));
    }

    public function stats() {
        $total = M('device_log')->count();
        $uniqueQQ = M('device_log')->where("qq != ''")->distinct(true)->field('qq')->select();
        $uniqueFingerprint = M('device_log')->distinct(true)->field('fingerprint')->select();
        $dayAgo = time() - 86400;
        $active24h = M('device_log')->where(array('last_seen' => array('egt', $dayAgo)))->count();

        $this->ajaxReturn(array(
            'code' => 0,
            'data' => array(
                'total' => $total,
                'unique_users' => count($uniqueQQ),
                'unique_devices' => count($uniqueFingerprint),
                'active_24h' => $active24h,
            )
        ));
    }

    private function parseUA($ua) {
        if (stripos($ua, 'ClashMeta') !== false || stripos($ua, 'clash-verge') !== false || stripos($ua, 'clash.meta') !== false) return 'Clash';
        if (stripos($ua, 'Shadowrocket') !== false) return 'Shadowrocket';
        if (stripos($ua, 'Quantumult') !== false) return 'Quantumult';
        if (stripos($ua, 'Surge') !== false) return 'Surge';
        if (stripos($ua, 'Stash') !== false) return 'Stash';
        if (stripos($ua, 'v2ray') !== false) return 'V2Ray';
        return $ua ? mb_substr($ua, 0, 30) : '未知';
    }
}
