#!/bin/bash

# 脚本名称：reset_counts.sh
# 功能：重置数据库表中的count和clashcount字段为0
# 日志记录：自动记录执行日志和错误信息

# 自动检测网站根目录
detect_website_root() {
    local detected_root=""
    local current_dir="$(pwd)"
    
    # 检查当前目录是否包含网站特征文件
    if [ -f "index.php" ] || [ -f "admin.php" ] || [ -d "Application" ] || [ -d "ThinkPHP" ]; then
        detected_root="$current_dir"
        echo "$detected_root"
        return 0
    fi
    
    # 检查当前目录的上级目录（最多向上3级）
    local script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    local parent_dirs=(
        "$(dirname "$script_dir")"
        "$(dirname "$(dirname "$script_dir")")"
        "$(dirname "$(dirname "$(dirname "$script_dir")")")"
    )
    
    for parent_dir in "${parent_dirs[@]}"; do
        if [ -f "$parent_dir/index.php" ] || [ -f "$parent_dir/admin.php" ] || [ -d "$parent_dir/Application" ] || [ -d "$parent_dir/ThinkPHP" ]; then
            detected_root="$parent_dir"
            echo "$detected_root"
            return 0
        fi
    done
    
    # 如果都找不到，使用当前目录作为默认值
    detected_root="$current_dir"
    echo "$detected_root"
    return 0
}

# 自动检测网站根目录
WEBSITE_ROOT=$(detect_website_root)

# 环境自适应配置
if [[ "$WEBSITE_ROOT" == *"/www/wwwroot/"* ]] || [ -d "/www/wwwroot" ] || [[ "$(pwd)" == *"/www/wwwroot/"* ]]; then
    # 生产环境 - 宝塔面板
    LOG_DIR="/var/log/reset_counts"
    echo "检测到生产环境，使用系统日志目录: $LOG_DIR"
else
    # 本地开发环境
    LOG_DIR="$WEBSITE_ROOT/logs"
    echo "检测到本地环境，使用项目日志目录: $LOG_DIR"
fi

# 配置部分 - 从 .env 文件读取，如果没有则使用默认值
ENV_FILE="$WEBSITE_ROOT/.env"
if [ -f "$ENV_FILE" ]; then
    DB_HOST=$(grep '^DB_HOST=' "$ENV_FILE" | cut -d'=' -f2)
    DB_USER=$(grep '^DB_USER=' "$ENV_FILE" | cut -d'=' -f2)
    DB_PASS=$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d'=' -f2)
    DB_NAME=$(grep '^DB_NAME=' "$ENV_FILE" | cut -d'=' -f2)
fi
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-myphpweb}"
TABLE_NAME="yg_short_dingyue" # 表名

# 日志文件配置
LOG_FILE="$LOG_DIR/reset_counts_$(date +'%Y%m%d').log" # 日志文件

# 创建日志目录
mkdir -p "$LOG_DIR"
touch "$LOG_FILE"
chmod 644 "$LOG_FILE"

# 日志记录函数
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# 主程序开始
log "========== 开始执行重置操作 =========="

# 记录当前时间
START_TIME=$(date +%s)

# 执行数据库更新
log "正在连接数据库并执行更新操作..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE $TABLE_NAME SET count = 0, clashcount = 0;" 2>> "$LOG_FILE"

# 检查执行结果
if [ $? -eq 0 ]; then
    AFFECTED_ROWS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -Nse "SELECT ROW_COUNT();")
    log "成功更新了 $AFFECTED_ROWS 条记录"
else
    log "错误：数据库更新失败，请检查日志文件"
fi

# 计算执行时间
END_TIME=$(date +%s)
ELAPSED_TIME=$((END_TIME - START_TIME))
log "操作完成，耗时 ${ELAPSED_TIME} 秒"
log "========== 执行结束 ==========\n"