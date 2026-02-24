#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import sys
import base64
import json
import re
import urllib.parse
import os
import socket
import struct
from collections import defaultdict

# IP地址段到国家的映射（简化版，实际应用中可以使用更完整的数据库）
IP_COUNTRY_MAPPING = {
    # 美国IP段
    '65.49.': 'US',  # 美国
    '23.106.': 'US',  # 美国
    '198.35.': 'US',  # 美国
    '162.248.': 'US',  # 美国
    '45.62.': 'US',   # 美国
    
    # 其他常见IP段可以继续添加
}

# 国家/地区映射表 - 全球完整版
COUNTRY_MAPPING = {
    # 东亚
    '中国': 'CN', '香港': 'HK', '台湾': 'TW', '澳门': 'MO', '日本': 'JP', '韩国': 'KR', '朝鲜': 'KP', '蒙古': 'MN',
    
    # 东南亚
    '越南': 'VN', '老挝': 'LA', '柬埔寨': 'KH', '泰国': 'TH', '缅甸': 'MM', '马来西亚': 'MY', '新加坡': 'SG',
    '印度尼西亚': 'ID', '菲律宾': 'PH', '文莱': 'BN', '东帝汶': 'TL', '巴布亚新几内亚': 'PG',
    
    # 南亚
    '印度': 'IN', '巴基斯坦': 'PK', '孟加拉国': 'BD', '斯里兰卡': 'LK', '尼泊尔': 'NP', '不丹': 'BT', '马尔代夫': 'MV', '阿富汗': 'AF',
    
    # 中亚
    '哈萨克斯坦': 'KZ', '乌兹别克斯坦': 'UZ', '土库曼斯坦': 'TM', '塔吉克斯坦': 'TJ', '吉尔吉斯斯坦': 'KG',
    
    # 西亚/中东
    '伊朗': 'IR', '伊拉克': 'IQ', '科威特': 'KW', '沙特阿拉伯': 'SA', '阿联酋': 'AE', '阿曼': 'OM', '也门': 'YE',
    '卡塔尔': 'QA', '巴林': 'BH', '约旦': 'JO', '黎巴嫩': 'LB', '叙利亚': 'SY', '以色列': 'IL', '巴勒斯坦': 'PS', '土耳其': 'TR',
    '塞浦路斯': 'CY', '格鲁吉亚': 'GE', '亚美尼亚': 'AM', '阿塞拜疆': 'AZ',
    
    # 北非
    '埃及': 'EG', '利比亚': 'LY', '突尼斯': 'TN', '阿尔及利亚': 'DZ', '摩洛哥': 'MA', '毛里塔尼亚': 'MR',
    
    # 西非
    '塞内加尔': 'SN', '冈比亚': 'GM', '几内亚比绍': 'GW', '几内亚': 'GN', '塞拉利昂': 'SL', '利比里亚': 'LR',
    '科特迪瓦': 'CI', '加纳': 'GH', '多哥': 'TG', '贝宁': 'BJ', '尼日尔': 'NE', '尼日利亚': 'NG', '喀麦隆': 'CM',
    '乍得': 'TD', '中非共和国': 'CF', '赤道几内亚': 'GQ', '加蓬': 'GA', '刚果共和国': 'CG', '刚果民主共和国': 'CD',
    
    # 东非
    '苏丹': 'SD', '南苏丹': 'SS', '埃塞俄比亚': 'ET', '厄立特里亚': 'ER', '吉布提': 'DJ', '索马里': 'SO', '肯尼亚': 'KE',
    '乌干达': 'UG', '坦桑尼亚': 'TZ', '卢旺达': 'RW', '布隆迪': 'BI', '安哥拉': 'AO', '赞比亚': 'ZM', '马拉维': 'MW',
    '莫桑比克': 'MZ', '津巴布韦': 'ZW', '博茨瓦纳': 'BW', '纳米比亚': 'NA', '南非': 'ZA', '莱索托': 'LS', '斯威士兰': 'SZ',
    '马达加斯加': 'MG', '毛里求斯': 'MU', '塞舌尔': 'SC', '科摩罗': 'KM', '佛得角': 'CV', '圣多美和普林西比': 'ST',
    
    # 欧洲
    '俄罗斯': 'RU', '乌克兰': 'UA', '白俄罗斯': 'BY', '摩尔多瓦': 'MD', '爱沙尼亚': 'EE', '拉脱维亚': 'LV', '立陶宛': 'LT',
    '波兰': 'PL', '捷克': 'CZ', '斯洛伐克': 'SK', '匈牙利': 'HU', '罗马尼亚': 'RO', '保加利亚': 'BG', '希腊': 'GR',
    '阿尔巴尼亚': 'AL', '北马其顿': 'MK', '塞尔维亚': 'RS', '黑山': 'ME', '波斯尼亚和黑塞哥维那': 'BA', '克罗地亚': 'HR',
    '斯洛文尼亚': 'SI', '奥地利': 'AT', '瑞士': 'CH', '列支敦士登': 'LI', '德国': 'DE', '法国': 'FR', '比利时': 'BE',
    '荷兰': 'NL', '卢森堡': 'LU', '英国': 'GB', '爱尔兰': 'IE', '冰岛': 'IS', '挪威': 'NO', '瑞典': 'SE', '芬兰': 'FI',
    '丹麦': 'DK', '葡萄牙': 'PT', '西班牙': 'ES', '意大利': 'IT', '马耳他': 'MT', '圣马力诺': 'SM', '梵蒂冈': 'VA',
    '摩纳哥': 'MC', '安道尔': 'AD',
    
    # 北美
    '美国': 'US', '加拿大': 'CA', '墨西哥': 'MX',
    
    # 中美洲
    '危地马拉': 'GT', '伯利兹': 'BZ', '萨尔瓦多': 'SV', '洪都拉斯': 'HN', '尼加拉瓜': 'NI', '哥斯达黎加': 'CR', '巴拿马': 'PA',
    
    # 加勒比海
    '古巴': 'CU', '牙买加': 'JM', '海地': 'HT', '多米尼加': 'DO', '巴哈马': 'BS', '巴巴多斯': 'BB', '特立尼达和多巴哥': 'TT',
    '格林纳达': 'GD', '圣文森特和格林纳丁斯': 'VC', '圣卢西亚': 'LC', '多米尼克': 'DM', '安提瓜和巴布达': 'AG', '圣基茨和尼维斯': 'KN',
    '波多黎各': 'PR', '美属维尔京群岛': 'VI', '英属维尔京群岛': 'VG', '开曼群岛': 'KY', '百慕大': 'BM', '特克斯和凯科斯群岛': 'TC',
    '安圭拉': 'AI', '蒙特塞拉特': 'MS', '阿鲁巴': 'AW', '库拉索': 'CW', '圣马丁': 'SX', '法属圣马丁': 'MF', '瓜德罗普': 'GP',
    '马提尼克': 'MQ', '圣巴泰勒米': 'BL', '法属圭亚那': 'GF',
    
    # 南美
    '巴西': 'BR', '阿根廷': 'AR', '智利': 'CL', '秘鲁': 'PE', '哥伦比亚': 'CO', '委内瑞拉': 'VE', '厄瓜多尔': 'EC',
    '玻利维亚': 'BO', '巴拉圭': 'PY', '乌拉圭': 'UY', '圭亚那': 'GY', '苏里南': 'SR', '法属圭亚那': 'GF',
    
    # 大洋洲
    '澳大利亚': 'AU', '新西兰': 'NZ', '斐济': 'FJ', '所罗门群岛': 'SB', '瓦努阿图': 'VU', '新喀里多尼亚': 'NC',
    '法属波利尼西亚': 'PF', '库克群岛': 'CK', '纽埃': 'NU', '托克劳': 'TK', '萨摩亚': 'WS', '汤加': 'TO', '图瓦卢': 'TV',
    '基里巴斯': 'KI', '瑙鲁': 'NR', '帕劳': 'PW', '密克罗尼西亚': 'FM', '马绍尔群岛': 'MH', '北马里亚纳群岛': 'MP',
    '关岛': 'GU', '美属萨摩亚': 'AS', '法属瓦利斯和富图纳': 'WF', '皮特凯恩群岛': 'PN', '诺福克岛': 'NF',
    
    # 其他地区
    '格陵兰': 'GL', '法罗群岛': 'FO', '直布罗陀': 'GI', '马恩岛': 'IM', '泽西岛': 'JE', '根西岛': 'GG',
    '圣赫勒拿': 'SH', '阿森松岛': 'AC', '特里斯坦达库尼亚': 'TA', '福克兰群岛': 'FK', '南乔治亚和南桑威奇群岛': 'GS',
    '布韦岛': 'BV', '法属南部领地': 'TF', '赫德岛和麦克唐纳群岛': 'HM', '澳大利亚南极领地': 'AQ', '罗斯属地': 'AQ',
    '英属印度洋领地': 'IO', '圣诞岛': 'CX', '科科斯群岛': 'CC', '诺福克岛': 'NF', '托克劳': 'TK', '纽埃': 'NU',
    '库克群岛': 'CK', '皮特凯恩群岛': 'PN', '法属波利尼西亚': 'PF', '瓦利斯和富图纳': 'WF', '新喀里多尼亚': 'NC',
    '法属圭亚那': 'GF', '瓜德罗普': 'GP', '马提尼克': 'MQ', '留尼汪': 'RE', '马约特': 'YT', '圣皮埃尔和密克隆': 'PM',
    '圣巴泰勒米': 'BL', '圣马丁': 'MF', '阿鲁巴': 'AW', '库拉索': 'CW', '圣马丁': 'SX', '博内尔': 'BQ',
    '萨巴': 'BQ', '圣尤斯特歇斯': 'BQ', '安圭拉': 'AI', '百慕大': 'BM', '英属维尔京群岛': 'VG', '开曼群岛': 'KY',
    '蒙特塞拉特': 'MS', '特克斯和凯科斯群岛': 'TC', '美属维尔京群岛': 'VI', '波多黎各': 'PR', '关岛': 'GU',
    '美属萨摩亚': 'AS', '北马里亚纳群岛': 'MP', '贝克岛': 'UM', '豪兰岛': 'UM', '贾维斯岛': 'UM', '约翰斯顿环礁': 'UM',
    '金曼礁': 'UM', '中途岛': 'UM', '纳瓦萨岛': 'UM', '巴尔米拉环礁': 'UM', '威克岛': 'UM'
}

