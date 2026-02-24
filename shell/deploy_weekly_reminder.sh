#!/bin/bash

# 订阅到期提醒邮件脚本
# 用于查找即将到期的用户并发送提醒邮件
# 建议添加到cron任务中，每天执行一次

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# 项目根目录（脚本位于 shell/ 子目录）
PROJECT_PATH="$(dirname "$SCRIPT_DIR")"

# 检测运行环境
if [[ "$OSTYPE" == "linux-gnu"* ]] || [[ "$OSTYPE" == "freebsd"* ]]; then
    # Linux/FreeBSD 环境
    if [[ -d "/www/wwwroot" ]]; then
        # 宝塔面板环境
        echo "检测到生产环境（宝塔面板）"
        
        # 方法1：通过脚本路径推断宝塔网站目录
        BT_SITE=$(echo "$SCRIPT_DIR" | sed -n 's|\(/www/wwwroot/[^/]*\).*|\1|p')
        
        # 方法2：如果方法1失败，查找包含必要文件的宝塔网站目录
        if [ -z "$BT_SITE" ]; then
            for site_dir in /www/wwwroot/*; do
                if [ -d "$site_dir" ] && [ -f "$site_dir/.env" ]; then
                    BT_SITE="$site_dir"
                    break
                fi
            done
        fi
        
        # 方法3：如果前两种方法都失败，使用第一个找到的宝塔网站目录
        if [ -z "$BT_SITE" ]; then
            BT_SITE=$(find /www/wwwroot -maxdepth 1 -type d -name "*" 2>/dev/null | head -1)
        fi
        
        if [ -n "$BT_SITE" ]; then
            PROJECT_PATH="$BT_SITE"
        fi
    fi
    echo "检测到生产环境（宝塔面板）"
else
    echo "检测到本地环境"
fi

# 切换到项目目录，确保.env能被正确读取
cd "$PROJECT_PATH" || {
    echo "❌ 错误：无法切换到项目目录: $PROJECT_PATH"
    exit 1
}

echo "使用项目路径: $PROJECT_PATH"

# 配置区：请根据实际情况修改
if [ ! -f .env ]; then
  echo "❌ 错误：.env 文件不存在，请将.env放在项目根目录下。"
  exit 1
fi
DB_HOST=$(grep '^DB_HOST=' .env | cut -d '=' -f2- | tr -d '\r')
DB_NAME=$(grep '^DB_NAME=' .env | cut -d '=' -f2- | tr -d '\r')
DB_USER=$(grep '^DB_USER=' .env | cut -d '=' -f2- | tr -d '\r')
DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d '=' -f2- | tr -d '\r')
DB_PORT=$(grep '^DB_PORT=' .env | cut -d '=' -f2- | tr -d '\r')
DB_PREFIX=$(grep '^DB_PREFIX=' .env | cut -d '=' -f2- | tr -d '\r')

# 邮件队列表名
QUEUE_TABLE="${DB_PREFIX}email_queue"
# 订阅表名
SUB_TABLE="${DB_PREFIX}short_dingyue"

NOW_TS=$(date +%s)
# 兼容不同系统的date命令
if date -d "+7 days" +%s >/dev/null 2>&1; then
    # Linux系统
    SEVEN_DAYS_LATER=$(date -d "+7 days" +%s)
else
    # macOS系统
    SEVEN_DAYS_LATER=$(date -v+7d +%s)
fi

# 只查找7天内即将到期的用户（endtime > now 且 endtime <= now+7天）
SQL="SELECT qq, endtime FROM $SUB_TABLE WHERE endtime > $NOW_TS AND endtime <= $SEVEN_DAYS_LATER;"

USERS=$(mysql -h$DB_HOST -P$DB_PORT -u$DB_USER -p$DB_PASSWORD -D$DB_NAME -N -e "$SQL")

if [ -z "$USERS" ]; then
  echo "没有即将到期的用户，无需提醒。"
  exit 0
fi

COUNT=0
while read -r QQ ENDTIME; do
  if [ -z "$QQ" ] || [ -z "$ENDTIME" ]; then
    continue
  fi
  TO_EMAIL="${QQ}@qq.com"
  
  # 计算是否已到期（endtime <= now 表示已到期）
  if [ "$ENDTIME" -le "$NOW_TS" ]; then
    IS_EXPIRED=1
    MAIL_SUBJECT="订阅已到期"
  else
    IS_EXPIRED=0
    MAIL_SUBJECT="订阅即将到期"
  fi
  
  # 使用PHP脚本调用EmailTemplate类生成邮件内容
  MAIL_CONTENT=$(php -r "
<?php
require_once __DIR__ . '/Application/Common/Common/function.php';
require_once __DIR__ . '/Application/Common/Common/EmailTemplate.class.php';

// 加载环境变量
\$envFile = __DIR__ . '/.env';
if (file_exists(\$envFile)) {
    \$envContent = file_get_contents(\$envFile);
    foreach (explode(\"\n\", \$envContent) as \$line) {
        if (strpos(\$line, '=') !== false && !empty(trim(\$line)) {
            list(\$key, \$value) = explode('=', \$line, 2);
            \$key = trim(\$key);
            \$value = trim(\$value);
            if (!empty(\$key)) {
                \$_ENV[\$key] = \$value;
                putenv(\"\$key=\$value\");
            }
        }
    }
}

// 创建EmailTemplate实例
\$emailTemplate = new EmailTemplate();

// 生成到期提醒邮件内容
\$content = \$emailTemplate->getExpirationTemplate('$QQ', $ENDTIME, $IS_EXPIRED);

// 输出邮件内容
echo \$content;
")
  
  # 插入到邮件队列表（使用content字段而不是body字段）
  INSERT_SQL="INSERT INTO $QUEUE_TABLE (to_email, subject, content, status, created_at, scheduled_at, updated_at, retry_count, max_retries, priority) VALUES ('$TO_EMAIL', '$MAIL_SUBJECT', '$(echo "$MAIL_CONTENT" | sed "s/'/\\'/g")', 'pending', $NOW_TS, $NOW_TS, $NOW_TS, 0, 3, 1);"
  mysql -h$DB_HOST -P$DB_PORT -u$DB_USER -p$DB_PASSWORD -D$DB_NAME -e "$INSERT_SQL"
  
  # 兼容不同系统的date命令
  if date -d "@$ENDTIME" "+%Y年%m月%d日" >/dev/null 2>&1; then
      # Linux系统
      ENDTIME_STR=$(date -d "@$ENDTIME" "+%Y年%m月%d日")
  else
      # macOS系统
      ENDTIME_STR=$(date -r "$ENDTIME" "+%Y年%m月%d日")
  fi
  
  echo "已加入续费提醒邮件队列: $TO_EMAIL (到期日: $ENDTIME_STR, 主题: $MAIL_SUBJECT)"
  COUNT=$((COUNT+1))
done <<< "$USERS"

echo "共处理 $COUNT 个即将到期用户。"
exit 0