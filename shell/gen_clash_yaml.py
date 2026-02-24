import sys
import base64
import json
import re
import urllib.parse
import os

# 尝试导入 yaml，如果失败则使用自定义的 YAML 生成函数
try:
    import yaml
    HAS_YAML = True
except ImportError:
    HAS_YAML = False
    print("警告: PyYAML 库未安装，将使用自定义 YAML 生成器")

def unicode_decode(s):
    try:
        return json.loads(f'"{s}"')
    except Exception:
        return s

def dict_to_yaml(data, indent=0):
    """自定义 YAML 生成函数，不依赖 yaml 库"""
    result = []
    indent_str = '  ' * indent
    
    if isinstance(data, dict):
        for key, value in data.items():
            if isinstance(value, dict):
                result.append(f"{indent_str}{key}:")
                result.append(dict_to_yaml(value, indent + 1))
            elif isinstance(value, list):
                result.append(f"{indent_str}{key}:")
                for item in value:
                    if isinstance(item, dict):
                        result.append(f"{indent_str}  -")
                        result.append(dict_to_yaml(item, indent + 2))
                    else:
                        result.append(f"{indent_str}  - {item}")
            elif isinstance(value, bool):
                result.append(f"{indent_str}{key}: {str(value).lower()}")
            elif value is None:
                result.append(f"{indent_str}{key}: null")
            else:
                # 处理字符串，确保特殊字符被正确转义
                if isinstance(value, str) and ('#' in value or ':' in value or '"' in value or "'" in value):
                    result.append(f'{indent_str}{key}: "{value}"')
                else:
                    result.append(f"{indent_str}{key}: {value}")
    elif isinstance(data, list):
        for item in data:
            if isinstance(item, dict):
                result.append(f"{indent_str}-")
                result.append(dict_to_yaml(item, indent + 1))
            else:
                result.append(f"{indent_str}- {item}")
    else:
        result.append(f"{indent_str}{data}")
    
    return '\n'.join(result)

if len(sys.argv) != 3:
    print("用法: python3 gen_clash_yaml.py 节点明文文件 输出clash.yaml")
    print(f'当前工作目录: {os.getcwd()}')
    sys.exit(1)

input_file = sys.argv[1]
output_file = sys.argv[2]

# 动态设置模板文件路径
script_dir = os.path.dirname(os.path.abspath(__file__))

# 只检测当前目录及其子目录，不检测其他网站目录
possible_template_dirs = [
    script_dir,  # 当前脚本目录
    os.path.join(script_dir, '..'),  # 上级目录
    os.path.join(script_dir, '..', 'shell'),  # 上级目录的shell文件夹
    './shell',  # 相对路径
]

template_dir = None
for dir_path in possible_template_dirs:
    head_file = os.path.join(dir_path, "clash_template_head.yaml")
    tail_file = os.path.join(dir_path, "clash_template_tail.yaml")
    if os.path.exists(head_file) and os.path.exists(tail_file):
        template_dir = dir_path
        break

if template_dir is None:
    print(f"错误: 无法找到模板文件")
    print(f"已尝试的路径: {possible_template_dirs}")
    sys.exit(1)

head_file = os.path.join(template_dir, "clash_template_head.yaml")
tail_file = os.path.join(template_dir, "clash_template_tail.yaml")

print(f"使用模板目录: {template_dir}")

# 检查模板文件是否存在
if not os.path.exists(head_file):
    print(f"错误: 模板文件不存在: {head_file}")
    sys.exit(1)
if not os.path.exists(tail_file):
    print(f"错误: 模板文件不存在: {tail_file}")
    sys.exit(1)

proxies = []
proxy_names = []
name_count = {}

