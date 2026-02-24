#!/bin/bash
# 天猫VPN节点获取 - 宝塔面板定时任务脚本
# 适用于宝塔面板定时任务设置

# 自动检测环境：如果在VPS环境则使用VPS路径，否则使用当前脚本所在目录
if [ -d "/www/wwwroot/dy.moneyfly.club/shell" ]; then
    # VPS环境
    SCRIPT_DIR="/www/wwwroot/dy.moneyfly.club/shell"
    echo "检测到VPS环境，使用VPS目录: $SCRIPT_DIR"
else
    # 本地环境：使用脚本所在目录
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    echo "检测到本地环境，使用脚本目录: $SCRIPT_DIR"
fi

SCRIPT_FILE="$SCRIPT_DIR/tianmao.py"
LOG_FILE="$SCRIPT_DIR/tianmao.log"

# 设置web目录（用于HTTP访问）
if [ -d "/www/wwwroot/dy.moneyfly.club/shell" ]; then
    WEB_DIR="/www/wwwroot/dy.moneyfly.club/shell"
else
    WEB_DIR="$SCRIPT_DIR"
fi

# 检查并创建脚本目录
if [ ! -d "$SCRIPT_DIR" ]; then
    echo "创建脚本目录: $SCRIPT_DIR" | tee -a "$LOG_FILE" 2>/dev/null || echo "创建脚本目录: $SCRIPT_DIR"
    mkdir -p "$SCRIPT_DIR" || {
        echo "错误: 无法创建脚本目录: $SCRIPT_DIR" | tee -a "$LOG_FILE" 2>/dev/null || echo "错误: 无法创建脚本目录: $SCRIPT_DIR"
        exit 1
    }
fi

# 进入脚本目录
cd "$SCRIPT_DIR" || {
    echo "错误: 无法进入脚本目录: $SCRIPT_DIR" | tee -a "$LOG_FILE" 2>/dev/null || echo "错误: 无法进入脚本目录: $SCRIPT_DIR"
    exit 1
}

# 记录开始时间（使用tee确保即使日志文件不存在也能输出）
{
    echo "=========================================="
    echo "定时任务开始执行: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "当前工作目录: $(pwd)"
    echo "脚本目录: $SCRIPT_DIR"
} | tee -a "$LOG_FILE" 2>/dev/null || {
    echo "=========================================="
    echo "定时任务开始执行: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "当前工作目录: $(pwd)"
    echo "脚本目录: $SCRIPT_DIR"
}

# 检查Python环境
if ! command -v python3 &> /dev/null; then
    echo "错误: Python3 未安装" | tee -a "$LOG_FILE" 2>/dev/null || echo "错误: Python3 未安装"
    echo "尝试自动安装Python3..." | tee -a "$LOG_FILE" 2>/dev/null || echo "尝试自动安装Python3..."
    
    # 检测系统类型并安装Python3
    if command -v apt-get &> /dev/null; then
        # Debian/Ubuntu系统
        apt-get update > /dev/null 2>&1
        apt-get install -y python3 python3-pip > /dev/null 2>&1
    elif command -v yum &> /dev/null; then
        # CentOS/RHEL系统
        yum install -y python3 python3-pip > /dev/null 2>&1
    elif command -v dnf &> /dev/null; then
        # Fedora系统
        dnf install -y python3 python3-pip > /dev/null 2>&1
    else
        echo "错误: 无法自动安装Python3，请手动安装" | tee -a "$LOG_FILE" 2>/dev/null || echo "错误: 无法自动安装Python3，请手动安装"
        exit 1
    fi
    
    # 再次检查
    if ! command -v python3 &> /dev/null; then
        echo "错误: Python3 安装失败" | tee -a "$LOG_FILE" 2>/dev/null || echo "错误: Python3 安装失败"
        exit 1
    fi
    echo "Python3 安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "Python3 安装成功"
fi

