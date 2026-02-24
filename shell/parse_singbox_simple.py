#!/usr/bin/env python3
import re
import sys
import base64
import json

if len(sys.argv) < 2:
    print('用法: python3 parse_singbox_simple.py <config.json>')
    sys.exit(1)

input_file = sys.argv[1]

def find_complete_json_object(content, start_pos):
    """从指定位置开始找到完整的JSON对象"""
    brace_count = 0
    end_pos = start_pos
    
    for i, char in enumerate(content[start_pos:], start_pos):
        if char == '{':
            brace_count += 1
        elif char == '}':
            brace_count -= 1
            if brace_count == 0:
                end_pos = i + 1
                break
    
    if brace_count == 0:
        return content[start_pos:end_pos]
    return None

def extract_nodes_from_content(content):
    """从内容中提取所有支持的节点"""
    nodes = []
    
    # 清理注释
    content = re.sub(r'/\*.*?\*/', '', content, flags=re.DOTALL)
    content = re.sub(r'//.*$', '', content, flags=re.MULTILINE)
    
    # 支持的节点类型
    supported_types = ['vless', 'hysteria2', 'anytls', 'trojan', 'shadowsocks', 'vmess', 'wireguard']
    
    for node_type in supported_types:
        # 找到所有该类型的开始位置
        type_pattern = rf'"type":\s*"{node_type}"'
        type_matches = re.finditer(type_pattern, content)
        
        for type_match in type_matches:
            # 从type字段开始向前找到对象的开始
            start_pos = type_match.start()
            while start_pos > 0 and content[start_pos] != '{':
                start_pos -= 1
            
            if content[start_pos] == '{':
                # 找到完整的JSON对象
                json_obj_str = find_complete_json_object(content, start_pos)
                if json_obj_str:
                    try:
                        # 尝试解析JSON对象
                        node_obj = json.loads(json_obj_str)
                        node = {'type': node_type}
                        
                        # 提取基本信息
                        if 'server' in node_obj:
                            node['server'] = node_obj['server']
                        if 'server_port' in node_obj:
                            node['port'] = str(node_obj['server_port'])
                        
                        # 提取协议特定信息
                        if node_type in ['vless', 'vmess'] and 'uuid' in node_obj:
                            node['uuid'] = node_obj['uuid']
                        
                        if node_type in ['hysteria2', 'anytls', 'shadowsocks', 'trojan'] and 'password' in node_obj:
                            node['password'] = node_obj['password']
                        
                        # 提取SNI信息 - 可能在tls对象内
                        if 'server_name' in node_obj:
                            node['server_name'] = node_obj['server_name']
                        elif 'tls' in node_obj and isinstance(node_obj['tls'], dict) and 'server_name' in node_obj['tls']:
                            node['server_name'] = node_obj['tls']['server_name']
                        
                        # 提取Reality配置
                        if 'tls' in node_obj and isinstance(node_obj['tls'], dict):
                            tls_config = node_obj['tls']
                            if 'reality' in tls_config and isinstance(tls_config['reality'], dict):
                                reality_config = tls_config['reality']
                                if 'public_key' in reality_config:
                                    node['reality_public_key'] = reality_config['public_key']
                                if 'short_id' in reality_config:
                                    node['reality_short_id'] = reality_config['short_id']
                        
                        # 提取其他TLS配置
                        if 'tls' in node_obj and isinstance(node_obj['tls'], dict):
                            tls_config = node_obj['tls']
                            if 'utls' in tls_config and isinstance(tls_config['utls'], dict):
                                utls_config = tls_config['utls']
                                if 'fingerprint' in utls_config:
                                    node['utls_fingerprint'] = utls_config['fingerprint']
                        
                        # 提取packet_encoding
                        if 'packet_encoding' in node_obj:
                            node['packet_encoding'] = node_obj['packet_encoding']
                        
                        # 只添加有基本信息的节点
                        if 'server' in node and 'port' in node:
                            nodes.append(node)
                            
                    except json.JSONDecodeError:
                        # 如果JSON解析失败，跳过这个对象
                        continue
    
    return nodes

