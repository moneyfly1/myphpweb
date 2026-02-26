-- ============================================================
-- 订阅管理系统 - 完整数据库迁移脚本
-- 安全执行：所有操作使用 IF NOT EXISTS / INSERT IGNORE
-- 执行方式：mysql -u用户名 -p 数据库名 < install.sql
-- ============================================================

-- -----------------------------------------------
-- 0. 清理文章/Posts相关数据（已废弃功能）
-- -----------------------------------------------
DELETE FROM `yg_auth_rule` WHERE `name` LIKE 'Admin/Posts/%' OR `name` LIKE 'Admin/ShowNav/posts';
DELETE FROM `yg_admin_nav` WHERE `mca` LIKE 'Admin/Posts/%' OR `mca` LIKE 'Admin/ShowNav/posts';

-- -----------------------------------------------
-- 1. 安全添加列的存储过程
-- -----------------------------------------------
DROP PROCEDURE IF EXISTS `_add_column_if_not_exists`;
DELIMITER //
CREATE PROCEDURE `_add_column_if_not_exists`(
    IN tbl VARCHAR(64),
    IN col VARCHAR(64),
    IN col_def VARCHAR(255)
)
BEGIN
    SET @db = DATABASE();
    SET @tbl = tbl;
    SET @col = col;
    SELECT COUNT(*) INTO @exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tbl AND COLUMN_NAME = @col;
    IF @exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `', @col, '` ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- yg_user 新增字段
CALL _add_column_if_not_exists('yg_user', 'balance', "decimal(10,2) NOT NULL DEFAULT 0 COMMENT '账户余额'");
CALL _add_column_if_not_exists('yg_user', 'invited_by', "int(11) unsigned DEFAULT NULL COMMENT '邀请人ID'");
CALL _add_column_if_not_exists('yg_user', 'invite_code_used', "varchar(32) DEFAULT '' COMMENT '使用的邀请码'");
CALL _add_column_if_not_exists('yg_user', 'user_level_id', "int(11) unsigned DEFAULT NULL COMMENT 'VIP等级ID'");
CALL _add_column_if_not_exists('yg_user', 'total_consumption', "decimal(10,2) NOT NULL DEFAULT 0 COMMENT '累计消费'");
CALL _add_column_if_not_exists('yg_user', 'theme', "varchar(20) NOT NULL DEFAULT 'default' COMMENT '主题'");
CALL _add_column_if_not_exists('yg_user', 'device_management_enabled', "tinyint(1) NOT NULL DEFAULT 0 COMMENT '设备管理开关'");

-- yg_order 新增字段
CALL _add_column_if_not_exists('yg_order', 'discount_amount', "decimal(10,2) NOT NULL DEFAULT 0 COMMENT '折扣金额'");
CALL _add_column_if_not_exists('yg_order', 'coupon_code', "varchar(32) DEFAULT '' COMMENT '使用的优惠码'");

DROP PROCEDURE IF EXISTS `_add_column_if_not_exists`;

-- -----------------------------------------------
-- 2. 公告表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_announcement` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL COMMENT 'HTML内容',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0通知 1维护 2促销 3紧急',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `start_time` int(11) unsigned NOT NULL DEFAULT 0,
  `end_time` int(11) unsigned NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_popup` tinyint(1) NOT NULL DEFAULT 0 COMMENT '登录后弹窗',
  `popup_type` varchar(20) NOT NULL DEFAULT 'once' COMMENT 'once/always/daily',
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告表';

-- -----------------------------------------------
-- 3. 工单表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_ticket` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_no` varchar(20) NOT NULL COMMENT '工单号',
  `user_id` int(11) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0咨询 1故障 2建议 3其他',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待处理 1处理中 2已回复 3已关闭',
  `priority` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0低 1中 2高',
  `assigned_to` int(11) unsigned DEFAULT NULL COMMENT '指派管理员ID',
  `rating` tinyint(1) DEFAULT NULL COMMENT '1-5评分',
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(11) unsigned NOT NULL DEFAULT 0,
  `closed_at` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_no` (`ticket_no`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单表';

CREATE TABLE IF NOT EXISTS `yg_ticket_reply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `content` text NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复表';
-- -----------------------------------------------
-- 4. 邀请码表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_invite_code` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT '邀请码',
  `user_id` int(11) unsigned NOT NULL COMMENT '创建者',
  `used_count` int(11) NOT NULL DEFAULT 0,
  `max_uses` int(11) NOT NULL DEFAULT 5,
  `reward_type` varchar(20) NOT NULL DEFAULT 'balance' COMMENT 'balance/days',
  `inviter_reward` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '邀请人奖励',
  `invitee_reward` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '被邀请人奖励',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` int(11) unsigned DEFAULT NULL,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请码表';