def clean_name(name):
    import re
    if not name:
        return name

    # 检查是否是重命名后的格式（如：英国-Trojan-001, 香港-SS-001等）
    # 如果是这种格式，直接返回，不进行清理
    if re.match(r'^[^\s]+-[A-Za-z]+-\d+$', name):
        return name

    # 更强的机场后缀清理，支持多种常见无用后缀
    patterns = [
        r'[\s]*[-_][\s]*(官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)[\s]*$',
        r'[\s]*[-_][\s]*[0-9]+[\s]*$',
        r'[\s]*[-_][\s]*[A-Za-z]+[\s]*$',
        # 直接以这些词结尾也去除
        r'(官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)$',
        # 处理没有空格的情况，如"-迅云加速"
        r'[-_](官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)$'
    ]
    for pattern in patterns:
        name = re.sub(pattern, '', name)

    # 去掉所有空格
    name = re.sub(r'[\s]+', '', name)
    name = name.strip()
    return name

def get_unique_name(name):
    # 先清理名称
    name = clean_name(name)
    name = name.strip()
    if not name:
        name = "节点"
    
    # 检查名称是否已存在，如果存在则添加编号
    original_name = name
    counter = 1
    while name in name_count:
        counter += 1
        name = f"{original_name}{counter:02d}"
    
    # 记录使用的名称
    name_count[name] = True
    return name

def decode_vmess(vmess_url):
    try:
        b64 = vmess_url[8:]
        b64 += '=' * (-len(b64) % 4)
        raw = base64.b64decode(b64).decode('utf-8')
        data = json.loads(raw)
        name = data.get('ps', '')
        server = data.get('add')
        
        # 解码节点名称
        if name:
            name = urllib.parse.unquote(name)
            name = unicode_decode(name)
        
        # 如果没有节点名称或名称为默认值，使用美国作为默认名称
        if not name or name.strip() == '' or name == 'vmess':
            name = "美国"
        port = int(data.get('port'))
        uuid = data.get('id')
        alterId = int(data.get('aid', 0))
        cipher = data.get('scy', 'auto')
        network = data.get('net', 'tcp')
        tls = data.get('tls', '') == 'tls'
        
        proxy = {
            'name': get_unique_name(name),
            'type': 'vmess',
            'server': server,
            'port': port,
            'uuid': uuid,
            'alterId': alterId,
            'cipher': cipher,
            'udp': True,
            'tls': tls,
        }
        
        # 处理不同的网络类型
        if network == 'ws':
            proxy['network'] = 'ws'
            proxy['ws-opts'] = {
                'path': data.get('path', '/'),
                'headers': {'Host': data.get('host', '')}
            }
        elif network == 'grpc':
            proxy['network'] = 'grpc'
            proxy['grpc-opts'] = {
                'grpc-service-name': data.get('path', '')
            }
        elif network == 'h2':
            proxy['network'] = 'h2'
            proxy['h2-opts'] = {
                'host': [data.get('host', '')],
                'path': data.get('path', '/')
            }
        elif network == 'tcp':
            proxy['network'] = 'tcp'
            # TCP可能有http伪装
            if data.get('type') == 'http':
                proxy['http-opts'] = {
                    'method': 'GET',
                    'path': [data.get('path', '/')],
                    'headers': {'Host': [data.get('host', '')]}
                }
        
        # 处理TLS相关配置
        if tls:
            proxy['tls'] = True
            if data.get('sni'):
                proxy['servername'] = data.get('sni')
            elif data.get('host'):
                proxy['servername'] = data.get('host')
            
            # 处理ALPN
            if data.get('alpn'):
                proxy['alpn'] = data.get('alpn').split(',')
        
        return proxy
    except Exception as e:
        print(f"VMess解析异常: {str(e)}")
        import traceback
        traceback.print_exc()
        return None

