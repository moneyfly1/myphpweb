#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
天猫VPN + Pilishai + 派大星VPN节点获取脚本 - VPS版本
适用于宝塔面板定时任务
输出路径: /www/wwwroot/dy.moneyfly.club/shell/tianmao.txt
功能: 获取天猫VPN节点、Pilishai VPN节点和派大星VPN节点并合并输出
"""

import requests
import uuid
import time
import random
import string
import os
import sys
import logging
from datetime import datetime
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import yaml
import base64
import urllib.parse
from pathlib import Path
import binascii
import json

# 尝试导入pyaes，如果失败则提供清晰的错误信息
try:
    import pyaes
except ImportError:
    print("错误: 缺少pyaes模块")
    print("请运行以下命令安装: pip3 install pyaes")
    print("或者在VPS上运行: pip3 install pyaes")
    exit(1)

# VPS路径配置
VPS_DIR = "/www/wwwroot/dy.moneyfly.club/shell"

# 采集开关配置 - 默认关闭
COLLECT_SUPERVPN = False  # SuperVPN 采集开关，True=开启，False=关闭
COLLECT_PAIDAXING = True  # 派大星VPN 采集开关，True=开启，False=关闭
COLLECT_VMESS = False  # VMess节点采集开关，True=开启，False=关闭

# 检查是否在VPS环境中运行
if os.path.exists("/www/wwwroot"):
    # 在VPS环境中，强制使用VPS目录
    VPS_DIR = "/www/wwwroot/dy.moneyfly.club/shell"
    print(f"检测到VPS环境，使用VPS目录: {VPS_DIR}")
else:
    # 本地测试时使用当前目录
    VPS_DIR = os.path.dirname(os.path.abspath(__file__))
    print(f"本地环境，使用当前目录: {VPS_DIR}")

# 确保输出目录存在
try:
    os.makedirs(VPS_DIR, exist_ok=True)
    print(f"输出目录已确认: {VPS_DIR}")
except Exception as e:
    print(f"创建输出目录失败: {e}")
    VPS_DIR = os.path.dirname(os.path.abspath(__file__))
    print(f"使用当前目录作为输出目录: {VPS_DIR}")

nodes_file = os.path.join(VPS_DIR, "tianmao.txt")
clash_file = os.path.join(VPS_DIR, "tianmao_clash.yaml")
base64_file = os.path.join(VPS_DIR, "tianmao64.txt")
log_file = os.path.join(VPS_DIR, "tianmao.log")

# 配置日志
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(log_file, encoding='utf-8'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Function to generate random email
def generate_random_email():
    random_string = ''.join(random.choices(string.ascii_lowercase + string.digits, k=8))
    return f"{random_string}@qq.com"

# Function to generate random User-Agent
def generate_random_user_agent():
    user_agents = [
        "okhttp/4.12.0",
        "Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Mobile Safari/537.36",
        "Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36",
        "Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1"
    ]
    return random.choice(user_agents)

# Function to get Pilishai VPN nodes
def get_pilishai_nodes(session):
    """
    获取 Pilishai VPN 节点
    """
    logger.info("开始获取 Pilishai VPN 节点")
    
    try:
        # Pilishai API 配置
        pilishai_url = "https://app.pilishavpn.com/vpn-api/business/equipment/add"
        pilishai_headers = {
            'Content-Type': 'application/json',
            'Connection': 'keep-alive',
            'Accept': '*/*',
            'User-Agent': 'Pilishai/1.1.0 (com.pilisha.pilisha; build:3; iOS 16.6.1) Alamofire/5.10.0',
            'Accept-Language': 'zh-Hans-US;q=1.0, en-US;q=0.9'
        }
        
        # 生成随机 MAC 地址
        mac_address = f"{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}-{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}-{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}-{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}-{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}{random.randint(0x00, 0xff):02X}"
        
        pilishai_data = {
            "macAddress": mac_address
        }
        
        logger.info(f"  - Pilishai API URL: {pilishai_url}")
        logger.info(f"  - MAC 地址: {mac_address}")
        logger.info(f"  - 发送请求到 Pilishai API...")
        
        # 发送请求
        response = session.post(pilishai_url, headers=pilishai_headers, json=pilishai_data, verify=True, timeout=15)
        logger.info(f"  - 收到响应，状态码: {response.status_code}")
        response.raise_for_status()
        
        result = response.json()
        logger.info(f"  - 解析响应数据成功")
        
        if result.get("code") != 200:
            logger.error(f"  - Pilishai API 返回错误: {result.get('msg', '未知错误')}")
            return []
        
        # 提取 vmess 节点
        vmess_list = result.get("data", {}).get("vmessList", [])
        logger.info(f"  - 找到 {len(vmess_list)} 个 Pilishai 节点")
        
        pilishai_nodes = []
        for i, vmess_url in enumerate(vmess_list):
            try:
                # 解析 vmess 节点
                if vmess_url.startswith("vmess://"):
                    # 解码 base64
                    encoded_data = vmess_url[8:]  # 移除 "vmess://" 前缀
                    decoded_data = base64.b64decode(encoded_data + "==").decode('utf-8')
                    
                    # 解析 JSON 配置
                    import json
                    vmess_config = json.loads(decoded_data)
                    
                    # vmess 节点不需要添加 # 和名称，保持原始格式
                    full_url = vmess_url
                    
                    node_info = {
                        "url": full_url,
                        "source": "pilishai"
                    }
                    pilishai_nodes.append(node_info)
                    logger.info(f"    - 添加 Pilishai 节点 {i+1}: {vmess_config.get('ps', f'Pilishai-{i+1}')}")
                    
            except Exception as e:
                logger.warning(f"    - 解析 Pilishai 节点 {i+1} 失败: {e}")
                continue
        
        logger.info(f"  - 成功获取 {len(pilishai_nodes)} 个 Pilishai 节点")
        return pilishai_nodes
        
    except requests.exceptions.SSLError:
        logger.warning("  - Pilishai API 遇到 SSL 错误，尝试禁用 SSL 验证...")
        try:
            logger.info("  - 重新发送 Pilishai 请求（禁用 SSL 验证）...")
            response = session.post(pilishai_url, headers=pilishai_headers, json=pilishai_data, verify=False, timeout=15)
            logger.info(f"  - 收到响应，状态码: {response.status_code}")
            response.raise_for_status()
            
            result = response.json()
            logger.info(f"  - 解析响应数据成功")
            
            if result.get("code") != 200:
                logger.error(f"  - Pilishai API 返回错误: {result.get('msg', '未知错误')}")
                return []
            
            # 提取 vmess 节点
            vmess_list = result.get("data", {}).get("vmessList", [])
            logger.info(f"  - 找到 {len(vmess_list)} 个 Pilishai 节点")
            
            pilishai_nodes = []
            for i, vmess_url in enumerate(vmess_list):
                try:
                    if vmess_url.startswith("vmess://"):
                        encoded_data = vmess_url[8:]
                        decoded_data = base64.b64decode(encoded_data + "==").decode('utf-8')
                        
                        import json
                        vmess_config = json.loads(decoded_data)
                        
                        # vmess 节点不需要添加 # 和名称，保持原始格式
                        full_url = vmess_url
                        
                        node_info = {
                            "url": full_url,
                            "source": "pilishai"
                        }
                        pilishai_nodes.append(node_info)
                        logger.info(f"    - 添加 Pilishai 节点 {i+1}: {vmess_config.get('ps', f'Pilishai-{i+1}')}")
                        
                except Exception as e:
                    logger.warning(f"    - 解析 Pilishai 节点 {i+1} 失败: {e}")
                    continue
            
            logger.info(f"  - 成功获取 {len(pilishai_nodes)} 个 Pilishai 节点")
            return pilishai_nodes
            
        except requests.RequestException as e:
            logger.error(f"  - Pilishai 节点获取失败: {e}")
            return []
    except requests.RequestException as e:
        logger.error(f"  - Pilishai 节点获取失败: {e}")
        return []
    except Exception as e:
        logger.error(f"  - 处理 Pilishai 节点时发生错误: {e}")
        return []


# Function to get Paidaxing VPN nodes
def get_paidaxing_nodes(session):
    """
    获取派大星VPN节点
    """
    logger.info("开始获取派大星VPN节点")
    
    try:
        # 派大星VPN API 配置
        paidaxing_url = "https://ioa.onskrgames.uk/getLines"
        paidaxing_headers = {
            'accept': '/',
            'accept-language': 'zh-Hans-CN;q=1, en-CN;q=0.9',
            'appversion': '1.3.1',
            'user-agent': 'SkrKK/1.3.1 (iPhone; iOS 13.5; Scale/2.00)',
            'content-type': 'application/x-www-form-urlencoded',
            'Cookie': 'PHPSESSID=fnffo1ivhvt0ouo6ebqn86a0d4'
        }
        
        paidaxing_data = {
            'data': '4265a9c353cd8624fd2bc7b5d75d2f18b1b5e66ccd37e2dfa628bcb8f73db2f14ba98bc6a1d8d0d1c7ff1ef0823b11264d0addaba2bd6a30bdefe06f4ba994ed'
        }
        
        # AES 解密参数
        paidaxing_key = b'65151f8d966bf596'
        paidaxing_iv = b'88ca0f0ea1ecf975'
        
        logger.info(f"  - 派大星VPN API URL: {paidaxing_url}")
        logger.info(f"  - 发送请求到派大星VPN API...")
        
        # 发送请求
        response = session.post(paidaxing_url, headers=paidaxing_headers, data=paidaxing_data, verify=True, timeout=15)
        logger.info(f"  - 收到响应，状态码: {response.status_code}")
        response.raise_for_status()
        
        if response.status_code == 200:
            # 解密响应数据
            encrypted_data = response.text.strip()
            logger.info(f"  - 开始解密派大星VPN数据...")
            
            try:
                # 十六进制解码
                encrypted_bytes = binascii.unhexlify(encrypted_data)
                
                # AES 解密函数
                def decrypt_paidaxing(data, key, iv):
                    cipher = pyaes.AESModeOfOperationCBC(key, iv=iv)
                    decrypted = b''.join(cipher.decrypt(data[i:i+16]) for i in range(0, len(data), 16))
                    # 移除 PKCS7 填充
                    return decrypted[:-decrypted[-1]]
                
                # 解密数据
                decrypted_data = decrypt_paidaxing(encrypted_bytes, paidaxing_key, paidaxing_iv)
                paidaxing_nodes_data = json.loads(decrypted_data)
                
                logger.info(f"  - 解密成功，找到 {len(paidaxing_nodes_data.get('data', []))} 个派大星VPN节点")
                
                paidaxing_nodes = []
                for i, node in enumerate(paidaxing_nodes_data.get('data', [])):
                    try:
                        # 构建 SS 节点 - 使用与天猫节点相同的格式
                        ss_config = f"aes-256-cfb:{node['password']}"
                        ss_base64 = base64.b64encode(ss_config.encode('utf-8')).decode('utf-8')
                        # 使用中文名称，与天猫节点格式保持一致
                        clean_title = node.get('title', f'节点{i+1}').replace(',', '').replace(' ', '')
                        node_url = f"ss://{ss_base64}@{node['ip']}:{node['port']}#派大星-{clean_title}"
                        
                        node_info = {
                            "url": node_url,
                            "source": "paidaxing"
                        }
                        paidaxing_nodes.append(node_info)
                        logger.info(f"    - 添加派大星VPN节点 {i+1}: 派大星-{node.get('title', f'节点{i+1}')}")
                        
                    except Exception as e:
                        logger.warning(f"    - 解析派大星VPN节点 {i+1} 失败: {e}")
                        continue
                
                logger.info(f"  - 成功获取 {len(paidaxing_nodes)} 个派大星VPN节点")
                return paidaxing_nodes
                
            except Exception as e:
                logger.error(f"  - 派大星VPN数据解密失败: {e}")
                return []
        else:
            logger.error(f"  - 派大星VPN API 返回错误状态码: {response.status_code}")
            return []
        
    except requests.exceptions.SSLError:
        logger.warning("  - 派大星VPN API 遇到 SSL 错误，尝试禁用 SSL 验证...")
        try:
            logger.info("  - 重新发送派大星VPN请求（禁用 SSL 验证）...")
            response = session.post(paidaxing_url, headers=paidaxing_headers, data=paidaxing_data, verify=False, timeout=15)
            logger.info(f"  - 收到响应，状态码: {response.status_code}")
            response.raise_for_status()
            
            if response.status_code == 200:
                encrypted_data = response.text.strip()
                logger.info(f"  - 开始解密派大星VPN数据...")
                
                try:
                    encrypted_bytes = binascii.unhexlify(encrypted_data)
                    
                    def decrypt_paidaxing(data, key, iv):
                        cipher = pyaes.AESModeOfOperationCBC(key, iv=iv)
                        decrypted = b''.join(cipher.decrypt(data[i:i+16]) for i in range(0, len(data), 16))
                        return decrypted[:-decrypted[-1]]
                    
                    decrypted_data = decrypt_paidaxing(encrypted_bytes, paidaxing_key, paidaxing_iv)
                    paidaxing_nodes_data = json.loads(decrypted_data)
                    
                    logger.info(f"  - 解密成功，找到 {len(paidaxing_nodes_data.get('data', []))} 个派大星VPN节点")
                    
                    paidaxing_nodes = []
                    for i, node in enumerate(paidaxing_nodes_data.get('data', [])):
                        try:
                            ss_config = f"aes-256-cfb:{node['password']}"
                            ss_base64 = base64.b64encode(ss_config.encode('utf-8')).decode('utf-8')
                            # 使用中文名称，与天猫节点格式保持一致
                            clean_title = node.get('title', f'节点{i+1}').replace(',', '').replace(' ', '')
                            node_url = f"ss://{ss_base64}@{node['ip']}:{node['port']}#派大星-{clean_title}"
                            
                            node_info = {
                                "url": node_url,
                                "source": "paidaxing"
                            }
                            paidaxing_nodes.append(node_info)
                            logger.info(f"    - 添加派大星VPN节点 {i+1}: 派大星-{node.get('title', f'节点{i+1}')}")
                            
                        except Exception as e:
                            logger.warning(f"    - 解析派大星VPN节点 {i+1} 失败: {e}")
                            continue
                    
                    logger.info(f"  - 成功获取 {len(paidaxing_nodes)} 个派大星VPN节点")
                    return paidaxing_nodes
                    
                except Exception as e:
                    logger.error(f"  - 派大星VPN数据解密失败: {e}")
                    return []
            else:
                logger.error(f"  - 派大星VPN API 返回错误状态码: {response.status_code}")
                return []
                
        except requests.RequestException as e:
            logger.error(f"  - 派大星VPN节点获取失败: {e}")
            return []
    except requests.RequestException as e:
        logger.error(f"  - 派大星VPN节点获取失败: {e}")
        return []
    except Exception as e:
        logger.error(f"  - 处理派大星VPN节点时发生错误: {e}")
        return []

# Function to generate headers
def generate_headers(device_id, token=None, auth_token=None):
    headers = {
        "deviceid": device_id,
        "devicetype": "1",
        "Content-Type": "application/json; charset=UTF-8",
        "Host": "api.tianmiao.icu",
        "Connection": "Keep-Alive",
        "Accept-Encoding": "gzip",
        "User-Agent": generate_random_user_agent()
    }
    if token and auth_token:
        headers["token"] = token
        headers["authtoken"] = auth_token
    return headers

# Function to create a session with retry logic
def create_session():
    session = requests.Session()
    retries = Retry(total=3, backoff_factor=1, status_forcelist=[429, 500, 502, 503, 504])
    session.mount("https://", HTTPAdapter(max_retries=retries))
    return session

# Function to sort nodes by region for display
def sort_nodes(nodes):
    region_order = ["HK-香港", "SG-新加坡", "JP-日本", "TW-台湾", "KR-韩国", "US-美国", "IDN-印尼", "MY-马来西亚"]
    sorted_nodes = []
    remaining_nodes = []
    
    for node in nodes:
        if "url" not in node:
            remaining_nodes.append(node)
            continue
            
        try:
            url_parts = node["url"].split("#")
            if len(url_parts) < 2:
                remaining_nodes.append(node)
                continue
                
            node_name = urllib.parse.unquote(url_parts[1])
            matched = False
            for region in region_order:
                if node_name.startswith(region):
                    sorted_nodes.append(node)
                    matched = True
                    break
            if not matched:
                remaining_nodes.append(node)
        except:
            remaining_nodes.append(node)
    
    return sorted_nodes + remaining_nodes

# Function to get SuperVPN nodes
def get_supervpn_nodes(session):
    """
    获取 SuperVPN 节点
    """
    logger.info("开始获取 SuperVPN 节点")
    
    try:
        # SuperVPN API 配置
        api_url = "https://api.9527.click/v2/node/list"
        headers = {
            'Host': 'api.9527.click',
            'Content-Type': 'application/json',
            'Connection': 'keep-alive',
            'Accept': '*/*',
            'User-Agent': 'International/3.3.35 (iPhone; iOS 18.0.1; Scale/3.00)',
            'Accept-Language': 'zh-Hans-CN;q=1',
            'Accept-Encoding': 'gzip, deflate, br'
        }
        
        uid = "3690911436885991424"
        payload = {
            "key": "G8Jxb2YtcONGmQwN7b5odg==",
            "uid": uid,
            "vercode": "1",
            "uuid": str(uuid.uuid4())
        }
        
        logger.info(f"  - SuperVPN API URL: {api_url}")
        logger.info(f"  - 发送请求到 SuperVPN API...")
        
        # 发送请求
        response = session.post(api_url, headers=headers, json=payload, timeout=15)
        
        if response.status_code == 200:
            data = response.json()
            logger.info(f"  - SuperVPN API 响应成功")
            
            if 'data' not in data:
                logger.warning("  - SuperVPN API 返回数据格式错误")
                return []
            
            # 解密节点数据
            encrypted_key = b'VXH2THdPBsHEp+TY'
            encrypted_iv = b'VXH2THdPBsHEp+TY'
            supervpn_nodes = []
            
            for node in data['data']:
                try:
                    # 解密IP和主机名
                    if 'ip' in node and node['ip']:
                        node['ip'] = decrypt_aes_data(node['ip'], encrypted_key, encrypted_iv)
                    
                    if 'host' in node and node['host']:
                        node['host'] = decrypt_aes_data(node['host'], encrypted_key, encrypted_iv)
                    
                    if 'ov_host' in node and node['ov_host']:
                        node['ov_host'] = decrypt_aes_data(node['ov_host'], encrypted_key, encrypted_iv)
                    
                    host = node.get('host') or node.get('ip')
                    name = node.get('name', 'Unknown')
                    
                    if host:
                        # 生成Trojan节点字典
                        trojan_node = {
                            "url": f"trojan://{uid}@{host}:443#{name}",
                            "name": name,
                            "type": "trojan"
                        }
                        supervpn_nodes.append(trojan_node)
                        
                except Exception as e:
                    logger.warning(f"  - 处理SuperVPN节点时出错: {e}")
                    continue
            
            logger.info(f"  - 成功获取 {len(supervpn_nodes)} 个 SuperVPN 节点")
            return supervpn_nodes
            
        else:
            logger.error(f"  - SuperVPN API 请求失败: {response.status_code}")
            return []
            
    except requests.RequestException as e:
        logger.error(f"  - SuperVPN 节点获取失败: {e}")
        return []
    except Exception as e:
        logger.error(f"  - 处理 SuperVPN 节点数据时发生错误: {e}")
        return []

def decrypt_aes_data(encrypted_data, key, iv):
    """
    使用AES算法解密数据
    """
    try:
        decrypted_data = base64.b64decode(encrypted_data)
        aes = pyaes.AESModeOfOperationCBC(key, iv=iv)
        decrypted_output = b""
        while decrypted_data:
            decrypted_output += aes.decrypt(decrypted_data[:16])
            decrypted_data = decrypted_data[16:]
        padding_length = decrypted_output[-1]
        return decrypted_output[:-padding_length].decode('utf-8')
    except Exception as e:
        logger.warning(f"  - AES解密失败: {e}")
        return encrypted_data

# Function to get VMess nodes
def get_vmess_nodes(session):
    """
    获取VMess节点（从m4twf.xyz API）
    """
    logger.info("开始获取 VMess 节点")
    
    try:
        # VMess API 配置
        vmess_url = "https://www.m4twf.xyz:20000/api/evmess?&proto=v2&platform=android&googleplay=1&ver=3.0.5&deviceid=1bcec3395995cf19unknown&unicode=1bcec3395995cf19unknown&t=1717462751804&code=9GFZ2R&recomm_code=&f=2024-06-04&install=2024-06-04&token=amSTaWVnkZWOk2xscWlsb5mZbmRolGuRZ2mQl5Jrkmhnaw==&package=com.honeybee.network&area="
        
        # 解密密钥和IV
        vmess_key = b'ks9KUrbWJj46AftX'
        vmess_iv = b'ks9KUrbWJj46AftX'
        
        # 存储解密后的节点信息
        decrypted_nodes = set()
        
        # IP地理位置查询缓存（避免重复查询相同IP）
        ip_country_cache = {}
        
        def get_country_by_ip(ip_address):
            """根据IP地址查询国家名称（使用免费API）"""
            if not ip_address:
                return "未知"
            
            # 检查缓存
            if ip_address in ip_country_cache:
                return ip_country_cache[ip_address]
            
            try:
                # 使用免费的ip-api.com API查询IP地理位置
                # 免费版限制：每分钟45次请求
                api_url = f"http://ip-api.com/json/{ip_address}?fields=status,country,countryCode&lang=zh-CN"
                response = session.get(api_url, timeout=5)
                
                if response.status_code == 200:
                    data = response.json()
                    if data.get('status') == 'success':
                        country = data.get('country', '未知')
                        # 缓存结果
                        ip_country_cache[ip_address] = country
                        return country
                    else:
                        ip_country_cache[ip_address] = "未知"
                        return "未知"
                else:
                    ip_country_cache[ip_address] = "未知"
                    return "未知"
            except Exception as e:
                logger.debug(f"    - IP查询失败 {ip_address}: {e}")
                ip_country_cache[ip_address] = "未知"
                return "未知"
        
        def fix_vmess_node_name(vmess_url_str, country_name, node_number):
            """解析vmess链接，根据国家名称和序号生成节点名称"""
            if not vmess_url_str.startswith('vmess://'):
                return vmess_url_str
            
            try:
                # 提取base64部分
                b64_part = vmess_url_str[8:]
                # 解码JSON
                json_str = base64.b64decode(b64_part).decode('utf-8')
                data = json.loads(json_str)
                
                # 检查ps字段是否是数字，或者是需要替换的格式
                ps = data.get('ps')
                if isinstance(ps, (int, float)) or (isinstance(ps, str) and ps.isdigit()) or not ps:
                    # 生成新的节点名称：国家名+序号（如：日本01）
                    new_name = f"{country_name}{node_number:02d}"
                    data['ps'] = new_name
                    
                    # 重新编码
                    new_json_str = json.dumps(data, separators=(',', ':'))
                    new_b64 = base64.b64encode(new_json_str.encode('utf-8')).decode('utf-8')
                    return f"vmess://{new_b64}"
                else:
                    # ps已经是字符串，直接返回原链接
                    return vmess_url_str
            except Exception as e:
                logger.warning(f"    - 修复节点名称失败：{e}")
                return vmess_url_str
        
        def fetch_and_decrypt_vmess():
            """获取并解密单个VMess节点"""
            try:
                random_suffix = random.randint(1, 100)
                response = session.get(vmess_url + str(random_suffix), timeout=15)
                if response.status_code == 200:
                    encrypted_data = response.text.strip()
                    try:
                        # 将Base64编码的加密数据解码
                        encrypted_data_bytes = base64.b64decode(encrypted_data)
                        # 使用Crypto.Cipher.AES解密（与原始get_vmess.py保持一致）
                        try:
                            from Crypto.Cipher import AES
                            cipher = AES.new(vmess_key, AES.MODE_CBC, vmess_iv)
                            decrypted_data = cipher.decrypt(encrypted_data_bytes)
                            return decrypted_data.decode('utf-8', errors='ignore').rstrip('\x00')
                        except ImportError:
                            # 如果Crypto不可用，使用pyaes（备用方案）
                            cipher = pyaes.AESModeOfOperationCBC(vmess_key, iv=vmess_iv)
                            decrypted_output = b""
                            for i in range(0, len(encrypted_data_bytes), 16):
                                chunk = encrypted_data_bytes[i:i+16]
                                if len(chunk) == 16:
                                    decrypted_output += cipher.decrypt(chunk)
                            # 移除PKCS7填充
                            if decrypted_output:
                                padding_length = decrypted_output[-1]
                                if padding_length <= 16:
                                    decrypted_data = decrypted_output[:-padding_length]
                                    return decrypted_data.decode('utf-8', errors='ignore').rstrip('\x00')
                            return None
                    except Exception as e:
                        logger.debug(f"    - 解密失败：{e}")
                        return None
                else:
                    logger.debug(f"    - 请求失败，状态码: {response.status_code}")
                    return None
            except Exception as e:
                logger.debug(f"    - 获取节点失败：{e}")
                return None
        
        logger.info(f"  - VMess API URL: {vmess_url}")
        logger.info(f"  - 开始获取并解密节点信息（尝试50次）...")
        
        # 重要：重复获取并解密节点信息50次，使用set自动去重
        # 这是关键步骤，必须获取50次才能获得足够的唯一节点
        for i in range(50):
            node_info = fetch_and_decrypt_vmess()
            if node_info:
                # 使用set自动去重，确保每个节点只保存一次
                decrypted_nodes.add(node_info)
            if (i + 1) % 10 == 0:
                logger.info(f"    - 已尝试 {i+1}/50 次，获取到 {len(decrypted_nodes)} 个唯一节点")
        
        logger.info(f"  - 50次获取完成，共获取到 {len(decrypted_nodes)} 个唯一节点（已自动去重）")
        
        # 第一步：解析所有节点，提取IP地址并查询国家
        logger.info(f"  - 开始查询节点IP地理位置...")
        nodes_with_country = []
        total_nodes = len(decrypted_nodes)
        processed = 0
        
        for node in decrypted_nodes:
            try:
                if not node.startswith('vmess://'):
                    logger.debug(f"    - 跳过非vmess节点: {node[:50]}...")
                    continue
                
                # 解析节点获取IP地址
                b64_part = node[8:]
                json_str = base64.b64decode(b64_part).decode('utf-8')
                node_data = json.loads(json_str)
                ip_address = node_data.get('add')
                
                if not ip_address:
                    logger.warning(f"    - 节点缺少IP地址: {node_data}")
                    continue
                
                logger.debug(f"    - 解析节点成功，IP: {ip_address}")
                
                # 查询IP地址对应的国家（带缓存，相同IP不会重复查询）
                country = get_country_by_ip(ip_address)
                
                nodes_with_country.append({
                    'url': node,
                    'ip': ip_address,
                    'country': country,
                    'data': node_data
                })
                
                processed += 1
                # 每处理10个节点显示一次进度
                if processed % 10 == 0:
                    logger.info(f"    - 已处理 {processed}/{total_nodes} 个节点...")
                
                # 添加延迟，避免API请求过快（ip-api.com免费版限制每分钟45次）
                # 延迟1.5秒，确保每分钟最多40次请求
                time.sleep(1.5)
                
            except Exception as e:
                logger.debug(f"    - 解析节点失败: {e}")
                continue
        
        logger.info(f"  - 完成IP地理位置查询，共 {len(nodes_with_country)} 个节点")
        
        # 第二步：按国家分组并编号
        logger.info(f"  - 开始按国家分组并编号...")
        country_groups = {}
        for node_info in nodes_with_country:
            country = node_info['country']
            if country not in country_groups:
                country_groups[country] = []
            country_groups[country].append(node_info)
        
        logger.info(f"  - 节点按国家分组完成，共 {len(country_groups)} 个国家/地区")
        for country, nodes in country_groups.items():
            logger.info(f"    - {country}: {len(nodes)} 个节点")
        
        # 第三步：对每个国家的节点进行编号并生成最终节点列表
        vmess_nodes = []
        for country, nodes in country_groups.items():
            for index, node_info in enumerate(nodes, start=1):
                try:
                    # 生成节点名称：国家名+序号（如：日本01、日本02）
                    fixed_node = fix_vmess_node_name(node_info['url'], country, index)
                    
                    if fixed_node and fixed_node.startswith('vmess://'):
                        node_name = f"{country}{index:02d}"
                        
                        node_result = {
                            "url": fixed_node,
                            "source": "vmess"
                        }
                        vmess_nodes.append(node_result)
                        logger.debug(f"    - 添加VMess节点: {node_name} (IP: {node_info['ip']})")
                except Exception as e:
                    logger.warning(f"    - 处理VMess节点失败: {e}")
                    continue
        
        logger.info(f"  - 成功获取并命名 {len(vmess_nodes)} 个 VMess 节点")
        return vmess_nodes
        
    except requests.RequestException as e:
        logger.error(f"  - VMess节点获取失败: {e}")
        return []
    except Exception as e:
        logger.error(f"  - 处理VMess节点时发生错误: {e}")
        return []

# Function to get node priority for sorting in proxy groups
def get_node_priority(node_name):
    priority_map = {
        "HK-香港": 1,
        "SG-新加坡": 2,
        "JP-日本": 3,
        "TW-台湾": 4,
        "KR-韩国": 5,
        "US-美国": 6
    }
    
    asian_regions = ["CN-中国", "TH-泰国", "VN-越南", "PH-菲律宾", "IN-印度", 
                     "IDN-印尼", "MY-马来西亚", "KH-柬埔寨", "LA-老挝", "MM-缅甸"]
    
    southeast_asian_regions = ["TH-泰国", "VN-越南", "PH-菲律宾", "ID-印尼", 
                              "MY-马来西亚", "KH-柬埔寨", "LA-老挝", "MM-缅甸", "SG-新加坡"]
    
    for region, priority in priority_map.items():
        if node_name.startswith(region):
            return priority
    
    for region in asian_regions:
        if node_name.startswith(region):
            return 7
    
    for region in southeast_asian_regions:
        if node_name.startswith(region) and not any(node_name.startswith(r) for r in priority_map.keys()):
            return 7
    
    return 8

# Function to save nodes to file
def save_nodes_to_file(nodes, file_path):
    try:
        # 确保目录存在
        os.makedirs(os.path.dirname(file_path), exist_ok=True)
        
        with open(file_path, 'w', encoding='utf-8') as f:
            for node in nodes:
                if "url" not in node:
                    continue
                    
                try:
                    url_parts = node["url"].split("#")
                    if len(url_parts) < 2:
                        f.write(f"{node['url']}\n")
                    else:
                        decoded_name = urllib.parse.unquote(url_parts[1])
                        f.write(f"{url_parts[0]}#{decoded_name}\n")
                except:
                    f.write(f"{node['url']}\n")
        logger.info(f"节点文件已保存至: {file_path}")
        return file_path
    except IOError as e:
        logger.error(f"保存节点到文件失败: {e}")
        return None

# Function to generate base64 subscription for v2rayn and soft router
def generate_base64_subscription(nodes, file_path):
    """
    生成base64格式的订阅文件，适用于v2rayn、软路由等客户端
    """
    try:
        # 确保目录存在
        os.makedirs(os.path.dirname(file_path), exist_ok=True)
        
        # 收集所有节点URL
        node_urls = []
        for node in nodes:
            if "url" not in node:
                continue
            node_urls.append(node["url"])
        
        if not node_urls:
            logger.warning("没有找到有效的节点URL")
            return None
        
        # 清理节点URL，保持中文名称不变
        cleaned_urls = []
        for url in node_urls:
            # 移除可能的空白字符
            url = url.strip()
            if url and url.startswith(('ss://', 'ssr://', 'vmess://', 'trojan://', 'vless://')):
                # 检查URL格式完整性
                if url.startswith('vmess://'):
                    # vmess 节点格式: vmess://base64 (不需要 # 符号)
                    cleaned_urls.append(url)
                    logger.info(f"保持vmess节点: {url[:50]}...")
                elif url.count('@') == 1 and '#' in url:
                    # 其他节点格式: protocol://auth@server:port#name
                    cleaned_urls.append(url)
                    logger.info(f"保持原始节点: {url.split('#')[1] if '#' in url else '未知'}")
                elif url.startswith('ss://') and '#' in url and '@' not in url:
                    # SS节点格式: ss://base64#name (派大星节点格式)
                    cleaned_urls.append(url)
                    logger.info(f"保持SS节点: {url.split('#')[1] if '#' in url else '未知'}")
                else:
                    logger.warning(f"跳过格式不完整的节点: {url[:50]}...")
        
        if not cleaned_urls:
            logger.warning("没有找到有效的节点URL格式")
            return None
        
        # 将所有节点URL用换行符连接，确保每行一个节点
        subscription_content = "\n".join(cleaned_urls)
        
        # 确保内容以换行符结尾（软路由关键要求）
        subscription_content += '\n'
        
        # 验证生成的订阅内容
        logger.info(f"订阅内容预览: {subscription_content[:100]}...")
        logger.info(f"订阅内容长度: {len(subscription_content)} 字符")
        logger.info(f"是否以换行符结尾: {subscription_content.endswith(chr(10))}")
        
        # 将内容进行base64编码
        base64_content = base64.b64encode(subscription_content.encode('utf-8')).decode('utf-8')
        
        # 保存base64编码的内容到文件
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(base64_content)
        
        # 验证生成的文件
        logger.info(f"Base64订阅文件已保存至: {file_path}")
        logger.info(f"订阅包含 {len(cleaned_urls)} 个节点")
        logger.info(f"Base64文件大小: {len(base64_content)} 字符")
        
        # 验证文件格式
        try:
            # 重新解码验证
            test_decoded = base64.b64decode(base64_content).decode('utf-8')
            test_lines = test_decoded.split('\n')
            valid_lines = [line for line in test_lines if line.strip()]
            
            logger.info(f"验证结果: {len(valid_lines)} 个有效节点")
            logger.info(f"格式验证: 以换行符结尾 = {test_decoded.endswith(chr(10))}")
            logger.info("✅ 软路由订阅格式验证通过")
            
        except Exception as e:
            logger.error(f"格式验证失败: {e}")
        
        logger.info("支持软路由、v2rayn等客户端订阅")
        return file_path
        
    except Exception as e:
        logger.error(f"生成Base64订阅失败: {e}")
        return None

# Function to generate Clash config
def generate_clash_config(nodes, file_path):
    flag_emoji_map = {
        "HK-香港": "🇭🇰", "SG-新加坡": "🇸🇬", "JP-日本": "🇯🇵", "TW-台湾": "🇹🇼",
        "KR-韩国": "🇰🇷", "US-美国": "🇺🇸", "IDN-印尼": "🇮🇩", "MY-马来西亚": "🇲🇾",
        "CN-中国": "🇨🇳", "TH-泰国": "🇹🇭", "VN-越南": "🇻🇳", "PH-菲律宾": "🇵🇭",
        "IN-印度": "🇮🇳", "KH-柬埔寨": "🇰🇭", "LA-老挝": "🇱🇦", "MM-缅甸": "🇲🇲",
        "FR-法国": "🇫🇷", "TR-土耳其": "🇹🇷", "RU-俄罗斯": "🇷🇺", "MX-墨西哥": "🇲🇽",
        "AR-阿根廷": "🇦🇷", "UK-英国": "🇬🇧", "DXB-迪拜": "🇦🇪"
    }
    clash_config = {
        "dns": {
            "enable": True,
            "nameserver": ["119.29.29.29", "223.5.5.5"],
            "nameserver-policy": {
                "ChinaClassical,Apple,SteamCN,geosite:cn": ["tls://1.12.12.12", "223.5.5.5"]
            },
            "fallback": ["8.8.8.8", "1.1.1.1", "tls://dns.google:853", "tls://1.0.0.1:853"]
        },
        "proxies": [],
        "proxy-groups": [
            {"name": "🚀 节点选择", "type": "select", "proxies": []},
            {"name": "🌍 国外媒体", "type": "select", "proxies": ["🚀 节点选择", "🎯 全球直连"]},
            {"name": "Ⓜ️ 微软服务", "type": "select", "proxies": ["🎯 全球直连", "🚀 节点选择"]},
            {"name": "🍎 苹果服务", "type": "select", "proxies": ["🎯 全球直连", "🚀 节点选择"]},
            {"name": "📦 PikPak", "type": "select", "proxies": ["🚀 节点选择", "🎯 全球直连"]},
            {"name": "🤖 OpenAI", "type": "select", "proxies": ["🚀 节点选择", "🎯 全球直连"]},
            {"name": "🐟 漏网之鱼", "type": "select", "proxies": ["🚀 节点选择", "🎯 全球直连"]},
            {"name": "🎯 全球直连", "type": "select", "proxies": ["DIRECT"]}
        ],
        "rules": [
            "IP-CIDR,129.146.160.80/32,DIRECT,no-resolve",
            "IP-CIDR,148.135.52.61/32,DIRECT,no-resolve",
            "IP-CIDR,148.135.56.101/32,DIRECT,no-resolve",
            "IP-CIDR,37.123.193.133/32,DIRECT,no-resolve",
            "IP-CIDR,111.119.203.69/32,DIRECT,no-resolve",
            "IP-CIDR,110.238.105.126/32,DIRECT,no-resolve",
            "IP-CIDR,166.108.206.148/32,DIRECT,no-resolve",
            "IP-CIDR,155.248.181.42/32,DIRECT,no-resolve",
            "IP-CIDR,176.126.114.184/32,DIRECT,no-resolve",
            "IP-CIDR,103.238.129.152/32,DIRECT,no-resolve",
            "IP-CIDR,45.66.217.124/32,DIRECT,no-resolve",
            "IP-CIDR,183.2.133.144/32,DIRECT,no-resolve",
            "IP-CIDR,103.103.245.13/32,DIRECT,no-resolve",
            "DOMAIN,oiyun.de,DIRECT",
            "DOMAIN,github.moeyy.xyz,DIRECT",
            "DOMAIN,hk.xybhdy.top,DIRECT",
            "DOMAIN,hd1dc.com,DIRECT",
            "RULE-SET,LocalAreaNetwork,DIRECT",
            "RULE-SET,BanAD,REJECT",
            "RULE-SET,BanAdobe,REJECT",
            "RULE-SET,GoogleFCM,🚀 节点选择",
            "RULE-SET,SteamCN,DIRECT",
            "RULE-SET,Microsoft,Ⓜ️ 微软服务",
            "RULE-SET,Apple,🍎 苹果服务",
            "RULE-SET,Telegram,🚀 节点选择",
            "RULE-SET,PikPak,📦 PikPak",
            "RULE-SET,OpenAI,🤖 OpenAI",
            "RULE-SET,Claude,🤖 OpenAI",
            "RULE-SET,Gemini,🤖 OpenAI",
            "RULE-SET,ProxyMedia,🌍 国外媒体",
            "RULE-SET,ProxyClassical,🚀 节点选择",
            "RULE-SET,ChinaCIDr,DIRECT",
            "RULE-SET,ChinaClassical,DIRECT",
            "GEOIP,CN,DIRECT",
            "MATCH,🐟 漏网之鱼"
        ],
        "rule-providers": {
            "Apple": {"behavior": "classical", "interval": 604800, "path": "./rules/Apple.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/refs/heads/master/rule/Clash/Apple/Apple.yaml"},
            "BanAD": {"behavior": "domain", "interval": 604800, "path": "./rules/BanAD.yaml", "type": "http", "url": "https://raw.githubusercontent.com/Loyalsoldier/clash-rules/release/reject.txt"},
            "BanAdobe": {"behavior": "classical", "interval": 604800, "path": "./rules/BanAdobe.yaml", "type": "http", "url": "https://raw.githubusercontent.com/ignaciocastro/a-dove-is-dumb/main/clash.yaml"},
            "ChinaCIDr": {"behavior": "ipcidr", "interval": 604800, "path": "./rules/CNCIDR.yaml", "type": "http", "url": "https://raw.githubusercontent.com/Loyalsoldier/clash-rules/release/cncidr.txt"},
            "ChinaClassical": {"behavior": "domain", "interval": 604800, "path": "./rules/ChinaClassical.yaml", "type": "http", "url": "https://raw.githubusercontent.com/Loyalsoldier/clash-rules/release/direct.txt"},
            "Claude": {"behavior": "classical", "interval": 604800, "path": "./rules/Claude.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/master/rule/Clash/Claude/Claude.yaml"},
            "Gemini": {"behavior": "classical", "interval": 604800, "path": "./rules/Gemini.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/master/rule/Clash/Gemini/Gemini.yaml"},
            "GoogleFCM": {"behavior": "classical", "interval": 604800, "path": "./rules/GoogleFCM.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/refs/heads/master/rule/Clash/GoogleFCM/GoogleFCM.yaml"},
            "LocalAreaNetwork": {"behavior": "classical", "interval": 604800, "path": "./rules/LocalAreaNetwork.yaml", "type": "http", "url": "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/Providers/LocalAreaNetwork.yaml"},
            "Microsoft": {"behavior": "classical", "interval": 604800, "path": "./rules/Microsoft.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/refs/heads/master/rule/Clash/Microsoft/Microsoft.yaml"},
            "OpenAI": {"behavior": "classical", "interval": 604800, "path": "./rules/OpenAI.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/master/rule/Clash/OpenAI/OpenAI.yaml"},
            "PikPak": {"behavior": "classical", "interval": 604800, "path": "./rules/PikPak.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/master/rule/Clash/PikPak/PikPak.yaml"},
            "ProxyClassical": {"behavior": "domain", "interval": 604800, "path": "./rules/ProxyClassical.yaml", "type": "http", "url": "https://raw.githubusercontent.com/Loyalsoldier/clash-rules/release/proxy.txt"},
            "ProxyMedia": {"behavior": "classical", "interval": 604800, "path": "./rules/ProxyMedia.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/master/rule/Clash/GlobalMedia/GlobalMedia_Classical.yaml"},
            "SteamCN": {"behavior": "classical", "interval": 604800, "path": "./rules/SteamCN.yaml", "type": "http", "url": "https://raw.githubusercontent.com/blackmatrix7/ios_rule_script/refs/heads/master/rule/Clash/SteamCN/SteamCN.yaml"},
            "Telegram": {"behavior": "classical", "interval": 604800, "path": "./rules/Telegram.yaml", "type": "http", "url": "https://raw.githubusercontent.com/ACL4SSR/ACL4SSR/master/Clash/Providers/Ruleset/Telegram.yaml"}
        }
    }
    
    node_info_list = []
    
    for node in nodes:
        if "url" not in node:
            continue
            
        try:
            url = node["url"]
            if "#" not in url:
                continue
                
            url_parts = url.split("#")
            if len(url_parts) < 2:
                continue
                
            name = urllib.parse.unquote(url_parts[1])
            
            flag_added = False
            for region, emoji in flag_emoji_map.items():
                if name.startswith(region):
                    name = f"{emoji}{name}"
                    flag_added = True
                    break
            if not flag_added:
                name = f"🌐{name}"
            
            if url_parts[0].startswith("trojan://"):
                # 处理Trojan节点
                trojan_url = url_parts[0]
                if "@" not in trojan_url:
                    continue
                    
                # 解析trojan://password@server:port#name
                auth_part, server_port = trojan_url.split("@")
                password = auth_part.replace("trojan://", "")
                
                server_port_parts = server_port.split(":")
                if len(server_port_parts) < 2:
                    continue
                    
                server = server_port_parts[0]
                port = server_port_parts[1].split("/")[0] if "/" in server_port_parts[1] else server_port_parts[1]
                
                proxy = {
                    "name": name,
                    "type": "trojan",
                    "server": server,
                    "port": int(port),
                    "password": password,
                    "sni": server,
                    "udp": True
                }
            elif "@" in url_parts[0]:
                # 处理SS节点
                auth_part, server_port = url_parts[0].split("@")
                if "://" not in auth_part:
                    continue
                    
                base64_auth = auth_part.split("://")[1]
                try:
                    cipher_password = base64.b64decode(base64_auth + "==").decode("utf-8")
                except:
                    continue
                
                if ":" not in cipher_password:
                    continue
                    
                cipher, password = cipher_password.split(":", 1)
                
                server_port_parts = server_port.split(":")
                if len(server_port_parts) < 2:
                    continue
                    
                server = server_port_parts[0]
                port = server_port_parts[1].split("/")[0] if "/" in server_port_parts[1] else server_port_parts[1]
                
                proxy = {
                    "name": name,
                    "type": "ss",
                    "server": server,
                    "port": int(port),
                    "cipher": cipher,
                    "password": password,
                    "udp": True
                }
            else:
                continue
            
            priority = get_node_priority(urllib.parse.unquote(url_parts[1]))
            node_info_list.append({
                "proxy": proxy,
                "priority": priority,
                "name": name
            })
            
        except Exception as e:
            logger.error(f"解析节点 {node.get('url', '未知')} 失败: {e}")
            continue
    
    node_info_list.sort(key=lambda x: (x["priority"], x["name"]))
    
    for node_info in node_info_list:
        clash_config["proxies"].append(node_info["proxy"])
        clash_config["proxy-groups"][0]["proxies"].append(node_info["name"])
    
    try:
        # 确保目录存在
        os.makedirs(os.path.dirname(file_path), exist_ok=True)
        
        with open(file_path, 'w', encoding='utf-8') as f:
            yaml.dump(clash_config, f, allow_unicode=True, sort_keys=False)
        logger.info(f"Clash配置文件已保存至: {file_path}")
        return file_path
    except IOError as e:
        logger.error(f"保存Clash配置文件失败: {e}")
        return None

# Main function
def main():
    logger.info("=" * 50)
    logger.info("天猫VPN + Pilishai + 派大星VPN节点获取脚本启动")
    logger.info(f"开始时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    # 显示路径信息
    logger.info(f"输出目录: {VPS_DIR}")
    logger.info(f"节点文件: {nodes_file}")
    logger.info(f"Clash文件: {clash_file}")
    logger.info(f"Base64文件: {base64_file}")
    logger.info(f"日志文件: {log_file}")
    
    # 显示采集开关状态
    logger.info("=" * 30)
    logger.info("采集开关状态:")
    logger.info(f"  - SuperVPN 采集: {'开启' if COLLECT_SUPERVPN else '关闭'}")
    logger.info(f"  - 派大星VPN 采集: {'开启' if COLLECT_PAIDAXING else '关闭'}")
    logger.info(f"  - VMess节点采集: {'开启' if COLLECT_VMESS else '关闭'}")
    logger.info("=" * 30)
    
    # 步骤1: 初始化参数
    logger.info("步骤1: 初始化参数")
    device_id = str(uuid.uuid4())
    email = generate_random_email()
    password = "asd789369"
    invite_code = "ghqhsqRD"
    logger.info(f"  - 设备ID: {device_id}")
    logger.info(f"  - 生成邮箱: {email}")
    logger.info(f"  - 邀请码: {invite_code}")
    
    # 步骤2: 创建会话
    logger.info("步骤2: 创建HTTP会话")
    session = create_session()
    logger.info("  - HTTP会话创建成功")

    # 步骤3: 开始注册流程
    logger.info("步骤3: 开始注册账户")
    register_url = "https://api.tianmiao.icu/api/register"
    register_data = {
        "email": email,
        "invite_code": "",
        "password": password,
        "password_word": password
    }
    headers = generate_headers(device_id)
    logger.info(f"  - 注册URL: {register_url}")
    logger.info(f"  - 请求头已生成")
    
    try:
        logger.info("  - 发送注册请求...")
        response = session.post(register_url, headers=headers, json=register_data, verify=True, timeout=10)
        logger.info(f"  - 收到响应，状态码: {response.status_code}")
        response.raise_for_status()
        result = response.json()
        logger.info(f"  - 解析响应数据: {result}")
        
        if result.get("code") != 1:
            logger.error(f"  - 注册失败: {result.get('message')}")
            return False
        
        token = result["data"]["auth_data"]
        auth_token = result["data"]["token"]
        logger.info(f"  - 注册成功: 邮箱 {email}")
        logger.info(f"  - 获取到Token: {token[:20]}...")
        logger.info(f"  - 获取到AuthToken: {auth_token[:20]}...")
        
    except requests.exceptions.SSLError:
        logger.warning("  - 注册中遇到SSL错误，尝试禁用SSL验证...")
        try:
            logger.info("  - 重新发送注册请求（禁用SSL验证）...")
            response = session.post(register_url, headers=headers, json=register_data, verify=False, timeout=10)
            logger.info(f"  - 收到响应，状态码: {response.status_code}")
            response.raise_for_status()
            result = response.json()
            logger.info(f"  - 解析响应数据: {result}")
            
            if result.get("code") != 1:
                logger.error(f"  - 注册失败: {result.get('message')}")
                return False
                
            token = result["data"]["auth_data"]
            auth_token = result["data"]["token"]
            logger.info(f"  - 注册成功: 邮箱 {email}")
            logger.info(f"  - 获取到Token: {token[:20]}...")
            logger.info(f"  - 获取到AuthToken: {auth_token[:20]}...")
            
        except requests.RequestException as e:
            logger.error(f"  - 注册失败: {e}")
            return False
    except requests.RequestException as e:
        logger.error(f"  - 注册失败: {e}")
        return False
    
    # 步骤4: 等待随机时间
    wait_time = random.uniform(2, 5)
    logger.info(f"步骤4: 等待 {wait_time:.2f} 秒...")
    time.sleep(wait_time)

    # 步骤5: 绑定邀请码
    logger.info("步骤5: 开始绑定邀请码")
    bind_url = "https://api.tianmiao.icu/api/bandInviteCode"
    bind_data = {"invite_code": invite_code}
    headers = generate_headers(device_id, token, auth_token)
    logger.info(f"  - 绑定URL: {bind_url}")
    logger.info(f"  - 邀请码: {invite_code}")
    logger.info(f"  - 更新请求头（包含Token）")
    
    try:
        logger.info("  - 发送绑定邀请码请求...")
        response = session.post(bind_url, headers=headers, json=bind_data, verify=True, timeout=10)
        logger.info(f"  - 收到响应，状态码: {response.status_code}")
        response.raise_for_status()
        result = response.json()
        logger.info(f"  - 解析响应数据: {result}")
        
        if result.get("code") != 1:
            logger.error(f"  - 邀请码绑定失败: {result.get('message')}")
            return False
        
        logger.info(f"  - 邀请码绑定成功: {invite_code}")
        
    except requests.exceptions.SSLError:
        logger.warning("  - 绑定邀请码遇到SSL错误，尝试禁用SSL验证...")
        try:
            logger.info("  - 重新发送绑定请求（禁用SSL验证）...")
            response = session.post(bind_url, headers=headers, json=bind_data, verify=False, timeout=10)
            logger.info(f"  - 收到响应，状态码: {response.status_code}")
            response.raise_for_status()
            result = response.json()
            logger.info(f"  - 解析响应数据: {result}")
            
            if result.get("code") != 1:
                logger.error(f"  - 邀请码绑定失败: {result.get('message')}")
                return False
                
            logger.info(f"  - 邀请码绑定成功: {invite_code}")
        except requests.RequestException as e:
            logger.error(f"  - 邀请码绑定失败: {e}")
            return False
    except requests.RequestException as e:
        logger.error(f"  - 邀请码绑定失败: {e}")
        return False
    
    # 步骤6: 等待随机时间
    wait_time = random.uniform(2, 5)
    logger.info(f"步骤6: 等待 {wait_time:.2f} 秒...")
    time.sleep(wait_time)

    # 步骤7: 获取节点列表
    logger.info("步骤7: 开始获取节点列表")
    node_url = "https://api.tianmiao.icu/api/nodeListV2"
    node_data = {
        "protocol": "all",
        "include_ss": "1",
        "include_shadowsocks": "1",
        "include_trojan": "1"
    }
    logger.info(f"  - 节点列表URL: {node_url}")
    logger.info(f"  - 请求参数: {node_data}")
    
    try:
        logger.info("  - 发送获取节点列表请求...")
        response = session.post(node_url, headers=headers, json=node_data, verify=True, timeout=10)
        logger.info(f"  - 收到响应，状态码: {response.status_code}")
        response.raise_for_status()
        result = response.json()
        logger.info(f"  - 解析响应数据成功")
        
        if result.get("code") != 1:
            logger.error(f"  - 节点列表获取失败: {result.get('message')}")
            return False
        
        logger.info("  - 节点列表获取成功")
        
        # 步骤8: 解析节点数据
        logger.info("步骤8: 解析节点数据")
        vip_nodes = []
        logger.info(f"  - 开始解析 {len(result['data'])} 个节点组")
        
        for i, node_group in enumerate(result["data"]):
            logger.info(f"  - 处理节点组 {i+1}: 类型={node_group.get('type', 'unknown')}")
            if node_group["type"] == "vip" and "node" in node_group:
                node_count = len(node_group["node"])
                logger.info(f"    - 找到VIP节点组，包含 {node_count} 个节点")
                for j, node in enumerate(node_group["node"]):
                    if isinstance(node, dict) and "url" in node:
                        vip_nodes.append(node)
                        logger.info(f"    - 添加节点 {j+1}: {node.get('url', 'unknown')[:50]}...")
        
        logger.info(f"  - 总共找到 {len(vip_nodes)} 个VIP节点")
        
        # 步骤8.5: 获取 Pilishai 节点
        logger.info("步骤8.5: 获取 Pilishai VPN 节点")
        pilishai_nodes = get_pilishai_nodes(session)
        
        
        # 步骤8.7: 获取派大星VPN节点（根据开关决定）
        if COLLECT_PAIDAXING:
            logger.info("步骤8.7: 获取派大星VPN节点（开关已开启）")
            paidaxing_nodes = get_paidaxing_nodes(session)
        else:
            logger.info("步骤8.7: 跳过派大星VPN节点采集（开关已关闭）")
            paidaxing_nodes = []
        
        # 步骤8.8: 获取SuperVPN节点（根据开关决定）
        if COLLECT_SUPERVPN:
            logger.info("步骤8.8: 获取SuperVPN节点（开关已开启）")
            supervpn_nodes = get_supervpn_nodes(session)
        else:
            logger.info("步骤8.8: 跳过SuperVPN节点采集（开关已关闭）")
            supervpn_nodes = []
        
        # 步骤8.9: 获取VMess节点（根据开关决定）
        if COLLECT_VMESS:
            logger.info("步骤8.9: 获取VMess节点（开关已开启）")
            vmess_nodes = get_vmess_nodes(session)
        else:
            logger.info("步骤8.9: 跳过VMess节点采集（开关已关闭）")
            vmess_nodes = []
        
        # 合并所有节点
        all_nodes = vip_nodes + pilishai_nodes + paidaxing_nodes + supervpn_nodes + vmess_nodes
        logger.info(f"  - 天猫节点: {len(vip_nodes)} 个")
        logger.info(f"  - Pilishai 节点: {len(pilishai_nodes)} 个")
        logger.info(f"  - 派大星VPN节点: {len(paidaxing_nodes)} 个")
        logger.info(f"  - SuperVPN节点: {len(supervpn_nodes)} 个")
        logger.info(f"  - VMess节点: {len(vmess_nodes)} 个")
        logger.info(f"  - 总计节点: {len(all_nodes)} 个")
        
        if all_nodes:
            # 步骤9: 排序节点
            logger.info("步骤9: 排序节点")
            sorted_nodes = sort_nodes(all_nodes)
            logger.info(f"  - 节点排序完成，共 {len(sorted_nodes)} 个节点")
            
            # 步骤10: 显示前5个节点
            logger.info("步骤10: 显示前5个节点")
            logger.info("  - 前5个付费节点:")
            for i, node in enumerate(sorted_nodes[:5]):
                if "url" in node:
                    url_parts = node["url"].split("#")
                    if len(url_parts) > 1:
                        decoded_name = urllib.parse.unquote(url_parts[1])
                        logger.info(f"    {i+1}. {url_parts[0]}#{decoded_name}")
                    else:
                        logger.info(f"    {i+1}. {node['url']}")
            
            # 步骤11: 保存节点文件
            logger.info("步骤11: 保存节点文件")
            nodes_file_path = save_nodes_to_file(sorted_nodes, nodes_file)
            if nodes_file_path:
                logger.info(f"  - 节点文件已保存至: {nodes_file_path}")
            else:
                logger.error("  - 节点文件保存失败")
                return False
            
            # 步骤12: 生成Base64订阅文件
            logger.info("步骤12: 生成Base64订阅文件")
            base64_file_path = generate_base64_subscription(sorted_nodes, base64_file)
            if base64_file_path:
                logger.info(f"  - Base64订阅文件已保存至: {base64_file_path}")
                logger.info("  - 此文件可直接用于v2rayn等客户端的订阅")
            else:
                logger.error("  - Base64订阅文件保存失败")
                return False
            
            # 步骤13: 生成Clash配置
            logger.info("步骤13: 生成Clash配置文件")
            clash_file_path = generate_clash_config(sorted_nodes, clash_file)
            if clash_file_path:
                logger.info(f"  - Clash配置文件已保存至: {clash_file_path}")
            else:
                logger.error("  - Clash配置文件保存失败")
                return False
            
            # 步骤14: 完成
            logger.info("步骤14: 脚本执行完成")
            logger.info("=" * 50)
            logger.info("脚本执行完成")
            logger.info(f"结束时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
            
            # 构建采集结果描述
            sources = ["天猫"]
            if len(pilishai_nodes) > 0:
                sources.append("Pilishai")
            if COLLECT_PAIDAXING and len(paidaxing_nodes) > 0:
                sources.append("派大星VPN")
            if COLLECT_SUPERVPN and len(supervpn_nodes) > 0:
                sources.append("SuperVPN")
            if COLLECT_VMESS and len(vmess_nodes) > 0:
                sources.append("VMess")
            
            sources_str = " + ".join(sources)
            logger.info(f"成功获取 {len(sorted_nodes)} 个节点（{sources_str}）")
            return True
        else:
            logger.warning("  - 没有找到VIP节点")
            return False
        
    except requests.exceptions.SSLError:
        logger.warning("  - 获取节点列表遇到SSL错误，尝试禁用SSL验证...")
        try:
            logger.info("  - 重新发送获取节点列表请求（禁用SSL验证）...")
            response = session.post(node_url, headers=headers, json=node_data, verify=False, timeout=10)
            logger.info(f"  - 收到响应，状态码: {response.status_code}")
            response.raise_for_status()
            result = response.json()
            logger.info(f"  - 解析响应数据成功")
            
            if result.get("code") != 1:
                logger.error(f"  - 节点列表获取失败: {result.get('message')}")
                return False
                
            logger.info("  - 节点列表获取成功")
            
            # 重新解析节点数据
            logger.info("步骤8: 解析节点数据（SSL重试）")
            vip_nodes = []
            logger.info(f"  - 开始解析 {len(result['data'])} 个节点组")
            
            for i, node_group in enumerate(result["data"]):
                logger.info(f"  - 处理节点组 {i+1}: 类型={node_group.get('type', 'unknown')}")
                if node_group["type"] == "vip" and "node" in node_group:
                    node_count = len(node_group["node"])
                    logger.info(f"    - 找到VIP节点组，包含 {node_count} 个节点")
                    for j, node in enumerate(node_group["node"]):
                        if isinstance(node, dict) and "url" in node:
                            vip_nodes.append(node)
                            logger.info(f"    - 添加节点 {j+1}: {node.get('url', 'unknown')[:50]}...")
            
            logger.info(f"  - 总共找到 {len(vip_nodes)} 个VIP节点")
            
            # 步骤8.5: 获取 Pilishai 节点（SSL重试版本）
            logger.info("步骤8.5: 获取 Pilishai VPN 节点（SSL重试版本）")
            pilishai_nodes = get_pilishai_nodes(session)
            
            
            # 步骤8.7: 获取派大星VPN节点（SSL重试版本，根据开关决定）
            if COLLECT_PAIDAXING:
                logger.info("步骤8.7: 获取派大星VPN节点（SSL重试版本，开关已开启）")
                paidaxing_nodes = get_paidaxing_nodes(session)
            else:
                logger.info("步骤8.7: 跳过派大星VPN节点采集（SSL重试版本，开关已关闭）")
                paidaxing_nodes = []
            
            # 步骤8.8: 获取SuperVPN节点（SSL重试版本，根据开关决定）
            if COLLECT_SUPERVPN:
                logger.info("步骤8.8: 获取SuperVPN节点（SSL重试版本，开关已开启）")
                supervpn_nodes = get_supervpn_nodes(session)
            else:
                logger.info("步骤8.8: 跳过SuperVPN节点采集（SSL重试版本，开关已关闭）")
                supervpn_nodes = []
            
            # 步骤8.9: 获取VMess节点（SSL重试版本，根据开关决定）
            if COLLECT_VMESS:
                logger.info("步骤8.9: 获取VMess节点（SSL重试版本，开关已开启）")
                vmess_nodes = get_vmess_nodes(session)
            else:
                logger.info("步骤8.9: 跳过VMess节点采集（SSL重试版本，开关已关闭）")
                vmess_nodes = []
            
            # 合并所有节点
            all_nodes = vip_nodes + pilishai_nodes + paidaxing_nodes + supervpn_nodes + vmess_nodes
            logger.info(f"  - 天猫节点: {len(vip_nodes)} 个")
            logger.info(f"  - Pilishai 节点: {len(pilishai_nodes)} 个")
            logger.info(f"  - 派大星VPN节点: {len(paidaxing_nodes)} 个")
            logger.info(f"  - SuperVPN节点: {len(supervpn_nodes)} 个")
            logger.info(f"  - VMess节点: {len(vmess_nodes)} 个")
            logger.info(f"  - 总计节点: {len(all_nodes)} 个")
            
            if all_nodes:
                # 步骤9-13: 处理节点（与正常流程相同）
                logger.info("步骤9: 排序节点")
                sorted_nodes = sort_nodes(all_nodes)
                logger.info(f"  - 节点排序完成，共 {len(sorted_nodes)} 个节点")
                
                logger.info("步骤10: 显示前5个节点")
                logger.info("  - 前5个付费节点:")
                for i, node in enumerate(sorted_nodes[:5]):
                    if "url" in node:
                        url_parts = node["url"].split("#")
                        if len(url_parts) > 1:
                            decoded_name = urllib.parse.unquote(url_parts[1])
                            logger.info(f"    {i+1}. {url_parts[0]}#{decoded_name}")
                        else:
                            logger.info(f"    {i+1}. {node['url']}")
                
                logger.info("步骤11: 保存节点文件")
                nodes_file_path = save_nodes_to_file(sorted_nodes, nodes_file)
                if nodes_file_path:
                    logger.info(f"  - 节点文件已保存至: {nodes_file_path}")
                else:
                    logger.error("  - 节点文件保存失败")
                    return False
                
                logger.info("步骤12: 生成Base64订阅文件")
                base64_file_path = generate_base64_subscription(sorted_nodes, base64_file)
                if base64_file_path:
                    logger.info(f"  - Base64订阅文件已保存至: {base64_file_path}")
                    logger.info("  - 此文件可直接用于v2rayn等客户端的订阅")
                else:
                    logger.error("  - Base64订阅文件保存失败")
                    return False
                
                logger.info("步骤13: 生成Clash配置文件")
                clash_file_path = generate_clash_config(sorted_nodes, clash_file)
                if clash_file_path:
                    logger.info(f"  - Clash配置文件已保存至: {clash_file_path}")
                else:
                    logger.error("  - Clash配置文件保存失败")
                    return False
                
                logger.info("步骤14: 脚本执行完成")
                logger.info("=" * 50)
                logger.info("脚本执行完成")
                logger.info(f"结束时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
                
                # 构建采集结果描述
                sources = ["天猫"]
                if len(pilishai_nodes) > 0:
                    sources.append("Pilishai")
                if COLLECT_PAIDAXING and len(paidaxing_nodes) > 0:
                    sources.append("派大星VPN")
                if COLLECT_SUPERVPN and len(supervpn_nodes) > 0:
                    sources.append("SuperVPN")
                if COLLECT_VMESS and len(vmess_nodes) > 0:
                    sources.append("VMess")
                
                sources_str = " + ".join(sources)
                logger.info(f"成功获取 {len(sorted_nodes)} 个节点（{sources_str}）")
                return True
            else:
                logger.warning("  - 没有找到VIP节点")
                return False
                
        except requests.RequestException as e:
            logger.error(f"  - 节点列表获取失败: {e}")
            return False
    except requests.RequestException as e:
        logger.error(f"  - 节点列表获取失败: {e}")
        return False
    except Exception as e:
        logger.error(f"  - 处理节点数据时发生错误: {e}")
        import traceback
        logger.error(f"  - 错误详情: {traceback.format_exc()}")
        return False

if __name__ == "__main__":
    try:
        success = main()
        sys.exit(0 if success else 1)
    except KeyboardInterrupt:
        logger.error("脚本被用户中断")
        sys.exit(1)
    except Exception as e:
        logger.error(f"脚本执行时发生未捕获的异常: {e}")
        import traceback
        logger.error(f"错误详情: {traceback.format_exc()}")
        sys.exit(1)