def unicode_decode(s):
    """解码Unicode转义序列"""
    try:
        return json.loads(f'"{s}"')
    except Exception:
        return s

def clean_name(name):
    """清理节点名称"""
    if not name:
        return name

    # 检查是否是标准格式的节点名称（如 JM-SS-024, US-VMess-001）
    import re
    standard_pattern = r'^[A-Z]{2}-[A-Za-z]+-\d+$'
    if re.match(standard_pattern, name.strip()):
        return name.strip()  # 如果是标准格式，直接返回，不进行清理

    # 去除特殊字符和表情符号，但保留中文和英文
    # 移除表情符号和特殊符号
    name = re.sub(r'[⚡️🔰🎯🚀💎⭐️🌟✨🔥💯🎉🎊🎈🎁🎂🎄🎃🎗️🎖️🏆🥇🥈🥉🏅🎪🎭🎨🎬🎤🎧🎼🎹🎸🎻🎺🎷🥁🎮🎲🎯🎳🎰🎪🎭🎨🎬🎤🎧🎼🎹🎸🎻🎺🎷🥁🎮🎲🎯🎳🎰]', '', name)
    
    # 移除管道符号和其后的内容
    name = re.sub(r'\|.*$', '', name)
    
    # 去除常见无用后缀
    patterns = [
        r'[\s]*[-_][\s]*(官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)[\s]*$',
        r'[\s]*[-_][\s]*[0-9]+[\s]*$',
        r'[\s]*[-_][\s]*[A-Za-z]+[\s]*$',
        r'(官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)$',
        r'[-_](官网|网址|连接|试用|导入|免费|Hoshino|Network|续|费|qq|超时|请更新|订阅|通知|域名|套餐|剩余|到期|流量|GB|TB|过期|expire|traffic|remain|迅云加速|快云加速|脉冲云|闪连一元公益机场|一元公益机场|公益机场|机场|加速|云)$'
    ]
    
    for pattern in patterns:
        name = re.sub(pattern, '', name)
    
    # 去掉所有空格
    name = re.sub(r'[\s]+', '', name)
    return name.strip()

