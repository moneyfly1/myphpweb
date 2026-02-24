#!/bin/bash

set -e

# 设置字符编码，避免排序时的编码问题
export LC_ALL=C
export LANG=C

# 记录脚本开始时间
SCRIPT_START_TIME=$(date +%s)

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_with_level() {
    local level="$1"
    shift
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$level] $*"
}

# 清理函数
cleanup() {
    local exit_code=$?
    local end_time=$(date +%s)
    local duration=$((end_time - SCRIPT_START_TIME))
    log "脚本执行完成，耗时: ${duration}秒"
    if [ $exit_code -eq 0 ]; then
        log "✅ 脚本执行成功"
    else
        log "❌ 脚本执行失败，退出码: $exit_code"
    fi
    # 在开发环境下保留临时文件用于调试
    if [ "$ENVIRONMENT" = "development" ] || [ "$ENVIRONMENT" = "macos" ]; then
        log "开发环境，保留临时文件用于调试: $TMP_DIR"
    else
        if [ -d "$TMP_DIR" ]; then
            rm -rf "$TMP_DIR"
            log "清理临时文件完成"
        fi
    fi
    exit $exit_code
}

trap cleanup EXIT

# 环境自适应配置
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# 检测操作系统
detect_os() {
    case "$(uname -s)" in
        Linux*)     echo "linux";;
        Darwin*)    echo "macos";;
        CYGWIN*|MINGW*|MSYS*) echo "windows";;
        *)          echo "unknown";;
    esac
}

OS_TYPE=$(detect_os)
log "检测到操作系统: $OS_TYPE"

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
log "检测到网站根目录: $WEBSITE_ROOT"

# 环境检测逻辑
if [ "$OS_TYPE" = "windows" ]; then
    # Windows环境
    ENVIRONMENT="windows"
    TMP_DIR="./tmp/heduian_merge"
    TARGET_DIR="./shell"
    PYTHON_SCRIPT="./shell/get_all_nodes.py"
    SCRIPT_DIR="."
    log "检测到Windows环境"
elif [[ "$WEBSITE_ROOT" == *"/www/wwwroot/"* ]] || [ -d "/www/wwwroot" ] || [[ "$(pwd)" == *"/www/wwwroot/"* ]]; then
    # 生产环境 - 宝塔面板
    ENVIRONMENT="production"
    TMP_DIR="/tmp/heduian_merge"
    TARGET_DIR="$WEBSITE_ROOT/shell"
    PYTHON_SCRIPT="$WEBSITE_ROOT/shell/get_all_nodes.py"
    SCRIPT_DIR="$WEBSITE_ROOT"
    log "检测到生产环境（宝塔面板）"
    log "生产环境配置："
    log "  - 网站根目录: $WEBSITE_ROOT"
    log "  - 目标目录: $TARGET_DIR"
    log "  - Python脚本: $PYTHON_SCRIPT"
elif [ "$OS_TYPE" = "linux" ]; then
    # 普通Linux环境
    ENVIRONMENT="linux"
    TMP_DIR="/tmp/heduian_merge"
    TARGET_DIR="$WEBSITE_ROOT/shell"
    PYTHON_SCRIPT="$WEBSITE_ROOT/shell/get_all_nodes.py"
    SCRIPT_DIR="$WEBSITE_ROOT"
    log "检测到普通Linux环境"
    log "Linux环境配置："
    log "  - 网站根目录: $WEBSITE_ROOT"
    log "  - 目标目录: $TARGET_DIR"
    log "  - Python脚本: $PYTHON_SCRIPT"
elif [ "$OS_TYPE" = "macos" ]; then
    # macOS环境
    ENVIRONMENT="macos"
    TMP_DIR="./tmp/heduian_merge"
    TARGET_DIR="./shell"
    PYTHON_SCRIPT="./shell/get_all_nodes.py"
    log "检测到macOS环境"
    log "macOS环境配置："
    log "  - 网站根目录: $WEBSITE_ROOT"
    log "  - 目标目录: $TARGET_DIR"
    log "  - Python脚本: $PYTHON_SCRIPT"
