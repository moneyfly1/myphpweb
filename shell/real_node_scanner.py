# -*- coding: utf-8 -*-

# 真实节点扫描器
# 完全基于原始scaner.py的逻辑，从网站实际获取节点数据

import json
import os
import ssl
import gzip
import urllib.parse
import urllib.request
import base64
import re
import sys
import platform
from datetime import datetime
from typing import List, Dict, Optional, Tuple
from copy import deepcopy

# 禁用SSL证书验证
ssl._create_default_https_context = ssl._create_unverified_context

class RealNodeScanner:
    def __init__(self):
        self.script_start_time = datetime.now()
        self.os_type = self.detect_os()
        self.script_dir = os.path.dirname(os.path.abspath(__file__))
        self.tmp_dir = os.path.join(self.script_dir, "tmp", "52panda_merge")
        self.target_dir = self.script_dir  # 直接保存到脚本所在目录
        self.target_file = os.path.join(self.target_dir, "52vpn.txt")
        
        # 创建必要目录
        os.makedirs(self.tmp_dir, exist_ok=True)
        os.makedirs(self.target_dir, exist_ok=True)
        
        # 网站配置
        self.websites = [
            {"name": "52vpn", "domain": "https://52vpn.cc", "email": "kdaisywendy@gmail.com", "password": "kdaisywendy"},
            {"name": "heduian", "domain": "https://www.heduian.my", "email": "kdaisywendy@gmail.com", "password": "kdaisywendy"}
        ]
        
        # 请求头
        self.headers = {
            "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            "accept": "application/json, text/javascript, */*; q=0.01",
            "accept-language": "zh-CN,zh;q=0.9",
            "dnt": "1",
            "Connection": "keep-alive",
            "content-type": "application/x-www-form-urlencoded; charset=UTF-8",
            "x-requested-with": "XMLHttpRequest",
        }
        
        # 清理旧日志文件（保留最近1000行）
        self.cleanup_log_file()
        
        self.log(f"初始化完成 - 操作系统: {self.os_type}")
        self.log(f"临时目录: {self.tmp_dir}")
        self.log(f"目标目录: {self.target_dir}")
        self.log(f"目标文件: {self.target_file}")
        self.log(f"日志文件: {os.path.join(self.script_dir, '52vpn.log')}")

    def log(self, message: str, level: str = "INFO"):
        """日志输出"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        log_message = f"[{timestamp}] [{level}] {message}"
        
        # 强制输出到标准输出，确保宝塔面板能捕获
        print(log_message, flush=True)
        
        # 同时写入日志文件 - 支持多个日志位置
        log_files = [
            os.path.join(self.script_dir, "52vpn.log"),  # 脚本目录
            "/tmp/52vpn.log"  # 系统临时目录，方便计划任务查看
        ]
        
        for log_file in log_files:
            try:
                with open(log_file, 'a', encoding='utf-8') as f:
                    f.write(log_message + '\n')
                    f.flush()  # 强制刷新缓冲区
            except Exception as e:
                print(f"写入日志文件失败 {log_file}: {e}", flush=True)

    def cleanup_log_file(self):
        """清理日志文件，保留最近1000行"""
        log_files = [
            os.path.join(self.script_dir, "52vpn.log"),  # 脚本目录
            "/tmp/52vpn.log"  # 系统临时目录
        ]
        
        for log_file in log_files:
            try:
                if os.path.exists(log_file):
                    with open(log_file, 'r', encoding='utf-8') as f:
                        lines = f.readlines()
                    
                    # 如果日志行数超过1000行，只保留最后1000行
                    if len(lines) > 1000:
                        with open(log_file, 'w', encoding='utf-8') as f:
                            f.writelines(lines[-1000:])
            except Exception as e:
                print(f"清理日志文件失败 {log_file}: {e}")

    def detect_os(self) -> str:
        """检测操作系统"""
        system = platform.system().lower()
        if system == "windows":
            return "windows"
        elif system == "darwin":
            return "macos"
        elif system == "linux":
            return "linux"
        else:
            return "unknown"


    def check_domain(self, domain: str) -> bool:
        """检查域名是否可破解"""
        try:
            url = domain + "/getnodelist"
            request = urllib.request.Request(url, headers=self.headers)
            response = urllib.request.urlopen(request, timeout=10)
            if response.getcode() == 200:
                content = response.read()
                data = json.loads(content)
                # 如果返回 {"ret": -1}，说明需要登录才能获取节点
                return "ret" in data and data["ret"] == -1
        except Exception as e:
            self.log(f"检查域名失败: {str(e)}", "ERROR")
        return False


    def login_account(self, domain: str, email: str, passwd: str, retry: int = 3) -> str:
        """登录账户并获取Cookie"""
        try:
            login_url = domain + "/auth/login"
            headers = deepcopy(self.headers)
            headers["origin"] = domain
            headers["referer"] = login_url
            
            params = {"email": email, "passwd": passwd}
            data = urllib.parse.urlencode(params).encode(encoding="UTF8")
            request = urllib.request.Request(login_url, data=data, headers=headers, method="POST")

            response = urllib.request.urlopen(request, timeout=10)
            if response.getcode() == 200:
                cookie = response.getheader("Set-Cookie")
                if cookie:
                    self.log(f"登录成功: {domain}")
                    return cookie
                else:
                    self.log(f"登录失败: 未获取到Cookie", "ERROR")
                    return ""
            else:
                self.log(f"登录失败: HTTP {response.getcode()}", "ERROR")
                return ""
        except Exception as e:
            self.log(f"登录异常: {str(e)}", "ERROR")
            retry -= 1
            return self.login_account(domain, email, passwd, retry) if retry > 0 else ""

    def get_cookie_from_header(self, cookie_header: str) -> str:
        """从Cookie头中提取关键Cookie"""
        if not cookie_header:
            return ""
        
        # 提取关键Cookie字段
        regex = r"(__cfduid|uid|email|key|ip|expire_in)=([^;]+)"
        matches = re.findall(regex, cookie_header)
        cookie = ";".join(["=".join(x) for x in matches]).strip()
        
        return cookie

    def fetch_nodes(self, domain: str, cookie: str, retry: int = 3) -> bytes:
        """获取节点数据"""
        headers = deepcopy(self.headers)
        headers["cookie"] = cookie
        
        # 为heduian网站设置更长的超时时间
        timeout = 60 if "heduian" in domain else 10
        
        while retry > 0:
            retry -= 1
            try:
                url = f"{domain}/getnodelist"
                request = urllib.request.Request(url=url, headers=headers)
                response = urllib.request.urlopen(request, timeout=timeout)
                
                if response.getcode() == 200:
                    content = response.read()
                    self.log(f"成功获取节点数据: {domain}, 长度: {len(content)} 字节")
                    
                    # 保存原始响应用于调试
                    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                    debug_file = os.path.join(self.tmp_dir, f"nodes_response_{timestamp}.json")
                    with open(debug_file, 'w', encoding='utf-8') as f:
                        try:
                            json_data = json.loads(content.decode('utf-8'))
                            json.dump(json_data, f, ensure_ascii=False, indent=2)
                        except:
                            f.write(content.decode('utf-8', errors='ignore'))
                    self.log(f"节点数据已保存: {debug_file}")
                    
                    return content
                else:
                    self.log(f"获取节点失败: HTTP {response.getcode()}", "ERROR")
            except Exception as e:
                self.log(f"获取节点异常: {str(e)}", "ERROR")
        
        return b""

    def parse_ss_node(self, node: dict, uuid: str, user_info: dict = None) -> Optional[dict]:
        """解析SS节点"""
        if not uuid:
            return None

        # 从用户信息中获取加密方式和密码
        method = "aes-256-cfb"  # 默认加密方式
        password = uuid  # 默认使用UUID作为密码
        
        if user_info:
            method = user_info.get("method", "aes-256-cfb")
            # 如果用户信息中有密码字段，使用它；否则使用UUID
            password = user_info.get("passwd", uuid)
        
        result = {
            "name": node.get("name"),
            "type": "ss",
            "uuid": uuid,
            "method": method,
            "password": password,
        }

        server = node.get("server")
        if not server:
            return None
        
        # 处理特殊格式的服务器信息
        if "port=" in server and "#" in server:
            # 处理类似 "Asia.vpn52.xyz;port=20255#47197" 的格式
            parts = server.split(";")
            host = parts[0]
            port_part = parts[1] if len(parts) > 1 else ""
            
            if "port=" in port_part and "#" in port_part:
                port_str = port_part.split("#")[1]
                port = int(port_str) if port_str.isdigit() else 443
            else:
                port = 443
            
            result["server"] = host
            result["port"] = port
            return result
            
        # 处理标准格式 "host;port;alterId;network;tls;obfs"
        items = server.split(";")
        if len(items) < 2:
            return None

        host = items[0]
        port = int(items[1]) if items[1].isdigit() else 443

        result["server"] = host
        result["port"] = port
        return result

    def parse_vless_node(self, node: dict, uuid: str) -> Optional[dict]:
        """解析VLESS节点（支持Reality协议）"""
        if not uuid:
            return None

        result = {
            "name": node.get("name"),
            "type": "vless",
            "uuid": uuid,
        }

        server = node.get("server")
        if not server:
            return None
        
        # 解析VLESS节点的特殊格式
        # 格式: "host;port=xxx&flow=xxx&security=xxx&dest=xxx&serverPort=xxx&serverName=xxx&privateKey=xxx&publicKey=xxx&shortId=xxx"
        if "port=" in server and "&" in server:
            # 提取主机名
            host = server.split(";")[0]
            
            # 解析参数
            params_str = server.split(";")[1] if ";" in server else ""
            params = {}
            
            if params_str:
                for param in params_str.split("&"):
                    if "=" in param:
                        key, value = param.split("=", 1)
                        params[key] = value
            
            # 提取端口
            port = int(params.get("port", 443))
            
            # 提取Reality相关参数
            flow = params.get("flow", "")
            security = params.get("security", "")
            dest = params.get("dest", "")
            server_port = params.get("serverPort", "443")
            server_name = params.get("serverName", "")
            private_key = params.get("privateKey", "")
            public_key = params.get("publicKey", "")
            short_id = params.get("shortId", "")
            
            result["server"] = host
            result["port"] = port
            result["flow"] = flow
            result["security"] = security
            result["dest"] = dest
            result["serverPort"] = int(server_port) if server_port.isdigit() else 443
            result["serverName"] = server_name
            result["privateKey"] = private_key
            result["publicKey"] = public_key
            result["shortId"] = short_id
            
            return result
        
        # 如果不是特殊格式，按标准格式处理
        items = server.split(";")
        if len(items) >= 2:
            host = items[0]
            port = int(items[1]) if items[1].isdigit() else 443
            
            # 解析WebSocket参数
            if len(items) > 5:
                obfs = items[5]
                if obfs and obfs.strip() != "":
                    for s in obfs.split("|"):
                        if "=" in s:
                            key, value = s.split("=", 1)
                            if key == "path":
                                result["path"] = value
                            elif key == "host":
                                result["host"] = value
            
            result["server"] = host
            result["port"] = port
            return result
        
        return None

    def parse_vmess_node(self, node: dict, uuid: str) -> Optional[dict]:
        """解析VMess节点"""
        if not uuid:
            return None

        result = {
            "name": node.get("name"),
            "type": "vmess",
            "uuid": uuid,
            "cipher": "auto",
            "skip-cert-verify": True,
        }

        server = node.get("server")
        if not server:
            return None
        
        # 处理特殊格式的服务器信息
        if "port=" in server and "#" in server:
            # 处理类似 "Asia.vpn52.xyz;port=20255#47197" 的格式
            parts = server.split(";")
            host = parts[0]
            port_part = parts[1] if len(parts) > 1 else ""
            
            if "port=" in port_part and "#" in port_part:
                port_str = port_part.split("#")[1]
                port = int(port_str) if port_str.isdigit() else 443
            else:
                port = 443
            
            result["alterId"] = 0
            result["network"] = "tcp"
            result["tls"] = False
            result["server"] = host
            result["port"] = port
            return result
            
        items = server.split(";")
        if len(items) < 3:
            return None
            
        result["alterId"] = int(items[2]) if items[2].isdigit() else 0

        network = items[3].strip() if len(items) > 3 else "tcp"
        if network == "" or "tls" in network:
            network = items[4].strip() if len(items) > 4 else "tcp"
        result["network"] = network
        result["tls"] = "tls" in items[3] if len(items) > 3 else False

        host = items[0]
        port = int(items[1]) if items[1].isdigit() else 443

        # 解析WebSocket参数
        if len(items) > 5:
            obfs = items[5]
            opts = {}
            if obfs and obfs.strip() != "":
                for s in obfs.split("|"):
                    words = s.split("=")
                    if len(words) != 2:
                        continue

                    if words[0] == "server":
                        host = words[1]
                    elif words[0] == "outside_port":
                        port = int(words[1])
                    elif words[0] == "path":
                        opts["path"] = words[1]
                    elif words[0] == "host":
                        opts["headers"] = {"Host": words[1]}

            if opts:
                result["ws-opts"] = opts

        result["server"] = host
        result["port"] = port
        return result

    def convert_nodes(self, content: bytes) -> List[dict]:
        """转换节点数据"""
        if not content:
            return []
            
        try:
            data = json.loads(content.decode('utf-8'))
            nodeinfo = data.get("nodeinfo", None)
            if not nodeinfo:
                self.log(f"无法获取节点列表，响应: {content.decode('utf-8', errors='ignore')[:200]}", "ERROR")
                return []

            nodes_muport = nodeinfo.get("nodes_muport", [])
            if not nodes_muport:
                self.log("没有找到用户端口信息", "WARNING")
                return []

            # 提取所有UUID和用户信息
            uuids = set()
            user_info_map = {}  # 存储UUID到用户信息的映射
            for nm in nodes_muport:
                user = nm.get("user", None)
                if user and user.get("uuid", ""):
                    uuid = user.get("uuid").strip()
                    uuids.add(uuid)
                    user_info_map[uuid] = user

            if not uuids:
                self.log("没有找到有效的UUID", "WARNING")
                return []

            self.log(f"找到 {len(uuids)} 个UUID")

            # 解析所有节点
            arrays = []
            nodes = nodeinfo.get("nodes", [])
            self.log(f"找到 {len(nodes)} 个节点")
            
            # 统计节点类型
            node_type_stats = {"vmess": 0, "ss": 0, "vless": 0}
            
            for node in nodes:
                # 跳过离线节点
                if node.get("online") == -1:
                    continue

                for uuid in uuids:
                    try:
                        raw_node = node.get("raw_node", {})
                        node_type = raw_node.get("type", 0)
                        
                        # 根据server字段格式判断节点类型
                        server = raw_node.get("server", "")
                        user_info = user_info_map.get(uuid, {})
                        
                        if "port=" in server and "&" in server:
                            # VLESS节点格式: "vr45.heduian.link;port=30845&flow=xtls-rprx-vision&security=reality&..."
                            node_type_stats["vless"] = node_type_stats.get("vless", 0) + 1
                            result = self.parse_vless_node(raw_node, uuid)
                        elif "port=" in server and "#" in server:
                            # SS节点格式: "sg1.vpn52.xyz;port=20255#47297"
                            node_type_stats["ss"] += 1
                            result = self.parse_ss_node(raw_node, uuid, user_info)
                        else:
                            # VMess节点格式: "hk1.vpn52.xyz;28032;0;tcp;;"
                            node_type_stats["vmess"] += 1
                            result = self.parse_vmess_node(raw_node, uuid)
                        
                        if result:
                            arrays.append(result)
                    except Exception as e:
                        self.log(f"解析节点失败: {str(e)}", "ERROR")
                        continue
            
            # 输出节点类型统计
            if node_type_stats:
                self.log("节点类型统计:")
                if node_type_stats["vmess"] > 0:
                    self.log(f"  - VMess节点: {node_type_stats['vmess']} 个")
                if node_type_stats["ss"] > 0:
                    self.log(f"  - SS节点: {node_type_stats['ss']} 个")
                if node_type_stats["vless"] > 0:
                    self.log(f"  - VLESS节点: {node_type_stats['vless']} 个")
                        
            return arrays
        except Exception as e:
            self.log(f"转换节点数据失败: {str(e)}", "ERROR")
            return []

    def generate_ss_link(self, node: dict) -> str:
        """生成SS链接"""
        try:
            # SS链接格式: ss://method:password@server:port#name
            method = node.get("method", "aes-256-cfb")
            password = node.get("password", node.get("uuid", ""))  # 优先使用password字段
            server = node.get("server", "")
            port = node.get("port", 443)
            name = node.get("name", "")
            
            # URL编码节点名称
            encoded_name = urllib.parse.quote(name, safe='')
            
            # 构建SS链接
            ss_link = f"ss://{method}:{password}@{server}:{port}#{encoded_name}"
            
            return ss_link
        except Exception as e:
            self.log(f"生成SS链接失败: {str(e)}", "ERROR")
            return ""

    def generate_vmess_link(self, node: dict) -> str:
        """生成VMess链接"""
        try:
            vmess_data = {
                "v": "2",
                "ps": node.get("name", ""),
                "add": node.get("server", ""),
                "port": str(node.get("port", 443)),
                "id": node.get("uuid", ""),
                "aid": str(node.get("alterId", 0)),
                "scy": "auto",
                "net": node.get("network", "tcp"),
                "type": "none",
                "host": "",
                "path": "",
                "tls": "tls" if node.get("tls", False) else ""
            }
            
            # 处理WebSocket参数
            if node.get("network") == "ws":
                ws_opts = node.get("ws-opts", {})
                if "path" in ws_opts:
                    vmess_data["path"] = ws_opts["path"]
                if "headers" in ws_opts and "Host" in ws_opts["headers"]:
                    vmess_data["host"] = ws_opts["headers"]["Host"]
            
            # 编码为base64
            vmess_json = json.dumps(vmess_data, separators=(',', ':'))
            vmess_base64 = base64.b64encode(vmess_json.encode('utf-8')).decode('utf-8')
            
            return f"vmess://{vmess_base64}"
        except Exception as e:
            self.log(f"生成VMess链接失败: {str(e)}", "ERROR")
            return ""

    def generate_vless_link(self, node: dict) -> str:
        """生成VLESS链接"""
        try:
            # VLESS链接格式: vless://uuid@server:port?encryption=none&security=reality&sni=serverName&pbk=publicKey&sid=shortId&type=tcp&headerType=none&flow=xtls-rprx-vision#name
            uuid = node.get("uuid", "")
            server = node.get("server", "")
            port = node.get("port", 443)
            name = node.get("name", "")
            
            # 构建查询参数
            params = []
            params.append("encryption=none")
            
            # 添加Reality相关参数
            if node.get("security") == "reality":
                params.append("security=reality")
                if node.get("serverName"):
                    params.append(f"sni={node.get('serverName')}")
                if node.get("publicKey"):
                    params.append(f"pbk={node.get('publicKey')}")
                if node.get("shortId"):
                    params.append(f"sid={node.get('shortId')}")
                # 注意：不添加spx参数，因为实际节点不需要
                if node.get("flow"):
                    params.append(f"flow={node.get('flow')}")
            else:
                params.append("security=none")
            
            # 根据是否有WebSocket参数设置type
            if node.get("path") or node.get("host"):
                params.append("type=ws")
                if node.get("path"):
                    params.append(f"path={node.get('path')}")
                if node.get("host"):
                    params.append(f"host={node.get('host')}")
            else:
                params.append("type=tcp")
            
            params.append("headerType=none")
            # 注意：不添加fp参数，因为实际节点不需要
            
            # URL编码节点名称
            encoded_name = urllib.parse.quote(name, safe='')
            
            # 构建VLESS链接
            query_string = "&".join(params)
            vless_link = f"vless://{uuid}@{server}:{port}?{query_string}#{encoded_name}"
            
            return vless_link
        except Exception as e:
            self.log(f"生成VLESS链接失败: {str(e)}", "ERROR")
            return ""

    def scan_website(self, website: dict) -> List[str]:
        """扫描单个网站"""
        domain = website["domain"]
        email = website["email"]
        password = website["password"]
        
        self.log(f"🔍 开始扫描: {website['name']} ({domain})")
        
        # 步骤1: 检查域名是否可破解
        self.log(f"  🔗 检查域名连通性...")
        if not self.check_domain(domain):
            self.log(f"  ❌ 域名不可破解: {domain}", "WARNING")
            return []
        self.log(f"  ✅ 域名检查通过")
        
        # 步骤2: 登录账户
        self.log(f"  🔐 尝试登录账户: {email}")
        cookie_header = self.login_account(domain, email, password)
        if not cookie_header:
            self.log(f"  ❌ 登录失败: {domain}", "ERROR")
            return []
        self.log(f"  ✅ 登录成功")
        
        # 步骤3: 提取Cookie
        self.log(f"  🍪 提取认证Cookie...")
        cookie = self.get_cookie_from_header(cookie_header)
        if not cookie:
            self.log(f"  ❌ 无法提取Cookie: {domain}", "ERROR")
            return []
        self.log(f"  ✅ Cookie提取成功")
        
        # 步骤4: 获取节点数据
        self.log(f"  📡 获取节点数据...")
        content = self.fetch_nodes(domain, cookie)
        if not content:
            self.log(f"  ❌ 无法获取节点数据: {domain}", "ERROR")
            return []
        self.log(f"  ✅ 节点数据获取成功，大小: {len(content)} 字节")
        
        # 步骤5: 转换节点数据
        self.log(f"  🔄 解析节点数据...")
        nodes = self.convert_nodes(content)
        if not nodes:
            self.log(f"  ❌ 无法解析节点数据: {domain}", "ERROR")
            return []
        self.log(f"  ✅ 节点解析成功，共 {len(nodes)} 个节点")
        
        # 步骤6: 生成节点链接
        self.log(f"  🔗 生成节点链接...")
        node_links = []
        node_type_count = {"ss": 0, "vmess": 0, "vless": 0}
        
        for node in nodes:
            if node.get("type") == "ss":
                link = self.generate_ss_link(node)
                node_type_count["ss"] += 1
            elif node.get("type") == "vless":
                link = self.generate_vless_link(node)
                node_type_count["vless"] += 1
            else:
                link = self.generate_vmess_link(node)
                node_type_count["vmess"] += 1
            
            if link:
                node_links.append(link)
        
        # 显示节点类型统计
        type_info = []
        for node_type, count in node_type_count.items():
            if count > 0:
                type_info.append(f"{node_type.upper()}: {count}个")
        
        self.log(f"  ✅ 链接生成完成，共 {len(node_links)} 个有效链接")
        if type_info:
            self.log(f"  📊 节点类型: {', '.join(type_info)}")
        
        return node_links

    def merge_and_deduplicate_nodes(self, all_nodes: List[str]) -> List[str]:
        """合并和去重节点"""
        self.log("  🔄 开始节点去重处理...")
        
        # 去重，保持顺序
        seen = set()
        unique_nodes = []
        for node in all_nodes:
            if node and node not in seen:
                seen.add(node)
                unique_nodes.append(node)
        
        removed_count = len(all_nodes) - len(unique_nodes)
        self.log(f"  📊 去重统计:")
        self.log(f"    • 原始节点: {len(all_nodes)} 个")
        self.log(f"    • 去重后节点: {len(unique_nodes)} 个")
        self.log(f"    • 移除重复: {removed_count} 个")
        
        if removed_count > 0:
            self.log(f"  ✅ 去重完成，移除 {removed_count} 个重复节点")
        else:
            self.log(f"  ✅ 无重复节点，保持原有 {len(unique_nodes)} 个节点")
        
        return unique_nodes

    def sort_nodes(self, nodes: List[str]) -> List[str]:
        """对节点进行排序：vless → vmess → hysteria2 → anytls → ss"""
        self.log("  📋 开始节点排序优化...")
        
        # 按优先级分类节点
        node_types = {
            'vless': [], 'vmess': [], 'hysteria2': [], 
            'anytls': [], 'ss': [], 'other': []
        }
        
        for node in nodes:
            if node.startswith('vless://'):
                node_types['vless'].append(node)
            elif node.startswith('vmess://'):
                node_types['vmess'].append(node)
            elif node.startswith('hysteria2://'):
                node_types['hysteria2'].append(node)
            elif node.startswith('anytls://'):
                node_types['anytls'].append(node)
            elif node.startswith('ss://'):
                node_types['ss'].append(node)
            else:
                node_types['other'].append(node)
        
        # 按指定顺序组合
        sorted_nodes = (node_types['vless'] + node_types['vmess'] + 
                       node_types['hysteria2'] + node_types['anytls'] + 
                       node_types['ss'] + node_types['other'])
        
        # 显示统计信息
        self.log(f"  📊 节点类型分布:")
        priority_order = ['vless', 'vmess', 'hysteria2', 'anytls', 'ss', 'other']
        for node_type in priority_order:
            if node_types[node_type]:
                self.log(f"    • {node_type.upper()}: {len(node_types[node_type])} 个")
        
        self.log(f"  ✅ 排序完成，总节点: {len(sorted_nodes)} 个")
        return sorted_nodes

    def generate_final_file(self, nodes: List[str]) -> bool:
        """生成最终base64编码文件"""
        self.log("  💾 开始生成订阅文件...")
        
        if not nodes:
            self.log("  ❌ 没有有效节点，无法生成订阅文件", "ERROR")
            return False
        
        # 过滤出有效的节点URL
        self.log("  🔍 验证节点格式...")
        valid_nodes = []
        invalid_count = 0
        
        for node in nodes:
            if re.match(r'^(vmess://|vless://|ss://|hysteria2://|anytls://|trojan://|wireguard://)', node):
                valid_nodes.append(node)
            else:
                invalid_count += 1
        
        if invalid_count > 0:
            self.log(f"  ⚠️  发现 {invalid_count} 个无效节点，已过滤")
        
        if not valid_nodes:
            self.log("  ❌ 没有找到有效节点", "ERROR")
            return False
        
        self.log(f"  ✅ 节点验证完成，有效节点: {len(valid_nodes)} 个")
        
        # 生成base64编码
        self.log("  🔐 生成base64编码...")
        content = '\n'.join(valid_nodes)
        encoded = base64.b64encode(content.encode('utf-8')).decode('utf-8')
        
        # 移除base64编码中的换行符，确保输出为单行
        encoded = encoded.replace('\n', '').replace('\r', '')
        
        # 写入目标文件
        self.log("  📝 写入订阅文件...")
        with open(self.target_file, 'w', encoding='utf-8') as f:
            f.write(encoded)
        
        file_size = len(encoded)
        self.log("  ✅ 订阅文件生成成功:")
        self.log(f"    • 文件路径: {self.target_file}")
        self.log(f"    • 文件大小: {file_size} 字节")
        self.log(f"    • 节点数量: {len(valid_nodes)} 个")
        self.log(f"    • 编码格式: 单行base64（V2RayN兼容）")
        
        # 验证文件格式
        self.log("  🔍 验证文件格式...")
        try:
            decoded = base64.b64decode(encoded)
            decoded_text = decoded.decode('utf-8', errors='ignore')
            lines = [line.strip() for line in decoded_text.split('\n') if line.strip()]
            valid_count = len([line for line in lines if any(line.startswith(prefix) for prefix in ['ss://', 'ssr://', 'vmess://', 'vless://', 'trojan://', 'hysteria2://', 'hy2://', 'tuic://'])])
            self.log(f"    • 解码验证: {valid_count} 个有效节点")
            
            if valid_count > 0:
                self.log("  ✅ 文件格式验证通过")
                # 预览前3个节点
                self.log("  📋 节点预览:")
                for i, node in enumerate(valid_nodes[:3]):
                    self.log(f"    {i+1}. {node[:60]}...")
                return True
            else:
                self.log("  ⚠️  警告: 没有找到有效节点", "WARNING")
                return False
        except Exception as e:
            self.log(f"  ❌ 验证失败: {e}", "ERROR")
            return False

    def run(self):
        """运行主程序"""
        self.log("=" * 80)
        self.log("🚀 开始执行52vpn节点采集脚本")
        self.log("=" * 80)
        
        try:
            # 步骤1: 初始化检查
            self.log("📋 步骤1: 系统初始化检查")
            self.log(f"  ✓ 操作系统: {self.os_type}")
            self.log(f"  ✓ 脚本目录: {self.script_dir}")
            self.log(f"  ✓ 目标文件: {self.target_file}")
            self.log(f"  ✓ 配置网站数量: {len(self.websites)} 个")
            
            # 步骤2: 扫描所有网站节点
            self.log("")
            self.log("🌐 步骤2: 开始扫描网站节点")
            self.log("-" * 60)
            
            all_nodes = []
            total_websites = len(self.websites)
            
            for i, website in enumerate(self.websites, 1):
                self.log(f"📡 [{i}/{total_websites}] 正在处理: {website['name']} ({website['domain']})")
                
                try:
                    nodes = self.scan_website(website)
                    all_nodes.extend(nodes)
                    self.log(f"  ✅ {website['name']} 采集完成，获得 {len(nodes)} 个节点")
                except Exception as e:
                    self.log(f"  ❌ {website['name']} 采集失败: {str(e)}", "ERROR")
                
                self.log("")
            
            # 步骤3: 统计采集结果
            self.log("📊 步骤3: 采集结果统计")
            self.log(f"  📈 总采集节点数: {len(all_nodes)} 个")
            
            if not all_nodes:
                self.log("❌ 所有网站都未能获取到节点数据", "ERROR")
                return False
            
            # 步骤4: 合并和去重
            self.log("")
            self.log("🔄 步骤4: 节点去重处理")
            unique_nodes = self.merge_and_deduplicate_nodes(all_nodes)
            
            # 步骤5: 节点排序
            self.log("")
            self.log("📋 步骤5: 节点排序优化")
            sorted_nodes = self.sort_nodes(unique_nodes)
            
            # 步骤6: 生成最终文件
            self.log("")
            self.log("💾 步骤6: 生成订阅文件")
            success = self.generate_final_file(sorted_nodes)
            
            # 步骤7: 完成总结
            if success:
                self.log("")
                self.log("=" * 80)
                self.log("🎉 52vpn节点采集脚本执行完成!")
                self.log("=" * 80)
                
                # 计算执行时间
                end_time = datetime.now()
                duration = (end_time - self.script_start_time).total_seconds()
                
                self.log("📋 执行总结:")
                self.log(f"  ⏱️  总耗时: {duration:.2f} 秒")
                self.log(f"  🌐 处理网站: {total_websites} 个")
                self.log(f"  📡 原始节点: {len(all_nodes)} 个")
                self.log(f"  🔄 去重后节点: {len(unique_nodes)} 个")
                self.log(f"  📁 最终文件: {self.target_file}")
                self.log(f"  📊 文件大小: {os.path.getsize(self.target_file) if os.path.exists(self.target_file) else 0} 字节")
                
                # 生成状态文件
                self.create_status_file(success, len(sorted_nodes), duration)
                return True
            else:
                self.log("❌ 最终文件生成失败", "ERROR")
                self.create_status_file(False, 0, 0)
                return False
                
        except Exception as e:
            self.log(f"💥 脚本执行异常: {str(e)}", "ERROR")
            return False

    def create_status_file(self, success: bool, node_count: int, duration: float):
        """创建状态文件，方便查看采集结果"""
        status_file = os.path.join(self.script_dir, "status.json")
        try:
            status_data = {
                "last_run": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "success": success,
                "node_count": node_count,
                "duration_seconds": round(duration, 2),
                "output_file": "52vpn.txt" if success else None,
                "log_file": "52vpn.log"
            }
            
            with open(status_file, 'w', encoding='utf-8') as f:
                json.dump(status_data, f, ensure_ascii=False, indent=2)
                
            self.log(f"状态文件已更新: {status_file}")
        except Exception as e:
            self.log(f"创建状态文件失败: {e}", "ERROR")

def main():
    """主函数"""
    # 强制刷新输出缓冲区，确保宝塔面板能实时看到日志
    import sys
    sys.stdout.flush()
    sys.stderr.flush()
    
    scanner = RealNodeScanner()
    success = scanner.run()
    
    if success:
        print("\n✅ 脚本执行成功", flush=True)
        sys.exit(0)
    else:
        print("\n❌ 脚本执行失败", flush=True)
        sys.exit(1)

if __name__ == "__main__":
    main()
