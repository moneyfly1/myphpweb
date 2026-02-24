# 订阅管理系统

基于 ThinkPHP 3.x 的订阅服务管理平台，包含前台用户端和后台管理端。

## 技术栈

- PHP 5.6+ / ThinkPHP 3.x
- MySQL 5.7+
- jQuery 3.6.0 / Font Awesome 6.4
- 统一设计系统（design-tokens.css）

## 目录结构

```
├── Application/
│   ├── Admin/          # 后台管理模块
│   ├── Home/           # 前台用户模块
│   └── Common/         # 公共模型、控制器、配置
├── Public/
│   ├── css/            # 统一样式文件
│   ├── js/             # 公共JS
│   ├── design-tokens.css
│   └── statics/        # 第三方库
├── ThinkPHP/           # 框架核心
├── Upload/
│   ├── backup/         # 数据库备份
│   └── ...             # 用户上传目录
├── shell/              # 辅助脚本（含定时任务PHP和Shell脚本）
├── index.php           # 前台入口
├── admin.php           # 后台入口
└── .env                # 环境配置（不提交到git）
```

## 部署到服务器

以宝塔面板为例，网站目录为 `/www/wwwroot/dingyue.moneyfly.top`。

### 1. 克隆代码

```bash
cd /www/wwwroot
git clone git@github.com:moneyfly1/myphpweb.git dingyue.moneyfly.top
```

如果目录已存在：

```bash
cd /www/wwwroot/dingyue.moneyfly.top
git init
git remote add origin git@github.com:moneyfly1/myphpweb.git
git fetch origin
git reset --hard origin/main
```

### 2. 配置环境变量

```bash
cp .env.example .env   # 如果有示例文件
# 或者手动创建 .env，填入以下配置：
```

`.env` 必填项：

```ini
# 数据库
DB_TYPE=mysqli
DB_HOST=127.0.0.1
DB_NAME=你的数据库名
DB_USER=你的数据库用户
DB_PASSWORD=你的数据库密码
DB_PORT=3306
DB_PREFIX=yg_

# 邮件
EMAIL_FROM_NAME=上网订阅信息
EMAIL_SMTP=smtpdm.aliyun.com
EMAIL_USERNAME=你的邮箱
EMAIL_PASSWORD=你的邮箱密码
EMAIL_SMTP_SECURE=ssl
EMAIL_PORT=465

# 阿里云OSS（头像存储）
ALIOSS_KEY_ID=你的AccessKeyId
ALIOSS_KEY_SECRET=你的AccessKeySecret
ALIOSS_END_POINT=oss-cn-hangzhou.aliyuncs.com
ALIOSS_BUCKET=你的Bucket名

# 通知推送
TELEGRAM_ENABLED=1
TELEGRAM_BOT_TOKEN=你的Bot Token
TELEGRAM_CHAT_ID=你的Chat ID
BARK_ENABLED=1
BARK_KEY=你的Bark Key
BARK_SERVER=https://api.day.app
NOTIFY_EMAIL_ENABLED=1
NOTIFY_EMAIL_TO=接收通知的邮箱
```

### 3. 设置目录权限

```bash
cd /www/wwwroot/dingyue.moneyfly.top

# 运行时目录可写
chmod -R 755 Application/Runtime
chown -R www:www Application/Runtime

# 上传目录可写
chmod -R 755 Upload
chown -R www:www Upload

# .env 仅 owner 可读
chmod 600 .env
chown www:www .env
```

### 4. 配置 Nginx

在宝塔面板的网站设置中，添加伪静态规则：

```nginx
location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.php?s=$1 last;
    }
}
```

### 5. 导入数据库

在宝塔面板创建数据库后，导入 SQL 文件（如有）。

### 6. 验证

- 前台：`https://dingyue.moneyfly.top/`
- 后台：`https://dingyue.moneyfly.top/admin.php`

## 更新部署

在服务器上拉取最新代码：

```bash
cd /www/wwwroot/dingyue.moneyfly.top
git pull origin main
```

注意：`.env` 文件不在 git 中，拉取不会覆盖你的配置。

## 定时任务

系统内置了定时任务管理页面（后台 → 定时任务），支持在页面上手动执行。

如需自动执行，在宝塔面板「计划任务」中添加 Shell 脚本，或在服务器 crontab 中添加：

```cron
# 处理邮件队列（每5分钟）
*/5 * * * * /usr/bin/php /www/wwwroot/dingyue.moneyfly.top/shell/process_email_queue.php process >> /dev/null 2>&1

# 清理邮件队列（每天凌晨3点）
0 3 * * * /usr/bin/php /www/wwwroot/dingyue.moneyfly.top/shell/process_email_queue.php clean >> /dev/null 2>&1

# 日志轮转（每天凌晨2点）
0 2 * * * /usr/bin/php /www/wwwroot/dingyue.moneyfly.top/shell/log_manager.php rotate >> /dev/null 2>&1

# 日志清理（每天凌晨2:05）
5 2 * * * /usr/bin/php /www/wwwroot/dingyue.moneyfly.top/shell/log_manager.php clean >> /dev/null 2>&1

# 到期提醒邮件（每天上午9点）
0 9 * * * /usr/bin/php /www/wwwroot/dingyue.moneyfly.top/shell/generate_expire_mail.php >> /dev/null 2>&1

# 用户同步（每周一凌晨4点）
0 4 * * 1 /usr/bin/php /www/wwwroot/dingyue.moneyfly.top/shell/sync_user_with_short.php >> /dev/null 2>&1
```

## 后台功能

| 模块 | 说明 |
|------|------|
| 仪表盘 | 系统概览统计 |
| 订阅管理 | 管理用户订阅 |
| 用户管理 | 用户列表、添加、编辑 |
| 套餐管理 | 套餐配置 |
| 订单列表 | 订单查看和管理 |
| 邮件队列 | 邮件发送状态监控 |
| 通知设置 | Telegram/Bark/邮件通知 |
| 定时任务 | 定时脚本管理和执行 |
| 权限管理 | 管理员权限和角色 |
| 系统设置 | 站点配置 |
