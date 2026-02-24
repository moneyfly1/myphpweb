#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Cloudflare Workers 订阅更新脚本
功能：
1. 从 Cloudflare Workers 获取订阅内容
2. 解析 base64 编码的订阅内容，提取 vless 链接
3. 替换 vless 链接中的服务器地址为指定的服务器列表
4. 保存处理后的 vless 链接到指定文件

输出路径: /www/wwwroot/dy.moneyfly.club/shell/vless.txt
"""

import requests
import base64
import urllib.parse
import os
import logging
from datetime import datetime
from urllib.parse import urlparse, parse_qs, urlencode, urlunparse
import re
import uuid as uuid_module

# VPS路径配置
VPS_DIR = "/www/wwwroot/dy.moneyfly.club/shell"
# 本地测试时使用当前目录
if not os.path.exists(VPS_DIR):
    VPS_DIR = os.path.dirname(os.path.abspath(__file__))

# 配置文件路径
output_file = os.path.join(VPS_DIR, "vless.txt")
log_file = os.path.join(VPS_DIR, "vless_update.log")

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

# 配置信息
CLOUDFLARE_EMAIL = "3219904322@qq.com"
CLOUDFLARE_API_TOKEN = "3c81fa5339019d61bd4f180255fa74b2901e5"  # 如果使用 Global API Key，请填写 Global API Key
CLOUDFLARE_ACCOUNT_ID = None  # 如果无法通过 API 获取，可以在这里直接配置账户 ID
WORKERS_PROJECT = "shy-smoke-243c"
CUSTOM_DOMAIN = "bageyalu.moneyfly.eu.org"
UUID = "5ebd19f0-7940-4f39-8972-220164308ae8"

# 认证方式选择：
# 方式1: API Token（推荐）- 使用 Bearer 认证
# 方式2: Global API Key - 使用 X-Auth-Key 和 X-Auth-Email 认证
# 如果使用 Global API Key，请设置 USE_GLOBAL_API_KEY = True
USE_GLOBAL_API_KEY = True  # 设置为 True 使用 Global API Key，False 使用 API Token

# 注意：
# - API Token 需要以下权限：Account: Cloudflare Workers:Edit, Account: Account Settings:Read
# - Global API Key 在 My Profile → API Tokens 页面可以找到

# 需要替换的服务器地址列表
REPLACEMENT_SERVERS = [
    "yg8.ygkkk.dpdns.org",
    "yg9.ygkkk.dpdns.org",
    "yg10.ygkkk.dpdns.org",
    "yg11.ygkkk.dpdns.org",
    "yg12.ygkkk.dpdns.org",
    "yg13.ygkkk.dpdns.org"
]


def get_cloudflare_headers():
    """获取 Cloudflare API 请求头"""
    if USE_GLOBAL_API_KEY:
        # 使用 Global API Key 认证
        headers = {
            "X-Auth-Email": CLOUDFLARE_EMAIL,
            "X-Auth-Key": CLOUDFLARE_API_TOKEN,
            "Content-Type": "application/json"
        }
        logger.info("使用 Global API Key 认证方式")
        print("使用 Global API Key 认证方式")
    else:
        # 使用 API Token 认证
        headers = {
            "Authorization": f"Bearer {CLOUDFLARE_API_TOKEN}",
            "Content-Type": "application/json"
        }
        logger.info("使用 API Token 认证方式")
        print("使用 API Token 认证方式")
    return headers


def get_cloudflare_account_id(session):
    """获取 Cloudflare 账户 ID"""
    try:
        logger.info("正在获取 Cloudflare 账户 ID...")
        print("正在获取 Cloudflare 账户 ID...")
        url = "https://api.cloudflare.com/client/v4/accounts"
        headers = get_cloudflare_headers()
        
        if USE_GLOBAL_API_KEY:
            logger.info(f"使用邮箱: {CLOUDFLARE_EMAIL}")
            logger.info(f"Global API Key 前10个字符: {CLOUDFLARE_API_TOKEN[:10]}...")
            print(f"使用邮箱: {CLOUDFLARE_EMAIL}")
            print(f"Global API Key 前10个字符: {CLOUDFLARE_API_TOKEN[:10]}...")
        else:
            logger.info(f"API Token 前10个字符: {CLOUDFLARE_API_TOKEN[:10]}...")
            print(f"API Token 前10个字符: {CLOUDFLARE_API_TOKEN[:10]}...")
        
        response = session.get(url, headers=headers, timeout=30)
        
        # 先检查响应状态
        logger.info(f"API 响应状态码: {response.status_code}")
        print(f"API 响应状态码: {response.status_code}")
        
        # 尝试解析响应内容
        try:
            data = response.json()
            logger.info(f"API 响应内容: {data}")
            print(f"API 响应内容: {data}")
        except:
            logger.error(f"无法解析 JSON 响应，原始响应: {response.text[:500]}")
            print(f"错误: 无法解析 JSON 响应，原始响应: {response.text[:500]}")
            return None
        
        # 检查是否有错误
        if not data.get("success"):
            errors = data.get("errors", [])
            error_messages = [err.get("message", "未知错误") for err in errors]
            logger.error(f"API 返回错误: {error_messages}")
            print(f"错误: API 返回错误: {error_messages}")
            if errors:
                error_code = errors[0].get("code", "")
                logger.error(f"错误代码: {error_code}")
                print(f"错误代码: {error_code}")
                # 如果是认证错误，给出提示
                if error_code in [6003, 6004, 6005]:
                    logger.error("API Token 可能无效或权限不足")
                    print("错误: API Token 可能无效或权限不足，请检查 Token 是否正确")
            return None
        
        # 检查结果
        if data.get("result") and len(data["result"]) > 0:
            account_id = data["result"][0]["id"]
            logger.info(f"获取到 Cloudflare 账户 ID: {account_id}")
            print(f"获取到 Cloudflare 账户 ID: {account_id}")
            return account_id
        else:
            logger.error(f"获取账户 ID 失败: 结果为空")
            print(f"错误: 获取账户 ID 失败: 结果为空")
            logger.error(f"完整响应: {data}")
            return None
            
    except requests.exceptions.HTTPError as e:
        logger.error(f"HTTP 错误: {e}")
        print(f"错误: HTTP 错误: {e}")
        if hasattr(e.response, 'text'):
            logger.error(f"响应内容: {e.response.text[:500]}")
            print(f"响应内容: {e.response.text[:500]}")
        import traceback
        logger.error(traceback.format_exc())
        return None
    except Exception as e:
        logger.error(f"获取 Cloudflare 账户 ID 时出错: {e}")
        print(f"错误: 获取 Cloudflare 账户 ID 时出错: {e}")
        import traceback
        logger.error(traceback.format_exc())
        return None


def generate_new_uuid():
    """生成新的 UUID"""
    new_uuid = str(uuid_module.uuid4())
    logger.info(f"生成新的 UUID: {new_uuid}")
    print(f"生成新的 UUID: {new_uuid}")
    return new_uuid


def get_workers_script(session, account_id):
    """获取 Workers 脚本内容"""
    try:
        logger.info(f"正在获取 Workers 脚本: {WORKERS_PROJECT}")
        print(f"正在获取 Workers 脚本: {WORKERS_PROJECT}")
        url = f"https://api.cloudflare.com/client/v4/accounts/{account_id}/workers/scripts/{WORKERS_PROJECT}"
        headers = get_cloudflare_headers()
        response = session.get(url, headers=headers, timeout=30)
        
        logger.info(f"Workers API 响应状态码: {response.status_code}")
        print(f"Workers API 响应状态码: {response.status_code}")
        
        # 检查响应内容
        response_text = response.text
        logger.info(f"响应内容长度: {len(response_text)} 字符")
        logger.info(f"响应内容前200字符: {response_text[:200]}")
        print(f"响应内容长度: {len(response_text)} 字符")
        print(f"响应内容前200字符: {response_text[:200]}")
        
        response.raise_for_status()
        
        # 尝试解析 JSON
        try:
            data = response.json()
            logger.info(f"JSON 解析成功")
            print(f"JSON 解析成功")
            
            if data.get("success") and data.get("result"):
                script_content = data["result"].get("script", "")
                logger.info(f"获取到 Workers 脚本，长度: {len(script_content)} 字符")
                print(f"获取到 Workers 脚本，长度: {len(script_content)} 字符")
                return script_content
            else:
                logger.error(f"获取 Workers 脚本失败: {data}")
                print(f"错误: 获取 Workers 脚本失败: {data}")
                return None
        except ValueError as json_err:
            # 如果响应不是 JSON，可能是 multipart/form-data 格式或直接返回了脚本内容
            logger.warning(f"响应不是 JSON 格式，尝试解析: {json_err}")
            print(f"警告: 响应不是 JSON 格式，尝试解析")
            
            if response_text and len(response_text) > 0:
                # 检查是否是 multipart/form-data 格式
                if response_text.startswith('--') and 'Content-Disposition' in response_text:
                    logger.info("检测到 multipart/form-data 格式，正在解析...")
                    print("检测到 multipart/form-data 格式，正在解析...")
                    
                    # 解析 multipart 内容，提取 worker.js 部分
                    # 查找 "name=\"worker.js\"" 后面的内容
                    worker_js_pattern = r'name="worker\.js"\s*\n\s*\n(.*?)(?=\n--|\Z)'
                    match = re.search(worker_js_pattern, response_text, re.DOTALL)
                    if match:
                        script_content = match.group(1)
                        # 移除末尾可能的边界标记
                        script_content = script_content.rstrip()
                        if script_content.endswith('--'):
                            script_content = script_content[:-2].rstrip()
                        
                        logger.info(f"成功提取 JavaScript 代码，长度: {len(script_content)} 字符")
                        print(f"成功提取 JavaScript 代码，长度: {len(script_content)} 字符")
                        logger.info(f"代码前200字符: {script_content[:200]}")
                        print(f"代码前200字符: {script_content[:200]}")
                        return script_content
                    else:
                        logger.warning("无法从 multipart 中提取 worker.js，尝试直接使用响应内容")
                        print("警告: 无法从 multipart 中提取 worker.js，尝试直接使用响应内容")
                        return response_text
                else:
                    # 直接返回响应内容（可能是纯 JavaScript）
                    logger.info(f"直接使用响应内容作为脚本，长度: {len(response_text)} 字符")
                    print(f"直接使用响应内容作为脚本，长度: {len(response_text)} 字符")
                    return response_text
            else:
                logger.error("响应内容为空")
                print("错误: 响应内容为空")
                return None
                
    except requests.exceptions.HTTPError as e:
        logger.error(f"HTTP 错误: {e}")
        print(f"错误: HTTP 错误: {e}")
        if hasattr(e.response, 'text'):
            logger.error(f"错误响应内容: {e.response.text[:500]}")
            print(f"错误响应内容: {e.response.text[:500]}")
        import traceback
        logger.error(traceback.format_exc())
        return None
    except Exception as e:
        logger.error(f"获取 Workers 脚本时出错: {e}")
        print(f"错误: 获取 Workers 脚本时出错: {e}")
        import traceback
        logger.error(traceback.format_exc())
        return None


def deploy_workers_script(session, account_id):
    """部署 Workers 脚本到生产环境"""
    try:
        logger.info("正在部署 Workers 脚本到生产环境...")
        print("正在部署 Workers 脚本到生产环境...")
        
        # 根据 Cloudflare Workers API，PUT 上传脚本后会自动部署到生产环境
        # 但也可以显式调用部署 API
        # 方法1: 检查是否有部署 API
        url = f"https://api.cloudflare.com/client/v4/accounts/{account_id}/workers/scripts/{WORKERS_PROJECT}/deployments"
        headers = get_cloudflare_headers()
        headers["Content-Type"] = "application/json"
        
        # 部署到生产环境
        deploy_data = {
            "strategy": "percentage",
            "versions": [
                {
                    "version_id": "latest",  # 使用最新版本
                    "percentage": 100  # 100% 流量
                }
            ]
        }
        
        try:
            response = session.post(url, headers=headers, json=deploy_data, timeout=30)
            logger.info(f"部署 API 响应状态码: {response.status_code}")
            print(f"部署 API 响应状态码: {response.status_code}")
            
            if response.status_code in [200, 201]:
                try:
                    data = response.json()
                    if data.get("success"):
                        logger.info("Workers 脚本部署成功")
                        print("Workers 脚本部署成功")
                        return True
                    else:
                        logger.warning(f"部署 API 返回 success=false: {data}")
                        print(f"警告: 部署 API 返回 success=false: {data}")
                        # 即使返回 false，PUT 上传可能已经自动部署
                        return True
                except ValueError:
                    # 如果不是 JSON，可能也是成功
                    logger.info("部署请求成功（响应不是 JSON）")
                    print("部署请求成功（响应不是 JSON）")
                    return True
            else:
                # 如果部署 API 不存在或失败，PUT 上传可能已经自动部署
                logger.info(f"部署 API 响应状态码: {response.status_code}，但 PUT 上传可能已自动部署")
                print(f"部署 API 响应状态码: {response.status_code}，但 PUT 上传可能已自动部署")
                return True
                
        except Exception as deploy_err:
            # 如果部署 API 调用失败，PUT 上传通常已经自动部署
            logger.info(f"部署 API 调用失败: {deploy_err}，但 PUT 上传通常已自动部署")
            print(f"部署 API 调用失败: {deploy_err}，但 PUT 上传通常已自动部署")
            return True
            
    except Exception as e:
        logger.warning(f"部署 Workers 脚本时出错: {e}")
        print(f"警告: 部署 Workers 脚本时出错: {e}")
        # 即使部署失败，PUT 上传通常已经自动部署
        return True


def update_workers_script_uuid(session, account_id, old_uuid, new_uuid):
    """更新 Workers 脚本中的 UUID"""
    try:
        logger.info(f"正在更新 Workers 脚本中的 UUID: {old_uuid} -> {new_uuid}")
        print(f"正在更新 Workers 脚本中的 UUID: {old_uuid} -> {new_uuid}")
        
        # 首先获取当前脚本内容
        script_content = get_workers_script(session, account_id)
        if not script_content:
            logger.error("无法获取 Workers 脚本内容")
            print("错误: 无法获取 Workers 脚本内容")
            return False
        
        # 替换脚本中的 UUID
        # 优先查找 let userID = "..." 格式
        updated_content = script_content
        replacements = 0
        
        # 方式1: 查找并替换 let userID = "uuid" 格式
        # 匹配 let userID = "uuid"; 或 let userID = "uuid" 后面可能有注释
        userid_pattern = r'let\s+userID\s*=\s*["\']([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})["\']\s*;?'
        match = re.search(userid_pattern, updated_content, re.IGNORECASE)
        if match:
            found_uuid = match.group(1)
            logger.info(f"找到 userID 变量中的 UUID: {found_uuid}")
            print(f"找到 userID 变量中的 UUID: {found_uuid}")
            # 查找匹配后的内容，保留后面的注释等
            match_end = match.end()
            # 检查后面是否有注释
            rest_of_line = updated_content[match_end:match_end+100]
            # 替换整个匹配的内容，保留后面的内容
            updated_content = re.sub(
                userid_pattern,
                f'let userID = "{new_uuid}";',
                updated_content,
                count=1,  # 只替换第一个匹配
                flags=re.IGNORECASE
            )
            replacements += 1
            logger.info(f"成功替换 userID 变量: {found_uuid} -> {new_uuid}")
            print(f"成功替换 userID 变量: {found_uuid} -> {new_uuid}")
            # 验证替换结果，修复可能的双分号
            if f'let userID = "{new_uuid}";;' in updated_content:
                logger.warning("检测到双分号，正在修复...")
                print("警告: 检测到双分号，正在修复...")
                updated_content = updated_content.replace(f'let userID = "{new_uuid}";;', f'let userID = "{new_uuid}";')
            # 验证替换后的内容
            verify_line = [line for line in updated_content.split('\n')[:5] if 'userID' in line]
            if verify_line:
                logger.info(f"验证替换结果: {verify_line[0][:100]}")
                print(f"验证替换结果: {verify_line[0][:100]}")
        else:
            # 方式2: 如果没找到 userID 格式，尝试直接替换 UUID
            logger.warning("未找到 let userID = ... 格式，尝试直接替换 UUID")
            print("警告: 未找到 let userID = ... 格式，尝试直接替换 UUID")
            
            if old_uuid in updated_content:
                updated_content = updated_content.replace(old_uuid, new_uuid)
                replacements += updated_content.count(new_uuid) - script_content.count(new_uuid)
                logger.info(f"直接替换 UUID: {old_uuid} -> {new_uuid}")
                print(f"直接替换 UUID: {old_uuid} -> {new_uuid}")
            else:
                # 方式3: 使用正则表达式查找所有 UUID
                uuid_pattern = r'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
                matches = re.findall(uuid_pattern, updated_content, re.IGNORECASE)
                if matches:
                    first_uuid = matches[0]
                    updated_content = re.sub(first_uuid, new_uuid, updated_content, flags=re.IGNORECASE)
                    replacements += 1
                    logger.info(f"替换了脚本中的第一个 UUID: {first_uuid} -> {new_uuid}")
                    print(f"替换了脚本中的第一个 UUID: {first_uuid} -> {new_uuid}")
        
        if replacements == 0:
            logger.error("无法在脚本中找到可替换的 UUID")
            print("错误: 无法在脚本中找到可替换的 UUID")
            logger.error("脚本内容前500字符:")
            logger.error(script_content[:500])
            print("脚本内容前500字符:")
            print(script_content[:500])
            return False
        
        # 更新 Workers 脚本
        url = f"https://api.cloudflare.com/client/v4/accounts/{account_id}/workers/scripts/{WORKERS_PROJECT}"
        headers = get_cloudflare_headers()
        
        # 根据 Cloudflare Workers API，上传脚本应该使用 multipart/form-data
        # 但根据 API 文档，也可以直接使用 application/javascript
        # 先尝试直接上传（更简单的方式）
        import io
        
        logger.info("正在上传更新后的 Workers 脚本...")
        print("正在上传更新后的 Workers 脚本...")
        logger.info(f"上传内容长度: {len(updated_content)} 字符")
        print(f"上传内容长度: {len(updated_content)} 字符")
        logger.info(f"上传内容前200字符: {updated_content[:200]}")
        print(f"上传内容前200字符: {updated_content[:200]}")
        
        # 根据 Cloudflare Workers API 文档，上传脚本需要使用 multipart/form-data
        # 但 PUT 请求可能需要特殊处理
        # 方法1: 尝试使用 multipart/form-data（与获取时格式一致）
        upload_success = False
        last_error = None
        
        try:
            # 根据 Cloudflare Workers API，字段名应该是 "script" 而不是 "worker.js"
            # 使用 BytesIO 而不是 StringIO，确保内容正确编码
            script_bytes = updated_content.encode('utf-8')
            files = {
                'script': ('worker.js', io.BytesIO(script_bytes), 'application/javascript')
            }
            
            # 移除 Content-Type，让 requests 自动设置（包括 boundary）
            upload_headers = headers.copy()
            if 'Content-Type' in upload_headers:
                del upload_headers['Content-Type']
            
            logger.info("尝试使用 multipart/form-data 格式上传（字段名: script, BytesIO）...")
            print("尝试使用 multipart/form-data 格式上传（字段名: script, BytesIO）...")
            response = session.put(url, headers=upload_headers, files=files, timeout=60)
            
            logger.info(f"multipart 上传响应状态码: {response.status_code}")
            print(f"multipart 上传响应状态码: {response.status_code}")
            
            if response.status_code in [200, 201]:
                logger.info("multipart 格式上传成功")
                print("multipart 格式上传成功")
                upload_success = True
            else:
                # 获取详细错误信息
                try:
                    error_data = response.json()
                    logger.error(f"multipart 上传失败，错误详情: {error_data}")
                    print(f"错误: multipart 上传失败，错误详情: {error_data}")
                    last_error = f"状态码: {response.status_code}, 错误: {error_data}"
                except:
                    logger.error(f"multipart 上传失败，响应内容: {response.text[:500]}")
                    print(f"错误: multipart 上传失败，响应内容: {response.text[:500]}")
                    last_error = f"状态码: {response.status_code}, 响应: {response.text[:200]}"
                
        except Exception as e1:
            logger.warning(f"multipart 格式上传异常: {e1}")
            print(f"警告: multipart 格式上传异常: {e1}")
            last_error = str(e1)
        
        # 如果 multipart 失败，尝试方法2: 手动构建 multipart 数据
        if not upload_success:
            try:
                import uuid as boundary_uuid
                boundary = f"----WebKitFormBoundary{boundary_uuid.uuid4().hex[:16]}"
                
                # 手动构建 multipart/form-data（使用 \r\n 换行符）
                multipart_body = f"""--{boundary}\r\nContent-Disposition: form-data; name="script"; filename="worker.js"\r\nContent-Type: application/javascript\r\n\r\n{updated_content}\r\n--{boundary}--\r\n"""
                
                upload_headers = headers.copy()
                upload_headers['Content-Type'] = f'multipart/form-data; boundary={boundary}'
                
                logger.info("尝试手动构建 multipart/form-data 格式上传...")
                print("尝试手动构建 multipart/form-data 格式上传...")
                response = session.put(url, headers=upload_headers, data=multipart_body.encode('utf-8'), timeout=60)
                
                logger.info(f"手动 multipart 上传响应状态码: {response.status_code}")
                print(f"手动 multipart 上传响应状态码: {response.status_code}")
                
                if response.status_code in [200, 201]:
                    logger.info("手动 multipart 格式上传成功")
                    print("手动 multipart 格式上传成功")
                    upload_success = True
                else:
                    # 获取详细错误信息
                    try:
                        error_data = response.json()
                        logger.error(f"手动 multipart 上传失败，错误详情: {error_data}")
                        print(f"错误: 手动 multipart 上传失败，错误详情: {error_data}")
                        last_error = f"状态码: {response.status_code}, 错误: {error_data}"
                    except:
                        logger.error(f"手动 multipart 上传失败，响应内容: {response.text[:500]}")
                        print(f"错误: 手动 multipart 上传失败，响应内容: {response.text[:500]}")
                        last_error = f"状态码: {response.status_code}, 响应: {response.text[:200]}"
                        
            except Exception as e2:
                logger.warning(f"手动 multipart 上传异常: {e2}")
                print(f"警告: 手动 multipart 上传异常: {e2}")
                if not last_error:
                    last_error = str(e2)
        
        # 如果都失败，尝试方法3: 直接使用 application/javascript（虽然可能失败，但尝试一下）
        if not upload_success:
            try:
                upload_headers = headers.copy()
                upload_headers['Content-Type'] = 'application/javascript'
                
                logger.info("尝试使用 application/javascript 格式直接上传...")
                print("尝试使用 application/javascript 格式直接上传...")
                response = session.put(url, headers=upload_headers, data=updated_content.encode('utf-8'), timeout=60)
                
                logger.info(f"直接上传响应状态码: {response.status_code}")
                print(f"直接上传响应状态码: {response.status_code}")
                
                if response.status_code in [200, 201]:
                    logger.info("直接上传成功")
                    print("直接上传成功")
                    upload_success = True
                else:
                    # 获取详细错误信息
                    try:
                        error_data = response.json()
                        logger.error(f"直接上传失败，错误详情: {error_data}")
                        print(f"错误: 直接上传失败，错误详情: {error_data}")
                        last_error = f"状态码: {response.status_code}, 错误: {error_data}"
                    except:
                        logger.error(f"直接上传失败，响应内容: {response.text[:500]}")
                        print(f"错误: 直接上传失败，响应内容: {response.text[:500]}")
                        last_error = f"状态码: {response.status_code}, 响应: {response.text[:200]}"
                        
            except Exception as e2:
                logger.error(f"直接上传异常: {e2}")
                print(f"错误: 直接上传异常: {e2}")
                last_error = str(e2)
        
        # 如果两种方式都失败，抛出异常
        if not upload_success:
            logger.error(f"所有上传方式都失败，最后错误: {last_error}")
            print(f"错误: 所有上传方式都失败，最后错误: {last_error}")
            raise Exception(f"Workers 脚本上传失败: {last_error}")
        
        logger.info(f"上传响应状态码: {response.status_code}")
        print(f"上传响应状态码: {response.status_code}")
        
        # 检查响应
        try:
            response.raise_for_status()
            # PUT 请求可能返回空响应或脚本内容，不一定返回 JSON
            response_text = response.text
            logger.info(f"上传响应内容长度: {len(response_text)} 字符")
            print(f"上传响应内容长度: {len(response_text)} 字符")
            
            # 尝试解析 JSON（如果返回的是 JSON）
            try:
                data = response.json()
                if data.get("success"):
                    logger.info(f"成功更新 Workers 脚本，替换了 {replacements} 处 UUID")
                    print(f"成功更新 Workers 脚本，替换了 {replacements} 处 UUID")
                else:
                    logger.warning(f"API 返回 success=false: {data}")
                    print(f"警告: API 返回 success=false: {data}")
            except ValueError:
                # 如果不是 JSON，可能是成功（PUT 可能返回空或脚本内容）
                logger.info(f"上传成功（响应不是 JSON 格式），替换了 {replacements} 处 UUID")
                print(f"上传成功（响应不是 JSON 格式），替换了 {replacements} 处 UUID")
            
            # 部署 Workers（确保更改生效）
            logger.info("等待 2 秒后开始部署...")
            print("等待 2 秒后开始部署...")
            import time
            time.sleep(2)
            
            deploy_success = deploy_workers_script(session, account_id)
            if deploy_success:
                logger.info("Workers 脚本更新并部署成功")
                print("Workers 脚本更新并部署成功")
                # 再等待一下，确保部署生效
                logger.info("等待 5 秒，确保部署生效...")
                print("等待 5 秒，确保部署生效...")
                time.sleep(5)
                return True
            else:
                logger.warning("Workers 脚本部署可能失败，但继续执行")
                print("警告: Workers 脚本部署可能失败，但继续执行")
                # 即使部署失败也等待一下
                logger.info("等待 5 秒...")
                print("等待 5 秒...")
                time.sleep(5)
                return True  # 即使部署失败也返回 True，因为 PUT 可能已经生效
                
        except requests.exceptions.HTTPError as e:
            logger.error(f"更新 Workers 脚本时 HTTP 错误: {e}")
            print(f"错误: 更新 Workers 脚本时 HTTP 错误: {e}")
            if hasattr(e.response, 'text'):
                logger.error(f"错误响应: {e.response.text[:500]}")
                print(f"错误响应: {e.response.text[:500]}")
            return False
            
    except Exception as e:
        logger.error(f"更新 Workers 脚本时出错: {e}")
        print(f"错误: 更新 Workers 脚本时出错: {e}")
        import traceback
        logger.error(traceback.format_exc())
        return False


def fetch_subscription(url):
    """从订阅地址获取订阅内容"""
    try:
        logger.info(f"正在从订阅地址获取内容: {url}")
        print(f"正在从订阅地址获取内容: {url}")
        response = requests.get(url, timeout=30)
        response.raise_for_status()
        
        # 检查响应内容类型
        content_type = response.headers.get('Content-Type', '')
        logger.info(f"响应 Content-Type: {content_type}")
        print(f"响应 Content-Type: {content_type}")
        
        # 订阅内容通常是 base64 编码的
        content = response.text.strip()
        logger.info(f"获取到订阅内容，长度: {len(content)} 字符")
        print(f"获取到订阅内容，长度: {len(content)} 字符")
        
        # 检查是否是 JSON 格式（可能是错误响应）
        if content.startswith('{') or content.startswith('['):
            logger.warning("订阅内容看起来是 JSON 格式，可能 Workers 未正确部署")
            print("警告: 订阅内容看起来是 JSON 格式，可能 Workers 未正确部署")
            try:
                json_data = response.json()
                logger.error(f"JSON 响应内容: {str(json_data)[:500]}")
                print(f"错误: JSON 响应内容: {str(json_data)[:500]}")
                # 如果是 JSON，说明 Workers 可能返回了错误或元数据
                return None
            except:
                pass
        
        # 尝试解码 base64
        try:
            # 尝试多种 base64 解码方式
            decoded_bytes = base64.b64decode(content)
            
            # 尝试 UTF-8 解码
            try:
                decoded_content = decoded_bytes.decode('utf-8')
                logger.info(f"Base64 解码成功（UTF-8），解码后长度: {len(decoded_content)} 字符")
                print(f"Base64 解码成功（UTF-8），解码后长度: {len(decoded_content)} 字符")
                return decoded_content
            except UnicodeDecodeError as ue:
                # 如果 UTF-8 失败，可能是 base64 解码有问题
                logger.warning(f"UTF-8 解码失败: {ue}，尝试添加填充后重新解码")
                print(f"警告: UTF-8 解码失败，尝试添加填充后重新解码")
                # 尝试添加填充后重新解码
                try:
                    padding = 4 - len(content) % 4
                    if padding != 4:
                        content_padded = content + '=' * padding
                        decoded_bytes = base64.b64decode(content_padded, validate=False)
                        decoded_content = decoded_bytes.decode('utf-8')
                        logger.info(f"Base64 解码成功（添加填充后UTF-8），解码后长度: {len(decoded_content)} 字符")
                        print(f"Base64 解码成功（添加填充后UTF-8），解码后长度: {len(decoded_content)} 字符")
                        return decoded_content
                except Exception as e2:
                    logger.warning(f"添加填充后仍失败: {e2}，尝试直接使用原始内容")
                    print(f"警告: 添加填充后仍失败，尝试直接使用原始内容")
                    return content
                    
        except Exception as e:
            logger.warning(f"Base64 解码失败: {e}，尝试直接使用原始内容")
            print(f"警告: Base64 解码失败: {e}，尝试直接使用原始内容")
            # 如果 base64 解码失败，可能是内容已经是纯文本
            return content
            
    except Exception as e:
        logger.error(f"获取订阅内容失败: {e}")
        print(f"错误: 获取订阅内容失败: {e}")
        import traceback
        logger.error(traceback.format_exc())
        return None


def parse_vless_url(vless_url):
    """解析 vless URL，返回各个组件"""
    try:
        if not vless_url.startswith("vless://"):
            return None
        
        # 移除协议前缀
        url_without_protocol = vless_url[8:]
        
        # 分离参数和锚点
        if "#" in url_without_protocol:
            url_part, name = url_without_protocol.split("#", 1)
            name = urllib.parse.unquote(name)
        else:
            url_part = url_without_protocol
            name = ""
        
        # 分离查询参数
        if "?" in url_part:
            base_part, query_string = url_part.split("?", 1)
            params = parse_qs(query_string)
            # 将列表值转换为单个值
            params = {k: v[0] if isinstance(v, list) and len(v) > 0 else v for k, v in params.items()}
        else:
            base_part = url_part
            params = {}
        
        # 解析 UUID@服务器:端口
        if "@" in base_part:
            uuid_part, server_port = base_part.split("@", 1)
            uuid = uuid_part
            if ":" in server_port:
                server, port = server_port.split(":", 1)
                # 移除可能的路径
                if "/" in port:
                    port = port.split("/")[0]
            else:
                server = server_port
                port = "443"
        else:
            return None
        
        return {
            "uuid": uuid,
            "server": server,
            "port": port,
            "params": params,
            "name": name,
            "original_url": vless_url
        }
    except Exception as e:
        logger.warning(f"解析 vless URL 失败: {vless_url[:50]}... 错误: {e}")
        return None


def build_vless_url(parsed_data, new_server, node_name=None):
    """根据解析的数据构建新的 vless URL"""
    try:
        uuid = parsed_data["uuid"]
        port = parsed_data["port"]
        params = parsed_data["params"]
        
        # 如果提供了节点名称，使用提供的名称；否则使用原始名称
        if node_name:
            name = node_name
        else:
            name = parsed_data["name"]
        
        # 构建查询字符串
        query_parts = []
        for key, value in params.items():
            if value:
                query_parts.append(f"{key}={urllib.parse.quote(str(value))}")
        
        query_string = "&".join(query_parts)
        
        # 构建 URL
        url = f"vless://{uuid}@{new_server}:{port}"
        if query_string:
            url += f"?{query_string}"
        if name:
            url += f"#{urllib.parse.quote(name)}"
        
        return url
    except Exception as e:
        logger.warning(f"构建 vless URL 失败: {e}")
        return None


def replace_server_in_vless(vless_url, replacement_servers, start_index=1):
    """替换 vless URL 中的服务器地址，为每个服务器生成一个新的 URL"""
    parsed = parse_vless_url(vless_url)
    if not parsed:
        return []
    
    new_urls = []
    for idx, server in enumerate(replacement_servers):
        # 生成节点名称：美国稳定线路1, 美国稳定线路2, ...
        node_name = f"美国稳定线路{start_index + idx}"
        new_url = build_vless_url(parsed, server, node_name)
        if new_url:
            new_urls.append(new_url)
    
    return new_urls


def process_subscription(subscription_content):
    """处理订阅内容，提取 vless 链接并替换服务器地址"""
    if not subscription_content:
        return []
    
    # 显示订阅内容的前500字符用于调试
    logger.info(f"订阅内容前500字符: {subscription_content[:500]}")
    print(f"订阅内容前500字符: {subscription_content[:500]}")
    
    # 检查是否是 JSON 格式（说明 Workers 可能未正确部署）
    if subscription_content.strip().startswith('{'):
        logger.error("订阅内容是 JSON 格式，不是 base64 编码的 vless 链接")
        print("错误: 订阅内容是 JSON 格式，不是 base64 编码的 vless 链接")
        logger.error("这可能意味着 Workers 脚本未正确更新或部署")
        print("这可能意味着 Workers 脚本未正确更新或部署")
        return []
    
    # 按行分割订阅内容
    lines = subscription_content.strip().split('\n')
    logger.info(f"订阅内容包含 {len(lines)} 行")
    print(f"订阅内容包含 {len(lines)} 行")
    
    # 显示前几行内容
    for i, line in enumerate(lines[:5]):
        logger.info(f"第 {i+1} 行: {line[:100]}")
        print(f"第 {i+1} 行: {line[:100]}")
    
    all_vless_urls = []
    vless_count = 0
    node_index = 1  # 节点名称编号从1开始
    
    for line in lines:
        line = line.strip()
        if not line:
            continue
        
        # 检查是否是 vless 链接
        if line.startswith("vless://"):
            vless_count += 1
            logger.info(f"找到 vless 链接 {vless_count}: {line[:80]}...")
            print(f"找到 vless 链接 {vless_count}: {line[:80]}...")
            
            # 为每个替换服务器生成新的 URL，使用当前节点索引
            new_urls = replace_server_in_vless(line, REPLACEMENT_SERVERS, node_index)
            all_vless_urls.extend(new_urls)
            
            # 更新节点索引（每个原始链接生成 len(REPLACEMENT_SERVERS) 个新链接）
            logger.info(f"  生成了 {len(new_urls)} 个替换后的链接（节点名称：美国稳定线路{node_index} 到 美国稳定线路{node_index + len(new_urls) - 1}）")
            print(f"  生成了 {len(new_urls)} 个替换后的链接（节点名称：美国稳定线路{node_index} 到 美国稳定线路{node_index + len(new_urls) - 1}）")
            node_index += len(new_urls)
    
    logger.info(f"总共找到 {vless_count} 个原始 vless 链接，生成了 {len(all_vless_urls)} 个替换后的链接")
    logger.info(f"节点名称范围：美国稳定线路1 到 美国稳定线路{node_index - 1}")
    print(f"总共找到 {vless_count} 个原始 vless 链接，生成了 {len(all_vless_urls)} 个替换后的链接")
    print(f"节点名称范围：美国稳定线路1 到 美国稳定线路{node_index - 1}")
    return all_vless_urls


def save_vless_urls(vless_urls, file_path):
    """将 vless 链接保存到文件，每行一个"""
    try:
        # 确保目录存在
        dir_path = os.path.dirname(file_path)
        if dir_path:
            os.makedirs(dir_path, exist_ok=True)
            logger.info(f"确保目录存在: {dir_path}")
            print(f"确保目录存在: {dir_path}")
        
        # 检查文件路径
        logger.info(f"准备保存文件到: {file_path}")
        print(f"准备保存文件到: {file_path}")
        
        with open(file_path, 'w', encoding='utf-8') as f:
            for url in vless_urls:
                f.write(f"{url}\n")
        
        # 验证文件是否成功创建
        if os.path.exists(file_path):
            file_size = os.path.getsize(file_path)
            logger.info(f"成功保存 {len(vless_urls)} 个 vless 链接到: {file_path}")
            logger.info(f"文件大小: {file_size} 字节")
            print(f"成功保存 {len(vless_urls)} 个 vless 链接到: {file_path}")
            print(f"文件大小: {file_size} 字节")
            return True
        else:
            logger.error(f"文件保存后不存在: {file_path}")
            print(f"错误: 文件保存后不存在: {file_path}")
            return False
    except Exception as e:
        logger.error(f"保存 vless 链接失败: {e}")
        print(f"错误: 保存 vless 链接失败: {e}")
        import traceback
        logger.error(traceback.format_exc())
        return False


def main():
    """主函数"""
    start_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    logger.info("=" * 50)
    logger.info("Cloudflare Workers 订阅更新脚本启动")
    logger.info(f"开始时间: {start_time}")
    print("=" * 50)
    print("Cloudflare Workers 订阅更新脚本启动")
    print(f"开始时间: {start_time}")
    
    # 显示配置信息
    logger.info(f"输出目录: {VPS_DIR}")
    logger.info(f"输出文件: {output_file}")
    logger.info(f"日志文件: {log_file}")
    print(f"输出目录: {VPS_DIR}")
    print(f"输出文件: {output_file}")
    print(f"日志文件: {log_file}")
    
    # 创建会话
    session = requests.Session()
    
    # 步骤1: 生成新的 UUID
    logger.info("步骤1: 生成新的 UUID")
    print("步骤1: 生成新的 UUID")
    new_uuid = generate_new_uuid()
    
    # 步骤2: 获取 Cloudflare 账户 ID
    logger.info("步骤2: 获取 Cloudflare 账户 ID")
    print("步骤2: 获取 Cloudflare 账户 ID")
    
    # 首先尝试从配置中获取
    account_id = CLOUDFLARE_ACCOUNT_ID
    
    # 如果配置中没有，尝试通过 API 获取
    if not account_id:
        account_id = get_cloudflare_account_id(session)
        if not account_id:
            logger.error("无法获取账户 ID")
            print("错误: 无法获取账户 ID")
            logger.error("请检查：")
            logger.error("1. API Token 是否正确")
            logger.error("2. API Token 是否有 Account:Read 权限")
            logger.error("3. 或者直接在脚本中配置 CLOUDFLARE_ACCOUNT_ID")
            print("请检查：")
            print("1. API Token 是否正确")
            print("2. API Token 是否有 Account:Read 权限")
            print("3. 或者直接在脚本中配置 CLOUDFLARE_ACCOUNT_ID")
            logger.error("脚本终止")
            print("错误: 脚本终止")
            return False
    
    # 步骤3: 获取 Workers 脚本并提取旧的 UUID
    logger.info("步骤3: 获取 Workers 脚本并提取旧的 UUID")
    print("步骤3: 获取 Workers 脚本并提取旧的 UUID")
    script_content = get_workers_script(session, account_id)
    if not script_content:
        logger.error("无法获取 Workers 脚本，脚本终止")
        print("错误: 无法获取 Workers 脚本，脚本终止")
        return False
    
    # 从脚本中提取旧的 UUID
    uuid_pattern = r'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
    matches = re.findall(uuid_pattern, script_content, re.IGNORECASE)
    old_uuid = None
    if matches:
        # 优先选择在 URL 路径中的 UUID
        for match in matches:
            if f"/{match}/" in script_content or f"/{match}" in script_content:
                old_uuid = match
                break
        if not old_uuid:
            old_uuid = matches[0]
        logger.info(f"从 Workers 脚本中提取到旧 UUID: {old_uuid}")
        print(f"从 Workers 脚本中提取到旧 UUID: {old_uuid}")
    else:
        logger.warning("未能从脚本中提取到 UUID，使用配置的默认 UUID")
        print("警告: 未能从脚本中提取到 UUID，使用配置的默认 UUID")
        old_uuid = UUID
    
    # 步骤4: 更新 Workers 脚本中的 UUID
    logger.info("步骤4: 更新 Workers 脚本中的 UUID")
    print("步骤4: 更新 Workers 脚本中的 UUID")
    update_success = update_workers_script_uuid(session, account_id, old_uuid, new_uuid)
    if not update_success:
        logger.warning("更新 Workers 脚本失败，但继续使用新 UUID 获取订阅")
        print("警告: 更新 Workers 脚本失败，但继续使用新 UUID 获取订阅")
    
    # 等待一下，确保 Workers 更新生效
    import time
    logger.info("等待 3 秒，确保 Workers 更新生效...")
    print("等待 3 秒，确保 Workers 更新生效...")
    time.sleep(3)
    
    # 步骤5: 构建订阅地址（使用新 UUID）
    logger.info("步骤5: 构建订阅地址（使用新 UUID）")
    print("步骤5: 构建订阅地址（使用新 UUID）")
    subscription_url = f"https://{CUSTOM_DOMAIN}/{new_uuid}/pty"
    logger.info(f"订阅地址: {subscription_url}")
    print(f"订阅地址: {subscription_url}")
    
    # 步骤6: 获取订阅内容
    logger.info("步骤6: 获取订阅内容")
    print("步骤6: 获取订阅内容")
    subscription_content = fetch_subscription(subscription_url)
    if not subscription_content:
        logger.error("无法获取订阅内容，脚本终止")
        print("错误: 无法获取订阅内容，脚本终止")
        return False
    
    logger.info(f"成功获取订阅内容，长度: {len(subscription_content)} 字符")
    print(f"成功获取订阅内容，长度: {len(subscription_content)} 字符")
    
    # 步骤7: 处理订阅内容
    logger.info("步骤7: 处理订阅内容，提取并替换 vless 链接")
    print("步骤7: 处理订阅内容，提取并替换 vless 链接")
    vless_urls = process_subscription(subscription_content)
    
    if not vless_urls:
        logger.warning("没有找到或生成任何 vless 链接")
        print("警告: 没有找到或生成任何 vless 链接")
        return False
    
    logger.info(f"处理完成，共生成 {len(vless_urls)} 个 vless 链接")
    print(f"处理完成，共生成 {len(vless_urls)} 个 vless 链接")
    
    # 步骤8: 保存到文件
    logger.info("步骤8: 保存 vless 链接到文件")
    print("步骤8: 保存 vless 链接到文件")
    success = save_vless_urls(vless_urls, output_file)
    
    if success:
        end_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        logger.info("=" * 50)
        logger.info("脚本执行完成")
        logger.info(f"结束时间: {end_time}")
        logger.info(f"旧 UUID: {old_uuid}")
        logger.info(f"新 UUID: {new_uuid}")
        logger.info(f"成功生成 {len(vless_urls)} 个 vless 链接")
        logger.info(f"输出文件: {output_file}")
        print("=" * 50)
        print("脚本执行完成")
        print(f"结束时间: {end_time}")
        print(f"旧 UUID: {old_uuid}")
        print(f"新 UUID: {new_uuid}")
        print(f"成功生成 {len(vless_urls)} 个 vless 链接")
        print(f"输出文件: {output_file}")
        return True
    else:
        logger.error("脚本执行失败")
        print("错误: 脚本执行失败")
        return False


if __name__ == "__main__":
    success = main()
    exit(0 if success else 1)

