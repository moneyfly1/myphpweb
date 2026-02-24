<?php
/**
 * 日志管理脚本
 * 用于日志轮转、清理和监控
 */

class LogManager {
    private $logDirs = [];
    private $maxFiles = 10;
    private $maxSize = 10 * 1024 * 1024; // 10MB
    private $retentionDays = 30;
    
    public function __construct() {
        // 检测项目根目录
        $projectRoot = dirname(__FILE__);
        
        // 设置日志目录 - 主要使用Runtime目录
        $this->logDirs = [
            $projectRoot . '/Application/Runtime/Logs/',
            $projectRoot . '/Application/Runtime/Logs/Admin/',
            $projectRoot . '/Application/Runtime/Logs/Home/',
            $projectRoot . '/Application/Runtime/Logs/email/',
            $projectRoot . '/Application/Runtime/Logs/scripts/',
            $projectRoot . '/Upload/logs/',
            '/var/log/email_queue/',
            '/var/log/script_logs/'
        ];
        
        // 确保Runtime日志目录存在
        $this->ensureRuntimeLogDirs($projectRoot);
    }
    
    /**
     * 确保Runtime日志目录存在
     */
    private function ensureRuntimeLogDirs($projectRoot) {
        $runtimeDirs = [
            $projectRoot . '/Application/Runtime/Logs/',
            $projectRoot . '/Application/Runtime/Logs/Admin/',
            $projectRoot . '/Application/Runtime/Logs/Home/',
            $projectRoot . '/Application/Runtime/Logs/email/',
            $projectRoot . '/Application/Runtime/Logs/scripts/'
        ];
        
        foreach ($runtimeDirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    echo "创建日志目录: {$dir}\n";
                }
            }
        }
    }
    
    /**
     * 执行日志轮转
     */
    public function rotateLogs() {
        $totalRotated = 0;
        
        foreach ($this->logDirs as $logDir) {
            if (is_dir($logDir)) {
                $rotated = $this->rotateLogDir($logDir);
                $totalRotated += $rotated;
                echo "目录 {$logDir}: 轮转了 {$rotated} 个文件\n";
            }
        }
        
        return $totalRotated;
    }
    
    /**
     * 轮转单个目录的日志
     */
    private function rotateLogDir($logDir) {
        $files = glob($logDir . '*.log');
        if (empty($files)) {
            return 0;
        }
        
        // 按修改时间排序
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $rotated = 0;
        
        // 删除超过最大文件数量的旧文件
        if (count($files) > $this->maxFiles) {
            $filesToDelete = array_slice($files, $this->maxFiles);
            foreach ($filesToDelete as $file) {
                if (unlink($file)) {
                    $rotated++;
                }
            }
        }
        
        // 检查文件大小并压缩大文件
        foreach ($files as $file) {
            if (file_exists($file) && filesize($file) > $this->maxSize) {
                $compressedFile = $file . '.gz';
                if (!file_exists($compressedFile)) {
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $compressed = gzencode($content, 9);
                        if ($compressed !== false) {
                            if (file_put_contents($compressedFile, $compressed)) {
                                // 清空原文件，保留最后100行
                                $lines = file($file);
                                if ($lines !== false && count($lines) > 100) {
                                    $lastLines = array_slice($lines, -100);
                                    file_put_contents($file, implode('', $lastLines));
                                }
                                $rotated++;
                            }
                        }
                    }
                }
            }
        }
        
        return $rotated;
    }
    
    /**
     * 清理过期日志
     */
    public function cleanOldLogs() {
        $totalCleaned = 0;
        
        foreach ($this->logDirs as $logDir) {
            if (is_dir($logDir)) {
                $cleaned = $this->cleanOldLogsInDir($logDir);
                $totalCleaned += $cleaned;
                echo "目录 {$logDir}: 清理了 {$cleaned} 个过期文件\n";
            }
        }
        
        return $totalCleaned;
    }
    
    /**
     * 清理单个目录的过期日志
     */
    private function cleanOldLogsInDir($logDir) {
        $cutoffTime = time() - ($this->retentionDays * 24 * 60 * 60);
        $files = glob($logDir . '*.log*');
        
        $cleaned = 0;
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 获取日志统计信息
     */
    public function getLogStats() {
        $stats = [];
        
        foreach ($this->logDirs as $logDir) {
            if (is_dir($logDir)) {
                $files = glob($logDir . '*.log*');
                $totalSize = 0;
                $fileCount = count($files);
                
                foreach ($files as $file) {
                    $totalSize += filesize($file);
                }
                
                $stats[$logDir] = [
                    'files' => $fileCount,
                    'size' => $this->formatBytes($totalSize),
                    'size_bytes' => $totalSize
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * 格式化字节数
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * 监控日志大小
     */
    public function monitorLogs($threshold = 100 * 1024 * 1024) { // 100MB
        $stats = $this->getLogStats();
        $alerts = [];
        
        foreach ($stats as $dir => $stat) {
            if ($stat['size_bytes'] > $threshold) {
                $alerts[] = "警告: 日志目录 {$dir} 大小超过阈值 (" . $this->formatBytes($threshold) . ")";
            }
        }
        
        return $alerts;
    }
}

// 命令行执行
if (php_sapi_name() === 'cli') {
    $manager = new LogManager();
    
    if ($argc < 2) {
        echo "用法: php log_manager.php [rotate|clean|stats|monitor]\n";
        echo "  rotate  - 轮转日志文件\n";
        echo "  clean   - 清理过期日志\n";
        echo "  stats   - 显示日志统计\n";
        echo "  monitor - 监控日志大小\n";
        exit(1);
    }
    
    $action = $argv[1];
    
    switch ($action) {
        case 'rotate':
            echo "开始轮转日志...\n";
            $rotated = $manager->rotateLogs();
            echo "完成！共轮转了 {$rotated} 个文件\n";
            break;
            
        case 'clean':
            echo "开始清理过期日志...\n";
            $cleaned = $manager->cleanOldLogs();
            echo "完成！共清理了 {$cleaned} 个文件\n";
            break;
            
        case 'stats':
            echo "日志统计信息:\n";
            $stats = $manager->getLogStats();
            foreach ($stats as $dir => $stat) {
                echo "  {$dir}: {$stat['files']} 个文件, {$stat['size']}\n";
            }
            break;
            
        case 'monitor':
            echo "监控日志大小...\n";
            $alerts = $manager->monitorLogs();
            if (empty($alerts)) {
                echo "所有日志目录大小正常\n";
            } else {
                foreach ($alerts as $alert) {
                    echo $alert . "\n";
                }
            }
            break;
            
        default:
            echo "未知操作: {$action}\n";
            exit(1);
    }
}
?> 