else
    # 默认开发环境
    ENVIRONMENT="development"
    TMP_DIR="./tmp/heduian_merge"
    TARGET_DIR="./shell"
    PYTHON_SCRIPT="./shell/get_all_nodes.py"
    log "检测到开发环境"
    log "开发环境配置："
    log "  - 网站根目录: $WEBSITE_ROOT"
    log "  - 目标目录: $TARGET_DIR"
    log "  - Python脚本: $PYTHON_SCRIPT"
fi

TARGET_FILE="$TARGET_DIR/heduian.txt"

# 创建必要的目录
mkdir -p "$TMP_DIR"
mkdir -p "$TARGET_DIR"

log "当前环境: $ENVIRONMENT"
log "临时目录: $TMP_DIR"
log "目标目录: $TARGET_DIR"
log "目标文件: $TARGET_FILE"

# 检查工具函数
check_tools() {
    log "检查必要工具..."
    
    # 检查curl
    if ! command -v curl &> /dev/null; then
        if [ "$OS_TYPE" = "windows" ]; then
            log "警告: Windows环境下curl可能不可用，尝试使用PowerShell的Invoke-WebRequest"
        else
            log "错误: curl 未安装"
            log "Linux/macOS安装命令: sudo apt-get install curl 或 sudo yum install curl"
            exit 1
        fi
    else
        log "✅ curl 可用"
    fi
    
    # 检查python3
    if [ "$OS_TYPE" = "windows" ]; then
        # Windows环境下优先使用python
        if command -v python &> /dev/null; then
            PYTHON_CMD="python"
            log "✅ python 可用 (Windows环境)"
        elif command -v python3 &> /dev/null; then
            PYTHON_CMD="python3"
            log "✅ python3 可用 (Windows环境)"
        else
            log "错误: python/python3 未安装"
            log "Windows安装命令: 请从 https://www.python.org/downloads/ 下载安装"
            exit 1
        fi
    else
        # Linux/macOS环境下优先使用python3
        if command -v python3 &> /dev/null; then
            PYTHON_CMD="python3"
            log "✅ python3 可用"
        elif command -v python &> /dev/null; then
            log "⚠️  python3 不可用，但找到 python，将使用 python 替代"
            PYTHON_CMD="python"
        else
            log "错误: python3/python 未安装"
            log "Linux/macOS安装命令: sudo apt-get install python3 或 sudo yum install python3"
            exit 1
        fi
    fi
    
    # 检查base64
    if ! command -v base64 &> /dev/null; then
        if [ "$OS_TYPE" = "windows" ]; then
            log "警告: Windows环境下base64可能不可用，将使用Python替代"
        else
            log "错误: base64 未安装"
            log "Linux/macOS安装命令: sudo apt-get install coreutils 或 sudo yum install coreutils"
            exit 1
        fi
    else
        log "✅ base64 可用"
    fi
    
    log "✅ 工具检查完成"
}

# 验证配置文件
validate_config() {
    log "验证配置文件..."
    
    # 检查Python脚本
    if [ ! -f "$PYTHON_SCRIPT" ]; then
        log "Python脚本不存在，尝试查找..."
        
        # 查找get_all_nodes.py脚本
        local possible_paths=(
            "./shell/get_all_nodes.py"
            "$SCRIPT_DIR/get_all_nodes.py"
            "$(dirname "$WEBSITE_ROOT")/shell/get_all_nodes.py"
        )
        
        for path in "${possible_paths[@]}"; do
            if [ -f "$path" ]; then
                log "找到Python脚本: $path"
                PYTHON_SCRIPT="$path"
                break
            fi
        done
    fi
    
    if [ ! -f "$PYTHON_SCRIPT" ]; then
        log "错误: 无法找到get_all_nodes.py脚本"
        log "请确保脚本存在于以下位置之一："
        log "- $PYTHON_SCRIPT"
        log "- $SCRIPT_DIR/get_all_nodes.py"
        log "- ./shell/get_all_nodes.py"
        return 1
    fi
    
    log "使用Python脚本: $PYTHON_SCRIPT"
    log "配置文件验证完成"
    return 0
}