# 检查并安装pip
if ! command -v pip3 &> /dev/null && ! python3 -m pip --version &> /dev/null; then
    echo "检测到pip未安装，尝试自动安装..." | tee -a "$LOG_FILE" 2>/dev/null || echo "检测到pip未安装，尝试自动安装..."
    
    # 检测系统类型并安装pip
    if command -v apt-get &> /dev/null; then
        # Debian/Ubuntu系统
        apt-get update > /dev/null 2>&1
        apt-get install -y python3-pip > /dev/null 2>&1
    elif command -v yum &> /dev/null; then
        # CentOS/RHEL系统
        yum install -y python3-pip > /dev/null 2>&1
    elif command -v dnf &> /dev/null; then
        # Fedora系统
        dnf install -y python3-pip > /dev/null 2>&1
    else
        # 尝试使用get-pip.py安装
        echo "尝试使用get-pip.py安装pip..." | tee -a "$LOG_FILE" 2>/dev/null || echo "尝试使用get-pip.py安装pip..."
        curl -s https://bootstrap.pypa.io/get-pip.py -o /tmp/get-pip.py 2>/dev/null || wget -q https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py 2>/dev/null
        if [ -f /tmp/get-pip.py ]; then
            python3 /tmp/get-pip.py > /dev/null 2>&1
            rm -f /tmp/get-pip.py
        fi
    fi
    
    # 再次检查
    if ! command -v pip3 &> /dev/null && ! python3 -m pip --version &> /dev/null; then
        echo "警告: pip安装可能失败，将尝试其他方式" | tee -a "$LOG_FILE" 2>/dev/null || echo "警告: pip安装可能失败，将尝试其他方式"
    else
        echo "pip 安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "pip 安装成功"
    fi
fi

# 检查脚本文件是否存在
if [ ! -f "$SCRIPT_FILE" ]; then
    echo "错误: 脚本文件不存在: $SCRIPT_FILE" | tee -a "$LOG_FILE" 2>/dev/null || echo "错误: 脚本文件不存在: $SCRIPT_FILE"
    exit 1
fi

# 检测是否为VPS环境（通过检查/www/wwwroot目录）
if [ -d "/www/wwwroot" ]; then
    # VPS环境：尝试多种安装方式
    echo "检测到VPS环境，尝试安装Python包..." | tee -a "$LOG_FILE" 2>/dev/null || echo "检测到VPS环境，尝试安装Python包..."
    
    # 首先尝试安装系统级依赖（如果需要）
    if command -v apt-get &> /dev/null; then
        # Debian/Ubuntu: 尝试安装python3-dev和build-essential（某些包需要编译）
        apt-get update > /dev/null 2>&1
        apt-get install -y python3-dev build-essential > /dev/null 2>&1
    elif command -v yum &> /dev/null; then
        # CentOS/RHEL
        yum install -y python3-devel gcc > /dev/null 2>&1
    elif command -v dnf &> /dev/null; then
        # Fedora
        dnf install -y python3-devel gcc > /dev/null 2>&1
    fi
    
    # 方法1: 尝试使用pip3命令
    if command -v pip3 &> /dev/null; then
        pip3 install requests PyYAML urllib3 pyaes > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            PIP_INSTALL_CMD="pip3 install"
            echo "使用pip3命令安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "使用pip3命令安装成功"
        else
            # 尝试--user
            pip3 install --user requests PyYAML urllib3 pyaes > /dev/null 2>&1
            if [ $? -eq 0 ]; then
                PIP_INSTALL_CMD="pip3 install --user"
                echo "使用pip3 --user安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "使用pip3 --user安装成功"
            fi
        fi
    fi
    
    # 方法2: 如果pip3不可用，尝试python3 -m pip
    if [ -z "$PIP_INSTALL_CMD" ] || [ "$PIP_INSTALL_CMD" = "pip3 install" ]; then
        if python3 -m pip --version &> /dev/null; then
            python3 -m pip install requests PyYAML urllib3 pyaes > /dev/null 2>&1
            if [ $? -eq 0 ]; then
                PIP_INSTALL_CMD="python3 -m pip install"
                echo "使用python3 -m pip安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "使用python3 -m pip安装成功"
            else
                # 尝试--user
                python3 -m pip install --user requests PyYAML urllib3 pyaes > /dev/null 2>&1
                if [ $? -eq 0 ]; then
                    PIP_INSTALL_CMD="python3 -m pip install --user"
                    echo "使用python3 -m pip --user安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "使用python3 -m pip --user安装成功"
                fi
            fi
        fi
    fi
    
    # 方法3: 如果还是失败，尝试--break-system-packages
    if [ -z "$PIP_INSTALL_CMD" ] || [ "$PIP_INSTALL_CMD" = "pip3 install" ]; then
        if python3 -m pip --version &> /dev/null; then
            python3 -m pip install --break-system-packages requests PyYAML urllib3 pyaes > /dev/null 2>&1
            if [ $? -eq 0 ]; then
                PIP_INSTALL_CMD="python3 -m pip install --break-system-packages"
                echo "使用--break-system-packages标志安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "使用--break-system-packages标志安装成功"
            fi
        fi
    fi
    
    # 如果所有方法都失败，设置默认命令
    if [ -z "$PIP_INSTALL_CMD" ]; then
        if command -v pip3 &> /dev/null; then
            PIP_INSTALL_CMD="pip3 install"
        elif python3 -m pip --version &> /dev/null; then
            PIP_INSTALL_CMD="python3 -m pip install"
        else
            PIP_INSTALL_CMD="python3 -m pip install"
        fi
        echo "警告: 自动检测安装方式失败，将使用默认方式: $PIP_INSTALL_CMD" | tee -a "$LOG_FILE" 2>/dev/null || echo "警告: 自动检测安装方式失败，将使用默认方式: $PIP_INSTALL_CMD"
    fi
