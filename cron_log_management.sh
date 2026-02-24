#!/bin/bash

# 日志管理定时任务脚本
# 建议添加到crontab: 0 2 * * * /path/to/cron_log_management.sh

# 自动检测项目根目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# 日志文件 - 改为Runtime目录
LOG_FILE="$PROJECT_ROOT/Application/Runtime/Logs/scripts/cron_log_management.log"

# 创建日志目录
mkdir -p "$(dirname "$LOG_FILE")"

# 记录日志
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

log_message "开始执行日志管理任务"

# 检查PHP是否可用
if ! command -v php >/dev/null 2>&1; then
    log_message "错误: PHP未安装或不在PATH中"
    exit 1
fi

# 切换到项目目录
cd "$PROJECT_ROOT" || {
    log_message "错误: 无法切换到项目目录"
    exit 1
}

# 执行日志轮转
log_message "执行日志轮转..."
if php log_manager.php rotate >> "$LOG_FILE" 2>&1; then
    log_message "日志轮转完成"
else
    log_message "错误: 日志轮转失败"
fi

# 清理过期日志
log_message "清理过期日志..."
if php log_manager.php clean >> "$LOG_FILE" 2>&1; then
    log_message "过期日志清理完成"
else
    log_message "错误: 过期日志清理失败"
fi

# 监控日志大小
log_message "监控日志大小..."
if php log_manager.php monitor >> "$LOG_FILE" 2>&1; then
    log_message "日志监控完成"
else
    log_message "错误: 日志监控失败"
fi

# 清理日志文件本身（保留最近7天）
find "$(dirname "$LOG_FILE")" -name "*.log" -mtime +7 -delete 2>/dev/null

log_message "日志管理任务完成" 