# 处理heduian节点
process_heduian_nodes() {
    log "开始处理heduian节点..."
    
    # 执行Python脚本获取heduian节点并直接输出到临时文件
    $PYTHON_CMD -c "
import sys
import os
sys.path.append('$TARGET_DIR')
try:
    import get_all_nodes
    nodes = get_all_nodes.get_all_nodes()
    if nodes:
        with open('$TMP_DIR/heduian_nodes.txt', 'w', encoding='utf-8') as f:
            for node in nodes:
                f.write(node + '\n')
        print(f'成功生成 {len(nodes)} 个heduian节点')
        sys.exit(0)
    else:
        print('没有获取到heduian节点')
        sys.exit(1)
except Exception as e:
    print(f'处理heduian节点时出错: {e}')
    import traceback
    traceback.print_exc()
    sys.exit(1)
"
    
    if [ $? -eq 0 ]; then
        log "✅ heduian节点处理完成"
    else
        log "❌ heduian节点处理失败"
        # 创建空的heduian节点文件，避免后续处理出错
        touch "$TMP_DIR/heduian_nodes.txt"
        return 1
    fi
}

# 生成最终base64编码文件
generate_final_file() {
    log "生成最终base64编码文件..."
    
    # 清理节点文件，只保留V2RayN支持的节点URL
    log "清理节点文件，过滤非节点行..."
    grep -E "^(vmess://|vless://|ss://|ssr://|trojan://|hysteria2://|hy2://)" "$TMP_DIR/heduian_nodes.txt" > "$TMP_DIR/clean_nodes.txt" 2>/dev/null || {
        # 如果grep失败，直接复制文件
        cp "$TMP_DIR/heduian_nodes.txt" "$TMP_DIR/clean_nodes.txt"
    }
    
    clean_count=$(wc -l < "$TMP_DIR/clean_nodes.txt" 2>/dev/null || echo "0")
    log "清理后有效节点数量: $clean_count"
    
    if [ "$clean_count" -eq 0 ]; then
        log "❌ 没有有效节点，无法生成订阅文件"
        return 1
    fi
    
    # 根据环境选择base64编码方式
    if [ "$OS_TYPE" = "windows" ] && ! command -v base64 &> /dev/null; then
        # Windows环境下使用Python进行base64编码
        log "使用Python进行base64编码..."
        $PYTHON_CMD -c "
import base64
import sys
import re

try:
    with open('$TMP_DIR/clean_nodes.txt', 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    # 过滤出有效的节点URL
    valid_nodes = []
    for line in lines:
        line = line.strip()
        if re.match(r'^(vmess://|vless://|ss://|ssr://|hysteria2://|hy2://|anytls://|trojan://|wireguard://)', line):
            valid_nodes.append(line)
    
    # 每个节点一行，用换行符连接
    content = '\n'.join(valid_nodes)
    encoded = base64.b64encode(content.encode('utf-8')).decode('utf-8')
    
    # 移除base64编码中的换行符，确保输出为单行
    encoded = encoded.replace('\n', '').replace('\r', '')
    
    with open('$TARGET_FILE', 'w', encoding='utf-8') as f:
        f.write(encoded)
    
    print(f'编码成功，文件大小: {len(encoded)} 字节')
    print(f'有效节点数量: {len(valid_nodes)} 个')
    sys.exit(0)
except Exception as e:
    print(f'编码失败: {e}')
    sys.exit(1)
"
        PYTHON_EXIT_CODE=$?
        if [ $PYTHON_EXIT_CODE -ne 0 ]; then
            log "❌ Python base64编码失败"
            return 1
        fi
    else
        # 使用系统命令进行base64编码
        # 先进行base64编码，然后移除换行符确保单行显示
        base64 < "$TMP_DIR/clean_nodes.txt" | tr -d '\n\r' > "$TARGET_FILE" 2>/dev/null || {
            log "❌ base64编码失败"
            return 1
        }
    fi
    
    if [ $? -eq 0 ] && [ -s "$TARGET_FILE" ]; then
        FILE_SIZE=$(wc -c < "$TARGET_FILE" 2>/dev/null || echo "0")
        NODE_COUNT=$(wc -l < "$TMP_DIR/clean_nodes.txt" 2>/dev/null || echo "0")
        log "✅ 最终文件生成成功:"
        log "  - 文件路径: $TARGET_FILE"
        log "  - 文件大小: $FILE_SIZE 字节"
        log "  - 节点数量: $NODE_COUNT 个"
        log "  - 格式: 单行base64编码（符合V2RayN要求）"
        
        # 验证heduian文件格式
        log "验证heduian文件格式..."
        if command -v python3 &> /dev/null; then
            python3 -c "
import base64
import sys
try:
    with open('$TARGET_FILE', 'rb') as f:
        content = f.read()
    decoded = base64.b64decode(content)
    decoded_text = decoded.decode('utf-8', errors='ignore')
    lines = [line.strip() for line in decoded_text.split('\n') if line.strip()]
    valid_nodes = [line for line in lines if any(line.startswith(prefix) for prefix in ['ss://', 'ssr://', 'vmess://', 'vless://', 'trojan://', 'hysteria2://', 'hy2://', 'tuic://', 'hysteria://'])]
    print(f'解码成功，包含 {len(valid_nodes)} 个有效节点')
    if valid_nodes:
        print('前3个节点预览:')
        for i, node in enumerate(valid_nodes[:3]):
            print(f'  {i+1}: {node[:50]}...')
    else:
        print('警告: 没有找到有效节点')
        sys.exit(1)
except Exception as e:
    print(f'验证失败: {e}')
    sys.exit(1)
" 2>/dev/null && log "✅ heduian文件格式验证通过" || log "⚠️  heduian文件格式验证失败"
        else
            log "跳过格式验证（python3不可用）"
        fi
        
        # 预览前3个节点
        log "前3个节点预览:"
        head -n 3 "$TMP_DIR/clean_nodes.txt" | while IFS= read -r line; do
            log "  $line"
        done
    else
        log "❌ 最终文件生成失败"
        return 1
    fi
}

# 设置文件权限
set_file_permissions() {
    log "设置文件权限..."
    
    # 设置文件权限（仅在Unix-like系统上）
    if [ "$OS_TYPE" != "windows" ]; then
        if command -v chmod >/dev/null 2>&1; then
            chmod 644 "$TARGET_FILE"
            log "✅ 文件权限设置完成"
        else
            log "⚠️  chmod命令不可用，跳过权限设置"
        fi
        
        # 设置文件所有者（仅在生产环境）
        if [ "$ENVIRONMENT" = "production" ]; then
            if command -v chown >/dev/null 2>&1; then
                chown www:www "$TARGET_FILE" 2>/dev/null || log "警告: 无法设置文件所有者"
            else
                log "⚠️  chown命令不可用，跳过所有者设置"
            fi
        else
            log "非生产环境，跳过文件所有者设置"
        fi
    else
        log "Windows环境，跳过Unix权限设置"
    fi
}

# 主执行流程
main() {
    log "=== 开始执行heduian节点采集脚本 ==="
    
    # 检查工具
    check_tools
    
    # 验证配置
    validate_config
    
    # 处理heduian节点
    log "=== 第一步：处理heduian节点 ==="
    process_heduian_nodes
    
    # 生成最终文件
    log "=== 第二步：生成最终文件 ==="
    generate_final_file
    
    # 设置文件权限
    set_file_permissions
    
    log "=== heduian节点采集脚本执行完成 ==="
    log "最终文件: $TARGET_FILE"
}

# 执行主函数
main
