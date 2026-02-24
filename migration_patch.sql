-- ============================================================
-- 数据库迁移补丁 - 追加到现有数据库dump
-- 执行方式：追加到dump文件末尾或单独执行
-- ============================================================

-- -----------------------------------------------
-- 1. 删除Posts/文章管理相关的auth_rule记录
-- -----------------------------------------------
DELETE FROM `yg_auth_rule` WHERE `name` LIKE 'Admin/Posts/%' OR `name` LIKE 'Admin/ShowNav/posts';
DELETE FROM `yg_admin_nav` WHERE `mca` LIKE 'Admin/Posts/%' OR `mca` LIKE 'Admin/ShowNav/posts';

-- -----------------------------------------------
-- 2. 安全添加列的存储过程
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

-- -----------------------------------------------
-- 3. yg_user 新增字段
-- -----------------------------------------------
CALL _add_column_if_not_exists('yg_user', 'balance', "decimal(10,2) NOT NULL DEFAULT 0 COMMENT '账户余额'");
CALL _add_column_if_not_exists('yg_user', 'invited_by', "int(11) unsigned DEFAULT NULL COMMENT '邀请人ID'");
CALL _add_column_if_not_exists('yg_user', 'invite_code_used', "varchar(32) DEFAULT '' COMMENT '使用的邀请码'");
CALL _add_column_if_not_exists('yg_user', 'user_level_id', "int(11) unsigned DEFAULT NULL COMMENT 'VIP等级ID'");
CALL _add_column_if_not_exists('yg_user', 'total_consumption', "decimal(10,2) NOT NULL DEFAULT 0 COMMENT '累计消费'");
CALL _add_column_if_not_exists('yg_user', 'theme', "varchar(20) NOT NULL DEFAULT 'default' COMMENT '主题'");
CALL _add_column_if_not_exists('yg_user', 'device_management_enabled', "tinyint(1) NOT NULL DEFAULT 0 COMMENT '设备管理开关'");

-- -----------------------------------------------
-- 4. yg_order 新增字段
-- -----------------------------------------------
CALL _add_column_if_not_exists('yg_order', 'discount_amount', "decimal(10,2) NOT NULL DEFAULT 0 COMMENT '折扣金额'");
CALL _add_column_if_not_exists('yg_order', 'coupon_code', "varchar(32) DEFAULT '' COMMENT '使用的优惠码'");

DROP PROCEDURE IF EXISTS `_add_column_if_not_exists`;

-- -----------------------------------------------
-- 5. 创建新功能表（IF NOT EXISTS）
-- -----------------------------------------------

-- 公告表
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

-- 工单表
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

-- 邀请码表
CREATE TABLE IF NOT EXISTS `yg_invite_code` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `user_id` int(11) unsigned NOT NULL COMMENT '生成者ID',
  `max_uses` int(11) NOT NULL DEFAULT 1 COMMENT '最大使用次数',
  `used_count` int(11) NOT NULL DEFAULT 0,
  `reward_inviter` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '邀请人奖励',
  `reward_invitee` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '被邀请人奖励',
  `expire_at` int(11) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请码表';

CREATE TABLE IF NOT EXISTS `yg_invite_relation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `inviter_id` int(11) unsigned NOT NULL COMMENT '邀请人ID',
  `invitee_id` int(11) unsigned NOT NULL COMMENT '被邀请人ID',
  `invite_code` varchar(32) NOT NULL,
  `reward_given` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_inviter` (`inviter_id`),
  KEY `idx_invitee` (`invitee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请关系表';

-- 优惠券表
CREATE TABLE IF NOT EXISTS `yg_coupon` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0固定金额 1百分比',
  `value` decimal(10,2) NOT NULL COMMENT '折扣值',
  `min_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最低消费',
  `max_discount` decimal(10,2) DEFAULT NULL COMMENT '最大折扣额',
  `max_uses` int(11) NOT NULL DEFAULT 0 COMMENT '0无限制',
  `used_count` int(11) NOT NULL DEFAULT 0,
  `per_user_limit` int(11) NOT NULL DEFAULT 1,
  `start_time` int(11) unsigned NOT NULL DEFAULT 0,
  `end_time` int(11) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券表';

CREATE TABLE IF NOT EXISTS `yg_coupon_usage` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `order_id` int(11) unsigned NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_coupon` (`coupon_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券使用记录';

-- VIP等级表
CREATE TABLE IF NOT EXISTS `yg_user_level` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) NOT NULL COMMENT '等级名称',
  `level_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `min_consumption` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最低累计消费',
  `discount_rate` decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT '折扣率',
  `device_limit_bonus` int(11) NOT NULL DEFAULT 0 COMMENT '额外设备数',
  `benefits` text COMMENT '权益说明',
  `icon_url` varchar(255) DEFAULT '',
  `color` varchar(20) DEFAULT '#1677ff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VIP等级表';

-- 充值记录表
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

-- 邮件模板表
CREATE TABLE IF NOT EXISTS `yg_email_template` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '模板标识',
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `variables` varchar(500) DEFAULT '' COMMENT '可用变量',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件模板表';

-- 节点表
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

-- 用户节点关联表
CREATE TABLE IF NOT EXISTS `yg_user_node` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `node_id` int(11) unsigned NOT NULL,
  `assigned_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_node` (`user_id`,`node_id`),
  KEY `idx_node_id` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户节点关联表';

-- 登录历史表
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

-- 用户操作日志表
CREATE TABLE IF NOT EXISTS `yg_user_action_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(500) DEFAULT '',
  `ip` varchar(45) DEFAULT '',
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户操作日志表';