def detect_country_from_name(name):
    """从节点名称中检测国家"""
    if not name:
        return None
    
    # 清理名称
    clean_name_val = clean_name(name)
    
    # 1. 直接匹配国家名
    for country_name, country_code in COUNTRY_MAPPING.items():
        if country_name.lower() in clean_name_val.lower():
            return country_code
    
    # 2. 匹配国家代码 (如 PT-SS-056, JM-SS-024)
    # 创建国家代码到国家代码的映射（用于反向查找）
    country_code_mapping = {code: code for code in set(COUNTRY_MAPPING.values())}
    
    for country_code in country_code_mapping:
        # 匹配格式：XX-SS-数字 或 XX-协议-数字
        pattern = rf'{country_code}-[A-Za-z]+-\d+'
        if re.search(pattern, clean_name_val):
            return country_code
    
    return None

def detect_country_from_ip(ip):
    """从IP地址中检测国家"""
    if not ip:
        return None
    
    # 检查是否是有效的IP地址
    try:
        socket.inet_aton(ip)
    except socket.error:
        return None
    
    # 检查IP段映射
    for ip_prefix, country_code in IP_COUNTRY_MAPPING.items():
        if ip.startswith(ip_prefix):
            return country_code
    
    return None

def detect_country_from_domain(server):
    """从域名中检测国家"""
    if not server:
        return None
    
    # 忽略一些明显不是国家域名的域名
    ignore_domains = [
        '0000088888.cc',  # 明显不是国家域名
        'portablesubmarines.com',  # 明显不是国家域名
    ]
    
    for ignore_domain in ignore_domains:
        if ignore_domain in server:
            return None
    
    # 全球国家顶级域名映射
    country_tlds = {
        # 东亚
        '.cn': 'CN', '.hk': 'HK', '.tw': 'TW', '.mo': 'MO', '.jp': 'JP', '.kr': 'KR', '.kp': 'KP', '.mn': 'MN',
        
        # 东南亚
        '.vn': 'VN', '.la': 'LA', '.kh': 'KH', '.th': 'TH', '.mm': 'MM', '.my': 'MY', '.sg': 'SG',
        '.id': 'ID', '.ph': 'PH', '.bn': 'BN', '.tl': 'TL', '.pg': 'PG',
        
        # 南亚
        '.in': 'IN', '.pk': 'PK', '.bd': 'BD', '.lk': 'LK', '.np': 'NP', '.bt': 'BT', '.mv': 'MV', '.af': 'AF',
        
        # 中亚
        '.kz': 'KZ', '.uz': 'UZ', '.tm': 'TM', '.tj': 'TJ', '.kg': 'KG',
        
        # 西亚/中东
        '.ir': 'IR', '.iq': 'IQ', '.kw': 'KW', '.sa': 'SA', '.ae': 'AE', '.om': 'OM', '.ye': 'YE',
        '.qa': 'QA', '.bh': 'BH', '.jo': 'JO', '.lb': 'LB', '.sy': 'SY', '.il': 'IL', '.ps': 'PS', '.tr': 'TR',
        '.cy': 'CY', '.ge': 'GE', '.am': 'AM', '.az': 'AZ',
        
        # 北非
        '.eg': 'EG', '.ly': 'LY', '.tn': 'TN', '.dz': 'DZ', '.ma': 'MA', '.mr': 'MR',
        
        # 西非
        '.sn': 'SN', '.gm': 'GM', '.gw': 'GW', '.gn': 'GN', '.sl': 'SL', '.lr': 'LR',
        '.ci': 'CI', '.gh': 'GH', '.tg': 'TG', '.bj': 'BJ', '.ne': 'NE', '.ng': 'NG', '.cm': 'CM',
        '.td': 'TD', '.cf': 'CF', '.gq': 'GQ', '.ga': 'GA', '.cg': 'CG', '.cd': 'CD',
        
        # 东非
        '.sd': 'SD', '.ss': 'SS', '.et': 'ET', '.er': 'ER', '.dj': 'DJ', '.so': 'SO', '.ke': 'KE',
        '.ug': 'UG', '.tz': 'TZ', '.rw': 'RW', '.bi': 'BI', '.ao': 'AO', '.zm': 'ZM', '.mw': 'MW',
        '.mz': 'MZ', '.zw': 'ZW', '.bw': 'BW', '.na': 'NA', '.za': 'ZA', '.ls': 'LS', '.sz': 'SZ',
        '.mg': 'MG', '.mu': 'MU', '.sc': 'SC', '.km': 'KM', '.cv': 'CV', '.st': 'ST',
        
        # 欧洲
        '.ru': 'RU', '.ua': 'UA', '.by': 'BY', '.md': 'MD', '.ee': 'EE', '.lv': 'LV', '.lt': 'LT',
        '.pl': 'PL', '.cz': 'CZ', '.sk': 'SK', '.hu': 'HU', '.ro': 'RO', '.bg': 'BG', '.gr': 'GR',
        '.al': 'AL', '.mk': 'MK', '.rs': 'RS', '.me': 'ME', '.ba': 'BA', '.hr': 'HR',
        '.si': 'SI', '.at': 'AT', '.ch': 'CH', '.li': 'LI', '.de': 'DE', '.fr': 'FR', '.be': 'BE',
        '.nl': 'NL', '.lu': 'LU', '.uk': 'GB', '.ie': 'IE', '.is': 'IS', '.no': 'NO', '.se': 'SE', '.fi': 'FI',
        '.dk': 'DK', '.pt': 'PT', '.es': 'ES', '.it': 'IT', '.mt': 'MT', '.sm': 'SM', '.va': 'VA',
        '.mc': 'MC', '.ad': 'AD',
        
        # 北美
        '.us': 'US', '.ca': 'CA', '.mx': 'MX',
        
        # 中美洲
        '.gt': 'GT', '.bz': 'BZ', '.sv': 'SV', '.hn': 'HN', '.ni': 'NI', '.cr': 'CR', '.pa': 'PA',
        
        # 加勒比海
        '.cu': 'CU', '.jm': 'JM', '.ht': 'HT', '.do': 'DO', '.bs': 'BS', '.bb': 'BB', '.tt': 'TT',
        '.gd': 'GD', '.vc': 'VC', '.lc': 'LC', '.dm': 'DM', '.ag': 'AG', '.kn': 'KN',
        '.pr': 'PR', '.vi': 'VI', '.vg': 'VG', '.ky': 'KY', '.bm': 'BM', '.tc': 'TC',
        '.ai': 'AI', '.ms': 'MS', '.aw': 'AW', '.cw': 'CW', '.sx': 'SX', '.mf': 'MF', '.gp': 'GP',
        '.mq': 'MQ', '.bl': 'BL', '.gf': 'GF',
        
        # 南美
        '.br': 'BR', '.ar': 'AR', '.cl': 'CL', '.pe': 'PE', '.co': 'CO', '.ve': 'VE', '.ec': 'EC',
        '.bo': 'BO', '.py': 'PY', '.uy': 'UY', '.gy': 'GY', '.sr': 'SR',
        
        # 大洋洲
        '.au': 'AU', '.nz': 'NZ', '.fj': 'FJ', '.sb': 'SB', '.vu': 'VU', '.nc': 'NC',
        '.pf': 'PF', '.ck': 'CK', '.nu': 'NU', '.tk': 'TK', '.ws': 'WS', '.to': 'TO', '.tv': 'TV',
        '.ki': 'KI', '.nr': 'NR', '.pw': 'PW', '.fm': 'FM', '.mh': 'MH', '.mp': 'MP',
        '.gu': 'GU', '.as': 'AS', '.wf': 'WF', '.pn': 'PN', '.nf': 'NF',
        
        # 其他地区
        '.gl': 'GL', '.fo': 'FO', '.gi': 'GI', '.im': 'IM', '.je': 'JE', '.gg': 'GG',
        '.sh': 'SH', '.ac': 'AC', '.ta': 'TA', '.fk': 'FK', '.gs': 'GS',
        '.bv': 'BV', '.tf': 'TF', '.hm': 'HM', '.aq': 'AQ', '.io': 'IO', '.cx': 'CX', '.cc': 'CC',
        '.yt': 'YT', '.pm': 'PM', '.re': 'RE', '.bq': 'BQ', '.um': 'UM'
    }
    
    # 检查顶级域名
    for tld, country_code in country_tlds.items():
        if server.lower().endswith(tld):
            return country_code
    
    return None