def decode_ss(ss_url):
    try:
        # 首先检查是否有fragment（节点名称）
        url_parts = urllib.parse.urlparse(ss_url)
        name = ""
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
            name = unicode_decode(name)
        
        # 尝试第一种格式: ss://base64@server:port
        m = re.match(r'ss://([A-Za-z0-9+/=%]+)@([^:]+):(\d+)', ss_url)
        if m:
            userinfo, server, port = m.groups()
            try:
                # URL解码用户信息
                userinfo = urllib.parse.unquote(userinfo)
                userinfo += '=' * (-len(userinfo) % 4)
                method_pass = base64.b64decode(userinfo).decode('utf-8')
                method, password = method_pass.split(':', 1)
                
                # 如果没有节点名称，使用美国作为默认名称
                if not name:
                    name = "美国"
                
                proxy = {
                    'name': get_unique_name(name),
                    'type': 'ss',
                    'server': server,
                    'port': int(port),
                    'cipher': method,
                    'password': password,
                    'udp': True
                }
                return proxy
            except Exception as e:
                print(f"SS节点解析失败(格式1): {str(e)}")
                return None
        
        # 尝试第二种格式: ss://base64#name
        m = re.match(r'ss://([A-Za-z0-9+/=%]+)#(.+)', ss_url)
        if m:
            b64, name = m.groups()
            try:
                # URL解码base64部分
                b64 = urllib.parse.unquote(b64)
                b64 += '=' * (-len(b64) % 4)
                method_pass_server_port = base64.b64decode(b64).decode('utf-8')
                method, password_server_port = method_pass_server_port.split(':', 1)
                password, server_port = password_server_port.rsplit('@', 1)
                server, port = server_port.split(':')
                name = urllib.parse.unquote(name)
                name = unicode_decode(name)
                
                # 如果解析出的名称为空，使用美国作为默认名称
                if not name:
                    name = "美国"
                
                proxy = {
                    'name': get_unique_name(name),
                    'type': 'ss',
                    'server': server,
                    'port': int(port),
                    'cipher': method,
                    'password': password,
                    'udp': True
                }
                return proxy
            except Exception as e:
                print(f"SS节点解析失败(格式2): {str(e)}")
                return None
    except Exception as e:
        print(f"SS解析异常: {str(e)}")
        return None

def decode_trojan(trojan_url):
    try:
        url_parts = urllib.parse.urlparse(trojan_url)
        server = url_parts.hostname
        port = url_parts.port
        password = url_parts.username
        
        # 解析查询参数
        query_params = urllib.parse.parse_qs(url_parts.query)
        
        # 获取节点名称
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        else:
            # 如果没有节点名称，使用美国作为默认名称
            name = "美国"
        name = get_unique_name(name)
        
        proxy = {
            'name': name,
            'type': 'trojan',
            'server': server,
            'port': port,
            'password': password,
            'udp': True,
            'tls': True  # Trojan默认使用TLS
        }
        
        # 处理SNI (Server Name Indication)
        sni = query_params.get('sni', [''])[0]
        if sni:
            proxy['sni'] = sni
        # 如果没有指定SNI，则不添加sni配置
        
        # 处理跳过证书验证 - 支持多种参数名
        skip_cert_verify = False
        # 检查allowInsecure参数
        allow_insecure = query_params.get('allowInsecure', [''])[0]
        if allow_insecure == '1' or allow_insecure.lower() == 'true':
            skip_cert_verify = True
        
        # 检查skip-cert-verify参数
        skip_cert_verify_param = query_params.get('skip-cert-verify', [''])[0]
        if skip_cert_verify_param.lower() == 'true':
            skip_cert_verify = True
        
        if skip_cert_verify:
            proxy['skip-cert-verify'] = True
        
        # 处理传输协议
        network_type = query_params.get('type', ['tcp'])[0]
        if network_type == 'ws':
            proxy['network'] = 'ws'
            ws_opts = {}
            
            # WebSocket路径
            path = query_params.get('path', ['/'])[0]
            ws_opts['path'] = path
            
            # WebSocket主机头
            host = query_params.get('host', [''])[0]
            if host:
                ws_opts['headers'] = {'Host': host}
            
            proxy['ws-opts'] = ws_opts
        
        return proxy
    except Exception as e:
        print(f"Trojan解析异常: {str(e)}")
        return None