CREATE TABLE IF NOT EXISTS `yg_invite_relation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `invite_code_id` int(11) unsigned NOT NULL,
  `inviter_id` int(11) unsigned NOT NULL,
  `invitee_id` int(11) unsigned NOT NULL,
  `inviter_reward_amount` decimal(10,2) DEFAULT 0,
  `invitee_reward_amount` decimal(10,2) DEFAULT 0,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_inviter` (`inviter_id`),
  KEY `idx_invitee` (`invitee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请关系表';

-- -----------------------------------------------
-- 5. 优惠券表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_coupon` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1百分比折扣 2固定金额',
  `discount_value` decimal(10,2) NOT NULL COMMENT '折扣值',
  `min_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最低消费',
  `max_discount` decimal(10,2) DEFAULT NULL COMMENT '最大折扣金额',
  `valid_from` int(11) unsigned NOT NULL,
  `valid_until` int(11) unsigned NOT NULL,
  `total_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '0=不限量',
  `used_quantity` int(11) NOT NULL DEFAULT 0,
  `max_uses_per_user` int(11) NOT NULL DEFAULT 1,
  `applicable_packages` varchar(255) DEFAULT '' COMMENT '适用套餐ID逗号分隔',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券表';
CREATE TABLE IF NOT EXISTS `yg_coupon_usage` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `order_id` int(11) unsigned DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_coupon_user` (`coupon_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券使用记录';

-- -----------------------------------------------
-- 6. 用户等级表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_user_level` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) NOT NULL COMMENT '等级名称',
  `level_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `min_consumption` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最低累计消费',
  `discount_rate` decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT '折扣率(0.80=8折)',
  `device_limit_bonus` int(11) NOT NULL DEFAULT 0 COMMENT '额外设备数',
  `benefits` text COMMENT '权益说明',
  `icon_url` varchar(255) DEFAULT '',
  `color` varchar(20) DEFAULT '#1677ff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户等级表';

-- -----------------------------------------------
-- 7. 充值记录表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_recharge_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `username` varchar(30) DEFAULT '',
  `type` tinyint(1) NOT NULL COMMENT '1充值 2消费 3退款 4管理员调整 5邀请奖励',
  `amount` decimal(10,2) NOT NULL COMMENT '变动金额',
  `balance_before` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `order_id` int(11) unsigned DEFAULT NULL,
  `remark` varchar(255) DEFAULT '',
  `operator` varchar(50) DEFAULT '',
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值余额变动记录';
-- -----------------------------------------------
-- 8. 邮件模板表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_email_template` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '模板标识',
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL COMMENT 'HTML模板',
  `variables` varchar(500) DEFAULT '' COMMENT '可用变量说明',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件模板表';

-- -----------------------------------------------
-- 9. 节点表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_node` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `server` varchar(255) NOT NULL COMMENT '服务器地址',
  `port` int(11) NOT NULL DEFAULT 443,
  `type` varchar(20) NOT NULL DEFAULT 'vmess' COMMENT 'vmess/vless/trojan/ss',
  `latency` int(11) DEFAULT NULL COMMENT '延迟ms',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0未测试 1在线 2异常',
  `last_test` int(11) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT '对用户可见',
  `remark` varchar(255) DEFAULT '',
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='节点表';

-- -----------------------------------------------
-- 10. 登录历史表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_login_history` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT '',
  `login_time` int(11) unsigned NOT NULL DEFAULT 0,
  `device_type` varchar(50) DEFAULT '' COMMENT 'PC/Mobile/Tablet',
  `login_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0失败 1成功',
  `failure_reason` varchar(100) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_login_time` (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录历史表';
-- -----------------------------------------------
-- 11. 用户节点分配表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_user_node` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `node_id` int(11) unsigned NOT NULL,
  `assigned_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_node` (`user_id`, `node_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户节点分配表';

-- -----------------------------------------------
-- 12. 用户操作日志表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_user_action_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT 0,
  `qq` varchar(50) NOT NULL DEFAULT '',
  `action` varchar(100) NOT NULL DEFAULT '',
  `detail` text,
  `action_time` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_qq` (`qq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户操作日志';

-- -----------------------------------------------
-- 13. 订阅URL变更历史表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_short_dingyue_history` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT 0,
  `qq` varchar(50) NOT NULL DEFAULT '',
  `old_url` varchar(500) DEFAULT '',
  `new_url` varchar(500) DEFAULT '',
  `change_type` varchar(50) NOT NULL DEFAULT '',
  `change_time` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_qq` (`qq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订阅URL变更历史';

-- -----------------------------------------------
-- 14. 邮件队列表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_email_queue` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `to_email` varchar(255) NOT NULL COMMENT '收件人邮箱',
  `subject` varchar(500) NOT NULL COMMENT '邮件主题',
  `body` longtext NOT NULL COMMENT '邮件内容',
  `type` varchar(50) NOT NULL DEFAULT 'general' COMMENT '邮件类型',
  `priority` tinyint(1) NOT NULL DEFAULT 3 COMMENT '优先级1-5',
  `status` enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `retry_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '重试次数',
  `max_retries` int(11) unsigned NOT NULL DEFAULT 3 COMMENT '最大重试次数',
  `extra_data` text COMMENT '额外数据JSON',
  `error_message` text COMMENT '错误信息',
  `created_at` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '更新时间',
  `scheduled_at` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '计划发送时间',
  `sent_at` int(11) unsigned DEFAULT NULL COMMENT '实际发送时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_scheduled_at` (`scheduled_at`),
  KEY `idx_queue_process` (`status`,`scheduled_at`,`priority`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件发送队列';
-- -----------------------------------------------
-- 15. 设备访问日志表
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `yg_device_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `dingyue_id` int(11) unsigned NOT NULL DEFAULT 0,
  `qq` varchar(50) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `ua` varchar(500) DEFAULT '',
  `fingerprint` varchar(64) DEFAULT '',
  `ip_history` text COMMENT 'JSON格式IP历史',
  `last_seen` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_dingyue_id` (`dingyue_id`),
  KEY `idx_qq` (`qq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='设备访问日志';

-- -----------------------------------------------
-- 16. 权限规则 - 删除文章相关，添加新功能权限
-- -----------------------------------------------

-- 新功能权限规则
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`status`,`type`,`condition`) VALUES
(165,0,'Admin/Ticket/index','工单管理',1,1,''),
(166,165,'Admin/Ticket/detail','工单详情',1,1,''),
(167,165,'Admin/Ticket/close','关闭工单',1,1,''),
(168,165,'Admin/Ticket/assign_admin','指派工单',1,1,''),
(169,165,'Admin/Ticket/del','删除工单',1,1,''),
(170,0,'Admin/Announcement/index','公告管理',1,1,''),
(171,170,'Admin/Announcement/add','添加公告',1,1,''),
(172,170,'Admin/Announcement/edit','编辑公告',1,1,''),
(173,170,'Admin/Announcement/del','删除公告',1,1,''),
(174,170,'Admin/Announcement/toggle','切换公告',1,1,''),
(175,0,'Admin/Invite/index','邀请管理',1,1,''),
(176,175,'Admin/Invite/relations','邀请关系',1,1,''),
(177,175,'Admin/Invite/del','删除邀请码',1,1,''),
(178,175,'Admin/Invite/toggle','切换邀请码',1,1,''),
(179,0,'Admin/Coupon/index','优惠券管理',1,1,''),
(180,179,'Admin/Coupon/add','添加优惠券',1,1,''),
(181,179,'Admin/Coupon/edit','编辑优惠券',1,1,''),
(182,179,'Admin/Coupon/del','删除优惠券',1,1,''),
(183,179,'Admin/Coupon/toggle','切换优惠券',1,1,''),
(184,0,'Admin/UserLevel/index','VIP等级',1,1,''),
(185,184,'Admin/UserLevel/add','添加等级',1,1,''),
(186,184,'Admin/UserLevel/edit','编辑等级',1,1,''),
(187,184,'Admin/UserLevel/del','删除等级',1,1,''),
(188,0,'Admin/NodeMgr/index','节点管理',1,1,''),
(189,188,'Admin/NodeMgr/add','添加节点',1,1,''),
(190,188,'Admin/NodeMgr/edit','编辑节点',1,1,''),
(191,188,'Admin/NodeMgr/del','删除节点',1,1,''),
(192,188,'Admin/NodeMgr/collect','采集节点',1,1,''),
(193,188,'Admin/NodeMgr/doCollect','执行采集',1,1,''),
(194,188,'Admin/NodeMgr/assign','分配节点',1,1,''),
(195,188,'Admin/NodeMgr/doAssign','执行分配',1,1,''),
(196,188,'Admin/NodeMgr/unassign','取消分配',1,1,''),
(197,188,'Admin/NodeMgr/healthCheck','健康检查',1,1,''),
(198,188,'Admin/NodeMgr/healthCheckAll','批量检查',1,1,''),
(199,188,'Admin/NodeMgr/importPreview','导入预览',1,1,'');
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`status`,`type`,`condition`) VALUES
(200,0,'Admin/Device/index','设备管理',1,1,''),
(201,200,'Admin/Device/detail','设备详情',1,1,''),
(202,200,'Admin/Device/del','删除设备',1,1,''),
(203,200,'Admin/Device/batchDel','批量删除设备',1,1,''),
(204,200,'Admin/Device/stats','设备统计',1,1,''),
(205,0,'Admin/EmailTemplate/index','邮件模板',1,1,''),
(206,205,'Admin/EmailTemplate/edit','编辑模板',1,1,''),
(207,205,'Admin/EmailTemplate/preview','预览模板',1,1,''),
(208,0,'Admin/Backup/index','数据备份',1,1,''),
(209,208,'Admin/Backup/create','创建备份',1,1,''),
(210,208,'Admin/Backup/download','下载备份',1,1,''),
(211,208,'Admin/Backup/del','删除备份',1,1,''),
(212,0,'Admin/Cron/index','定时任务',1,1,''),
(213,212,'Admin/Cron/run','执行任务',1,1,''),
(214,212,'Admin/Cron/logs','任务日志',1,1,''),
(215,212,'Admin/Cron/crontab','Crontab配置',1,1,''),
(216,0,'Admin/Users/add','添加用户',1,1,''),
(217,0,'Admin/Users/edit','编辑用户',1,1,''),
(218,0,'Admin/Users/del','删除用户',1,1,''),
(219,0,'Admin/Users/adjustBalance','调整余额',1,1,''),
(220,0,'Admin/Users/userLogs','用户日志',1,1,''),
(221,0,'Admin/Dingyue/add','添加订阅',1,1,''),
(222,0,'Admin/Dingyue/edit','编辑订阅',1,1,''),
(223,0,'Admin/Dingyue/del','删除订阅',1,1,''),
(224,0,'Admin/Dingyue/detail','订阅详情',1,1,''),
(225,0,'Admin/Level/add','添加套餐',1,1,''),
(226,0,'Admin/Level/edit','编辑套餐',1,1,''),
(227,0,'Admin/Level/del','删除套餐',1,1,''),
(228,0,'Admin/Order/getOrderInfoByNo','订单详情',1,1,''),
(229,0,'Admin/Order/payedit','编辑支付',1,1,'');

-- -----------------------------------------------
-- 17. 更新超级管理员权限组（包含所有规则）
-- -----------------------------------------------
UPDATE `yg_auth_group` SET `rules` = '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,19,20,21,64,96,123,124,125,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,161,162,163,164,165,166,167,168,169,170,171,172,173,174,175,176,177,178,179,180,181,182,183,184,185,186,187,188,189,190,191,192,193,194,195,196,197,198,199,200,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,224,225,226,227,228,229' WHERE `id` = 1;

-- -----------------------------------------------
-- 18. 后台导航菜单
-- -----------------------------------------------
INSERT IGNORE INTO `yg_admin_nav` (`id`,`pid`,`name`,`mca`,`ico`,`order_number`) VALUES
(68,0,'工单管理','Admin/Ticket/index','ticket',9),
(69,0,'公告管理','Admin/Announcement/index','bullhorn',10),
(70,0,'邀请管理','Admin/Invite/index','share-alt',11),
(71,0,'优惠券','Admin/Coupon/index','tag',12),
(72,0,'VIP等级','Admin/UserLevel/index','star',13),
(73,0,'节点管理','Admin/NodeMgr/index','server',14),
(74,0,'设备管理','Admin/Device/index','mobile',15),
(75,0,'邮件模板','Admin/EmailTemplate/index','file-text',16),
(76,0,'数据备份','Admin/Backup/index','database',17),
(77,0,'定时任务','Admin/Cron/index','clock-o',18);

-- ============================================================
-- 迁移完成！
-- ============================================================