def detect_country(original_name, server):
    """综合检测国家"""
    # 1. 首先从节点名称中检测
    country = detect_country_from_name(original_name)
    if country:
        return country, True  # 返回国家代码和是否从名称检测到
    
    # 2. 从IP地址中检测
    country = detect_country_from_ip(server)
    if country:
        return country, False  # 返回国家代码和未从名称检测到
    
    # 3. 从域名中检测
    country = detect_country_from_domain(server)
    if country:
        return country, False  # 返回国家代码和未从名称检测到
    
    # 4. 默认返回US
    return 'US', False

def generate_new_name(country_code, protocol, index, original_name=None, from_name=False):
    """生成新的节点名称"""
    # 国家代码到中文名称的映射 - 全球完整版
    country_names = {
        # 东亚
        'CN': '中国', 'HK': '香港', 'TW': '台湾', 'MO': '澳门', 'JP': '日本', 'KR': '韩国', 'KP': '朝鲜', 'MN': '蒙古',
        
        # 东南亚
        'VN': '越南', 'LA': '老挝', 'KH': '柬埔寨', 'TH': '泰国', 'MM': '缅甸', 'MY': '马来西亚', 'SG': '新加坡',
        'ID': '印度尼西亚', 'PH': '菲律宾', 'BN': '文莱', 'TL': '东帝汶', 'PG': '巴布亚新几内亚',
        
        # 南亚
        'IN': '印度', 'PK': '巴基斯坦', 'BD': '孟加拉国', 'LK': '斯里兰卡', 'NP': '尼泊尔', 'BT': '不丹', 'MV': '马尔代夫', 'AF': '阿富汗',
        
        # 中亚
        'KZ': '哈萨克斯坦', 'UZ': '乌兹别克斯坦', 'TM': '土库曼斯坦', 'TJ': '塔吉克斯坦', 'KG': '吉尔吉斯斯坦',
        
        # 西亚/中东
        'IR': '伊朗', 'IQ': '伊拉克', 'KW': '科威特', 'SA': '沙特阿拉伯', 'AE': '阿联酋', 'OM': '阿曼', 'YE': '也门',
        'QA': '卡塔尔', 'BH': '巴林', 'JO': '约旦', 'LB': '黎巴嫩', 'SY': '叙利亚', 'IL': '以色列', 'PS': '巴勒斯坦', 'TR': '土耳其',
        'CY': '塞浦路斯', 'GE': '格鲁吉亚', 'AM': '亚美尼亚', 'AZ': '阿塞拜疆',
        
        # 北非
        'EG': '埃及', 'LY': '利比亚', 'TN': '突尼斯', 'DZ': '阿尔及利亚', 'MA': '摩洛哥', 'MR': '毛里塔尼亚',
        
        # 西非
        'SN': '塞内加尔', 'GM': '冈比亚', 'GW': '几内亚比绍', 'GN': '几内亚', 'SL': '塞拉利昂', 'LR': '利比里亚',
        'CI': '科特迪瓦', 'GH': '加纳', 'TG': '多哥', 'BJ': '贝宁', 'NE': '尼日尔', 'NG': '尼日利亚', 'CM': '喀麦隆',
        'TD': '乍得', 'CF': '中非共和国', 'GQ': '赤道几内亚', 'GA': '加蓬', 'CG': '刚果共和国', 'CD': '刚果民主共和国',
        
        # 东非
        'SD': '苏丹', 'SS': '南苏丹', 'ET': '埃塞俄比亚', 'ER': '厄立特里亚', 'DJ': '吉布提', 'SO': '索马里', 'KE': '肯尼亚',
        'UG': '乌干达', 'TZ': '坦桑尼亚', 'RW': '卢旺达', 'BI': '布隆迪', 'AO': '安哥拉', 'ZM': '赞比亚', 'MW': '马拉维',
        'MZ': '莫桑比克', 'ZW': '津巴布韦', 'BW': '博茨瓦纳', 'NA': '纳米比亚', 'ZA': '南非', 'LS': '莱索托', 'SZ': '斯威士兰',
        'MG': '马达加斯加', 'MU': '毛里求斯', 'SC': '塞舌尔', 'KM': '科摩罗', 'CV': '佛得角', 'ST': '圣多美和普林西比',
        
        # 欧洲
        'RU': '俄罗斯', 'UA': '乌克兰', 'BY': '白俄罗斯', 'MD': '摩尔多瓦', 'EE': '爱沙尼亚', 'LV': '拉脱维亚', 'LT': '立陶宛',
        'PL': '波兰', 'CZ': '捷克', 'SK': '斯洛伐克', 'HU': '匈牙利', 'RO': '罗马尼亚', 'BG': '保加利亚', 'GR': '希腊',
        'AL': '阿尔巴尼亚', 'MK': '北马其顿', 'RS': '塞尔维亚', 'ME': '黑山', 'BA': '波斯尼亚和黑塞哥维那', 'HR': '克罗地亚',
        'SI': '斯洛文尼亚', 'AT': '奥地利', 'CH': '瑞士', 'LI': '列支敦士登', 'DE': '德国', 'FR': '法国', 'BE': '比利时',
        'NL': '荷兰', 'LU': '卢森堡', 'GB': '英国', 'IE': '爱尔兰', 'IS': '冰岛', 'NO': '挪威', 'SE': '瑞典', 'FI': '芬兰',
        'DK': '丹麦', 'PT': '葡萄牙', 'ES': '西班牙', 'IT': '意大利', 'MT': '马耳他', 'SM': '圣马力诺', 'VA': '梵蒂冈',
        'MC': '摩纳哥', 'AD': '安道尔',
        
        # 北美
        'US': '美国', 'CA': '加拿大', 'MX': '墨西哥',
        
        # 中美洲
        'GT': '危地马拉', 'BZ': '伯利兹', 'SV': '萨尔瓦多', 'HN': '洪都拉斯', 'NI': '尼加拉瓜', 'CR': '哥斯达黎加', 'PA': '巴拿马',
        
        # 加勒比海
        'CU': '古巴', 'JM': '牙买加', 'HT': '海地', 'DO': '多米尼加', 'BS': '巴哈马', 'BB': '巴巴多斯', 'TT': '特立尼达和多巴哥',
        'GD': '格林纳达', 'VC': '圣文森特和格林纳丁斯', 'LC': '圣卢西亚', 'DM': '多米尼克', 'AG': '安提瓜和巴布达', 'KN': '圣基茨和尼维斯',
        'PR': '波多黎各', 'VI': '美属维尔京群岛', 'VG': '英属维尔京群岛', 'KY': '开曼群岛', 'BM': '百慕大', 'TC': '特克斯和凯科斯群岛',
        'AI': '安圭拉', 'MS': '蒙特塞拉特', 'AW': '阿鲁巴', 'CW': '库拉索', 'SX': '圣马丁', 'MF': '法属圣马丁', 'GP': '瓜德罗普',
        'MQ': '马提尼克', 'BL': '圣巴泰勒米', 'GF': '法属圭亚那',
        
        # 南美
        'BR': '巴西', 'AR': '阿根廷', 'CL': '智利', 'PE': '秘鲁', 'CO': '哥伦比亚', 'VE': '委内瑞拉', 'EC': '厄瓜多尔',
        'BO': '玻利维亚', 'PY': '巴拉圭', 'UY': '乌拉圭', 'GY': '圭亚那', 'SR': '苏里南',
        
        # 大洋洲
        'AU': '澳大利亚', 'NZ': '新西兰', 'FJ': '斐济', 'SB': '所罗门群岛', 'VU': '瓦努阿图', 'NC': '新喀里多尼亚',
        'PF': '法属波利尼西亚', 'CK': '库克群岛', 'NU': '纽埃', 'TK': '托克劳', 'WS': '萨摩亚', 'TO': '汤加', 'TV': '图瓦卢',
        'KI': '基里巴斯', 'NR': '瑙鲁', 'PW': '帕劳', 'FM': '密克罗尼西亚', 'MH': '马绍尔群岛', 'MP': '北马里亚纳群岛',
        'GU': '关岛', 'AS': '美属萨摩亚', 'WF': '法属瓦利斯和富图纳', 'PN': '皮特凯恩群岛', 'NF': '诺福克岛',
        
        # 其他地区
        'GL': '格陵兰', 'FO': '法罗群岛', 'GI': '直布罗陀', 'IM': '马恩岛', 'JE': '泽西岛', 'GG': '根西岛',
        'SH': '圣赫勒拿', 'AC': '阿森松岛', 'TA': '特里斯坦达库尼亚', 'FK': '福克兰群岛', 'GS': '南乔治亚和南桑威奇群岛',
        'BV': '布韦岛', 'TF': '法属南部领地', 'HM': '赫德岛和麦克唐纳群岛', 'AQ': '南极洲', 'IO': '英属印度洋领地', 'CX': '圣诞岛', 'CC': '科科斯群岛',
        'YT': '马约特', 'PM': '圣皮埃尔和密克隆', 'RE': '留尼汪', 'BQ': '博内尔', 'UM': '美国本土外小岛屿'
    }
    
    protocol_names = {
        'vmess': 'VMess',
        'ss': 'SS',
        'ssr': 'SSR',
        'trojan': 'Trojan',
        'vless': 'VLESS',
        'hysteria2': 'Hysteria2',
        'hy2': 'Hysteria2',
        'tuic': 'TUIC'
    }
    
    # 如果是从原名称检测到国家，尝试保留原国家名称
    if from_name and original_name:
        # 检查原名称是否已经是标准格式（如 JM-SS-024）
        import re
        standard_pattern = r'^([A-Z]{2})-[A-Za-z]+-\d+$'
        match = re.match(standard_pattern, original_name.strip())
        if match:
            # 如果原名称是标准格式，直接使用检测到的国家代码
            country_name = country_names.get(country_code, country_code)
            protocol_name = protocol_names.get(protocol, protocol.upper())
            return f"{country_name}-{protocol_name}-{index:03d}"
        else:
            # 尝试从原名称中提取中文国家名称
            for country_code, country_name in country_names.items():
                if country_name in original_name:
                    # 保留原国家名称，添加协议和序号
                    protocol_name = protocol_names.get(protocol, protocol.upper())
                    return f"{country_name}-{protocol_name}-{index:03d}"
    
    # 否则使用标准格式
    country_name = country_names.get(country_code, country_code)
    protocol_name = protocol_names.get(protocol, protocol.upper())
    return f"{country_name}-{protocol_name}-{index:03d}"