else
    # 本地环境（Mac）：尝试使用--user，如果失败则使用--break-system-packages
    # 先尝试--user安装
    pip3 install --user requests PyYAML urllib3 pyaes > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        PIP_INSTALL_CMD="pip3 install --user"
        echo "检测到本地环境，使用--user标志安装Python包" | tee -a "$LOG_FILE" 2>/dev/null || echo "检测到本地环境，使用--user标志安装Python包"
    else
        # 如果--user失败，使用--break-system-packages（Mac Homebrew Python需要）
        PIP_INSTALL_CMD="python3 -m pip install --break-system-packages"
        echo "检测到本地环境（Mac），使用--break-system-packages标志安装Python包" | tee -a "$LOG_FILE" 2>/dev/null || echo "检测到本地环境（Mac），使用--break-system-packages标志安装Python包"
    fi
fi

# 安装必要的Python包（如果不存在）
echo "安装必要的Python包..." | tee -a "$LOG_FILE" 2>/dev/null || echo "安装必要的Python包..."
if [ -f "$SCRIPT_DIR/requirements.txt" ]; then
    echo "使用requirements.txt安装依赖..." | tee -a "$LOG_FILE" 2>/dev/null || echo "使用requirements.txt安装依赖..."
    $PIP_INSTALL_CMD -r "$SCRIPT_DIR/requirements.txt" >> "$LOG_FILE" 2>&1
else
    echo "requirements.txt不存在，使用默认包安装..." | tee -a "$LOG_FILE" 2>/dev/null || echo "requirements.txt不存在，使用默认包安装..."
    $PIP_INSTALL_CMD requests PyYAML urllib3 pyaes >> "$LOG_FILE" 2>&1
fi

if [ $? -eq 0 ]; then
    echo "Python包安装成功" | tee -a "$LOG_FILE" 2>/dev/null || echo "Python包安装成功"
else
    echo "警告: Python包安装可能失败，尝试继续执行..." | tee -a "$LOG_FILE" 2>/dev/null || echo "警告: Python包安装可能失败，尝试继续执行..."
fi

# 检查关键模块是否可用
echo "检查Python模块..." | tee -a "$LOG_FILE" 2>/dev/null || echo "检查Python模块..."

# 检查所有必需的模块
MISSING_MODULES=""
python3 -c "import requests" 2>/dev/null || MISSING_MODULES="$MISSING_MODULES requests"
python3 -c "import yaml" 2>/dev/null || MISSING_MODULES="$MISSING_MODULES PyYAML"
python3 -c "import pyaes" 2>/dev/null || MISSING_MODULES="$MISSING_MODULES pyaes"

