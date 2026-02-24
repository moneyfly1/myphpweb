<?php
/**
 * 安全配置文件
 * 集中管理所有安全相关设置
 */

// 安全配置类
class SecurityConfig {
    
    // 调试模式配置
    const DEBUG_MODE = false;
    const ERROR_REPORTING = DEBUG_MODE ? E_ALL : 0;
    const DISPLAY_ERRORS = DEBUG_MODE;
    const LOG_ERRORS = !DEBUG_MODE;
    const ERROR_LOG = '/var/log/php_errors.log';
    
    // 会话安全配置
    const SESSION_NAME = 'BJYADMIN';
    const SESSION_LIFETIME = 1296000; // 15天
    const SESSION_SECURE = true;
    const SESSION_HTTPONLY = true;
    const SESSION_SAMESITE = 'Strict';
    
    // 文件上传安全配置
    const MAX_UPLOAD_SIZE = 10 * 1024 * 1024; // 10MB
    const ALLOWED_FILE_TYPES = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 
        'doc', 'docx', 'xls', 'xlsx', 'txt'
    ];
    
    // 密码安全配置
    const PASSWORD_MIN_LENGTH = 8;
    const PASSWORD_REQUIRE_SPECIAL = true;
    const PASSWORD_REQUIRE_NUMBER = true;
    const PASSWORD_REQUIRE_UPPERCASE = true;
    
    // 登录安全配置
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOGIN_LOCKOUT_TIME = 900; // 15分钟
    const SESSION_TIMEOUT = 3600; // 1小时
    
    // 日志配置
    const LOG_RETENTION_DAYS = 30;
    const LOG_MAX_FILES = 10;
    const LOG_MAX_SIZE = 10 * 1024 * 1024; // 10MB
    
    // 脚本执行安全配置
    const ALLOWED_SCRIPT_DIRS = [
        'shell/',
        'scripts/',
        'tools/'
    ];
    
    const DANGEROUS_COMMANDS = [
        'rm -rf /',
        'dd if=',
        'mkfs.',
        'fdisk ',
        'format ',
        'del C:\\',
        'format C:'
    ];
    
    /**
     * 应用安全配置
     */
    public static function apply() {
        // 错误处理配置
        error_reporting(self::ERROR_REPORTING);
        ini_set('display_errors', self::DISPLAY_ERRORS ? '1' : '0');
        ini_set('log_errors', self::LOG_ERRORS ? '1' : '0');
        if (self::LOG_ERRORS) {
            ini_set('error_log', self::ERROR_LOG);
        }
        
        // 会话安全配置
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', self::SESSION_SECURE ? '1' : '0');
        ini_set('session.cookie_samesite', self::SESSION_SAMESITE);
        ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
        ini_set('session.cookie_lifetime', self::SESSION_LIFETIME);
        
        // 文件上传配置
        ini_set('upload_max_filesize', self::MAX_UPLOAD_SIZE);
        ini_set('post_max_size', self::MAX_UPLOAD_SIZE);
        
        // 其他安全配置
        ini_set('expose_php', 'Off');
        ini_set('allow_url_fopen', 'Off');
        ini_set('allow_url_include', 'Off');
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '256M');
    }
    
    /**
     * 验证文件类型
     */
    public static function validateFileType($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::ALLOWED_FILE_TYPES);
    }
    
    /**
     * 验证密码强度
     */
    public static function validatePassword($password) {
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return false;
        }
        
        if (self::PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            return false;
        }
        
        if (self::PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        if (self::PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查脚本路径是否安全
     */
    public static function isScriptPathSafe($scriptPath) {
        $realPath = realpath($scriptPath);
        if ($realPath === false) {
            return false;
        }
        
        foreach (self::ALLOWED_SCRIPT_DIRS as $allowedDir) {
            if (strpos($realPath, $allowedDir) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检查脚本内容是否安全
     */
    public static function isScriptContentSafe($content) {
        foreach (self::DANGEROUS_COMMANDS as $command) {
            if (stripos($content, $command) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 生成安全的随机字符串
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 安全的密码哈希
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * 验证密码哈希
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * 检查是否需要重新哈希密码
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
}

// 自动应用安全配置
SecurityConfig::apply();
?> 