def decode_vmess(vmess_url):
    """解析VMess链接"""
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
        
        return {
            'original_name': name,
            'server': server,
            'protocol': 'vmess'
        }
    except Exception as e:
        print(f"VMess解析异常: {str(e)}")
        return None

def decode_ss(ss_url):
    """解析SS链接"""
    try:
        url_parts = urllib.parse.urlparse(ss_url)
        name = ""
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
            name = unicode_decode(name)
        
        # 解析服务器信息
        m = re.match(r'ss://([A-Za-z0-9+/=%]+)@([^:]+):(\d+)', ss_url)
        if m:
            server = m.group(2)
        else:
            m = re.match(r'ss://([A-Za-z0-9+/=%]+)#(.+)', ss_url)
            if m:
                b64 = urllib.parse.unquote(m.group(1))
                b64 += '=' * (-len(b64) % 4)
                method_pass_server_port = base64.b64decode(b64).decode('utf-8')
                server = method_pass_server_port.split('@')[-1].split(':')[0]
            else:
                server = ""
        
        return {
            'original_name': name,
            'server': server,
            'protocol': 'ss'
        }
    except Exception as e:
        print(f"SS解析异常: {str(e)}")
        return None

def decode_trojan(trojan_url):
    """解析Trojan链接"""
    try:
        url_parts = urllib.parse.urlparse(trojan_url)
        server = url_parts.hostname
        name = ""
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        
        return {
            'original_name': name,
            'server': server,
            'protocol': 'trojan'
        }
    except Exception as e:
        print(f"Trojan解析异常: {str(e)}")
        return None