if [ -n "$MISSING_MODULES" ]; then
    echo "缺少以下Python模块: $MISSING_MODULES" | tee -a "$LOG_FILE" 2>/dev/null || echo "缺少以下Python模块: $MISSING_MODULES"
    echo "尝试安装缺失的模块..." | tee -a "$LOG_FILE" 2>/dev/null || echo "尝试安装缺失的模块..."
    
    # 尝试多种安装方式
    INSTALL_SUCCESS=0
    
    # 方式1: 使用已确定的安装命令
    $PIP_INSTALL_CMD $MISSING_MODULES >> "$LOG_FILE" 2>&1
    if [ $? -eq 0 ]; then
        INSTALL_SUCCESS=1
    else
        # 方式2: 尝试--user
        pip3 install --user $MISSING_MODULES >> "$LOG_FILE" 2>&1
        if [ $? -eq 0 ]; then
            INSTALL_SUCCESS=1
        else
            # 方式3: 尝试python3 -m pip
            python3 -m pip install $MISSING_MODULES >> "$LOG_FILE" 2>&1
            if [ $? -eq 0 ]; then
                INSTALL_SUCCESS=1
            else
                # 方式4: 尝试--break-system-packages
                python3 -m pip install --break-system-packages $MISSING_MODULES >> "$LOG_FILE" 2>&1
                if [ $? -eq 0 ]; then
                    INSTALL_SUCCESS=1
                fi
            fi
        fi
    fi
    
    # 再次检查模块
    ALL_OK=1
    for module in $MISSING_MODULES; do
        case $module in
            requests)
                python3 -c "import requests" 2>/dev/null || ALL_OK=0
                ;;
            PyYAML)
                python3 -c "import yaml" 2>/dev/null || ALL_OK=0
                ;;
            pyaes)
                python3 -c "import pyaes" 2>/dev/null || ALL_OK=0
                ;;
        esac
    done
    
    if [ $ALL_OK -eq 0 ]; then
        echo "严重错误: 无法安装必需的Python模块" | tee -a "$LOG_FILE" 2>/dev/null || echo "严重错误: 无法安装必需的Python模块"
        echo "缺少的模块: $MISSING_MODULES" | tee -a "$LOG_FILE" 2>/dev/null || echo "缺少的模块: $MISSING_MODULES"
        echo "请手动运行以下命令安装:" | tee -a "$LOG_FILE" 2>/dev/null || echo "请手动运行以下命令安装:"
        echo "  pip3 install $MISSING_MODULES" | tee -a "$LOG_FILE" 2>/dev/null || echo "  pip3 install $MISSING_MODULES"
        echo "  或者: pip3 install --user $MISSING_MODULES" | tee -a "$LOG_FILE" 2>/dev/null || echo "  或者: pip3 install --user $MISSING_MODULES"
        echo "  或者: python3 -m pip install $MISSING_MODULES" | tee -a "$LOG_FILE" 2>/dev/null || echo "  或者: python3 -m pip install $MISSING_MODULES"
        exit 1
    else
        echo "所有必需的Python模块已成功安装" | tee -a "$LOG_FILE" 2>/dev/null || echo "所有必需的Python模块已成功安装"
    fi
else
    echo "所有必需的Python模块已可用" | tee -a "$LOG_FILE" 2>/dev/null || echo "所有必需的Python模块已可用"
fi

# 执行Python脚本
echo "开始执行节点获取脚本..." | tee -a "$LOG_FILE" 2>/dev/null || echo "开始执行节点获取脚本..."
echo "Python脚本路径: $SCRIPT_FILE" | tee -a "$LOG_FILE" 2>/dev/null || echo "Python脚本路径: $SCRIPT_FILE"
echo "当前工作目录: $(pwd)" | tee -a "$LOG_FILE" 2>/dev/null || echo "当前工作目录: $(pwd)"
echo "Python版本: $(python3 --version 2>&1)" | tee -a "$LOG_FILE" 2>/dev/null || echo "Python版本: $(python3 --version 2>&1)"