def decode_vless(vless_url):
    try:
        url_parts = urllib.parse.urlparse(vless_url)
        server = url_parts.hostname
        port = url_parts.port
        uuid = url_parts.username
        
        # 解析查询参数
        query_params = urllib.parse.parse_qs(url_parts.query)
        
        # 获取节点名称
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        else:
            # 如果没有节点名称，使用美国作为默认名称
            name = "美国"
        name = get_unique_name(name)
        
        proxy = {
            'name': name,
            'type': 'vless',
            'server': server,
            'port': port,
            'uuid': uuid,
            'udp': True
        }
        
        # 处理加密方式
        encryption = query_params.get('encryption', ['none'])[0]
        if encryption != 'none':
            proxy['encryption'] = encryption
        
        # 处理flow参数（用于Vision协议）- 必须在TLS配置之前处理
        flow = query_params.get('flow', [''])[0]
        if flow:
            proxy['flow'] = flow
        
        # 处理安全传输
        security = query_params.get('security', ['none'])[0]
        if security == 'tls':
            proxy['tls'] = True
            # 处理SNI
            sni = query_params.get('sni', [''])[0]
            if sni:
                proxy['servername'] = sni
            # 处理ALPN
            alpn = query_params.get('alpn', [''])[0]
            if alpn:
                proxy['alpn'] = alpn.split(',')
            # 如果有 flow=xtls-rprx-vision，需要添加 Reality 配置
            if flow == 'xtls-rprx-vision':
                reality_opts = {}
                pbk = query_params.get('pbk', [''])[0]
                if pbk:
                    reality_opts['public-key'] = pbk
                else:
                    # 使用默认的 public-key
                    reality_opts['public-key'] = 'cG9xf8Ia0y9KPQn2zS-YK6_ohVDZaRUegMr-bM7R9ig'
                sid = query_params.get('sid', [''])[0]
                if sid:
                    reality_opts['short-id'] = sid
                else:
                    # 使用默认的 short-id
                    reality_opts['short-id'] = '0123456789abcdef'
                proxy['reality-opts'] = reality_opts
                proxy['client-fingerprint'] = 'chrome'
        elif security == 'reality':
            proxy['tls'] = True
            # 处理SNI
            sni = query_params.get('sni', [''])[0]
            if sni:
                proxy['servername'] = sni
            # Reality配置
            reality_opts = {}
            pbk = query_params.get('pbk', [''])[0]
            if pbk:
                reality_opts['public-key'] = pbk
            else:
                # 使用默认的 public-key
                reality_opts['public-key'] = 'cG9xf8Ia0y9KPQn2zS-YK6_ohVDZaRUegMr-bM7R9ig'
            sid = query_params.get('sid', [''])[0]
            if sid:
                reality_opts['short-id'] = sid
            else:
                # 使用默认的 short-id
                reality_opts['short-id'] = '0123456789abcdef'
            proxy['reality-opts'] = reality_opts
            
            # Reality协议需要添加client-fingerprint
            proxy['client-fingerprint'] = 'chrome'
            
            # 添加其他必要的参数
            proxy['tfo'] = False
            proxy['skip-cert-verify'] = False
        
        # 处理传输协议
        network_type = query_params.get('type', ['tcp'])[0]
        if network_type == 'ws':
            proxy['network'] = 'ws'
            ws_opts = {}
            
            # WebSocket路径
            path = query_params.get('path', ['/'])[0]
            ws_opts['path'] = path
            
            # WebSocket Host头
            host = query_params.get('host', [''])[0]
            if host:
                ws_opts['headers'] = {'Host': host}
            
            proxy['ws-opts'] = ws_opts
        elif network_type == 'grpc':
            proxy['network'] = 'grpc'
            grpc_opts = {}
            service_name = query_params.get('serviceName', [''])[0]
            if service_name:
                grpc_opts['grpc-service-name'] = service_name
            proxy['grpc-opts'] = grpc_opts
        elif network_type == 'h2':
            proxy['network'] = 'h2'
            h2_opts = {}
            path = query_params.get('path', ['/'])[0]
            h2_opts['path'] = path
            host = query_params.get('host', [''])[0]
            if host:
                h2_opts['host'] = [host]
            proxy['h2-opts'] = h2_opts
        
        # 处理flow参数（用于Vision协议）- 必须在TLS配置之前处理
        flow = query_params.get('flow', [''])[0]
        if flow:
            proxy['flow'] = flow
        
        # 处理headerType参数
        header_type = query_params.get('headerType', [''])[0]
        if header_type and header_type != 'none':
            proxy['headerType'] = header_type
        
        return proxy
    except Exception as e:
        print(f"VLESS解析异常: {str(e)}")
        import traceback
        traceback.print_exc()
        return None