def decode_vless(vless_url):
    """解析VLESS链接"""
    try:
        url_parts = urllib.parse.urlparse(vless_url)
        server = url_parts.hostname
        name = ""
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        
        return {
            'original_name': name,
            'server': server,
            'protocol': 'vless'
        }
    except Exception as e:
        print(f"VLESS解析异常: {str(e)}")
        return None

def decode_ssr(ssr_url):
    """解析SSR链接"""
    try:
        b64 = ssr_url[6:]
        b64 += '=' * (-len(b64) % 4)
        raw = base64.b64decode(b64).decode('utf-8')
        parts = raw.split(':')
        
        if len(parts) >= 5:
            server = parts[0]
            
            # 解析remarks参数
            name = ""
            if 'remarks=' in raw:
                remarks_match = re.search(r'remarks=([^&]+)', raw)
                if remarks_match:
                    remarks_b64 = remarks_match.group(1)
                    try:
                        # 处理URL安全的base64编码
                        url_safe_value = remarks_b64.replace('-', '+').replace('_', '/')
                        padding_needed = (4 - len(url_safe_value) % 4) % 4
                        padded_value = url_safe_value + '=' * padding_needed
                        name = base64.b64decode(padded_value).decode('utf-8')
                        name = urllib.parse.unquote(name)
                        name = unicode_decode(name)
                    except:
                        pass
            
            return {
                'original_name': name,
                'server': server,
                'protocol': 'ssr'
            }
    except Exception as e:
        print(f"SSR解析异常: {str(e)}")
        return None