def generate_node_url(node):
    """生成标准格式的节点URL"""
    node_type = node['type']
    
    if node_type == 'vless':
        if 'uuid' not in node:
            return None
        url = f"vless://{node['uuid']}@{node['server']}:{node['port']}"
        params = []
        
        # 基本参数
        if 'server_name' in node:
            params.append(f"sni={node['server_name']}")
        
        # 检查是否有Reality配置
        if 'reality_public_key' in node:
            params.append("security=reality")
            params.append(f"fp={node.get('utls_fingerprint', 'ios')}")
            if 'reality_short_id' in node:
                params.append(f"sid={node['reality_short_id']}")
            params.append(f"pbk={node['reality_public_key']}")
        else:
            params.append("security=tls")
        
        # 传输类型
        if 'packet_encoding' in node and node['packet_encoding'] == 'xudp':
            params.append("type=udp")
        else:
            params.append("type=tcp")
        
        if params:
            url += "?" + "&".join(params)
        url += f"#singbox-{node_type}"
        return url
    
    elif node_type == 'hysteria2':
        if 'password' not in node:
            return None
        url = f"hysteria2://{node['password']}@{node['server']}:{node['port']}"
        params = []
        if 'server_name' in node:
            params.append(f"sni={node['server_name']}")
        params.append("insecure=0")
        if params:
            url += "?" + "&".join(params)
        url += f"#singbox-{node_type}"
        return url
    
    elif node_type == 'anytls':
        if 'password' not in node:
            return None
        url = f"anytls://{node['password']}@{node['server']}:{node['port']}"
        params = []
        if 'server_name' in node:
            params.append(f"sni={node['server_name']}")
        params.append("insecure=0")
        if params:
            url += "?" + "&".join(params)
        url += f"#singbox-{node_type}"
        return url
    
    elif node_type == 'shadowsocks':
        if 'password' not in node:
            return None
        method = "aes-256-gcm"  # 默认方法
        url = f"ss://{method}:{node['password']}@{node['server']}:{node['port']}"
        url += f"#singbox-{node_type}"
        return url
    
    elif node_type == 'vmess':
        if 'uuid' not in node:
            return None
        vmess_config = {
            "v": "2",
            "ps": f"singbox-{node_type}",
            "add": node['server'],
            "port": int(node['port']),
            "id": node['uuid'],
            "aid": "0",
            "net": "tcp",
            "type": "none",
            "host": "",
            "path": "",
            "tls": "none"
        }
        json_str = json.dumps(vmess_config)
        encoded = base64.b64encode(json_str.encode()).decode()
        return f"vmess://{encoded}"
    
    elif node_type == 'trojan':
        if 'password' not in node:
            return None
        url = f"trojan://{node['password']}@{node['server']}:{node['port']}"
        params = []
        if 'server_name' in node:
            params.append(f"sni={node['server_name']}")
        if params:
            url += "?" + "&".join(params)
        url += f"#singbox-{node_type}"
        return url
    
    elif node_type == 'wireguard':
        # wireguard需要更多参数，这里简化处理
        if 'private_key' in node and 'public_key' in node:
            url = f"wireguard://{node['private_key']}@{node['server']}:{node['port']}"
            params = [f"public_key={node['public_key']}"]
            if 'pre_shared_key' in node:
                params.append(f"pre_shared_key={node['pre_shared_key']}")
            url += "?" + "&".join(params)
            url += f"#singbox-{node_type}"
            return url
    
    return None

def main():
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # 提取节点
        nodes = extract_nodes_from_content(content)
        
        if not nodes:
            print("未找到任何节点")
            return
        
        # 生成URL并输出
        valid_nodes = 0
        for node in nodes:
            url = generate_node_url(node)
            if url:
                print(url)
                valid_nodes += 1
        
        print(f"\n总共找到 {len(nodes)} 个节点，成功生成 {valid_nodes} 个URL")
        
        # 显示节点类型统计
        type_counts = {}
        for node in nodes:
            node_type = node['type']
            type_counts[node_type] = type_counts.get(node_type, 0) + 1
        
        print("\n节点类型统计:")
        for node_type, count in type_counts.items():
            print(f"  {node_type}: {count} 个")
        
    except Exception as e:
        print(f"处理文件时出错: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main() 