def decode_ssr(ssr_url):
    try:
        b64 = ssr_url[6:]
        b64 += '=' * (-len(b64) % 4)
        try:
            raw = base64.b64decode(b64).decode('utf-8')
        except Exception as e:
            print(f"SSR base64 解码失败: {str(e)}")
            return None
        parts = raw.split(':')
        if len(parts) < 5:
            return None
            
        if len(parts) == 6:
            # 6部分格式: server:port:protocol:method:obfs:password_base64/?params
            server = parts[0]
            port = int(parts[1])
            protocol = parts[2]
            method = parts[3]
            actual_obfs = parts[4]
            password_and_params = parts[5]
            
            # 检查第6部分是否包含参数
            if '?' in password_and_params:
                password_b64 = password_and_params.split('?')[0].rstrip('/')
                actual_params_str = '?' + password_and_params.split('?', 1)[1]
            else:
                password_b64 = password_and_params
                actual_params_str = ''
        else:
            # 5部分格式: server:port:protocol:method:obfs/?params
            server = parts[0]
            port = int(parts[1])
            protocol = parts[2]
            method = parts[3]
            obfs_and_params = parts[4]
            
            if '?' in obfs_and_params:
                actual_obfs = obfs_and_params.split('?')[0].rstrip('/')
                actual_params_str = '?' + obfs_and_params.split('?', 1)[1]
            else:
                actual_obfs = obfs_and_params.rstrip('/')
                actual_params_str = ''
            password_b64 = ''
        
        # 解析参数
        params = {}
        if actual_params_str:
            param_str = actual_params_str[1:]  # 移除开头的?
            
            # 解析URL参数
            for param in param_str.split('&'):
                if '=' in param:
                    key, value = param.split('=', 1)
                    try:
                        # 处理URL安全的base64编码
                        url_safe_value = value.replace('-', '+').replace('_', '/')
                        # 修复base64填充
                        padding_needed = (4 - len(url_safe_value) % 4) % 4
                        padded_value = url_safe_value + '=' * padding_needed
                        decoded_value = base64.b64decode(padded_value).decode('utf-8')
                        params[key] = decoded_value
                    except Exception as e:
                        # 如果base64解码失败，尝试直接使用原始值
                        params[key] = value
        
        # 解码密码
        if password_b64:
            try:
                # 处理URL安全的base64编码
                url_safe_password = password_b64.replace('-', '+').replace('_', '/')
                # 修复base64填充
                padding_needed = (4 - len(url_safe_password) % 4) % 4
                padded_password = url_safe_password + '=' * padding_needed
                password = base64.b64decode(padded_password).decode('utf-8')
            except Exception as e:
                password = password_b64  # 如果解码失败，使用原始值
        else:
            # 如果没有password_b64，尝试从参数中获取，或使用服务器信息作为密码
            password = params.get('password', f'{server}_password')
        
        # 获取节点名称 - remarks参数也需要base64解码
        name = params.get('remarks', '')
        if name:
            # remarks参数已经在上面解析时进行了base64解码
            name = urllib.parse.unquote(name)
            name = unicode_decode(name)
            
            # 如果解码后的名称看起来不对，尝试其他编码
            if name and len(name) <= 10 and not any('\u4e00' <= char <= '\u9fff' for char in name):
                # 尝试重新解码原始remarks值
                original_remarks = None
                for param in actual_params_str[1:].split('&'):
                    if param.startswith('remarks='):
                        original_remarks = param.split('=', 1)[1]
                        break
                
                if original_remarks:
                    try:
                        # 尝试不同的编码方式
                        url_safe_value = original_remarks.replace('-', '+').replace('_', '/')
                        padding_needed = (4 - len(url_safe_value) % 4) % 4
                        padded_value = url_safe_value + '=' * padding_needed
                        
                        # 尝试UTF-8
                        try:
                            decoded_utf8 = base64.b64decode(padded_value).decode('utf-8')
                            if any('\u4e00' <= char <= '\u9fff' for char in decoded_utf8):
                                name = decoded_utf8
                        except:
                            pass
                        
                        # 尝试GBK
                        try:
                            decoded_gbk = base64.b64decode(padded_value).decode('gbk', errors='ignore')
                            if any('\u4e00' <= char <= '\u9fff' for char in decoded_gbk):
                                name = decoded_gbk
                        except:
                            pass
                        
                        # 尝试Big5
                        try:
                            decoded_big5 = base64.b64decode(padded_value).decode('big5', errors='ignore')
                            if any('\u4e00' <= char <= '\u9fff' for char in decoded_big5):
                                name = decoded_big5
                        except:
                            pass
                    except Exception as e:
                        print(f"重新解码失败: {e}")
        
        if not name:
            # 如果没有节点名称，使用美国作为默认名称
            name = "美国"
        name = get_unique_name(name)
        
        # 对于这种特殊的SSR格式，需要特殊处理cipher和protocol字段
        # 根据用户期望的映射：protocol应该是origin，cipher应该是chacha20-ietf
        if protocol == 'origin' and method == 'chacha20-ietf':
            # 特殊格式：protocol和method字段位置互换了
            actual_protocol = 'origin'
            cipher = 'chacha20-ietf'
        else:
            # 标准格式
            actual_protocol = protocol
            if method in ['origin', 'plain']:
                cipher = 'chacha20-ietf'  # 使用默认加密方式
            else:
                cipher = method
        
        proxy = {
            'name': name,
            'type': 'ssr',
            'server': server,
            'port': port,
            'cipher': cipher,
            'password': password,
            'protocol': actual_protocol,
            'obfs': actual_obfs,
            'udp': True
        }
        
        # 添加协议参数
        if 'protoparam' in params:
            proxy['protocol-param'] = params['protoparam']
        
        # 添加混淆参数
        if 'obfsparam' in params:
            proxy['obfs-param'] = params['obfsparam']
        
        return proxy
    except Exception as e:
        print(f"SSR解析异常: {str(e)}")
        import traceback
        traceback.print_exc()
        return None