def decode_hysteria2(hy2_url):
    """解析Hysteria2链接"""
    try:
        url_parts = urllib.parse.urlparse(hy2_url)
        server = url_parts.hostname
        name = ""
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        
        return {
            'original_name': name,
            'server': server,
            'protocol': 'hysteria2'
        }
    except Exception as e:
        print(f"Hysteria2解析异常: {str(e)}")
        return None

def decode_tuic(tuic_url):
    """解析TUIC链接"""
    try:
        url_parts = urllib.parse.urlparse(tuic_url)
        server = url_parts.hostname
        name = ""
        if url_parts.fragment:
            name = urllib.parse.unquote(url_parts.fragment)
        
        return {
            'original_name': name,
            'server': server,
            'protocol': 'tuic'
        }
    except Exception as e:
        print(f"TUIC解析异常: {str(e)}")
        return None

def rename_node(link):
    """重命名单个节点"""
    if link.startswith('vmess://'):
        node_info = decode_vmess(link)
    elif link.startswith('ss://'):
        node_info = decode_ss(link)
    elif link.startswith('trojan://'):
        node_info = decode_trojan(link)
    elif link.startswith('vless://'):
        node_info = decode_vless(link)
    elif link.startswith('ssr://'):
        node_info = decode_ssr(link)
    elif link.startswith('hysteria2://') or link.startswith('hy2://'):
        node_info = decode_hysteria2(link)
    elif link.startswith('tuic://'):
        node_info = decode_tuic(link)
    else:
        return link
    
    if not node_info:
        return link
    
    # 检测国家
    country_code, from_name = detect_country(node_info['original_name'], node_info['server'])
    
    return {
        'original_link': link,
        'original_name': node_info['original_name'],
        'server': node_info['server'],
        'protocol': node_info['protocol'],
        'country_code': country_code,
        'from_name': from_name
    }

def rebuild_vmess_link(original_link, new_name):
    """重建VMess链接"""
    try:
        b64 = original_link[8:]
        b64 += '=' * (-len(b64) % 4)
        raw = base64.b64decode(b64).decode('utf-8')
        data = json.loads(raw)
        
        # 更新节点名称
        data['ps'] = new_name
        
        # 重新编码
        new_json = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
        new_b64 = base64.b64encode(new_json.encode('utf-8')).decode('utf-8')
        
        return f'vmess://{new_b64}'
    except:
        return original_link

def rebuild_ss_link(original_link, new_name):
    """重建SS链接"""
    try:
        # 移除原有的fragment
        base_url = original_link.split('#')[0]
        return f'{base_url}#{urllib.parse.quote(new_name)}'
    except:
        return original_link

