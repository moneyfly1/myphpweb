import requests
import json
import base64
import os

def get_heduian_nodes():
    LOGIN_URL = 'https://www.heduian.my/auth/login'
    NODE_URL = 'https://www.heduian.my/getnodelist'
    EMAIL = 'kdaisywendy@gmail.com'
    PASSWORD = 'kdaisywendy'
    session = requests.Session()
    login_data = {
        'email': EMAIL,
        'passwd': PASSWORD,
        'remember_me': 'on'
    }
    links = []
    try:
        login_resp = session.post(LOGIN_URL, data=login_data)
        if login_resp.status_code != 200:
            print('[heduian] 登录失败')
            return []
        node_resp = session.get(NODE_URL)
        if node_resp.status_code != 200:
            print('[heduian] 获取节点信息失败')
            return []
        data = json.loads(node_resp.text)
        if data.get('ret') != 1 or not data.get('nodeinfo'):
            print('[heduian] API响应无效:', data)
            return []
        nodeinfo = data['nodeinfo']
        if 'nodes_muport' in nodeinfo and nodeinfo['nodes_muport'] and 'user' in nodeinfo['nodes_muport'][0]:
            user_info = nodeinfo['nodes_muport'][0]['user']
        else:
            user_info = nodeinfo['user']
        uuid = user_info['uuid']
        ss_password = user_info['passwd']
        method = user_info['method']
        for node in nodeinfo['nodes']:
            raw_node = node['raw_node']
            # VMESS节点 (分号分隔格式)
            if raw_node['server'].count(';') >= 3:
                server_parts = raw_node['server'].split(';')
                server = server_parts[0]
                port = server_parts[1]
                aid = server_parts[2] if len(server_parts) > 2 else '64'
                net = server_parts[3] if len(server_parts) > 3 else 'tcp'
                # 解析path和host
                host = ''
                path = ''
                if len(server_parts) > 5 and server_parts[5]:
                    for part in server_parts[5].split('|'):
                        if part.startswith('path='):
                            path = part[5:]
                        elif part.startswith('host='):
                            host = part[5:]
                vmess_config = {
                    "v": "2",
                    "ps": raw_node["name"],
                    "add": server,
                    "port": port,
                    "id": uuid,
                    "aid": str(aid),
                    "net": net,
                    "type": "none",
                    "host": host,
                    "path": path,
                    "tls": ""
                }
                vmess_link = base64.b64encode(json.dumps(vmess_config).encode()).decode()
                final_link = f'vmess://{vmess_link}'
                links.append(final_link)
            # VLESS节点 (port=格式)
            elif 'port=' in raw_node['server']:
                server_info = raw_node['server']
                # 解析服务器地址和参数
                parts = server_info.split(';')
                server = parts[0]
                
                # 解析参数
                params = {}
                if len(parts) > 1:
                    param_string = parts[1]
                    for param in param_string.split('&'):
                        if '=' in param:
                            key, value = param.split('=', 1)
                            params[key] = value
                
                port = params.get('port', '443')
                
                # 检查是否是 Hysteria2 协议（有 obfs 参数）
                obfs = params.get('obfs', '')
                obfs_password = params.get('obfs_password', params.get('obfs-password', ''))
                if obfs:
                    # 生成 Hysteria2 URL
                    # Hysteria2 格式: hysteria2://password@server:port?insecure=1&obfs=salamander&obfs-password=xxx&upmbps=1000&downmbps=1000#节点名称
                    # 对于使用 obfs 的 Hysteria2 节点：
                    # password: 使用 obfs_password（混淆密码作为认证密码），如果为空则使用 ss_password
                    # obfs-password: 使用 ss_password（用户密码作为混淆密码）
                    hysteria2_password = obfs_password if obfs_password else ss_password
                    hysteria2_url = f"hysteria2://{hysteria2_password}@{server}:{port}"
                    url_params = []
                    
                    # 添加 insecure 参数
                    allow_insecure = params.get('allow_insecure', params.get('insecure', ''))
                    if allow_insecure == '1' or allow_insecure.lower() == 'true':
                        url_params.append("insecure=1")
                    
                    # 添加 obfs 参数
                    if obfs:
                        url_params.append(f"obfs={obfs}")
                    
                    # 添加 obfs-password 参数（使用 ss_password）
                    if ss_password:
                        url_params.append(f"obfs-password={ss_password}")
                    
                    # 添加 upmbps 和 downmbps 参数
                    up_mbps = params.get('up_mbps', params.get('upmbps', ''))
                    if up_mbps:
                        url_params.append(f"upmbps={up_mbps}")
                    
                    down_mbps = params.get('down_mbps', params.get('downmbps', ''))
                    if down_mbps:
                        url_params.append(f"downmbps={down_mbps}")
                    
                    # 添加 SNI 参数（如果有）
                    sni = params.get('sni', params.get('serverName', ''))
                    if sni:
                        url_params.append(f"sni={sni}")
                    
                    if url_params:
                        hysteria2_url += "?" + "&".join(url_params)
                    
                    hysteria2_url += f"#{raw_node['name']}"
                    links.append(hysteria2_url)
                    continue
                
                # 如果不是 Hysteria2，继续处理 VLESS 节点
                flow = params.get('flow', '')
                security = params.get('security', 'none')
                sni = params.get('serverName', params.get('dest', ''))
                public_key = params.get('publicKey', '')
                short_id = params.get('shortId', '')
                
                # 检查是否是Reality配置
                if security == 'reality' and public_key:
                    # 生成Reality格式的VLESS URL
                    vless_url = f"vless://{uuid}@{server}:{port}"
                    url_params = []
                    
                    if sni:
                        url_params.append(f"sni={sni}")
                    url_params.append("security=reality")
                    
                    # 添加Reality参数
                    if public_key:
                        url_params.append(f"pbk={public_key}")
                    if short_id:
                        url_params.append(f"sid={short_id}")
                    
                    # 添加flow参数（通常是xtls-rprx-vision）
                    if flow:
                        url_params.append(f"flow={flow}")
                    
                    if url_params:
                        vless_url += "?" + "&".join(url_params)
                    
                    vless_url += f"#{raw_node['name']}"
                    links.append(vless_url)
                else:
                    # 生成标准VLESS配置
                    vless_config = {
                        "v": "2",
                        "ps": raw_node["name"],
                        "add": server,
                        "port": port,
                        "id": uuid,
                        "aid": "0",
                        "net": "tcp",
                        "type": "none",
                        "host": "",
                        "path": "",
                        "tls": security if security != 'none' else "",
                        "sni": sni,
                        "flow": flow
                    }
                    vless_link = base64.b64encode(json.dumps(vless_config).encode()).decode()
                    final_link = f'vless://{vless_link}'
                    links.append(final_link)
    except Exception as e:
        print('[heduian] 发生错误:', str(e))
    return links

def get_all_nodes():
    """获取heduian节点并返回节点列表"""
    print('开始获取heduian节点...')
    links_heduian = get_heduian_nodes()
    print(f'heduian节点数量: {len(links_heduian)}')
    
    # 去重处理
    print(f'去重前节点数量: {len(links_heduian)}')
    unique_links = list(dict.fromkeys(links_heduian))  # 保持顺序的去重
    print(f'去重后节点数量: {len(unique_links)}')
    print(f'去重数量: {len(links_heduian) - len(unique_links)}')
    
    return unique_links

if __name__ == '__main__':
    # 当直接运行脚本时，获取节点并打印信息
    nodes = get_all_nodes()
    print(f'总共获取到 {len(nodes)} 个唯一节点')
    
    # 预览前3个节点
    if nodes:
        print('前3个节点预览:')
        for i, node in enumerate(nodes[:3], 1):
            print(f'  {i}. {node}')
    else:
        print('没有获取到任何节点')