# 执行Python脚本并捕获输出和退出码
# 使用临时文件来捕获退出码，因为管道会改变$?
TEMP_OUTPUT=$(mktemp)
python3 "$SCRIPT_FILE" > "$TEMP_OUTPUT" 2>&1
PYTHON_EXIT_CODE=$?

# 将输出追加到日志文件
cat "$TEMP_OUTPUT" | tee -a "$LOG_FILE" 2>/dev/null || cat "$TEMP_OUTPUT" >> "$LOG_FILE" 2>&1
rm -f "$TEMP_OUTPUT"

echo "Python脚本执行完成，退出码: $PYTHON_EXIT_CODE" | tee -a "$LOG_FILE" 2>/dev/null || echo "Python脚本执行完成，退出码: $PYTHON_EXIT_CODE"

# 检查执行结果
if [ $PYTHON_EXIT_CODE -eq 0 ]; then
    echo "脚本执行成功: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
    
    # 检查输出文件
    if [ -f "$SCRIPT_DIR/tianmao.txt" ]; then
        NODE_COUNT=$(wc -l < "$SCRIPT_DIR/tianmao.txt")
        echo "成功生成节点文件，包含 $NODE_COUNT 个节点" >> "$LOG_FILE"
        echo "节点文件路径: $SCRIPT_DIR/tianmao.txt" >> "$LOG_FILE"
        echo "节点文件大小: $(du -h "$SCRIPT_DIR/tianmao.txt" | cut -f1)" >> "$LOG_FILE"
    else
        echo "警告: 节点文件未生成" >> "$LOG_FILE"
        echo "检查当前目录文件:" >> "$LOG_FILE"
        ls -la "$SCRIPT_DIR/" | grep -E "\.(txt|yaml)$" >> "$LOG_FILE"
        echo "检查VPS目录文件:" >> "$LOG_FILE"
        if [ -d "/www/wwwroot/dy.moneyfly.club/shell" ]; then
            ls -la "/www/wwwroot/dy.moneyfly.club/shell/" | grep -E "\.(txt|yaml)$" >> "$LOG_FILE"
        fi
    fi
    
    # 检查Base64订阅文件
    if [ -f "$SCRIPT_DIR/tianmao64.txt" ]; then
        BASE64_SIZE=$(wc -c < "$SCRIPT_DIR/tianmao64.txt")
        echo "成功生成Base64订阅文件，大小: $BASE64_SIZE 字节" >> "$LOG_FILE"
        echo "Base64订阅文件可用于v2rayn等客户端" >> "$LOG_FILE"
    else
        echo "警告: Base64订阅文件未生成" >> "$LOG_FILE"
        echo "检查VPS目录中的Base64文件:" >> "$LOG_FILE"
        if [ -f "/www/wwwroot/dy.moneyfly.club/shell/tianmao64.txt" ]; then
            BASE64_SIZE=$(wc -c < "/www/wwwroot/dy.moneyfly.club/shell/tianmao64.txt")
            echo "在VPS目录找到Base64文件，大小: $BASE64_SIZE 字节" >> "$LOG_FILE"
        fi
    fi
    
    # 检查Clash配置文件
    if [ -f "$SCRIPT_DIR/tianmao_clash.yaml" ]; then
        CLASH_SIZE=$(wc -c < "$SCRIPT_DIR/tianmao_clash.yaml")
        echo "成功生成Clash配置文件，大小: $CLASH_SIZE 字节" >> "$LOG_FILE"
    else
        echo "警告: Clash配置文件未生成" >> "$LOG_FILE"
        echo "检查VPS目录中的Clash文件:" >> "$LOG_FILE"
        if [ -f "/www/wwwroot/dy.moneyfly.club/shell/tianmao_clash.yaml" ]; then
            CLASH_SIZE=$(wc -c < "/www/wwwroot/dy.moneyfly.club/shell/tianmao_clash.yaml")
            echo "在VPS目录找到Clash文件，大小: $CLASH_SIZE 字节" >> "$LOG_FILE"
        fi
    fi
    
    # 显示HTTP访问地址
    echo "生成的文件HTTP访问地址:" >> "$LOG_FILE"
    
    # 原始节点文件
    if [ -f "$SCRIPT_DIR/tianmao.txt" ]; then
        echo "原始节点文件: http://dy.moneyfly.club/shell/tianmao.txt" >> "$LOG_FILE"
    fi
    
    # Base64订阅文件
    if [ -f "$SCRIPT_DIR/tianmao64.txt" ]; then
        echo "Base64订阅文件: http://dy.moneyfly.club/shell/tianmao64.txt" >> "$LOG_FILE"
        echo "  - 适用于v2rayn等客户端订阅" >> "$LOG_FILE"
    fi
    
    # Clash配置文件
    if [ -f "$SCRIPT_DIR/tianmao_clash.yaml" ]; then
        echo "Clash配置文件: http://dy.moneyfly.club/shell/tianmao_clash.yaml" >> "$LOG_FILE"
        echo "  - 适用于Clash客户端订阅" >> "$LOG_FILE"
    fi