def rebuild_trojan_link(original_link, new_name):
    """重建Trojan链接"""
    try:
        # 移除原有的fragment
        base_url = original_link.split('#')[0]
        return f'{base_url}#{urllib.parse.quote(new_name)}'
    except:
        return original_link

def rebuild_vless_link(original_link, new_name):
    """重建VLESS链接"""
    try:
        # 移除原有的fragment
        base_url = original_link.split('#')[0]
        return f'{base_url}#{urllib.parse.quote(new_name)}'
    except:
        return original_link

def rebuild_ssr_link(original_link, new_name):
    """重建SSR链接"""
    try:
        b64 = original_link[6:]
        b64 += '=' * (-len(b64) % 4)
        raw = base64.b64decode(b64).decode('utf-8')
        
        # 更新remarks参数
        if 'remarks=' in raw:
            # 编码新名称
            new_name_b64 = base64.b64encode(new_name.encode('utf-8')).decode('utf-8')
            # URL安全编码
            new_name_b64 = new_name_b64.replace('+', '-').replace('/', '_').replace('=', '')
            
            # 替换remarks参数
            raw = re.sub(r'remarks=[^&]+', f'remarks={new_name_b64}', raw)
        else:
            # 如果没有remarks参数，添加一个
            new_name_b64 = base64.b64encode(new_name.encode('utf-8')).decode('utf-8')
            new_name_b64 = new_name_b64.replace('+', '-').replace('/', '_').replace('=', '')
            
            if '?' in raw:
                raw = f'{raw}&remarks={new_name_b64}'
            else:
                raw = f'{raw}?remarks={new_name_b64}'
        
        # 重新编码
        new_b64 = base64.b64encode(raw.encode('utf-8')).decode('utf-8')
        return f'ssr://{new_b64}'
    except:
        return original_link

def rebuild_hysteria2_link(original_link, new_name):
    """重建Hysteria2链接"""
    try:
        # 移除原有的fragment
        base_url = original_link.split('#')[0]
        return f'{base_url}#{urllib.parse.quote(new_name)}'
    except:
        return original_link

def rebuild_tuic_link(original_link, new_name):
    """重建TUIC链接"""
    try:
        # 移除原有的fragment
        base_url = original_link.split('#')[0]
        return f'{base_url}#{urllib.parse.quote(new_name)}'
    except:
        return original_link

def rebuild_link_with_new_name(original_link, new_name, protocol):
    """根据新名称重建链接"""
    try:
        if protocol == 'vmess':
            return rebuild_vmess_link(original_link, new_name)
        elif protocol == 'ss':
            return rebuild_ss_link(original_link, new_name)
        elif protocol == 'trojan':
            return rebuild_trojan_link(original_link, new_name)
        elif protocol == 'vless':
            return rebuild_vless_link(original_link, new_name)
        elif protocol == 'ssr':
            return rebuild_ssr_link(original_link, new_name)
        elif protocol == 'hysteria2':
            return rebuild_hysteria2_link(original_link, new_name)
        elif protocol == 'tuic':
            return rebuild_tuic_link(original_link, new_name)
        else:
            return original_link
    except Exception as e:
        print(f"重建链接失败: {str(e)}")
        return original_link

def main():
    if len(sys.argv) != 3:
        print("用法: python3 node_renamer.py 输入文件 输出文件")
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    
    # 检查输入文件是否存在
    if not os.path.exists(input_file):
        print(f"错误: 输入文件不存在: {input_file}")
        sys.exit(1)
    
    # 读取输入文件
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            links = [line.strip() for line in f if line.strip()]
    except Exception as e:
        print(f"错误: 无法读取输入文件: {e}")
        sys.exit(1)
    
    print(f"开始处理 {len(links)} 个节点...")
    
    # 解析所有节点
    node_infos = []
    for link in links:
        node_info = rename_node(link)
        if isinstance(node_info, dict):
            node_infos.append(node_info)
        else:
            print(f"跳过无效链接: {link[:50]}...")
    
    print(f"成功解析 {len(node_infos)} 个节点")
    
    # 按国家分组
    country_groups = defaultdict(list)
    for node_info in node_infos:
        country_groups[node_info['country_code']].append(node_info)
    
    # 为每个国家的节点分配序号
    renamed_links = []
    for country_code, nodes in sorted(country_groups.items()):
        print(f"处理 {country_code} 节点: {len(nodes)} 个")
        
        # 按协议分组
        protocol_groups = defaultdict(list)
        for node in nodes:
            protocol_groups[node['protocol']].append(node)
        
        # 为每个协议的节点分配序号
        for protocol, protocol_nodes in protocol_groups.items():
            for i, node in enumerate(protocol_nodes, 1):
                new_name = generate_new_name(
                    node['country_code'], 
                    protocol, 
                    i, 
                    node['original_name'], 
                    node['from_name']
                )
                
                # 根据协议类型重新构建链接
                new_link = rebuild_link_with_new_name(node['original_link'], new_name, protocol)
                if new_link:
                    renamed_links.append(new_link)
                    print(f"  {node['original_name']} -> {new_name}")
    
    # 写入输出文件
    with open(output_file, 'w', encoding='utf-8') as f:
        for link in renamed_links:
            f.write(link + '\n')
    
    print(f"重命名完成，共处理 {len(renamed_links)} 个节点")
    print(f"输出文件: {output_file}")

if __name__ == '__main__':
    main() 