-- 短订阅历史表
CREATE TABLE IF NOT EXISTS `yg_short_dingyue_history` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `short_code` varchar(10) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `access_ip` varchar(45) DEFAULT '',
  `access_time` int(11) unsigned NOT NULL DEFAULT 0,
  `user_agent` varchar(500) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_short_code` (`short_code`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短订阅访问历史';

-- -----------------------------------------------
-- 6. 插入新功能的auth_rule权限规则（从ID 165开始）
-- -----------------------------------------------

-- 公告管理 (165-169)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(165,0,'Admin/Announcement/index','公告管理',1,1,''),
(166,165,'Admin/Announcement/add','添加公告',1,1,''),
(167,165,'Admin/Announcement/edit','编辑公告',1,1,''),
(168,165,'Admin/Announcement/del','删除公告',1,1,''),
(169,165,'Admin/Announcement/toggle','切换公告状态',1,1,'');

-- 工单管理 (170-174)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(170,0,'Admin/Ticket/index','工单管理',1,1,''),
(171,170,'Admin/Ticket/detail','工单详情',1,1,''),
(172,170,'Admin/Ticket/close','关闭工单',1,1,''),
(173,170,'Admin/Ticket/assign_admin','指派工单',1,1,''),
(174,170,'Admin/Ticket/del','删除工单',1,1,'');

-- 邀请管理 (175-178)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(175,0,'Admin/Invite/index','邀请管理',1,1,''),
(176,175,'Admin/Invite/relations','邀请关系',1,1,''),
(177,175,'Admin/Invite/del','删除邀请码',1,1,''),
(178,175,'Admin/Invite/toggle','切换邀请码状态',1,1,'');

-- 优惠券管理 (179-183)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(179,0,'Admin/Coupon/index','优惠券管理',1,1,''),
(180,179,'Admin/Coupon/add','添加优惠券',1,1,''),
(181,179,'Admin/Coupon/edit','编辑优惠券',1,1,''),
(182,179,'Admin/Coupon/del','删除优惠券',1,1,''),
(183,179,'Admin/Coupon/toggle','切换优惠券状态',1,1,'');

-- VIP等级管理 (184-187)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(184,0,'Admin/UserLevel/index','VIP等级管理',1,1,''),
(185,184,'Admin/UserLevel/add','添加等级',1,1,''),
(186,184,'Admin/UserLevel/edit','编辑等级',1,1,''),
(187,184,'Admin/UserLevel/del','删除等级',1,1,'');

-- 邮件模板管理 (188-190)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(188,0,'Admin/EmailTemplate/index','邮件模板管理',1,1,''),
(189,188,'Admin/EmailTemplate/edit','编辑模板',1,1,''),
(190,188,'Admin/EmailTemplate/preview','预览模板',1,1,'');

-- 节点管理 (191-202)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(191,0,'Admin/NodeMgr/index','节点管理',1,1,''),
(192,191,'Admin/NodeMgr/add','添加节点',1,1,''),
(193,191,'Admin/NodeMgr/edit','编辑节点',1,1,''),
(194,191,'Admin/NodeMgr/del','删除节点',1,1,''),
(195,191,'Admin/NodeMgr/collect','采集节点',1,1,''),
(196,191,'Admin/NodeMgr/importPreview','导入预览',1,1,''),
(197,191,'Admin/NodeMgr/doCollect','执行采集',1,1,''),
(198,191,'Admin/NodeMgr/assign','分配节点',1,1,''),
(199,191,'Admin/NodeMgr/doAssign','执行分配',1,1,''),
(200,191,'Admin/NodeMgr/unassign','取消分配',1,1,''),
(201,191,'Admin/NodeMgr/healthCheck','健康检查',1,1,''),
(202,191,'Admin/NodeMgr/healthCheckAll','批量健康检查',1,1,'');

-- 设备管理 (203-207)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(203,0,'Admin/Device/index','设备管理',1,1,''),
(204,203,'Admin/Device/detail','设备详情',1,1,''),
(205,203,'Admin/Device/del','删除设备',1,1,''),
(206,203,'Admin/Device/batchDel','批量删除设备',1,1,''),
(207,203,'Admin/Device/stats','设备统计',1,1,'');

-- 数据备份 (208-211)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(208,0,'Admin/Backup/index','数据备份',1,1,''),
(209,208,'Admin/Backup/create','创建备份',1,1,''),
(210,208,'Admin/Backup/download','下载备份',1,1,''),
(211,208,'Admin/Backup/del','删除备份',1,1,'');

-- 定时任务 (212-215)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(212,0,'Admin/Cron/index','定时任务',1,1,''),
(213,212,'Admin/Cron/run','执行任务',1,1,''),
(214,212,'Admin/Cron/logs','任务日志',1,1,''),
(215,212,'Admin/Cron/crontab','Crontab配置',1,1,'');

-- ConfigFile子操作 (216-219)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(216,164,'Admin/ConfigFile/save','保存配置',1,1,''),
(217,164,'Admin/ConfigFile/saveXr','保存Xray配置',1,1,''),
(218,164,'Admin/ConfigFile/saveClash','保存Clash配置',1,1,''),
(219,164,'Admin/ConfigFile/saveWork','保存Work配置',1,1,'');

-- Dingyue子操作 (220-235, pid=135)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(220,135,'Admin/Dingyue/add','添加订阅',1,1,''),
(221,135,'Admin/Dingyue/edit','编辑订阅',1,1,''),
(222,135,'Admin/Dingyue/del','删除订阅',1,1,''),
(223,135,'Admin/Dingyue/detail','订阅详情',1,1,''),
(224,135,'Admin/Dingyue/customerDetail','客户详情',1,1,''),
(225,135,'Admin/Dingyue/sendmail','发送邮件',1,1,''),
(226,135,'Admin/Dingyue/allDel','批量删除',1,1,''),
(227,135,'Admin/Dingyue/allPush','批量推送',1,1,''),
(228,135,'Admin/Dingyue/allDisable','批量禁用',1,1,''),
(229,135,'Admin/Dingyue/allEnable','批量启用',1,1,''),
(230,135,'Admin/Dingyue/editTime','修改时间',1,1,''),
(231,135,'Admin/Dingyue/loginAsUser','登录为用户',1,1,''),
(232,135,'Admin/Dingyue/resetSubscription','重置订阅',1,1,''),
(233,135,'Admin/Dingyue/editSetdrivers','设置设备数',1,1,''),
(234,135,'Admin/Dingyue/cleanAllDrivers','清除所有设备',1,1,''),
(235,135,'Admin/Dingyue/cleanDrivers','清除设备',1,1,'');

-- Users子操作 (236-248, pid=136)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(236,136,'Admin/Users/add','添加用户',1,1,''),
(237,136,'Admin/Users/edit','编辑用户',1,1,''),
(238,136,'Admin/Users/del','删除用户',1,1,''),
(239,136,'Admin/Users/sendmail','发送邮件',1,1,''),
(240,136,'Admin/Users/allDel','批量删除',1,1,''),
(241,136,'Admin/Users/allPush','批量推送',1,1,''),
(242,136,'Admin/Users/allDisable','批量禁用',1,1,''),
(243,136,'Admin/Users/allEnable','批量启用',1,1,''),
(244,136,'Admin/Users/editTime','修改时间',1,1,''),
(245,136,'Admin/Users/expiring','即将到期',1,1,''),
(246,136,'Admin/Users/batchExpireReminder','批量到期提醒',1,1,''),
(247,136,'Admin/Users/adjustBalance','调整余额',1,1,''),
(248,136,'Admin/Users/userLogs','用户日志',1,1,'');

-- Level子操作 (249-251, pid=137)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(249,137,'Admin/Level/add','添加套餐',1,1,''),
(250,137,'Admin/Level/edit','编辑套餐',1,1,''),
(251,137,'Admin/Level/del','删除套餐',1,1,'');

-- Order子操作 (252-253, pid=138)
INSERT IGNORE INTO `yg_auth_rule` (`id`,`pid`,`name`,`title`,`type`,`status`,`condition`) VALUES
(252,138,'Admin/Order/getOrderInfoByNo','查询订单',1,1,''),
(253,138,'Admin/Order/parseOrderDate','解析订单日期',1,1,'');

-- -----------------------------------------------
-- 7. 插入后台导航菜单（从ID 68开始）
-- -----------------------------------------------
INSERT IGNORE INTO `yg_admin_nav` (`id`,`pid`,`name`,`mca`,`ico`,`order_number`) VALUES
(68,0,'工单管理','Admin/Ticket/index','ticket',9),
(69,0,'公告管理','Admin/Announcement/index','bullhorn',10),
(70,0,'邀请管理','Admin/Invite/index','share-alt',11),
(71,0,'优惠券管理','Admin/Coupon/index','tag',12),
(72,0,'VIP等级','Admin/UserLevel/index','star',13),
(73,0,'节点管理','Admin/NodeMgr/index','server',14),
(74,0,'设备管理','Admin/Device/index','mobile',15),
(75,0,'邮件模板','Admin/EmailTemplate/index','file-text',16),
(76,0,'数据备份','Admin/Backup/index','database',17),
(77,0,'定时任务','Admin/Cron/index','clock-o',18);

-- -----------------------------------------------
-- 8. 更新超级管理员权限组（包含所有规则ID）
-- -----------------------------------------------
UPDATE `yg_auth_group` SET `rules` = '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,19,20,21,64,96,123,124,125,135,136,137,138,139,141,142,143,144,145,146,147,148,150,151,152,153,154,155,156,157,158,159,160,161,162,163,164,165,166,167,168,169,170,171,172,173,174,175,176,177,178,179,180,181,182,183,184,185,186,187,188,189,190,191,192,193,194,195,196,197,198,199,200,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,224,225,226,227,228,229,230,231,232,233,234,235,236,237,238,239,240,241,242,243,244,245,246,247,248,249,250,251,252,253' WHERE `id` = 1;

-- -----------------------------------------------
-- 9. 插入默认VIP等级数据
-- -----------------------------------------------
INSERT IGNORE INTO `yg_user_level` (`id`,`level_name`,`level_order`,`min_consumption`,`discount_rate`,`device_limit_bonus`,`benefits`,`icon_url`,`color`,`is_active`,`created_at`) VALUES
(1,'普通用户',0,0.00,1.00,0,'基础服务','','#999999',1,UNIX_TIMESTAMP()),
(2,'白银会员',1,100.00,0.95,1,'95折优惠,+1设备','','#C0C0C0',1,UNIX_TIMESTAMP()),
(3,'黄金会员',2,500.00,0.90,2,'9折优惠,+2设备','','#FFD700',1,UNIX_TIMESTAMP()),
(4,'钻石会员',3,2000.00,0.80,5,'8折优惠,+5设备','','#00BFFF',1,UNIX_TIMESTAMP());

-- -----------------------------------------------
-- 10. 插入默认邮件模板数据
-- -----------------------------------------------
INSERT IGNORE INTO `yg_email_template` (`name`,`subject`,`content`,`variables`,`is_active`,`created_at`,`updated_at`) VALUES
('register_verify','注册验证码','<p>您的验证码是: {code}</p><p>验证码有效期为10分钟，请及时使用。</p>','{code}',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('password_reset','密码重置','<p>您好，</p><p>您的密码重置链接: <a href="{link}">{link}</a></p><p>链接有效期为1小时，请及时使用。</p>','{link},{username}',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('subscription_expire','订阅到期提醒','<p>尊敬的 {username}，</p><p>您的订阅将于 {expire_date} 到期，请及时续费以免影响使用。</p>','{expire_date},{username}',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('payment_success','支付成功通知','<p>尊敬的用户，</p><p>您已成功支付 ¥{amount}，订单号：{order_no}</p><p>感谢您的支持！</p>','{amount},{order_no},{username}',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- ============================================================
-- 迁移补丁完成！
-- 说明：
-- 1. 已删除Posts/文章管理相关的auth_rule和admin_nav记录
-- 2. 已为yg_user表添加7个新字段（余额、邀请、VIP等级等）
-- 3. 已为yg_order表添加2个新字段（折扣金额、优惠码）
-- 4. 已创建14个新功能表（公告、工单、邀请、优惠券、VIP等级等）
-- 5. 已插入165-253共89条auth_rule权限规则
-- 6. 已插入68-77共10条admin_nav导航菜单
-- 7. 已更新超级管理员权限组包含所有规则ID
-- 8. 已插入4条默认VIP等级数据
-- 9. 已插入4条默认邮件模板数据
-- ============================================================