def decode_vless_reality(vless_url):
    try:
        url_parts = urllib.parse.urlparse(vless_url)
        server = url_parts.hostname
        port = url_parts.port
        uuid = url_parts.username
        
        query_params = urllib.parse.parse_qs(url_parts.query)
        
        # 获取节点名称
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        else:
            # 如果没有节点名称，使用美国作为默认名称
            name = "美国"
        name = get_unique_name(name)
        
        proxy = {
            'name': name,
            'type': 'vless',
            'server': server,
            'port': port,
            'uuid': uuid,
            'udp': True
        }
        
        # 处理加密方式
        encryption = query_params.get('encryption', ['none'])[0]
        if encryption != 'none':
            proxy['encryption'] = encryption
        
        # 处理传输协议
        network_type = query_params.get('type', ['tcp'])[0]
        if network_type:
            proxy['network'] = network_type
        
        # 处理WebSocket配置
        if network_type == 'ws':
            ws_opts = {}
            path = query_params.get('path', ['/'])[0]
            ws_opts['path'] = path
            host = query_params.get('host', [''])[0]
            if host:
                ws_opts['headers'] = {'Host': host}
            proxy['ws-opts'] = ws_opts
        elif network_type == 'grpc':
            grpc_opts = {}
            service_name = query_params.get('serviceName', [''])[0]
            if service_name:
                grpc_opts['grpc-service-name'] = service_name
            proxy['grpc-opts'] = grpc_opts
        elif network_type == 'h2':
            h2_opts = {}
            path = query_params.get('path', ['/'])[0]
            h2_opts['path'] = path
            host = query_params.get('host', [''])[0]
            if host:
                h2_opts['host'] = [host]
            proxy['h2-opts'] = h2_opts
        
        # 处理flow参数（用于Vision协议）- 必须在TLS配置之前处理
        flow = query_params.get('flow', [''])[0]
        if flow:
            proxy['flow'] = flow
        
        # 处理headerType参数
        header_type = query_params.get('headerType', [''])[0]
        if header_type and header_type != 'none':
            proxy['headerType'] = header_type
        
        # Reality配置
        proxy['tls'] = True
        
        # 处理SNI
        sni = query_params.get('sni', [''])[0]
        if sni:
            proxy['servername'] = sni
        
        # Reality配置
        reality_opts = {}
        pbk = query_params.get('pbk', [''])[0]
        if pbk:
            reality_opts['public-key'] = pbk
        else:
            # 使用默认的 public-key
            reality_opts['public-key'] = 'cG9xf8Ia0y9KPQn2zS-YK6_ohVDZaRUegMr-bM7R9ig'
        sid = query_params.get('sid', [''])[0]
        if sid:
            reality_opts['short-id'] = sid
        else:
            # 使用默认的 short-id
            reality_opts['short-id'] = '0123456789abcdef'
        proxy['reality-opts'] = reality_opts
        
        # Reality协议需要添加client-fingerprint
        proxy['client-fingerprint'] = 'chrome'
        
        # 添加其他必要的参数
        proxy['tfo'] = False
        proxy['skip-cert-verify'] = False
        
        return proxy
    except Exception as e:
        print(f"VLESS Reality解析异常: {str(e)}")
        import traceback
        traceback.print_exc()
        return None

