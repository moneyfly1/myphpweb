<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class NodeMgrController extends AdminBaseController {

    public function index() {
        $list = D('NodeMgr')->getData();
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['last_test_fmt'] = $v['last_test'] ? date('Y-m-d H:i:s', $v['last_test']) : '未检测';
                if ($v['status'] == 1) {
                    $list[$k]['status_text'] = '<span style="color:#52c41a">&#9679; 在线</span>';
                } elseif ($v['status'] == 2) {
                    $list[$k]['status_text'] = '<span style="color:#fa8c16">&#9679; 异常</span>';
                } else {
                    $list[$k]['status_text'] = '<span style="color:#f5222d">&#9679; 离线</span>';
                }
                $latency = intval($v['latency']);
                if ($latency > 0 && $latency < 100) {
                    $list[$k]['latency_html'] = '<span style="color:#52c41a">' . $latency . 'ms</span>';
                } elseif ($latency >= 100 && $latency < 300) {
                    $list[$k]['latency_html'] = '<span style="color:#faad14">' . $latency . 'ms</span>';
                } elseif ($latency >= 300) {
                    $list[$k]['latency_html'] = '<span style="color:#f5222d">' . $latency . 'ms</span>';
                } else {
                    $list[$k]['latency_html'] = '<span style="color:#999">-</span>';
                }
            }
        }
        $this->assign('list', $list ?: array());
        $this->display();
    }

    public function add() {
        if (IS_POST) {
            $data = I('post.');
            $data['port'] = intval($data['port']);
            $data['sort_order'] = intval($data['sort_order']);
            $data['is_visible'] = isset($data['is_visible']) ? intval($data['is_visible']) : 1;
            $data['status'] = 0;
            $data['created_at'] = time();
            $data['updated_at'] = time();
            $res = D('NodeMgr')->add($data);
            if ($res) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>1,'msg'=>'添加成功','url'=>U('Admin/NodeMgr/index')));
                } else {
                    $this->success('添加成功', U('Admin/NodeMgr/index'));
                }
            } else {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>0,'msg'=>'添加失败'));
                } else {
                    $this->error('添加失败');
                }
            }
        }
        $this->display();
    }

    public function edit() {
        if (IS_POST) {
            $temp = I('post.');
            $data = $temp;
            unset($data['id']);
            $data['port'] = intval($data['port']);
            $data['sort_order'] = intval($data['sort_order']);
            $data['is_visible'] = isset($data['is_visible']) ? intval($data['is_visible']) : 0;
            $data['updated_at'] = time();
            $result = D('NodeMgr')->where(array('id' => $temp['id']))->save($data);
            if ($result !== false) {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功','url'=>U('Admin/NodeMgr/index')));
                } else {
                    $this->success('修改成功', U('Admin/NodeMgr/index'));
                }
            } else {
                if(IS_AJAX) {
                    $this->ajaxReturn(array('status'=>0,'msg'=>'修改失败'));
                } else {
                    $this->error('修改失败');
                }
            }
        } else {
            $id = I('get.id', 0, 'intval');
            $data = D('NodeMgr')->getData(array('id' => $id));
            $this->assign('data', $data);
            $this->display();
        }
    }

    public function del() {
        $id = I('get.id', 0, 'intval');
        $result = D('NodeMgr')->where(array('id' => $id))->delete();
        if ($result) {
            if(IS_AJAX) {
                $this->ajaxReturn(array('status'=>1,'msg'=>'删除成功','url'=>U('Admin/NodeMgr/index')));
            } else {
                $this->success('删除成功', U('Admin/NodeMgr/index'));
            }
        } else {
            if(IS_AJAX) {
                $this->ajaxReturn(array('status'=>0,'msg'=>'删除失败'));
            } else {
                $this->error('删除失败');
            }
        }
    }

    // ==================== 节点采集 ====================

    public function collect() {
        $this->display();
    }

    /**
     * 预览解析结果（AJAX）
     */
    public function importPreview() {
        if (!IS_AJAX) $this->error('非法请求');
        $urls = I('post.urls', '', 'trim');
        if (empty($urls)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '请输入订阅地址'));
        }
        $urlList = array_filter(array_map('trim', explode("\n", $urls)));
        if (empty($urlList)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '未找到有效的订阅地址'));
        }
        $allNodes = array();
        $errors = array();
        foreach ($urlList as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = "无效URL: {$url}";
                continue;
            }
            $content = $this->_fetchUrl($url);
            if ($content === false) {
                $errors[] = "获取失败: {$url}";
                continue;
            }
            $decoded = @base64_decode($content, true);
            if ($decoded !== false && preg_match('/^[a-zA-Z0-9+\/=\s]+$/', $content)) {
                $content = $decoded;
            }
            $lines = array_filter(array_map('trim', explode("\n", $content)));
            foreach ($lines as $line) {
                $node = $this->_parseLine($line);
                if ($node) $allNodes[] = $node;
            }
        }
        // 去重 by server+port
        $unique = array();
        $seen = array();
        foreach ($allNodes as $node) {
            $key = $node['server'] . ':' . $node['port'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $node;
            }
        }
        // 标记已存在
        $model = D('NodeMgr');
        foreach ($unique as &$n) {
            $exists = $model->where(array('server' => $n['server'], 'port' => $n['port']))->find();
            $n['exists'] = $exists ? 1 : 0;
        }
        unset($n);
        $this->ajaxReturn(array('code' => 0, 'msg' => 'ok', 'data' => $unique, 'errors' => $errors));
    }

    /**
     * 执行导入（AJAX）
     */
    public function doCollect() {
        if (!IS_AJAX) $this->error('非法请求');
        $nodesJson = I('post.nodes', '', 'trim');
        if (empty($nodesJson)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '没有要导入的节点'));
        }
        $nodes = json_decode($nodesJson, true);
        if (empty($nodes) || !is_array($nodes)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '节点数据格式错误'));
        }
        $model = D('NodeMgr');
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($nodes as $node) {
            $server = isset($node['server']) ? trim($node['server']) : '';
            $port = isset($node['port']) ? intval($node['port']) : 0;
            if (empty($server) || $port <= 0) { $failed++; continue; }
            $exists = $model->where(array('server' => $server, 'port' => $port))->find();
            if ($exists) { $skipped++; continue; }
            $data = array(
                'name'       => isset($node['name']) ? $node['name'] : ($server . ':' . $port),
                'server'     => $server,
                'port'       => $port,
                'type'       => isset($node['type']) ? $node['type'] : 'vmess',
                'status'     => 0,
                'sort_order' => 0,
                'is_visible' => 1,
                'remark'     => isset($node['remark']) ? $node['remark'] : '',
                'created_at' => time(),
                'updated_at' => time(),
            );
            $res = $model->add($data);
            if ($res) { $imported++; } else { $failed++; }
        }
        $this->ajaxReturn(array(
            'code' => 0,
            'msg'  => "导入完成：成功{$imported}个，跳过{$skipped}个，失败{$failed}个",
            'data' => array('imported' => $imported, 'skipped' => $skipped, 'failed' => $failed),
        ));
    }

    // ==================== 节点分配 ====================

    public function assign() {
        $nodes = D('NodeMgr')->getData();
        $users = M('user')->field('id,username')->order('id asc')->select();
        // 获取已有分配
        $assignments = M('user_node')->select();
        $assignMap = array();
        if ($assignments) {
            foreach ($assignments as $a) {
                $assignMap[$a['node_id']][] = $a['user_id'];
            }
        }
        $this->assign('nodes', $nodes ?: array());
        $this->assign('users', $users ?: array());
        $this->assign('assignMap', $assignMap);
        $this->display();
    }

    /**
     * 批量分配节点给用户（AJAX）
     */
    public function doAssign() {
        if (!IS_AJAX) $this->error('非法请求');
        $nodeIds = I('post.node_ids', '');
        $userIds = I('post.user_ids', '');
        if (empty($nodeIds) || empty($userIds)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '请选择节点和用户'));
        }
        if (!is_array($nodeIds)) $nodeIds = explode(',', $nodeIds);
        if (!is_array($userIds)) $userIds = explode(',', $userIds);
        $nodeIds = array_map('intval', array_filter($nodeIds));
        $userIds = array_map('intval', array_filter($userIds));
        if (empty($nodeIds) || empty($userIds)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '参数无效'));
        }
        $model = M('user_node');
        $added = 0;
        $skipped = 0;
        foreach ($nodeIds as $nid) {
            foreach ($userIds as $uid) {
                $exists = $model->where(array('user_id' => $uid, 'node_id' => $nid))->find();
                if ($exists) { $skipped++; continue; }
                $res = $model->add(array(
                    'user_id'    => $uid,
                    'node_id'    => $nid,
                    'created_at' => time(),
                ));
                if ($res) $added++;
            }
        }
        $this->ajaxReturn(array('code' => 0, 'msg' => "分配完成：新增{$added}条，已存在{$skipped}条"));
    }

    /**
     * 取消分配（AJAX）
     */
    public function unassign() {
        if (!IS_AJAX) $this->error('非法请求');
        $nodeId = I('post.node_id', 0, 'intval');
        $userId = I('post.user_id', 0, 'intval');
        if ($nodeId <= 0 || $userId <= 0) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '参数无效'));
        }
        $result = M('user_node')->where(array('user_id' => $userId, 'node_id' => $nodeId))->delete();
        if ($result) {
            $this->ajaxReturn(array('code' => 0, 'msg' => '取消分配成功'));
        } else {
            $this->ajaxReturn(array('code' => 1, 'msg' => '取消分配失败'));
        }
    }

    // ==================== 健康检测 ====================

    public function healthCheck() {
        if (!IS_AJAX) $this->error('非法请求');
        $id = I('post.id', 0, 'intval');
        $node = D('NodeMgr')->getData(array('id' => $id));
        if (!$node) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '节点不存在'));
        }
        $start = microtime(true);
        $fp = @fsockopen($node['server'], $node['port'], $errno, $errstr, 5);
        $end = microtime(true);
        if ($fp) {
            fclose($fp);
            $latency = round(($end - $start) * 1000);
            $status = ($latency < 300) ? 1 : 2;
        } else {
            $latency = 0;
            $status = 0;
        }
        D('NodeMgr')->updateHealth($id, $latency, $status);
        if ($status == 0) {
            $latency_html = '<span style="color:#999">-</span>';
            $status_text = '<span style="color:#f5222d">&#9679; 离线</span>';
        } elseif ($latency < 100) {
            $latency_html = '<span style="color:#52c41a">' . $latency . 'ms</span>';
            $status_text = '<span style="color:#52c41a">&#9679; 在线</span>';
        } elseif ($latency < 300) {
            $latency_html = '<span style="color:#faad14">' . $latency . 'ms</span>';
            $status_text = '<span style="color:#52c41a">&#9679; 在线</span>';
        } else {
            $latency_html = '<span style="color:#f5222d">' . $latency . 'ms</span>';
            $status_text = '<span style="color:#fa8c16">&#9679; 异常</span>';
        }
        $this->ajaxReturn(array('code' => 0, 'msg' => 'ok', 'data' => array(
            'id' => $id, 'latency' => $latency, 'latency_html' => $latency_html,
            'status' => $status, 'status_text' => $status_text, 'last_test' => date('Y-m-d H:i:s'),
        )));
    }

    public function healthCheckAll() {
        if (!IS_AJAX) $this->error('非法请求');
        $nodes = D('NodeMgr')->getData();
        $results = array();
        if ($nodes) {
            foreach ($nodes as $node) {
                $start = microtime(true);
                $fp = @fsockopen($node['server'], $node['port'], $errno, $errstr, 5);
                $end = microtime(true);
                if ($fp) {
                    fclose($fp);
                    $latency = round(($end - $start) * 1000);
                    $status = ($latency < 300) ? 1 : 2;
                } else {
                    $latency = 0; $status = 0;
                }
                D('NodeMgr')->updateHealth($node['id'], $latency, $status);
                if ($status == 0) {
                    $lh = '<span style="color:#999">-</span>';
                    $st = '<span style="color:#f5222d">&#9679; 离线</span>';
                } elseif ($latency < 100) {
                    $lh = '<span style="color:#52c41a">' . $latency . 'ms</span>';
                    $st = '<span style="color:#52c41a">&#9679; 在线</span>';
                } elseif ($latency < 300) {
                    $lh = '<span style="color:#faad14">' . $latency . 'ms</span>';
                    $st = '<span style="color:#52c41a">&#9679; 在线</span>';
                } else {
                    $lh = '<span style="color:#f5222d">' . $latency . 'ms</span>';
                    $st = '<span style="color:#fa8c16">&#9679; 异常</span>';
                }
                $results[] = array('id' => $node['id'], 'latency' => $latency,
                    'latency_html' => $lh, 'status' => $status, 'status_text' => $st,
                    'last_test' => date('Y-m-d H:i:s'));
            }
        }
        $this->ajaxReturn(array('code' => 0, 'msg' => 'ok', 'data' => $results));
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 抓取订阅URL内容
     */
    private function _fetchUrl($url) {
        $opts = array('http' => array(
            'method'  => 'GET',
            'timeout' => 15,
            'header'  => "User-Agent: ClashForAndroid/2.5.12\r\n",
        ));
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ClashForAndroid/2.5.12');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result !== false ? $result : false;
        }
        $ctx = stream_context_create($opts);
        $result = @file_get_contents($url, false, $ctx);
        return $result !== false ? $result : false;
    }

    /**
     * 解析单行协议链接
     */
    private function _parseLine($line) {
        $line = trim($line);
        if (empty($line)) return false;
        if (strpos($line, 'vmess://') === 0) return $this->_parseVmess($line);
        if (strpos($line, 'vless://') === 0) return $this->_parseVless($line);
        if (strpos($line, 'trojan://') === 0) return $this->_parseTrojan($line);
        if (strpos($line, 'ss://') === 0) return $this->_parseSs($line);
        return false;
    }

    /**
     * 解析 vmess:// 链接
     * vmess://base64json => {add, port, id, net, type, host, path, tls, ps}
     */
    private function _parseVmess($line) {
        $b64 = substr($line, 8); // remove vmess://
        $json = @base64_decode($b64);
        if (!$json) return false;
        $obj = @json_decode($json, true);
        if (!$obj || !isset($obj['add']) || !isset($obj['port'])) return false;
        $name = isset($obj['ps']) ? $obj['ps'] : ($obj['add'] . ':' . $obj['port']);
        $remark = '';
        $parts = array();
        if (!empty($obj['net']))  $parts[] = 'net=' . $obj['net'];
        if (!empty($obj['tls']))  $parts[] = 'tls=' . $obj['tls'];
        if (!empty($obj['host'])) $parts[] = 'host=' . $obj['host'];
        if (!empty($obj['path'])) $parts[] = 'path=' . $obj['path'];
        if ($parts) $remark = implode(', ', $parts);
        return array(
            'name'   => $name,
            'server' => $obj['add'],
            'port'   => intval($obj['port']),
            'type'   => 'vmess',
            'remark' => $remark,
        );
    }

    /**
     * 解析 vless:// 链接
     * vless://uuid@server:port?params#name
     */
    private function _parseVless($line) {
        $rest = substr($line, 8); // remove vless://
        // 分离 fragment (#name)
        $name = '';
        if (($hashPos = strrpos($rest, '#')) !== false) {
            $name = urldecode(substr($rest, $hashPos + 1));
            $rest = substr($rest, 0, $hashPos);
        }
        // 分离 query string
        $remark = '';
        if (($qPos = strpos($rest, '?')) !== false) {
            $qs = substr($rest, $qPos + 1);
            parse_str($qs, $params);
            $rest = substr($rest, 0, $qPos);
            $parts = array();
            if (!empty($params['type']))     $parts[] = 'net=' . $params['type'];
            if (!empty($params['security'])) $parts[] = 'tls=' . $params['security'];
            if (!empty($params['sni']))      $parts[] = 'sni=' . $params['sni'];
            if (!empty($params['path']))     $parts[] = 'path=' . $params['path'];
            if (!empty($params['host']))     $parts[] = 'host=' . $params['host'];
            if ($parts) $remark = implode(', ', $parts);
        }
        // uuid@server:port
        if (($atPos = strpos($rest, '@')) === false) return false;
        $serverPart = substr($rest, $atPos + 1);
        if (($colonPos = strrpos($serverPart, ':')) === false) return false;
        $server = substr($serverPart, 0, $colonPos);
        $port = intval(substr($serverPart, $colonPos + 1));
        if (empty($server) || $port <= 0) return false;
        if (empty($name)) $name = $server . ':' . $port;
        return array(
            'name'   => $name,
            'server' => $server,
            'port'   => $port,
            'type'   => 'vless',
            'remark' => $remark,
        );
    }

    /**
     * 解析 trojan:// 链接
     * trojan://password@server:port?params#name
     */
    private function _parseTrojan($line) {
        $rest = substr($line, 9); // remove trojan://
        $name = '';
        if (($hashPos = strrpos($rest, '#')) !== false) {
            $name = urldecode(substr($rest, $hashPos + 1));
            $rest = substr($rest, 0, $hashPos);
        }
        $remark = '';
        if (($qPos = strpos($rest, '?')) !== false) {
            $qs = substr($rest, $qPos + 1);
            parse_str($qs, $params);
            $rest = substr($rest, 0, $qPos);
            $parts = array();
            if (!empty($params['type']))     $parts[] = 'net=' . $params['type'];
            if (!empty($params['security'])) $parts[] = 'tls=' . $params['security'];
            if (!empty($params['sni']))      $parts[] = 'sni=' . $params['sni'];
            if (!empty($params['path']))     $parts[] = 'path=' . $params['path'];
            if (!empty($params['host']))     $parts[] = 'host=' . $params['host'];
            if ($parts) $remark = implode(', ', $parts);
        }
        if (($atPos = strpos($rest, '@')) === false) return false;
        $serverPart = substr($rest, $atPos + 1);
        if (($colonPos = strrpos($serverPart, ':')) === false) return false;
        $server = substr($serverPart, 0, $colonPos);
        $port = intval(substr($serverPart, $colonPos + 1));
        if (empty($server) || $port <= 0) return false;
        if (empty($name)) $name = $server . ':' . $port;
        return array(
            'name'   => $name,
            'server' => $server,
            'port'   => $port,
            'type'   => 'trojan',
            'remark' => $remark,
        );
    }

    /**
     * 解析 ss:// 链接
     * ss://base64(method:password)@server:port#name
     * 或 ss://base64(method:password@server:port)#name
     */
    private function _parseSs($line) {
        $rest = substr($line, 5); // remove ss://
        $name = '';
        if (($hashPos = strrpos($rest, '#')) !== false) {
            $name = urldecode(substr($rest, $hashPos + 1));
            $rest = substr($rest, 0, $hashPos);
        }
        // 格式1: base64(method:password)@server:port
        if (($atPos = strpos($rest, '@')) !== false) {
            $userInfo = substr($rest, 0, $atPos);
            $serverPart = substr($rest, $atPos + 1);
            $decoded = @base64_decode($userInfo);
            if (!$decoded) $decoded = $userInfo;
            if (($colonPos = strrpos($serverPart, ':')) === false) return false;
            $server = substr($serverPart, 0, $colonPos);
            $port = intval(substr($serverPart, $colonPos + 1));
        } else {
            // 格式2: base64(method:password@server:port)
            $decoded = @base64_decode($rest);
            if (!$decoded) return false;
            if (($atPos = strpos($decoded, '@')) === false) return false;
            $serverPart = substr($decoded, $atPos + 1);
            if (($colonPos = strrpos($serverPart, ':')) === false) return false;
            $server = substr($serverPart, 0, $colonPos);
            $port = intval(substr($serverPart, $colonPos + 1));
        }
        if (empty($server) || $port <= 0) return false;
        if (empty($name)) $name = $server . ':' . $port;
        return array(
            'name'   => $name,
            'server' => $server,
            'port'   => $port,
            'type'   => 'ss',
            'remark' => '',
        );
    }

}
