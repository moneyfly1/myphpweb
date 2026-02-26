<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class ConfigFileController extends AdminBaseController
{
    // 配置文件路径
    private $xr_path = './Upload/true/xr';
    private $clash_path = './Upload/true/clash.yaml';
    private $work_path = './Upload/true/work';

    public function _initialize() {
        parent::_initialize();
        $superAdminIds = C('SUPER_ADMIN_IDS');
        if (!is_array($superAdminIds)) $superAdminIds = array(88);
        $uid = $_SESSION['admin']['id'];
        if (!in_array($uid, $superAdminIds)) {
            $group = M('auth_group_access')->where(['uid'=>$uid])->getField('group_id');
            if ($group != 1) {
                $this->error('只有超级管理员才能操作此功能');
            }
        }
    }

    // 编辑页面
    public function edit() {
        // 权限校验可加在这里
        $xr_content = is_file($this->xr_path) ? file_get_contents($this->xr_path) : '';
        $clash_content = is_file($this->clash_path) ? file_get_contents($this->clash_path) : '';
        $work_content = is_file($this->work_path) ? file_get_contents($this->work_path) : '';
        $this->assign('xr_content', $xr_content);
        $this->assign('clash_content', $clash_content);
        $this->assign('work_content', $work_content);
        $this->display();
    }

    // 保存操作
    public function save() {
        if (!IS_POST) {
            if (IS_AJAX) { $this->ajaxReturn(array('status'=>0,'info'=>'非法请求')); return; }
            $this->error('非法请求');
        }
        $xr_content = I('post.xr_content', '', '');
        $clash_content = I('post.clash_content', '', '');
        $work_content = I('post.work_content', '', '');

        // 内容长度限制（放宽限制）
        if (strlen($xr_content) > 1024*1024) {
            if (IS_AJAX) { $this->ajaxReturn(array('status'=>0,'info'=>'xr文件内容过大，最大允许1MB')); return; }
            $this->error('xr文件内容过大，最大允许1MB');
        }
        if (strlen($clash_content) > 4*1024*1024) {
            if (IS_AJAX) { $this->ajaxReturn(array('status'=>0,'info'=>'clash.yaml内容过大，最大允许4MB')); return; }
            $this->error('clash.yaml内容过大，最大允许4MB');
        }
        if (strlen($work_content) > 1024*1024) {
            if (IS_AJAX) { $this->ajaxReturn(array('status'=>0,'info'=>'work文件内容过大，最大允许1MB')); return; }
            $this->error('work文件内容过大，最大允许1MB');
        }
        // 禁止写入PHP标签
        if (stripos($xr_content, '<?php') !== false || stripos($clash_content, '<?php') !== false || stripos($work_content, '<?php') !== false) {
            if (IS_AJAX) { $this->ajaxReturn(array('status'=>0,'info'=>'禁止写入PHP代码')); return; }
            $this->error('禁止写入PHP代码');
        }

        $xr_result = file_put_contents($this->xr_path, $xr_content);
        $clash_result = file_put_contents($this->clash_path, $clash_content);
        $work_result = file_put_contents($this->work_path, $work_content);

        if ($xr_result !== false && $clash_result !== false && $work_result !== false) {
            if (IS_AJAX) { $this->ajaxReturn(array('status'=>1,'info'=>'保存成功')); return; }
            $this->success('保存成功');
        } else {
            if (IS_AJAX) { $this->ajaxReturn(array('status'=>0,'info'=>'保存失败，请检查文件权限')); return; }
            $this->error('保存失败，请检查文件权限');
        }
    }

    // 单独保存 xr 配置文件
    public function saveXr() {
        if (!IS_POST) {
            $this->ajaxReturn(['status' => 0, 'info' => '非法请求']);
        }
        $content = I('post.content', '', '');

        // 内容长度限制
        if (strlen($content) > 1024*1024) {
            $this->ajaxReturn(['status' => 0, 'info' => 'xr文件内容过大，最大允许1MB']);
        }
        // 禁止写入PHP标签
        if (stripos($content, '<?php') !== false) {
            $this->ajaxReturn(['status' => 0, 'info' => '禁止写入PHP代码']);
        }

        // 检查文件路径和权限
        $dir = dirname($this->xr_path);
        if (!is_writable($dir)) {
            $this->ajaxReturn(['status' => 0, 'info' => '目录无写权限: ' . $dir]);
        }
        if (!is_writable($this->xr_path) && file_exists($this->xr_path)) {
            $this->ajaxReturn(['status' => 0, 'info' => '文件无写权限: ' . $this->xr_path]);
        }

        $result = file_put_contents($this->xr_path, $content);
        if ($result !== false) {
            $this->ajaxReturn(['status' => 1, 'info' => 'xr 配置文件保存成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'info' => 'xr 配置文件保存失败，请检查文件权限。错误: ' . error_get_last()['message']]);
        }
    }

    // 单独保存 clash.yaml 配置文件
    public function saveClash() {
        if (!IS_POST) {
            $this->ajaxReturn(['status' => 0, 'info' => '非法请求']);
        }
        $content = I('post.content', '', '');

        // 内容长度限制
        if (strlen($content) > 4*1024*1024) {
            $this->ajaxReturn(['status' => 0, 'info' => 'clash.yaml内容过大，最大允许4MB']);
        }
        // 禁止写入PHP标签
        if (stripos($content, '<?php') !== false) {
            $this->ajaxReturn(['status' => 0, 'info' => '禁止写入PHP代码']);
        }

        // 检查文件路径和权限
        $dir = dirname($this->clash_path);
        if (!is_writable($dir)) {
            $this->ajaxReturn(['status' => 0, 'info' => '目录无写权限: ' . $dir]);
        }
        if (!is_writable($this->clash_path) && file_exists($this->clash_path)) {
            $this->ajaxReturn(['status' => 0, 'info' => '文件无写权限: ' . $this->clash_path]);
        }

        $result = file_put_contents($this->clash_path, $content);
        if ($result !== false) {
            $this->ajaxReturn(['status' => 1, 'info' => 'clash.yaml 配置文件保存成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'info' => 'clash.yaml 配置文件保存失败，请检查文件权限。错误: ' . error_get_last()['message']]);
        }
    }

    // 单独保存 work 配置文件
    public function saveWork() {
        if (!IS_POST) {
            $this->ajaxReturn(['status' => 0, 'info' => '非法请求']);
        }
        $content = I('post.content', '', '');

        // 内容长度限制
        if (strlen($content) > 1024*1024) {
            $this->ajaxReturn(['status' => 0, 'info' => 'work文件内容过大，最大允许1MB']);
        }
        // 禁止写入PHP标签
        if (stripos($content, '<?php') !== false) {
            $this->ajaxReturn(['status' => 0, 'info' => '禁止写入PHP代码']);
        }

        // 检查文件路径和权限
        $dir = dirname($this->work_path);
        if (!is_writable($dir)) {
            $this->ajaxReturn(['status' => 0, 'info' => '目录无写权限: ' . $dir]);
        }
        if (!is_writable($this->work_path) && file_exists($this->work_path)) {
            $this->ajaxReturn(['status' => 0, 'info' => '文件无写权限: ' . $this->work_path]);
        }

        $result = file_put_contents($this->work_path, $content);
        if ($result !== false) {
            $this->ajaxReturn(['status' => 1, 'info' => 'work 配置文件保存成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'info' => 'work 配置文件保存失败，请检查文件权限。错误: ' . error_get_last()['message']]);
        }
    }

} 