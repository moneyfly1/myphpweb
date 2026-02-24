<?php
/**
 * 邮件队列管理类
 * 用于处理邮件发送队列，提高邮件发送性能
 */
class EmailQueue {
    
    private $queueTable = 'email_queue';
    
    // 状态常量定义
    const STATUS_PENDING = 'pending';     // 待发送
    const STATUS_PROCESSING = 'processing';  // 处理中
    const STATUS_SENT = 'sent';        // 发送成功
    const STATUS_FAILED = 'failed';      // 发送失败
    
    /**
     * 添加邮件到队列
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件内容
     * @param string $type 邮件类型 (activation, password_reset, subscription, expiration, order)
     * @param int $priority 优先级 (1-5, 1最高)
     * @param array $extra 额外参数
     * @return bool
     */
    public function addToQueue($to, $subject, $body, $type = 'general', $priority = 3, $extra = []) {
        try {
            // 从.env文件获取数据库配置
            $host = env('DB_HOST');
            $dbname = env('DB_NAME');
            $username = env('DB_USER');
            $password = env('DB_PASSWORD');
            $port = env('DB_PORT', '3306');
            $prefix = env('DB_PREFIX', '');
            
            // 验证必要的配置是否存在
            if (empty($host) || empty($dbname) || empty($username) || empty($password)) {
                throw new Exception("数据库配置不完整，请检查.env文件中的DB_HOST、DB_NAME、DB_USER、DB_PASSWORD配置");
            }
            
            // 创建数据库连接
            $mysqli = new mysqli($host, $username, $password, $dbname, $port);
            if ($mysqli->connect_error) {
                throw new Exception("数据库连接失败: " . $mysqli->connect_error);
            }
            $mysqli->set_charset("utf8mb4");
            
            $data = [
                'to_email' => $to,
                'subject' => $subject,
                'body' => $body,
                'type' => $type,
                'priority' => $priority,
                'status' => self::STATUS_PENDING,
                'retry_count' => 0,
                'max_retries' => 3,
                'extra_data' => json_encode($extra),
                'created_at' => time(),
                'updated_at' => time(),
                'scheduled_at' => time() // 可以用于延迟发送
            ];
            
            // 构建SQL语句
            $tableName = $prefix . $this->queueTable;
            $fields = implode(', ', array_keys($data));
            $values = "'" . implode("', '", array_map([$mysqli, 'real_escape_string'], $data)) . "'";
            $sql = "INSERT INTO {$tableName} ({$fields}) VALUES ({$values})";
            
            $result = $mysqli->query($sql);
            
            // 添加调试日志
            $logFile = dirname(dirname(dirname(__DIR__))) . '/Runtime/Logs/email_queue.log';
            file_put_contents($logFile, "[addToQueue] 尝试插入邮件到队列: {$to}\n", FILE_APPEND);
            file_put_contents($logFile, "[addToQueue] SQL: {$sql}\n", FILE_APPEND);
            file_put_contents($logFile, "[addToQueue] mysqli->query结果: " . var_export($result, true) . "\n", FILE_APPEND);
            if (!$result) {
                file_put_contents($logFile, "[addToQueue] mysqli错误: " . $mysqli->error . "\n", FILE_APPEND);
            }
            
            $mysqli->close();
            
            return $result ? true : false;
        } catch (Exception $e) {
            error_log('添加邮件到队列失败: ' . $e->getMessage());
            $logFile = dirname(dirname(dirname(__DIR__))) . '/Runtime/Logs/email_queue.log';
            file_put_contents($logFile, "[addToQueue] 异常: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
    
    /**
     * 获取待发送的邮件
     * @param int $limit 获取数量
     * @return array
     */
    public function getPendingEmails($limit = 10) {
        try {
            $where = [
                'status' => self::STATUS_PENDING,
                'scheduled_at' => ['elt', time()],
                'retry_count' => ['lt', 'max_retries']
            ];
            
            return M($this->queueTable)
                ->where($where)
                ->order('priority ASC, created_at ASC')
                ->limit($limit)
                ->select();
        } catch (Exception $e) {
            error_log('获取待发送邮件失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 标记邮件为处理中
     * @param int $id 邮件ID
     * @return bool
     */
    public function markAsProcessing($id) {
        try {
            $data = [
                'status' => self::STATUS_PROCESSING,
                'updated_at' => time()
            ];
            return M($this->queueTable)->where(['id' => $id])->save($data);
        } catch (Exception $e) {
            error_log('标记邮件处理中失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 标记邮件发送成功
     * @param int $id 邮件ID
     * @return bool
     */
    public function markAsSent($id) {
        try {
            $data = [
                'status' => self::STATUS_SENT,
                'sent_at' => time(),
                'updated_at' => time()
            ];
            return M($this->queueTable)->where(['id' => $id])->save($data);
        } catch (Exception $e) {
            error_log('标记邮件发送成功失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 标记邮件发送失败
     * @param int $id 邮件ID
     * @param string $error 错误信息
     * @return bool
     */
    public function markAsFailed($id, $error = '') {
        try {
            // 先获取当前重试次数
            $email = M($this->queueTable)->where(['id' => $id])->find();
            if (!$email) {
                return false;
            }
            
            $retryCount = $email['retry_count'] + 1;
            $status = $retryCount >= $email['max_retries'] ? self::STATUS_FAILED : self::STATUS_PENDING;
            
            $data = [
                'status' => $status,
                'retry_count' => $retryCount,
                'error_message' => $error,
                'updated_at' => time()
            ];
            
            // 如果还可以重试，设置下次重试时间（指数退避）
            if ($status === self::STATUS_PENDING) {
                $delay = pow(2, $retryCount) * 60; // 2^n 分钟后重试
                $data['scheduled_at'] = time() + $delay;
            }
            
            return M($this->queueTable)->where(['id' => $id])->save($data);
        } catch (Exception $e) {
            error_log('标记邮件发送失败失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 处理邮件队列
     * @param int $batchSize 批处理大小
     * @return int 处理的邮件数量
     */
    public function processQueue($batchSize = 10) {
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0
        ];
        
        try {
            $emails = $this->getPendingEmails($batchSize);
            error_log('[processQueue] 获取待处理邮件数量: ' . count($emails));
            
            foreach ($emails as $email) {
                $stats['processed']++;
                error_log('[processQueue] 处理邮件ID: ' . $email['id']);
                
                // 标记为处理中
                $this->markAsProcessing($email['id']);
                error_log('[processQueue] 标记为processing: ' . $email['id']);
                
                // 发送邮件
                $result = $this->sendEmail($email);
                error_log('[processQueue] sendEmail结果: ' . var_export($result, true));
                
                if ($result) {
                    $this->markAsSent($email['id']);
                    error_log('[processQueue] 标记为sent: ' . $email['id']);
                    $stats['sent']++;
                } else {
                    $this->markAsFailed($email['id'], '邮件发送失败');
                    error_log('[processQueue] 标记为failed: ' . $email['id']);
                    $stats['failed']++;
                }
                
                // 添加小延迟避免过快发送
                usleep(100000); // 0.1秒
            }
        } catch (Exception $e) {
            error_log('[processQueue] 处理邮件队列失败: ' . $e->getMessage());
        }
        
        return $stats['processed'];
    }
    
    /**
     * 发送单个邮件
     * @param array $email 邮件数据
     * @return bool
     */
    private function sendEmail($email) {
        try {
            error_log('[sendEmail] 开始发送邮件: ' . $email['to_email']);
            $result = send_mail_direct($email['to_email'], $email['subject'], $email['body']);
            error_log('[sendEmail] send_mail_direct结果: ' . var_export($result, true));
            return $result;
        } catch (Exception $e) {
            error_log('[sendEmail] 发送邮件失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取队列统计信息
     * @return array
     */
    public function getQueueStatistics() {
        try {
            $stats = [];
            $stats['pending'] = M($this->queueTable)->where(['status' => self::STATUS_PENDING])->count();
            $stats['processing'] = M($this->queueTable)->where(['status' => self::STATUS_PROCESSING])->count();
            $stats['sent'] = M($this->queueTable)->where(['status' => self::STATUS_SENT])->count();
            $stats['failed'] = M($this->queueTable)->where(['status' => self::STATUS_FAILED])->count();
            $stats['total'] = M($this->queueTable)->count();
            return $stats;
        } catch (Exception $e) {
            error_log('获取队列统计失败: ' . $e->getMessage());
            return [
                'pending' => 0,
                'processing' => 0,
                'sent' => 0,
                'failed' => 0,
                'total' => 0
            ];
        }
    }
    
    /**
     * 获取队列统计信息（别名方法）
     * @return array
     */
    public function getQueueStats() {
        return $this->getQueueStatistics();
    }
    
    /**
     * 清理已发送和已失败的邮件记录
     * @param int $days 保留天数
     * @return int 清理的记录数
     */
    public function cleanSentEmails($days = 30) {
        try {
            $cutoffTime = time() - ($days * 24 * 60 * 60);
            
            // 清理已发送的邮件
            $sentWhere = [
                'status' => self::STATUS_SENT,
                'sent_at' => ['lt', $cutoffTime]
            ];
            $sentCount = M($this->queueTable)->where($sentWhere)->count();
            M($this->queueTable)->where($sentWhere)->delete();
            
            // 清理已失败的邮件
            $failedWhere = [
                'status' => self::STATUS_FAILED,
                'created_at' => ['lt', $cutoffTime]
            ];
            $failedCount = M($this->queueTable)->where($failedWhere)->count();
            M($this->queueTable)->where($failedWhere)->delete();
            
            $totalCount = $sentCount + $failedCount;
            error_log("清理邮件记录：已发送 {$sentCount} 条，已失败 {$failedCount} 条，总计 {$totalCount} 条");
            
            return $totalCount;
        } catch (Exception $e) {
            error_log('清理邮件记录失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 清理已发送和已失败的邮件记录（别名方法）
     * @param int $days 保留天数
     * @return int 清理的记录数
     */
    public function cleanupSentEmails($days = 30) {
        return $this->cleanSentEmails($days);
    }
}