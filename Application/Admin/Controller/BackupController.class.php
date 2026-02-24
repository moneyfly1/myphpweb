<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class BackupController extends AdminBaseController {

    private $backupDir;

    public function _initialize() {
        parent::_initialize();
        $this->backupDir = dirname(dirname(dirname(dirname(__DIR__)))) . '/Upload/backup/';
        if (!is_dir($this->backupDir)) mkdir($this->backupDir, 0755, true);
    }

    public function index() {
        $files = glob($this->backupDir . '*.sql');
        $backups = array();
        if ($files) {
            rsort($files);
            foreach ($files as $file) {
                $backups[] = array(
                    'name' => basename($file),
                    'size' => $this->formatSize(filesize($file)),
                    'time' => date('Y-m-d H:i:s', filemtime($file)),
                );
            }
        }
        $this->assign('backups', $backups);
        $this->display();
    }

    public function create() {
        if (!IS_AJAX) $this->error('非法请求');

        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbName = env('DB_NAME', '');
        $dbUser = env('DB_USER', '');
        $dbPass = env('DB_PASSWORD', '');
        $dbPort = env('DB_PORT', '3306');

        $filename = $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $this->backupDir . $filename;

        $cmd = sprintf(
            'mysqldump -h %s -P %s -u %s -p%s %s > %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );

        $output = array();
        $retval = 0;
        exec($cmd, $output, $retval);

        if ($retval === 0 && file_exists($filepath) && filesize($filepath) > 0) {
            $this->ajaxReturn(array(
                'code' => 0,
                'msg' => '备份成功',
                'data' => array(
                    'name' => $filename,
                    'size' => $this->formatSize(filesize($filepath)),
                )
            ));
        } else {
            @unlink($filepath);
            $this->ajaxReturn(array('code' => 1, 'msg' => '备份失败: ' . implode("\n", $output)));
        }
    }

    public function download() {
        $name = I('get.name', '', 'trim');
        $name = basename($name);
        $filepath = $this->backupDir . $name;
        if (!file_exists($filepath)) $this->error('文件不存在');

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    public function del() {
        $name = I('get.name', '', 'trim');
        $name = basename($name);
        $filepath = $this->backupDir . $name;
        if (file_exists($filepath)) {
            unlink($filepath);
            $this->success('删除成功', U('Admin/Backup/index'));
        } else {
            $this->error('文件不存在');
        }
    }

    private function formatSize($bytes) {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
