#!/bin/bash
# Cloudflare Workers 订阅更新脚本 - 宝塔面板定时任务版本
# 功能：每天自动更新 vless 链接并替换服务器地址

# 脚本目录
SCRIPT_DIR="/www/wwwroot/dy.moneyfly.club/shell"
LOG_FILE="$SCRIPT_DIR/vless_update.log"

# 进入脚本目录
cd "$SCRIPT_DIR" || {
    echo "错误: 无法进入脚本目录 $SCRIPT_DIR" | tee -a "$LOG_FILE"
    exit 1
}

# 记录开始时间
echo "==========================================" | tee -a "$LOG_FILE"
echo "执行时间: $(date '+%Y-%m-%d %H:%M:%S')" | tee -a "$LOG_FILE"
echo "==========================================" | tee -a "$LOG_FILE"
echo "当前工作目录: $(pwd)" | tee -a "$LOG_FILE"
echo "Python3 路径: $(which python3)" | tee -a "$LOG_FILE"
echo "Python3 版本: $(python3 --version)" | tee -a "$LOG_FILE"

# 执行 Python 脚本，同时输出到日志文件和标准输出
echo "开始执行 Python 脚本..." | tee -a "$LOG_FILE"
python3 "$SCRIPT_DIR/update_vless_from_cloudflare.py" 2>&1 | tee -a "$LOG_FILE"

# 检查执行结果
EXIT_CODE=${PIPESTATUS[0]}
if [ $EXIT_CODE -eq 0 ]; then
    echo "==========================================" | tee -a "$LOG_FILE"
    echo "脚本执行成功，退出代码: $EXIT_CODE" | tee -a "$LOG_FILE"
    echo "执行完成时间: $(date '+%Y-%m-%d %H:%M:%S')" | tee -a "$LOG_FILE"
    
    # 检查输出文件是否存在
    OUTPUT_FILE="$SCRIPT_DIR/vless.txt"
    if [ -f "$OUTPUT_FILE" ]; then
        FILE_SIZE=$(stat -f%z "$OUTPUT_FILE" 2>/dev/null || stat -c%s "$OUTPUT_FILE" 2>/dev/null || echo "未知")
        LINE_COUNT=$(wc -l < "$OUTPUT_FILE" 2>/dev/null || echo "未知")
        echo "输出文件已创建: $OUTPUT_FILE" | tee -a "$LOG_FILE"
        echo "文件大小: $FILE_SIZE 字节" | tee -a "$LOG_FILE"
        echo "文件行数: $LINE_COUNT 行" | tee -a "$LOG_FILE"
    else
        echo "警告: 输出文件不存在: $OUTPUT_FILE" | tee -a "$LOG_FILE"
    fi
    
    exit 0
else
    echo "==========================================" | tee -a "$LOG_FILE"
    echo "脚本执行失败，退出代码: $EXIT_CODE" | tee -a "$LOG_FILE"
    echo "执行完成时间: $(date '+%Y-%m-%d %H:%M:%S')" | tee -a "$LOG_FILE"
    exit 1
fi