else
    echo "脚本执行失败: $(date '+%Y-%m-%d %H:%M:%S')" | tee -a "$LOG_FILE" 2>/dev/null || echo "脚本执行失败: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "Python脚本退出码: $PYTHON_EXIT_CODE" | tee -a "$LOG_FILE" 2>/dev/null || echo "Python脚本退出码: $PYTHON_EXIT_CODE"
    echo "请检查日志文件获取详细错误信息: $LOG_FILE" | tee -a "$LOG_FILE" 2>/dev/null || echo "请检查日志文件获取详细错误信息: $LOG_FILE"
    
    # 显示最近的错误信息
    echo "最近的错误信息:" | tee -a "$LOG_FILE" 2>/dev/null || echo "最近的错误信息:"
    tail -20 "$LOG_FILE" 2>/dev/null | grep -i "error\|exception\|traceback\|failed" | tail -10 | tee -a "$LOG_FILE" 2>/dev/null || tail -20 "$LOG_FILE" 2>/dev/null | grep -i "error\|exception\|traceback\|failed" | tail -10
    
    # 检查Python模块
    echo "检查Python模块状态:" | tee -a "$LOG_FILE" 2>/dev/null || echo "检查Python模块状态:"
    python3 -c "import requests; print('requests: OK')" 2>&1 | tee -a "$LOG_FILE" 2>/dev/null || python3 -c "import requests; print('requests: OK')" >> "$LOG_FILE" 2>&1
    python3 -c "import yaml; print('yaml: OK')" 2>&1 | tee -a "$LOG_FILE" 2>/dev/null || python3 -c "import yaml; print('yaml: OK')" >> "$LOG_FILE" 2>&1
    python3 -c "import pyaes; print('pyaes: OK')" 2>&1 | tee -a "$LOG_FILE" 2>/dev/null || python3 -c "import pyaes; print('pyaes: OK')" >> "$LOG_FILE" 2>&1
    
    # 检查目录权限
    echo "检查目录权限:" | tee -a "$LOG_FILE" 2>/dev/null || echo "检查目录权限:"
    ls -ld "$SCRIPT_DIR" 2>&1 | tee -a "$LOG_FILE" 2>/dev/null || ls -ld "$SCRIPT_DIR" >> "$LOG_FILE" 2>&1
    echo "当前用户: $(whoami)" | tee -a "$LOG_FILE" 2>/dev/null || echo "当前用户: $(whoami)"
    echo "目录是否可写: $([ -w "$SCRIPT_DIR" ] && echo '是' || echo '否')" | tee -a "$LOG_FILE" 2>/dev/null || echo "目录是否可写: $([ -w "$SCRIPT_DIR" ] && echo '是' || echo '否')"
fi

echo "定时任务执行完成: $(date '+%Y-%m-%d %H:%M:%S')" | tee -a "$LOG_FILE" 2>/dev/null || echo "定时任务执行完成: $(date '+%Y-%m-%d %H:%M:%S')"
echo "==========================================" | tee -a "$LOG_FILE" 2>/dev/null || echo "=========================================="
