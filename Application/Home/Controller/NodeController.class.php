<?php
namespace Home\Controller;
use Think\Controller;
class NodeController extends Controller {
    public function index(){
        if(!check_user_login()){
            $this->error('请先登录','/login');
        }
        
        // 获取用户信息
        $userId = $_SESSION['users']['id'];
        $username = $_SESSION['users']['username'];
        
        // 获取用户订阅信息
        $dingyue = M('ShortDingyue')->where(['qq'=>$username])->find();
        if(!$dingyue){
            $this->error('未找到您的订阅信息');
        }
        
        // 计算剩余天数
        $endtime = $dingyue['endtime'];
        $nowtime = time();
        if($endtime == 0){
            $data['endtime'] = 0;
        }else{
            $data['endtime'] = floor(($endtime - $nowtime) / 86400);
        }

        // ====== 从数据库获取分配给该用户的节点 ======
        $dbNodes = array();
        $assignedNodeIds = M('user_node')->where(array('user_id' => $userId))->getField('node_id', true);
        if (!empty($assignedNodeIds)) {
            $assignedList = M('node')->where(array('id' => array('in', $assignedNodeIds), 'is_visible' => 1))->order('sort_order asc, id asc')->select();
            if ($assignedList) {
                $nodeId = 1;
                foreach ($assignedList as $row) {
                    $node = array(
                        'id'     => $nodeId++,
                        'name'   => $row['name'],
                        'type'   => $row['type'],
                        'server' => $row['server'],
                        'port'   => $row['port'],
                        'source' => 'db',
                    );
                    // 根据节点类型生成协议链接
                    switch($row['type']) {
                        case 'vmess':
                            $node['protocol_link'] = $this->generateVmessLink($node);
                            break;
                        case 'vless':
                            $node['protocol_link'] = $this->generateVlessLink($node);
                            break;
                        case 'trojan':
                            $node['protocol_link'] = $this->generateTrojanLink($node);
                            break;
                        case 'ss':
                            $node['protocol_link'] = $this->generateSSLink($node);
                            break;
                        default:
                            $node['protocol_link'] = $this->generateSSRLink($node);
                            break;
                    }
                    $dbNodes[] = $node;
                }
            }
        }

        // 读取clash.yaml文件
        $clashFile = './Upload/true/clash.yaml';
        if(file_exists($clashFile)){
            $clashContent = file_get_contents($clashFile);
            
            // 解析YAML格式的节点信息
            $nodes = array();
            
            // 查找proxies部分
            if(preg_match('/proxies:\s*\n(.*?)(?=\n\w|$)/s', $clashContent, $proxiesMatch)) {
                $proxiesSection = $proxiesMatch[1];
                
                // 分割每个节点配置块（以"- name:"开头）
                $nodeBlocks = preg_split('/(?=^- name:)/m', $proxiesSection);
                
                $nodeId = 1;
                foreach($nodeBlocks as $block) {
                    $block = trim($block);
                    if(empty($block) || !preg_match('/^- name:/', $block)) continue;
                    
                    $node = ['id' => $nodeId++];
                    
                    // 解析基本信息
                    if(preg_match('/^- name:\s*(.+)$/m', $block, $match)) {
                        $node['name'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*type:\s*(.+)$/m', $block, $match)) {
                        $node['type'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*server:\s*(.+)$/m', $block, $match)) {
                        $node['server'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*port:\s*(\d+)$/m', $block, $match)) {
                        $node['port'] = trim($match[1]);
                    }
                    
                    // 解析协议特定配置
                    if(preg_match('/^\s*uuid:\s*(.+)$/m', $block, $match)) {
                        $node['uuid'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*password:\s*(.+)$/m', $block, $match)) {
                        $node['password'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*cipher:\s*(.+)$/m', $block, $match)) {
                        $node['cipher'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*network:\s*(.+)$/m', $block, $match)) {
                        $node['network'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*tls:\s*(true|false)$/m', $block, $match)) {
                        $node['tls'] = trim($match[1]) === 'true';
                    }
                    if(preg_match('/^\s*udp:\s*(true|false)$/m', $block, $match)) {
                        $node['udp'] = trim($match[1]) === 'true';
                    }
                    if(preg_match('/^\s*servername:\s*(.+)$/m', $block, $match)) {
                        $node['servername'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*skip-cert-verify:\s*(true|false)$/m', $block, $match)) {
                        $node['skip_cert_verify'] = trim($match[1]) === 'true';
                    }
                    
                    // 解析WebSocket配置
                    if(preg_match('/ws-opts:\s*\n(.*?)(?=^\s*\w|$)/ms', $block, $wsMatch)) {
                        $wsOpts = $wsMatch[1];
                        if(preg_match('/^\s*path:\s*(.+)$/m', $wsOpts, $pathMatch)) {
                            $node['ws_path'] = trim($pathMatch[1]);
                        }
                        if(preg_match('/headers:\s*\n\s*Host:\s*(.+)$/m', $wsOpts, $hostMatch)) {
                            $node['ws_host'] = trim($hostMatch[1]);
                        }
                    }
                    
                    // SSR特有配置
                    if(preg_match('/^\s*protocol:\s*(.+)$/m', $block, $match)) {
                        $node['protocol'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*obfs:\s*(.+)$/m', $block, $match)) {
                        $node['obfs'] = trim($match[1]);
                    }
                    if(preg_match('/^\s*protocol-param:\s*(.+)$/m', $block, $match)) {
                        $node['protocol_param'] = trim($match[1], '"');
                    }
                    if(preg_match('/^\s*obfs-param:\s*(.+)$/m', $block, $match)) {
                        $node['obfs_param'] = trim($match[1], '"');
                    }
                    
                    // 设置默认值
                    if(!isset($node['uuid'])) $node['uuid'] = 'default-uuid-' . $nodeId;
                    if(!isset($node['password'])) $node['password'] = 'default-password';
                    if(!isset($node['cipher'])) $node['cipher'] = 'auto';
                    if(!isset($node['network'])) $node['network'] = 'tcp';
                    if(!isset($node['tls'])) $node['tls'] = false;
                    if(!isset($node['skip_cert_verify'])) $node['skip_cert_verify'] = true;
                    if(!isset($node['ws_path'])) $node['ws_path'] = '/';
                    
                    // 只添加有效的节点（至少有name和server）
                    if(isset($node['name']) && isset($node['server']) && isset($node['type'])) {
                        $nodes[] = $node;
                    }
                }
            }
            
            // 预定义的协议连接映射（根据v2rayse.com转换结果）
            $protocolLinks = [
                '🇹🇼 TW01' => 'ssr://Y24wMC5jbG9zZWFpLm9uZTo4ODAxOm9yaWdpbjpjaGFjaGEyMC1pZXRmOmh0dHBfc2ltcGxlOnBhc3N3ZC8/b2Jmc3BhcmFtPTQzMzU5LWdDWGtMemNBLmRvd25sb2FkLm1pY3Jvc29mdC5jb20mcHJvdG9wYXJhbT0mcmVtYXJrcz04Si1IdzhKLUhfVFcwMSZncm91cD1hSFIwY0hNNkx5OTJNbkpoZVhObExtTnZiUT09',
                '🇯🇵 JP01' => 'ssr://Y24wMC5jbG9zZWFpLm9uZTo4ODAyOm9yaWdpbjpjaGFjaGEyMC1pZXRmOmh0dHBfc2ltcGxlOnBhc3N3ZC8/b2Jmc3BhcmFtPTQzMzU5LWdDWGtMemNBLmRvd25sb2FkLm1pY3Jvc29mdC5jb20mcHJvdG9wYXJhbT0mcmVtYXJrcz04Si1IdzhKLUhfSlAwMSZncm91cD1hSFIwY0hNNkx5OTJNbkpoZVhObExtTnZiUT09',
                '🇺🇸 US01' => 'ssr://Y24wMC5jbG9zZWFpLm9uZTo4ODAzOm9yaWdpbjpjaGFjaGEyMC1pZXRmOmh0dHBfc2ltcGxlOnBhc3N3ZC8/b2Jmc3BhcmFtPTQzMzU5LWdDWGtMemNBLmRvd25sb2FkLm1pY3Jvc29mdC5jb20mcHJvdG9wYXJhbT0mcmVtYXJrcz04Si1IdzhKLUhfVVMwMSZncm91cD1hSFIwY0hNNkx5OTJNbkpoZVhObExtTnZiUT09',
                '🇭🇰 香港01' => 'ssr://Y24wMS5jbG9zZWFpLm9uZTo4MTAxOm9yaWdpbjpjaGFjaGEyMC1pZXRmOmh0dHBfc2ltcGxlOnBhc3N3ZC8/b2Jmc3BhcmFtPTQzMzU5LWdDWGtMemNBLmRvd25sb2FkLm1pY3Jvc29mdC5jb20mcHJvdG9wYXJhbT0mcmVtYXJrcz04Si1IdzhKLUhf6aaZ5rivMDEmZ3JvdXA9YUhSMGNITTZMeTkyTW5KaGVYTmxMbU52YlE9PQ==',
                '🇭🇰 香港02' => 'ssr://Y24wMS5jbG9zZWFpLm9uZTo4MTAyOm9yaWdpbjpjaGFjaGEyMC1pZXRmOmh0dHBfc2ltcGxlOnBhc3N3ZC8/b2Jmc3BhcmFtPTQzMzU5LWdDWGtMemNBLmRvd25sb2FkLm1pY3Jvc29mdC5jb20mcHJvdG9wYXJhbT0mcmVtYXJrcz04Si1IdzhKLUhf6aaZ5rivMDImZ3JvdXA9YUhSMGNITTZMeTkyTW5KaGVYTmxMbU52YlE9PQ=='
            ];
            
            // 为每个节点添加对应的协议连接
            foreach($nodes as &$node) {
                if(isset($protocolLinks[$node['name']])) {
                    $node['protocol_link'] = $protocolLinks[$node['name']];
                } else {
                    // 根据节点类型生成对应的协议连接
                    switch($node['type']) {
                        case 'ssr':
                            $node['protocol_link'] = $this->generateSSRLink($node);
                            break;
                        case 'vmess':
                            $node['protocol_link'] = $this->generateVmessLink($node);
                            break;
                        case 'vless':
                            $node['protocol_link'] = $this->generateVlessLink($node);
                            break;
                        case 'trojan':
                            $node['protocol_link'] = $this->generateTrojanLink($node);
                            break;
                        case 'ss':
                            $node['protocol_link'] = $this->generateSSLink($node);
                            break;
                        case 'hysteria':
                        case 'hysteria2':
                        case 'hy':
                        case 'hy2':
                            $node['protocol_link'] = $this->generateHysteriaLink($node);
                            break;
                        default:
                            // 默认生成SSR连接
                            $node['protocol_link'] = $this->generateSSRLink($node);
                            break;
                    }
                }
            }
            
            $data['nodes'] = $nodes;
            $data['total_nodes'] = count($nodes);
            $data['qq'] = $username;
        } else {
            $data['nodes'] = [];
            $data['total_nodes'] = 0;
            $data['qq'] = $username;
        }

        // 合并：DB分配节点在前，文件解析节点在后
        if (!empty($dbNodes)) {
            // 重新编号：DB节点先排，然后文件节点接续
            $merged = array();
            $idx = 1;
            foreach ($dbNodes as $dn) {
                $dn['id'] = $idx++;
                $merged[] = $dn;
            }
            foreach ($data['nodes'] as $fn) {
                $fn['id'] = $idx++;
                $fn['source'] = 'file';
                $merged[] = $fn;
            }
            $data['nodes'] = $merged;
            $data['total_nodes'] = count($merged);
        }
        
        $this->assign('data', $data);
        $this->display();
    }
    
    // 生成SSR链接的方法
    private function generateSSRLink($node) {
        $server = $node['server'];
        $port = $node['port'];
        $protocol = isset($node['protocol']) ? $node['protocol'] : 'origin';
        $method = isset($node['cipher']) ? $node['cipher'] : 'chacha20-ietf';
        $obfs = isset($node['obfs']) ? $node['obfs'] : 'plain';
        $password = isset($node['password']) ? $node['password'] : 'passwd';
        
        // Base64编码密码
        $passwordBase64 = base64_encode($password);
        
        // 处理obfs-param和protocol-param
        $obfsParam = '';
        $protoParam = '';
        
        if (isset($node['obfs_param'])) {
            $obfsParam = base64_encode($node['obfs_param']);
        }
        
        if (isset($node['protocol_param'])) {
            $protoParam = base64_encode($node['protocol_param']);
        }
        
        // 生成remarks (节点名称的Base64编码)
        $remarks = base64_encode($node['name']);
        
        // 生成group
        $group = base64_encode('aHR0cHM6Ly92MnJheXNlLmNvbQ==');
        
        // 构建SSR配置字符串
        $config = $server . ':' . $port . ':' . $protocol . ':' . $method . ':' . $obfs . ':' . $passwordBase64 . '/?group=' . $group . '&obfsparam=' . $obfsParam . '&protoparam=' . $protoParam . '&remarks=' . $remarks;
        
        return 'ssr://' . base64_encode($config);
    }
    
    // 生成VMess链接的方法
    private function generateVmessLink($node) {
        $config = [
            'v' => '2',
            'ps' => $node['name'],
            'add' => $node['server'],
            'port' => $node['port'],
            'id' => isset($node['uuid']) ? $node['uuid'] : 'default-uuid',
            'aid' => isset($node['alterId']) ? $node['alterId'] : '0',
            'net' => isset($node['network']) ? $node['network'] : 'tcp',
            'type' => 'none',
            'host' => '',
            'path' => '',
            'tls' => isset($node['tls']) && $node['tls'] ? 'tls' : '',
            'sni' => isset($node['sni']) ? $node['sni'] : ''
        ];
        
        // 处理WebSocket相关设置
        if (isset($node['ws_path'])) {
            $config['path'] = $node['ws_path'];
        }
        if (isset($node['ws_host'])) {
            $config['host'] = $node['ws_host'];
        }
        
        return 'vmess://' . base64_encode(json_encode($config));
    }
    
    // 生成Trojan链接的方法
    private function generateTrojanLink($node) {
        $password = isset($node['password']) ? $node['password'] : 'default-password';
        $server = $node['server'];
        $port = $node['port'];
        $sni = isset($node['sni']) ? $node['sni'] : $server;
        $name = urlencode($node['name']);
        
        return "trojan://{$password}@{$server}:{$port}?sni={$sni}#{$name}";
    }
    
    // 生成Shadowsocks链接的方法
    private function generateSSLink($node) {
        $method = isset($node['cipher']) ? $node['cipher'] : 'aes-256-gcm';
        $password = isset($node['password']) ? $node['password'] : 'default-password';
        $server = $node['server'];
        $port = $node['port'];
        $name = urlencode($node['name']);
        
        $userInfo = base64_encode($method . ':' . $password);
        
        return "ss://{$userInfo}@{$server}:{$port}#{$name}";
    }
    
    // 生成Hysteria链接的方法
    private function generateHysteriaLink($node) {
        $password = isset($node['password']) ? $node['password'] : (isset($node['auth']) ? $node['auth'] : 'default-password');
        $server = $node['server'];
        $port = $node['port'];
        $name = urlencode($node['name']);
        
        // 构建查询参数
        $params = [];
        $params['auth'] = $password;
        
        // 添加其他可能的参数
        if (isset($node['sni'])) {
            $params['peer'] = $node['sni'];
        }
        if (isset($node['servername'])) {
            $params['peer'] = $node['servername'];
        }
        if (isset($node['skip_cert_verify']) && $node['skip_cert_verify']) {
            $params['insecure'] = '1';
        }
        
        $queryString = http_build_query($params);
        
        return "hysteria://{$server}:{$port}?{$queryString}#{$name}";
    }
    
    // 生成VLESS链接的方法
    private function generateVlessLink($node) {
        $uuid = isset($node['uuid']) ? $node['uuid'] : 'default-uuid';
        $server = $node['server'];
        $port = $node['port'];
        $name = urlencode($node['name']);
        
        // 构建查询参数
        $params = [];
        
        // 传输协议
        if (isset($node['network'])) {
            $params['type'] = $node['network'];
        }
        
        // TLS设置
        if (isset($node['tls']) && $node['tls']) {
            $params['security'] = 'tls';
            if (isset($node['servername'])) {
                $params['sni'] = $node['servername'];
            }
        }
        
        // WebSocket设置
        if (isset($node['ws_path'])) {
            $params['path'] = $node['ws_path'];
        }
        if (isset($node['ws_host'])) {
            $params['host'] = $node['ws_host'];
        }
        
        // 构建查询字符串
        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . http_build_query($params);
        }
        
        return "vless://{$uuid}@{$server}:{$port}{$queryString}#{$name}";
    }
}