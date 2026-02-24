#!/bin/bash
# set -e  # 注释掉，避免脚本中断

# 记录脚本开始时间
SCRIPT_START_TIME=$(date +%s)

# 检测运行环境
is_production() {
    if [ -d "/www/wwwroot" ] || [ -d "/var/www/html" ] || [ "$(whoami)" = "root" ]; then
        return 0  # true
    else
        return 1  # false
    fi
}

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_with_level() {
    local level="$1"
    shift
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$level] $*"
}

# 自动检测网站根目录
detect_website_root() {
    local detected_root=""
    
    # 只检测当前目录，不检测其他网站目录
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

# 脚本结束时的清理函数
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
    
    # 清理临时文件
    if [ $exit_code -eq 0 ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
        log "清理临时文件完成"
    elif [ -d "$TMP_DIR" ]; then
        log "脚本失败，保留临时文件供调试: $TMP_DIR"
    fi
    
    exit $exit_code
}

# 设置退出时的清理函数
trap cleanup EXIT

# 自动检测网站根目录
WEBSITE_ROOT=$(detect_website_root)
log "使用网站根目录: $WEBSITE_ROOT"

# 环境配置 - 使用自动检测的路径
# 检查是否为生产环境（宝塔面板环境）
if [[ "$WEBSITE_ROOT" == *"/www/wwwroot/"* ]] || [ -d "/www/wwwroot" ] || [[ "$(pwd)" == *"/www/wwwroot/"* ]]; then
    # 生产环境 - 宝塔面板
    log "检测到生产环境（宝塔面板）"
    TMP_DIR="/tmp/clash_merge"
    TARGET_DIR="$WEBSITE_ROOT/Upload/true"
    PYTHON_SCRIPT="$WEBSITE_ROOT/shell/gen_clash_yaml.py"
    SCRIPT_DIR="$WEBSITE_ROOT/shell"
    log "生产环境配置："
    log "  - 网站根目录: $WEBSITE_ROOT"
    log "  - 目标目录: $TARGET_DIR"
    log "  - Python脚本: $PYTHON_SCRIPT"
else
    # 本地开发环境 - 使用正确的目录结构
    log "检测到本地开发环境"
    TMP_DIR="./tmp/clash_merge"
    TARGET_DIR="./Upload/true"
    PYTHON_SCRIPT="./shell/gen_clash_yaml.py"
    SCRIPT_DIR="./shell"
    log "本地环境配置："
    log "  - 网站根目录: $WEBSITE_ROOT"
    log "  - 目标目录: $TARGET_DIR"
    log "  - Python脚本: $PYTHON_SCRIPT"
fi

# 创建必要的目录
mkdir -p "$TMP_DIR"
mkdir -p "$TARGET_DIR"
log "创建临时目录: $TMP_DIR"
log "创建目标目录: $TARGET_DIR"

# 验证目录创建是否成功
if [ ! -d "$TARGET_DIR" ]; then
    log "错误: 无法创建目标目录: $TARGET_DIR"
    log "当前用户: $(whoami)"
    log "当前目录: $(pwd)"
    log "目标目录权限: $(ls -ld "$(dirname "$TARGET_DIR")" 2>/dev/null || echo '无法获取权限信息')"
    exit 1
fi

log "目标目录创建成功: $TARGET_DIR"
log "目标目录权限: $(ls -ld "$TARGET_DIR")"

# 检查并修复目标目录权限
log "检查目标目录权限..."
if [ ! -w "$TARGET_DIR" ]; then
    log "警告: 目标目录无写入权限，尝试修复..."
    # 尝试修改权限
    chmod 755 "$TARGET_DIR" 2>/dev/null || log "无法修改目录权限"
    # 尝试修改所有者
    chown $(whoami):$(whoami) "$TARGET_DIR" 2>/dev/null || log "无法修改目录所有者"
    
    # 最终检查
    if [ ! -w "$TARGET_DIR" ]; then
        log "警告: 目标目录权限问题，但继续执行..."
    fi
fi

# 最终权限检查
if [ -w "$TARGET_DIR" ]; then
    log "✅ 目标目录权限正常，可以写入"
else
    log "❌ 目标目录权限问题，尝试继续执行..."
fi

TARGET_FILE="$TARGET_DIR/xr"
log "xr文件将生成到: $TARGET_FILE"
log "clash.yaml文件将生成到: $TARGET_DIR/clash.yaml"

# 检查现有文件权限
if [ -f "$TARGET_FILE" ]; then
    log "现有xr文件权限: $(ls -l "$TARGET_FILE" 2>/dev/null || echo '无法获取权限')"
fi
if [ -f "$TARGET_DIR/clash.yaml" ]; then
    log "现有clash.yaml文件权限: $(ls -l "$TARGET_DIR/clash.yaml" 2>/dev/null || echo '无法获取权限')"
fi

# 定义下载文件的函数
download_file() {
    local url="$1"
    local output_file="$2"
    local max_retries=3
    local retry_count=0

    while [ "$retry_count" -lt "$max_retries" ]; do 
        if curl -s -m 30 -o "$output_file" "$url"; then
            if [ -s "$output_file" ] && [ "$(stat -c%s "$output_file" 2>/dev/null || wc -c < "$output_file")" -gt 10 ]; then
                log "下载成功: $url"
                return 0
            else
                log "警告: 文件内容为空或太小: $url"
            fi
        else
            log "下载失败: $url"
        fi
        retry_count=$((retry_count + 1))
        if [ "$retry_count" -lt "$max_retries" ]; then
            sleep 2
        fi
    done
    log "错误: 下载失败，已达到最大重试次数: $url"
    return 1
}

declare -a URLS=(
    "https://dy.moneyfly.club/Upload/true/dingyuetou.txt" 
    "https://dy.moneyfly.club/shell/tianmao64.txt"    
    "https://dy.moneyfly.club/Upload/true/work"
    "https://raw.githubusercontent.com/flyingparanoia/RadioDrama/81fcfd8647a4ec3ecbea896e6a5e32ec0d40ab65/flclashfull"    
    "https://raw.githubusercontent.com/jgchengxin/ssr_subscrible_tool/refs/heads/master/node.txt"
#    "https://jjsubmarines.com/members/getsub.php?service=731341&id=35900538-d931-4000-88ae-4751e9784470"
#    "https://raw.githubusercontent.com/Use4Free/VMesslinks/refs/heads/main/links/vmess"
#    "https://raw.githubusercontent.com/Huibq/TrojanLinks/refs/heads/master/links/ssr"
#    "https://raw.githubusercontent.com/Huibq/TrojanLinks/refs/heads/master/links/vmess"
#    "https://raw.githubusercontent.com/mfbpn/TrojanLinks/refs/heads/master/links/skr"
#    "https://raw.githubusercontent.com/moneyfly004/v2rayspeedtest/refs/heads/master/v2ray.txt"
#    "https://raw.githubusercontent.com/mfbpn/TrojanLinks/refs/heads/master/links/ssr"
)

# 检查并安装系统依赖
check_and_install_dependencies() {
    log "检查系统依赖..."
    
    # 检测系统类型
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
    elif [ -f /etc/redhat-release ]; then
        OS="CentOS"
    else
        OS="Unknown"
    fi
    
    log "检测到系统: $OS"
    
    # 检查Python3
    if ! command -v python3 &> /dev/null; then
        log "Python3 未安装，正在安装..."
        if [[ "$OS" == *"Ubuntu"* ]] || [[ "$OS" == *"Debian"* ]]; then
            apt-get update -y
            apt-get install -y python3 python3-pip
        elif [[ "$OS" == *"CentOS"* ]] || [[ "$OS" == *"Red Hat"* ]]; then
            yum update -y
            yum install -y python3 python3-pip
        else
            log "错误: 无法自动安装Python3，请手动安装"
            exit 1
        fi
    fi
    
    # 检查其他必需工具
    local required_tools=("curl" "base64" "jq" "grep" "awk" "sed")
    local missing_tools=()
    
    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            missing_tools+=("$tool")
        fi
    done
    
    if [ ${#missing_tools[@]} -gt 0 ]; then
        log "安装缺失的工具: ${missing_tools[*]}"
        if [[ "$OS" == *"Ubuntu"* ]] || [[ "$OS" == *"Debian"* ]]; then
            apt-get install -y "${missing_tools[@]}"
        elif [[ "$OS" == *"CentOS"* ]] || [[ "$OS" == *"Red Hat"* ]]; then
            yum install -y "${missing_tools[@]}"
        fi
    fi
    
    # 检查Python依赖
    if ! python3 -c "import yaml" 2>/dev/null; then
        log "安装Python依赖: PyYAML"
        pip3 install PyYAML
    fi
    
    log "所有依赖检查完成"
}

# 验证配置文件
validate_config() {
    log "验证配置文件..."
    
    # 检查Python脚本
    if [ ! -f "$PYTHON_SCRIPT" ]; then
        log "Python脚本不存在，尝试查找..."
        
        # 只检测当前目录及其子目录，不检测其他网站目录
        # 方法1: 在当前目录下查找
        if [ ! -f "$PYTHON_SCRIPT" ] && [ -f "./shell/gen_clash_yaml.py" ]; then
            log "找到Python脚本: ./shell/gen_clash_yaml.py"
            PYTHON_SCRIPT="./shell/gen_clash_yaml.py"
        fi
        
        # 方法2: 在脚本目录下查找
        if [ ! -f "$PYTHON_SCRIPT" ] && [ -f "$SCRIPT_DIR/gen_clash_yaml.py" ]; then
            log "找到Python脚本: $SCRIPT_DIR/gen_clash_yaml.py"
            PYTHON_SCRIPT="$SCRIPT_DIR/gen_clash_yaml.py"
        fi
        
        # 方法3: 在上级目录的shell文件夹中查找
        if [ ! -f "$PYTHON_SCRIPT" ]; then
            parent_shell_dir="$(dirname "$WEBSITE_ROOT")/shell"
            if [ -f "$parent_shell_dir/gen_clash_yaml.py" ]; then
                log "找到Python脚本: $parent_shell_dir/gen_clash_yaml.py"
                PYTHON_SCRIPT="$parent_shell_dir/gen_clash_yaml.py"
            fi
        fi
    fi
    
    if [ ! -f "$PYTHON_SCRIPT" ]; then
        log "错误: 无法找到gen_clash_yaml.py脚本"
        log "请确保脚本存在于以下位置之一："
        log "- $PYTHON_SCRIPT"
        log "- $SCRIPT_DIR/gen_clash_yaml.py"
        log "- ./shell/gen_clash_yaml.py"
        log "- 当前目录的shell文件夹"
        return 1
    fi
    
    log "使用Python脚本: $PYTHON_SCRIPT"
    
    # 检查节点重命名脚本
    RENAMER_SCRIPT="$SCRIPT_DIR/node_renamer.py"
    if [ ! -f "$RENAMER_SCRIPT" ]; then
        log "节点重命名脚本不存在，尝试查找..."
        
        # 只检测当前目录及其子目录
        # 方法1: 在当前目录下查找
        if [ ! -f "$RENAMER_SCRIPT" ] && [ -f "./shell/node_renamer.py" ]; then
            log "找到重命名脚本: ./shell/node_renamer.py"
            RENAMER_SCRIPT="./shell/node_renamer.py"
        fi
        
        # 方法2: 在脚本目录下查找
        if [ ! -f "$RENAMER_SCRIPT" ] && [ -f "$SCRIPT_DIR/node_renamer.py" ]; then
            log "找到重命名脚本: $SCRIPT_DIR/node_renamer.py"
            RENAMER_SCRIPT="$SCRIPT_DIR/node_renamer.py"
        fi
        
        # 方法3: 在上级目录的shell文件夹中查找
        if [ ! -f "$RENAMER_SCRIPT" ]; then
            parent_shell_dir="$(dirname "$WEBSITE_ROOT")/shell"
            if [ -f "$parent_shell_dir/node_renamer.py" ]; then
                log "找到重命名脚本: $parent_shell_dir/node_renamer.py"
                RENAMER_SCRIPT="$parent_shell_dir/node_renamer.py"
            fi
        fi
    fi
    
    if [ -f "$RENAMER_SCRIPT" ]; then
        log "使用节点重命名脚本: $RENAMER_SCRIPT"
    else
        log "警告: 未找到节点重命名脚本，将跳过重命名步骤"
    fi
    
    log "配置文件验证完成"
    return 0
}

check_and_install_dependencies
validate_config

log "开始下载源文件..."
successful_downloads=0
for i in "${!URLS[@]}"; do
    log "正在下载文件 $i: ${URLS[$i]}"
    if download_file "${URLS[$i]}" "$TMP_DIR/source_$i.txt"; then
        successful_downloads=$((successful_downloads + 1))
        file_size=$(stat -c%s "$TMP_DIR/source_$i.txt" 2>/dev/null || wc -c < "$TMP_DIR/source_$i.txt")
        log "文件 $i 下载成功，大小: $file_size 字节"

        # 检查每个文件中的链接数量（考虑base64编码）
        content_preview=$(head -c 100 "$TMP_DIR/source_$i.txt" | tr -d ' \t\n\r')
        if [[ "$content_preview" =~ ^[A-Za-z0-9+/=]+$ ]]; then
            log "文件 $i 可能是base64编码，解码后检查链接数量..."
            decoded_content=$(base64 -d "$TMP_DIR/source_$i.txt" 2>/dev/null || echo "")
            if [ ! -z "$decoded_content" ]; then
                link_count=$(echo "$decoded_content" | grep -cE '^(ss|ssr|vless|vmess|trojan)://' 2>/dev/null || echo "0")
                log "文件 $i 解码后包含 $link_count 个有效链接"

                if [ "$i" -ge 5 ] && [ "$link_count" -gt 0 ]; then
                    log "新增URL $i 解码后链接预览："
                    echo "$decoded_content" | grep -E '^(ss|ssr|vless|vmess|trojan)://' | head -3 | while read -r line; do
                        log "  $line"
                    done
                fi
            else
                link_count=0
                log "文件 $i base64解码失败，无法统计链接数量"
            fi
        else
            link_count=$(grep -cE '^(ss|ssr|vless|vmess|trojan)://' "$TMP_DIR/source_$i.txt" 2>/dev/null || echo "0")
            log "文件 $i 包含 $link_count 个有效链接"

            if [ "$i" -ge 5 ] && [ "$link_count" -gt 0 ]; then
                log "新增URL $i 链接预览："
                grep -E '^(ss|ssr|vless|vmess|trojan)://' "$TMP_DIR/source_$i.txt" | head -3 | while read -r line; do
                    log "  $line"
                done
            fi
        fi

        if [ "$i" -ge 5 ] && [ "$link_count" -eq 0 ]; then
            log "新增URL $i 内容预览（可能不是标准格式）："
            head -5 "$TMP_DIR/source_$i.txt" | while read -r line; do
                log "  $line"
            done
        fi
    else
        log "文件 $i 下载失败，跳过"
        touch "$TMP_DIR/source_$i.txt"
    fi
done

log "成功下载 $successful_downloads/${#URLS[@]} 个文件"

log "合并所有文件..."
cat "$TMP_DIR"/source_*.txt > "$TMP_DIR/all_content.txt"
total_size=$(stat -c%s "$TMP_DIR/all_content.txt" 2>/dev/null || wc -c < "$TMP_DIR/all_content.txt")
log "合并完成，总大小: $total_size 字节"

parse_vmess() {
    local vmess_link="$1"
    local decoded
    if ! command -v jq &> /dev/null; then
        log "警告: jq 未安装，跳过 vmess 解析"
        return 1
    fi
    decoded=$(echo "${vmess_link#vmess://}" | base64 -d 2>/dev/null)
    if [ $? -ne 0 ]; then
        log "警告: vmess base64 解码失败"
        return 1
    fi
    local server=$(echo "$decoded" | jq -r '.add' 2>/dev/null)
    local port=$(echo "$decoded" | jq -r '.port' 2>/dev/null)
    local uuid=$(echo "$decoded" | jq -r '.id' 2>/dev/null)
    if [ "$server" = "null" ] || [ "$port" = "null" ] || [ "$uuid" = "null" ]; then
        log "警告: vmess 解析失败，返回空值"
        return 1
    fi
    echo "$server:$port:$uuid"
}

parse_ss() {
    local ss_link="$1"
    local clean_link=$(echo "${ss_link#ss://}" | cut -d'#' -f1)
    local main_part=$(echo "$clean_link" | cut -d'?' -f1)

    if [[ "$main_part" == *"@"* ]]; then
        local userinfo=$(echo "$main_part" | cut -d'@' -f1)
        local serverinfo=$(echo "$main_part" | cut -d'@' -f2)
        local password=$(echo "$userinfo" | cut -d':' -f2-)
        local server=$(echo "$serverinfo" | cut -d':' -f1)
        local port=$(echo "$serverinfo" | cut -d':' -f2)
        echo "$server:$port:$password"
    else
        local decoded=$(echo "$main_part" | base64 -d 2>/dev/null)
        if [ $? -eq 0 ]; then
            local server_port_pass=$(echo "$decoded" | cut -d'@' -f2 2>/dev/null)
            if [ ! -z "$server_port_pass" ]; then
                local server=$(echo "$server_port_pass" | cut -d':' -f1)
                local port=$(echo "$server_port_pass" | cut -d':' -f2)
                local password=$(echo "$server_port_pass" | cut -d':' -f3)
                echo "$server:$port:$password"
            fi
        fi
    fi
    return 1
}

# 过滤函数，过滤节点名称关键词（适用于所有协议）
filter_links() {
    local keyword_pattern='官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain'
    awk -v kw="$keyword_pattern" '
    function url_decode(str,    cmd, decoded) {
        cmd="python3 -c '\''import sys,urllib.parse; print(urllib.parse.unquote(sys.argv[1]))'\'' \"" str "\""
        cmd | getline decoded
        close(cmd)
        return decoded
    }
    function fix_base64(str) {
        gsub("-", "+", str)
        gsub("_", "/", str)
        while (length(str) % 4 != 0) str = str "="
        return str
    }
    function base64_decode(str,    cmd, decoded) {
        str = fix_base64(str)
        cmd="printf \"%s\" \"" str "\" | base64 -d 2>/dev/null"
        cmd | getline decoded
        close(cmd)
        return decoded
    }
    function json_unicode_decode(str,    cmd, decoded) {
        cmd="python3 -c '\''import sys,json; print(json.loads(\"[\\\"\"+sys.argv[1]+\"\\\"\"]\")[0])'\'' \"" str "\""
        cmd | getline decoded
        close(cmd)
        return decoded
    }
    {
        line=$0
        if ($0 ~ /^vmess:\/\//) {
            b64=substr($0,8)
            json=base64_decode(b64)
            if (match(json, /\"ps\"[ ]*:[ ]*\"/)) {
                start = RSTART + RLENGTH
                if (match(substr(json, start), /[^\"]*\"/)) {
                    name = substr(json, start, RLENGTH-1)
                } else {
                    name = ""
                }
            } else {
                name = ""
            }
            if (name != "") name = json_unicode_decode(name)
            if (name == "" || name ~ kw) next
            print line
        }
        else if ($0 ~ /^ssr:\/\//) {
            b64=substr($0,7)
            decoded=base64_decode(b64)
            remarks=""
            if (match(decoded, /remarks=/)) {
                start = RSTART + RLENGTH - 1
                if (match(substr(decoded, start), /[^&;]*/)) {
                    remarks = url_decode(substr(decoded, start, RLENGTH))
                } else {
                    remarks = ""
                }
            }
            if (remarks == "" || remarks ~ kw) next
            print line
        }
        else if ($0 ~ /^(hysteria2|hy2|tuic):\/\//) {
            # 对于hysteria2和tuic协议，节点名称通常在#后面
            split(line, parts, "#")
            name=""
            if (length(parts) > 1) {
                name = url_decode(parts[2])
            }
            if (name == "" || name ~ kw) next
            print line
        }
        else {
            # 处理其他协议（ss, vless, trojan等）
            split(line, parts, "#")
            name=""
            if (length(parts) > 1) {
                name = url_decode(parts[2])
            }
            if (name == "" || name ~ kw) next
            print line
        }
    }'
}

# 重构链接函数，去除后缀、去掉空格等
reconstruct_links() {
    # 检测系统环境
    local os_type=""
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        os_type="linux"
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        os_type="macos"
    elif [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "cygwin" ]]; then
        os_type="windows"
    else
        os_type="unknown"
    fi
    
    # 根据系统选择合适的命令
    local sed_cmd="sed"
    local base64_cmd="base64"
    local python_cmd="python3"
    
    if [[ "$os_type" == "macos" ]]; then
        # macOS 需要特殊处理
        sed_cmd="gsed" 2>/dev/null || sed_cmd="sed"
    fi
    
    # 设置环境变量以确保正确的字符编码
    export LC_ALL=C.UTF-8
    export LANG=C.UTF-8
    
    # 使用Python进行重构处理，避免awk的编码问题
    python3 -c "
import sys
import re
import base64
import json
import urllib.parse

def clean_name(name):
    if not name:
        return name
    
    # 更强的机场后缀清理，支持多种常见无用后缀
    patterns = [
        r'[\\s]*[-_][\\s]*(官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)[\\s]*$',
        r'[\\s]*[-_][\\s]*[0-9]+[\\s]*$',
        r'[\\s]*[-_][\\s]*[A-Za-z]+[\\s]*$',
        # 直接以这些词结尾也去除
        r'(官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)$',
        # 处理没有空格的情况，如"-迅云加速"
        r'[-_](官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)$'
    ]
    
    for pattern in patterns:
        name = re.sub(pattern, '', name)
    
    # 去掉所有空格
    name = re.sub(r'[\\s]+', '', name)
    name = name.strip()
    
    return name

def reconstruct_vmess(line):
    try:
        b64 = line[8:]  # 去掉 'vmess://'
        json_str = base64.b64decode(b64).decode('utf-8')
        data = json.loads(json_str)
        
        if 'ps' in data:
            original_name = data['ps']
            clean_name_val = clean_name(original_name)
            
            if clean_name_val != original_name:
                data['ps'] = clean_name_val
                new_json = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
                new_b64 = base64.b64encode(new_json.encode('utf-8')).decode('utf-8')
                return f'vmess://{new_b64}'
    except:
        pass
    return line

def reconstruct_ssr(line):
    try:
        b64 = line[7:]  # 去掉 'ssr://'
        decoded = base64.b64decode(b64).decode('utf-8')
        
        # 解析SSR链接
        if 'remarks=' in decoded:
            parts = decoded.split('&')
            for i, part in enumerate(parts):
                if part.startswith('remarks='):
                    remarks = urllib.parse.unquote(part[8:])
                    clean_remarks = clean_name(remarks)
                    
                    if clean_remarks != remarks:
                        parts[i] = f'remarks={urllib.parse.quote(clean_remarks)}'
                        new_decoded = '&'.join(parts)
                        new_b64 = base64.b64encode(new_decoded.encode('utf-8')).decode('utf-8')
                        return f'ssr://{new_b64}'
    except:
        pass
    return line

def reconstruct_other(line):
    if '#' in line:
        parts = line.split('#', 1)
        if len(parts) == 2:
            name = urllib.parse.unquote(parts[1])
            clean_name_val = clean_name(name)
            
            if clean_name_val != name:
                clean_name_encoded = urllib.parse.quote(clean_name_val)
                return f'{parts[0]}#{clean_name_encoded}'
    return line

# 处理输入
for line in sys.stdin:
    line = line.strip()
    if not line:
        continue
        
    if line.startswith('vmess://'):
        print(reconstruct_vmess(line))
    elif line.startswith('ssr://'):
        print(reconstruct_ssr(line))
    elif any(line.startswith(prefix) for prefix in ['ss://', 'vless://', 'trojan://', 'hysteria2://', 'hy2://', 'tuic://']):
        print(reconstruct_other(line))
    else:
        print(line)
"
}

log "开始解析链接..."

# 处理前三个URL（不做过滤，直接提取所有链接，不参与重命名，保持顺序）
FIRST_LINKS_FILE="$TMP_DIR/first_links.txt"
> "$FIRST_LINKS_FILE"

for i in 0 1 2; do
    SOURCE_FILE="$TMP_DIR/source_$i.txt"
    if [ -f "$SOURCE_FILE" ]; then
        log "处理第 $((i+1)) 个URL（不做过滤，不参与重命名，保持顺序）..."
        file_size=$(stat -c%s "$SOURCE_FILE" 2>/dev/null || wc -c < "$SOURCE_FILE")
        content_preview=$(head -c 100 "$SOURCE_FILE" | tr -d ' \t\n\r')
        if [[ "$content_preview" =~ ^[A-Za-z0-9+/=]+$ ]]; then
            log "  第 $((i+1)) 个文件可能是base64编码，尝试解码..."
            decoded_content=$(base64 -d "$SOURCE_FILE" 2>/dev/null)
            if [ $? -eq 0 ] && [ ! -z "$decoded_content" ]; then
                log "  第 $((i+1)) 个文件 base64解码成功"
                echo "$decoded_content" | grep -E '^(ss|ssr|vless|vmess|trojan|hysteria2|hy2|tuic)://' | sed '/^\s*$/d' >> "$FIRST_LINKS_FILE"
            else
                log "  第 $((i+1)) 个文件 base64解码失败，按明文处理"
                grep -E '^(ss|ssr|vless|vmess|trojan|hysteria2|hy2|tuic)://' "$SOURCE_FILE" | sed '/^\s*$/d' >> "$FIRST_LINKS_FILE"
            fi
        else
            log "  第 $((i+1)) 个文件按明文处理"
            grep -E '^(ss|ssr|vless|vmess|trojan|hysteria2|hy2|tuic)://' "$SOURCE_FILE" | sed '/^\s*$/d' >> "$FIRST_LINKS_FILE"
        fi
    fi
done

first_count=$(wc -l < "$FIRST_LINKS_FILE")
log "前三个URL共提取了 $first_count 个节点（不参与重命名，保持顺序）"

# 其余URL内容过滤（跳过前三个）
> "$TMP_DIR/all_links_filtered.txt"
for i in "${!URLS[@]}"; do
    if [ "$i" -eq 0 ] || [ "$i" -eq 1 ] || [ "$i" -eq 2 ]; then
        continue
    fi
            if [ -f "$TMP_DIR/source_$i.txt" ]; then
            log "处理文件 $i..."
            file_size=$(stat -c%s "$TMP_DIR/source_$i.txt" 2>/dev/null || wc -c < "$TMP_DIR/source_$i.txt")
            content_preview=$(head -c 100 "$TMP_DIR/source_$i.txt" | tr -d ' \t\n\r')
            if [[ "$content_preview" =~ ^[A-Za-z0-9+/=]+$ ]]; then
                log "  文件 $i 可能是base64编码，尝试解码..."
                decoded_content=$(base64 -d "$TMP_DIR/source_$i.txt" 2>/dev/null || echo "")
                if [ ! -z "$decoded_content" ]; then
                    log "  文件 $i base64解码成功"
                    echo "$decoded_content" | grep -E '^(ss|ssr|vless|vmess|trojan|hysteria2|hy2|tuic)://' | sed '/^\s*$/d' | filter_links >> "$TMP_DIR/all_links_filtered.txt" || true
                else
                    log "  文件 $i base64解码失败，按明文处理"
                    grep -E '^(ss|ssr|vless|vmess|trojan|hysteria2|hy2|tuic)://' "$TMP_DIR/source_$i.txt" | sed '/^\s*$/d' | filter_links >> "$TMP_DIR/all_links_filtered.txt" || true
                fi
            else
                log "  文件 $i 按明文处理"
                grep -E '^(ss|ssr|vless|vmess|trojan|hysteria2|hy2|tuic)://' "$TMP_DIR/source_$i.txt" | sed '/^\s*$/d' | filter_links >> "$TMP_DIR/all_links_filtered.txt" || true
            fi
        fi
done

# 合并，先第一个URL，再其余去重后内容
awk '!seen[$0]++' "$TMP_DIR/all_links_filtered.txt" > "$TMP_DIR/all_links.txt"
cat "$FIRST_LINKS_FILE" "$TMP_DIR/all_links.txt" > "$TMP_DIR/final_links.txt"

# 对过滤后的链接进行重构处理（不包括第一个URL的节点）
log "开始重构链接（去除后缀、去掉空格等）..."
reconstruct_links < "$TMP_DIR/all_links.txt" > "$TMP_DIR/reconstructed_links.txt"

# 对重构后的链接再次去重
awk '!seen[$0]++' "$TMP_DIR/reconstructed_links.txt" > "$TMP_DIR/other_links.txt"

# 合并：第一个URL的节点 + 其他重命名后的节点
cat "$FIRST_LINKS_FILE" "$TMP_DIR/other_links.txt" > "$TMP_DIR/final_links.txt"

unique_count=$(wc -l < "$TMP_DIR/final_links.txt")
log "重构并去重后剩余 $unique_count 个链接"
log "最终内容预览："
head -10 "$TMP_DIR/final_links.txt"

# 节点重命名处理（只对非第一个URL的节点进行重命名）
log "开始节点重命名处理..."
RENAMER_SCRIPT="$SCRIPT_DIR/node_renamer.py"

# 如果重命名脚本不存在，尝试查找
if [ ! -f "$RENAMER_SCRIPT" ]; then
    log "节点重命名脚本不存在，尝试查找..."
    
    # 方法1: 在网站根目录下查找
    if [ -d "$WEBSITE_ROOT" ]; then
        found_renamer=$(find "$WEBSITE_ROOT" -name "node_renamer.py" -type f 2>/dev/null | head -1)
        if [ -n "$found_renamer" ]; then
            log "找到重命名脚本: $found_renamer"
            RENAMER_SCRIPT="$found_renamer"
        fi
    fi
    
    # 方法2: 在当前目录下查找
    if [ ! -f "$RENAMER_SCRIPT" ] && [ -f "./shell/node_renamer.py" ]; then
        log "找到重命名脚本: ./shell/node_renamer.py"
        RENAMER_SCRIPT="./shell/node_renamer.py"
    fi
fi

if [ -f "$RENAMER_SCRIPT" ]; then
    log "调用节点重命名脚本（只处理其他URL的节点）..."
    log "使用重命名脚本: $RENAMER_SCRIPT"
    python3 "$RENAMER_SCRIPT" "$TMP_DIR/other_links.txt" "$TMP_DIR/renamed_links.txt" > "$TMP_DIR/renamer_output.log" 2>&1
    renamer_exit_code=$?
    
    if [ $renamer_exit_code -eq 0 ] && [ -s "$TMP_DIR/renamed_links.txt" ]; then
        renamed_count=$(wc -l < "$TMP_DIR/renamed_links.txt")
        log "节点重命名成功，处理了 $renamed_count 个节点"
        log "重命名后的节点预览："
        head -10 "$TMP_DIR/renamed_links.txt"
        
        # 合并：第一个URL的原始节点 + 重命名后的其他节点
        cat "$FIRST_LINKS_FILE" "$TMP_DIR/renamed_links.txt" > "$TMP_DIR/final_links.txt"
        log "已合并第一个URL的原始节点和重命名后的其他节点"
    else
        log "节点重命名失败，使用原始节点列表"
        log "重命名脚本输出："
        cat "$TMP_DIR/renamer_output.log" 2>/dev/null || echo "无输出日志"
        log "继续使用原始节点列表进行后续处理"
    fi
else
    log "节点重命名脚本不存在，跳过重命名步骤"
fi

# 先用Python脚本验证和过滤节点，生成有效节点列表
log "调用Python脚本验证节点并生成有效节点列表..."

# 先删除旧文件，确保生成新文件（宝塔环境兼容）
log "准备删除旧文件..."

# 删除旧的xr文件
if [ -f "$TARGET_FILE" ]; then
    log "删除旧的xr文件: $TARGET_FILE"
    log "旧文件大小: $(stat -c%s "$TARGET_FILE" 2>/dev/null || wc -c < "$TARGET_FILE") 字节"
    rm -f "$TARGET_FILE" 2>/dev/null || {
        log "普通删除失败，但继续执行"
    }
    if [ ! -f "$TARGET_FILE" ]; then
        log "✅ 旧的xr文件删除成功"
    else
        log "⚠️  旧的xr文件可能未完全删除"
        # 强制删除
        rm -f "$TARGET_FILE" 2>/dev/null || log "强制删除也失败"
    fi
else
    log "旧的xr文件不存在，无需删除"
fi

# 删除旧的clash.yaml文件
if [ -f "$TARGET_DIR/clash.yaml" ]; then
    log "删除旧的clash.yaml文件: $TARGET_DIR/clash.yaml"
    log "旧文件大小: $(stat -c%s "$TARGET_DIR/clash.yaml" 2>/dev/null || wc -c < "$TARGET_DIR/clash.yaml") 字节"
    rm -f "$TARGET_DIR/clash.yaml" 2>/dev/null || {
        log "普通删除失败，但继续执行"
    }
    if [ ! -f "$TARGET_DIR/clash.yaml" ]; then
        log "✅ 旧的clash.yaml文件删除成功"
    else
        log "⚠️  旧的clash.yaml文件可能未完全删除"
        # 强制删除
        rm -f "$TARGET_DIR/clash.yaml" 2>/dev/null || log "强制删除也失败"
    fi
else
    log "旧的clash.yaml文件不存在，无需删除"
fi

if [ ! -f "$PYTHON_SCRIPT" ]; then
    log "警告: Python脚本不存在: $PYTHON_SCRIPT"
    log "使用备用方案：直接生成xr文件，跳过clash.yaml生成..."
    
    # 确保目标目录存在
    mkdir -p "$(dirname "$TARGET_FILE")"
    
    # 生成xr文件
    base64 -w 0 < "$TMP_DIR/final_links.txt" > "$TARGET_FILE" 2>/dev/null || {
        log "普通写入失败，但继续执行"
    }
    
    # 验证文件是否成功生成
    if [ -f "$TARGET_FILE" ] && [ -s "$TARGET_FILE" ]; then
        final_size=$(stat -c%s "$TARGET_FILE" 2>/dev/null || wc -c < "$TARGET_FILE")
        log "✅ 最终xr文件生成成功，大小: $final_size 字节"
        log "xr文件位置: $TARGET_FILE"
    else
        log "❌ 最终xr文件生成失败或为空"
    fi
    
    log "注意：由于缺少Python脚本，未生成clash.yaml文件"
    # 跳过后续的Python脚本调用
    SKIP_PYTHON=true
fi

if [ "$SKIP_PYTHON" != "true" ]; then
    # 确保目标目录存在
    mkdir -p "$TARGET_DIR"

    # 调用Python脚本生成clash.yaml
    log "调用Python脚本生成clash.yaml..."
    python3 "$PYTHON_SCRIPT" "$TMP_DIR/final_links.txt" "$TARGET_DIR/clash.yaml" > "$TMP_DIR/python_output.log" 2>&1
    python_exit_code=$?

    if [ $python_exit_code -eq 0 ] && [ -s "$TARGET_DIR/clash.yaml" ]; then
        log "clash.yaml 已成功生成: $TARGET_DIR/clash.yaml"
        # 从Python输出中提取处理的节点数量
        processed_count=$(grep "共处理" "$TMP_DIR/python_output.log" | grep -o '[0-9]\+' | tail -1)
        if [ -n "$processed_count" ]; then
            log "Python脚本成功处理 $processed_count 个有效节点"
        else
            log "无法获取处理的节点数量"
        fi
        
        # 直接使用重命名后的节点列表生成xr文件
        log "生成最终的xr文件..."
        log "源文件大小: $(stat -c%s "$TMP_DIR/final_links.txt" 2>/dev/null || wc -c < "$TMP_DIR/final_links.txt") 字节"
        
        # 确保目标目录存在
        mkdir -p "$(dirname "$TARGET_FILE")"
        
        # 生成xr文件
        base64 -w 0 < "$TMP_DIR/final_links.txt" > "$TARGET_FILE" 2>/dev/null || {
            log "普通写入失败，但继续执行"
        }
        
        # 验证文件是否成功生成
        if [ -f "$TARGET_FILE" ] && [ -s "$TARGET_FILE" ]; then
            final_size=$(stat -c%s "$TARGET_FILE" 2>/dev/null || wc -c < "$TARGET_FILE")
            log "✅ xr文件生成成功，大小: $final_size 字节"
        else
            log "❌ xr文件生成失败或为空"
            # 尝试重新生成
            base64 -w 0 < "$TMP_DIR/final_links.txt" > "$TARGET_FILE" 2>/dev/null || {
                log "重新生成也失败"
            }
        fi
        
        # 验证xr文件和clash.yaml节点数量是否一致
        xr_count=$(base64 -d < "$TARGET_FILE" | wc -l | tr -d ' \n')
        clash_count=$(grep -c '^- name:' "$TARGET_DIR/clash.yaml" 2>/dev/null | tr -d ' \n' || echo 0)
        log "验证结果: xr文件包含 $xr_count 个节点，clash.yaml包含 $clash_count 个节点"
        
        if [ "$xr_count" -eq "$clash_count" ]; then
            log "✅ 节点数量一致，处理成功！"
        else
            log "⚠️  节点数量不一致，但文件已生成"
        fi
    else
        log "clash.yaml 生成失败，请检查python脚本"
        log "Python脚本输出："
        cat "$TMP_DIR/python_output.log" 2>/dev/null || echo "无输出日志"
        
        # 如果Python失败，仍然生成xr文件
        log "使用原始节点列表生成xr文件..."
        base64 -w 0 < "$TMP_DIR/final_links.txt" > "$TARGET_FILE" 2>/dev/null || {
            log "普通写入失败，但继续执行"
        }
    fi
fi

# 设置文件权限
log "设置文件权限..."

# 设置xr文件权限
chmod 644 "$TARGET_FILE" 2>/dev/null || {
    log "普通权限设置失败，但继续执行"
}

# 尝试设置文件所有者
if is_production; then
    chown $(whoami):$(whoami) "$TARGET_FILE" 2>/dev/null || {
        log "无法设置为当前用户，但继续执行"
    }
else
    log "本地环境：跳过所有者设置"
fi

# 设置clash.yaml文件权限
if [ -f "$TARGET_DIR/clash.yaml" ]; then
    chmod 644 "$TARGET_DIR/clash.yaml" 2>/dev/null || {
        log "普通权限设置失败，但继续执行"
    }
    
    if is_production; then
        chown $(whoami):$(whoami) "$TARGET_DIR/clash.yaml" 2>/dev/null || {
            log "无法设置为当前用户，但继续执行"
        }
    fi
    
    # 更新文件时间戳
    touch "$TARGET_DIR/clash.yaml" 2>/dev/null || {
        log "普通时间戳更新失败，但继续执行"
    }
    log "clash.yaml文件权限设置完成，时间戳已更新"
fi

# 更新xr文件时间戳
touch "$TARGET_FILE" 2>/dev/null || {
    log "普通时间戳更新失败，但继续执行"
}
log "xr文件时间戳已更新"

# 等待2秒，确保文件可被外部访问
sleep 2

# 验证最终文件
if [ -s "$TARGET_FILE" ]; then
    final_size=$(stat -c%s "$TARGET_FILE" 2>/dev/null || wc -c < "$TARGET_FILE")
    log "✅ 最终xr文件生成成功，大小: $final_size 字节"
    log "xr文件位置: $TARGET_FILE"
else
    log "❌ 最终xr文件生成失败或为空"
fi

# 验证clash.yaml文件
if [ -s "$TARGET_DIR/clash.yaml" ]; then
    clash_size=$(stat -c%s "$TARGET_DIR/clash.yaml" 2>/dev/null || wc -c < "$TARGET_DIR/clash.yaml")
    log "✅ clash.yaml文件生成成功，大小: $clash_size 字节"
    log "clash.yaml文件位置: $TARGET_DIR/clash.yaml"
else
    log "⚠️  clash.yaml文件不存在或为空"
fi

# 显示目标目录的最终内容
log "目标目录最终内容:"
ls -la "$TARGET_DIR" 2>/dev/null || log "无法列出目标目录内容"

log "脚本执行完成！"