def decode_hysteria2(hy2_url):
    try:
        url_parts = urllib.parse.urlparse(hy2_url)
        server = url_parts.hostname
        port = url_parts.port
        password = url_parts.username
        
        # 解析查询参数
        query_params = urllib.parse.parse_qs(url_parts.query)
        
        # 获取节点名称
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        else:
            # 如果没有节点名称，使用美国作为默认名称
            name = "美国"
        name = get_unique_name(name)
        
        proxy = {
            'name': name,
            'type': 'hysteria2',
            'server': server,
            'port': port,
            'password': password,
            'udp': True
        }
        
        # 处理SNI
        sni = query_params.get('sni', [''])[0]
        if sni:
            proxy['sni'] = sni
        
        # 处理insecure参数
        insecure = query_params.get('insecure', [''])[0]
        if insecure == '1' or insecure.lower() == 'true':
            proxy['skip-cert-verify'] = True
        
        # 处理upmbps和downmbps参数
        up_mbps = query_params.get('upmbps', [''])[0]
        if up_mbps:
            try:
                proxy['up-mbps'] = int(up_mbps)
            except ValueError:
                pass
        
        down_mbps = query_params.get('downmbps', [''])[0]
        if down_mbps:
            try:
                proxy['down-mbps'] = int(down_mbps)
            except ValueError:
                pass
        
        # 处理obfs参数
        obfs = query_params.get('obfs', [''])[0]
        if obfs:
            proxy['obfs'] = obfs
        
        # 处理obfs-password参数
        obfs_password = query_params.get('obfs-password', [''])[0]
        if obfs_password:
            proxy['obfs-password'] = obfs_password
        
        return proxy
    except Exception as e:
        print(f"Hysteria2解析异常: {str(e)}")
        import traceback
        traceback.print_exc()
        return None

def decode_tuic(tuic_url):
    try:
        url_parts = urllib.parse.urlparse(tuic_url)
        server = url_parts.hostname
        port = url_parts.port
        uuid = url_parts.username
        password = url_parts.password
        
        proxy = {
            'name': get_unique_name(urllib.parse.unquote(url_parts.fragment) if url_parts.fragment else "美国"),
            'type': 'tuic',
            'server': server,
            'port': port,
            'uuid': uuid,
            'password': password,
            'udp': True
        }
        return proxy
    except Exception as e:
        print(f"TUIC解析异常: {str(e)}")
        return None

# 读取节点文件
with open(input_file, 'r', encoding='utf-8') as f:
    content = f.read().strip()

# 检查是否是 base64 编码
try:
    # 尝试解码 base64
    decoded_content = base64.b64decode(content).decode('utf-8')
    lines = decoded_content.split('\n')
    print(f"检测到 base64 编码，解码后共 {len(lines)} 行")
except:
    # 如果不是 base64，按普通文本处理
    lines = content.split('\n')
    print(f"按普通文本处理，共 {len(lines)} 行")

