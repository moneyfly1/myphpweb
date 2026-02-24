#!/bin/bash

# 邮件队列清理脚本
# 用于宝塔面板定时任务 - 清理已发送的邮件记录
# 作者: 系统管理员
# 创建时间: $(date '+%Y-%m-%d %H:%M:%S')

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
PROJECT_PATH=$(detect_website_root)

# 环境自适应配置
# 检查是否为生产环境（宝塔面板环境）
if [[ "$PROJECT_PATH" == *"/www/wwwroot/"* ]] || [ -d "/www/wwwroot" ] || [[ "$(pwd)" == *"/www/wwwroot/"* ]] || [ -f "/etc/redhat-release" ] || [ -f "/etc/debian_version" ]; then
    # 生产环境 - 宝塔面板
    # 如果检测到生产环境，但项目路径不是宝塔路径，则智能查找正确的网站目录
    if [[ "$PROJECT_PATH" != *"/www/wwwroot/"* ]]; then
        # 智能查找包含当前脚本的宝塔网站目录
        SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
        BT_SITE=""
        
        # 方法1：查找包含当前脚本的宝塔网站目录
        if [[ "$SCRIPT_DIR" == *"/www/wwwroot/"* ]]; then
            # 从脚本路径中提取网站根目录
            BT_SITE=$(echo "$SCRIPT_DIR" | sed -n 's|\(/www/wwwroot/[^/]*\).*|\1|p')
        fi
        
        # 方法2：如果方法1失败，查找包含必要文件的宝塔网站目录
        if [ -z "$BT_SITE" ]; then
            for site_dir in /www/wwwroot/*; do
                if [ -d "$site_dir" ] && [ -f "$site_dir/process_email_queue.php" ]; then
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
    # 自动检测PHP路径
    if command -v php >/dev/null 2>&1; then
        PHP_PATH=$(which php)
    elif [ -f "/usr/bin/php" ]; then
        PHP_PATH="/usr/bin/php"
    else
        echo "错误：无法找到PHP可执行文件"
        exit 1
    fi
    LOG_FILE="$PROJECT_PATH/Application/Runtime/Logs/email_queue_cleanup.log"
    ERROR_LOG="$PROJECT_PATH/Application/Runtime/Logs/email_queue_cleanup_error.log"
    echo "检测到生产环境（宝塔面板），使用项目日志目录"
else
    # 本地开发环境
    # 自动检测PHP路径
    if command -v php >/dev/null 2>&1; then
        PHP_PATH=$(which php)
    elif command -v php3 >/dev/null 2>&1; then
        PHP_PATH=$(which php3)
    elif [ -f "/usr/bin/php" ]; then
        PHP_PATH="/usr/bin/php"
    elif [ -f "/opt/local/bin/php" ]; then
        PHP_PATH="/opt/local/bin/php"
    else
        echo "错误：无法找到PHP可执行文件"
        exit 1
    fi
    LOG_FILE="$PROJECT_PATH/Application/Runtime/Logs/email_queue_cleanup.log"
    ERROR_LOG="$PROJECT_PATH/Application/Runtime/Logs/email_queue_cleanup_error.log"
    echo "检测到本地环境，使用项目日志目录"
fi

echo "使用项目路径: $PROJECT_PATH"
echo "使用PHP路径: $PHP_PATH"
echo "使用日志文件: $LOG_FILE"

# 创建日志目录（如果不存在）
LOG_DIR=$(dirname "$LOG_FILE")
if [ ! -d "$LOG_DIR" ]; then
    mkdir -p "$LOG_DIR"
fi

# 函数：记录日志
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# 函数：记录错误日志
log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >> "$ERROR_LOG"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >> "$LOG_FILE"
}

# 检查项目目录是否存在
if [ ! -d "$PROJECT_PATH" ]; then
    log_error "项目目录不存在: $PROJECT_PATH"
    exit 1
fi

# 检查PHP文件是否存在
if [ ! -f "$PROJECT_PATH/process_email_queue.php" ]; then
    log_error "邮件队列处理文件不存在: $PROJECT_PATH/process_email_queue.php"
    exit 1
fi

# 检查PHP可执行文件是否存在
if [ ! -f "$PHP_PATH" ]; then
    log_error "PHP可执行文件不存在: $PHP_PATH"
    exit 1
fi

# 切换到项目目录
cd "$PROJECT_PATH" || {
    log_error "无法切换到项目目录: $PROJECT_PATH"
    exit 1
}

# 加载环境变量（如果.env文件存在）
if [ -f ".env" ]; then
    log_message "加载.env文件中的环境变量"
    # 读取.env文件并设置环境变量
    while IFS='=' read -r key value; do
        # 跳过注释行和空行
        if [[ ! "$key" =~ ^#.*$ ]] && [[ -n "$key" ]]; then
            # 去除值两端的引号和空格
            value=$(echo "$value" | sed 's/^["'\'']*//;s/["'\'']*$//')
            export "$key=$value"
            log_message "设置环境变量: $key"
        fi
    done < .env
else
    log_error ".env文件不存在，请确保配置文件存在"
    exit 1
fi

# 记录开始清理
log_message "开始清理邮件队列"

# 执行邮件队列清理
if "$PHP_PATH" -f process_email_queue.php clean 2>>"$ERROR_LOG"; then
    log_message "邮件队列清理完成"
    exit 0
else
    log_error "邮件队列清理失败，退出码: $?"
    exit 1
fi