# 解析每个节点
print(f"开始解析节点，共 {len(lines)} 行")
for i, line in enumerate(lines):
    line = line.strip()
    if not line:
        continue
    
    print(f"处理第 {i+1} 行: {line[:50]}...")
    
    if line.startswith('vmess://'):
        proxy = decode_vmess(line)
    elif line.startswith('ss://'):
        proxy = decode_ss(line)
    elif line.startswith('trojan://'):
        proxy = decode_trojan(line)
    elif line.startswith('vless://'):
        # 检查是否是Reality协议
        if ('reality' in line.lower() or 'pbk=' in line or 
            'flow=xtls-rprx-vision' in line):
            print(f"  识别为 Reality 协议")
            proxy = decode_vless_reality(line)
        else:
            print(f"  识别为普通 VLESS 协议")
            proxy = decode_vless(line)
    elif line.startswith('ssr://'):
        proxy = decode_ssr(line)
    elif line.startswith('hysteria2://') or line.startswith('hy2://'):
        proxy = decode_hysteria2(line)
    elif line.startswith('tuic://'):
        proxy = decode_tuic(line)
    else:
        proxy = None
        print(f"  未知协议类型")
    
    if proxy:
        proxies.append(proxy)
        proxy_names.append(proxy['name'])
        print(f"  ✅ 解析成功: {proxy['name']}")
    else:
        print(f"  ❌ 解析失败")

# 读取模板文件
with open(head_file, encoding='utf-8') as f:
    head = f.read().rstrip() + '\n'
with open(tail_file, encoding='utf-8') as f:
    tail = f.read().lstrip()

# 生成proxies部分的YAML
if HAS_YAML:
    proxies_yaml = yaml.dump({'proxies': proxies}, allow_unicode=True, sort_keys=False, indent=2)
else:
    # 使用自定义 YAML 生成器
    proxies_yaml = dict_to_yaml({'proxies': proxies})

# 处理tail部分，替换代理名称列表
tail_lines = tail.split('\n')
formatted_tail_lines = []
i = 0
while i < len(tail_lines):
    line = tail_lines[i]
    if line.strip():
        if line.startswith('  - name:') or line.startswith('- name:'):
            # 处理代理组
            if line.startswith('- name:'):
                formatted_tail_lines.append('  ' + line)
            else:
                formatted_tail_lines.append(line)
            
            # 查找proxies字段并替换
            j = i + 1
            while j < len(tail_lines):
                next_line = tail_lines[j]
                if 'proxies:' in next_line:
                    formatted_tail_lines.append(next_line)
                    
                    # 跳过原有的代理列表
                    k = j + 1
                    while k < len(tail_lines) and (tail_lines[k].startswith('      -') or not tail_lines[k].strip()):
                        if tail_lines[k].strip():
                            formatted_tail_lines.append(tail_lines[k])
                        k += 1
                    
                    # 添加新的代理名称列表
                    for proxy_name in proxy_names:
                        formatted_tail_lines.append(f'      - {proxy_name}')
                    
                    i = k - 1
                    break
                else:
                    formatted_tail_lines.append(next_line)
                    j += 1
            if j >= len(tail_lines):
                i = len(tail_lines)
        else:
            formatted_tail_lines.append(line)
    else:
        formatted_tail_lines.append(line)
    i += 1

formatted_tail = '\n'.join(formatted_tail_lines)
final_content = head + '\nproxies:\n' + proxies_yaml[9:] + '\nproxy-groups:\n' + formatted_tail

# 写入输出文件
with open(output_file, 'w', encoding='utf-8') as f:
    f.write(final_content)

# 验证YAML格式
if HAS_YAML:
    try:
        yaml.safe_load(final_content)
        print(f'YAML格式验证通过')
    except yaml.YAMLError as e:
        print(f'警告: YAML格式可能有问题:\n{e.problem}\n位置: {e.context}' if hasattr(e, 'problem') else f'警告: {e}')
        print('文件已生成，请手动检查格式')
else:
    print('使用自定义 YAML 生成器，跳过格式验证')

print(f'成功生成Clash配置文件: {output_file}')
print(f'共处理 {len(proxies)} 个节点')