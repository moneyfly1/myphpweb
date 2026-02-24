<?php
// 配置参数
define('CACHE_TIME', 3600); // 1小时缓存
$countryCounter = [];

// 获取子域名（带缓存）
function get_subdomains($domain) {
    $cacheFile = "subdomains_$domain.cache";
    
    if (file_exists($cacheFile) && time()-filemtime($cacheFile) < CACHE_TIME) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $subdomains = [];
    
    // 方法1: SecurityTrails API
    $url = "https://api.securitytrails.com/v1/domain/$domain/subdomains";
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => ['header' => "APIKEY: YOUR_API_KEY\r\n"]
    ]));
    
    if ($response && $data = json_decode($response, true)) {
        foreach ($data['subdomains'] as $sub) {
            $subdomains[] = "$sub.$domain";
        }
    }
    
    // 方法2: crt.sh 备用
    if (empty($subdomains)) {
        $url = "https://crt.sh/?q=%25.$domain&output=json";
        if ($json = @file_get_contents($url)) {
            $entries = json_decode($json, true);
            foreach ($entries as $entry) {
                $name = strtolower(str_replace(['*.', "'"], '', $entry['name_value']));
                if (preg_match("/\.{$domain}$/", $name)) {
                    $subdomains[] = $name;
                }
            }
        }
    }

    $subdomains = array_unique(array_filter($subdomains));
    file_put_contents($cacheFile, json_encode($subdomains));
    return $subdomains;
}

// 获取国家代码（带缓存和备用方案）
function get_country($host) {
    $cacheFile = "country_$host.cache";
    
    if (file_exists($cacheFile) && time()-filemtime($cacheFile) < CACHE_TIME) {
        return file_get_contents($cacheFile);
    }
    
    $ip = @gethostbyname($host);
    if ($ip === $host) return 'UNKNOWN';
    
    // 备用API列表
    $apis = [
        "https://ipapi.co/$ip/country/",
        "http://ip-api.com/json/$ip",
        "https://api.ipgeolocation.io/ipgeo?apiKey=YOUR_KEY&ip=$ip"
    ];
    
    foreach ($apis as $apiUrl) {
        if ($response = @file_get_contents($apiUrl)) {
            // 解析不同API响应格式
            if (strpos($apiUrl, 'ipapi.co') !== false) {
                $country = trim($response);
            } else {
                $data = json_decode($response, true);
                $country = $data['countryCode'] ?? ($data['country_code'] ?? 'UNKNOWN');
            }
            
            if (strlen($country) == 2) {
                file_put_contents($cacheFile, $country);
                return $country;
            }
        }
        sleep(1); // 避免速率限制
    }
    
    return 'UNKNOWN';
}

// 生成国家编号
function generate_country_code($country) {
    global $countryCounter;
    $count = ($countryCounter[$country] ?? 0) + 1;
    $countryCounter[$country] = $count;
    return $country . str_pad($count, 2, '0', STR_PAD_LEFT);
}

// 处理VMESS链接
function process_vmess($input, $startPort, $numPorts) {
    $json = json_decode(base64_decode(substr($input, 8)), true);
    $mainHost = $json['add'];
    $domain = implode('.', array_slice(explode('.', $mainHost), -2));
    
    $subdomains = get_subdomains($domain);
    if (empty($subdomains)) $subdomains = [$mainHost];
    
    $results = [];
    foreach ($subdomains as $sub) {
        $country = get_country($sub);
        for ($i=0; $i<$numPorts; $i++) {
            $json['port'] = $startPort + $i;
            $json['ps'] = generate_country_code($country);
            $results[] = [
                'type' => 'vmess',
                'name' => $json['ps'],
                'server' => $sub,
                'port' => $json['port'],
                'link' => 'vmess://' . base64_encode(json_encode($json))
            ];
        }
    }
    return $results;
}

// 处理SS链接
function process_ss($input, $startPort, $numPorts) {
    $parts = explode('#', $input);
    $main = substr($parts[0], 5);
    list($auth, $serverPort) = explode('@', $main);
    list($server, $origPort) = explode(':', $serverPort);
    
    $domain = implode('.', array_slice(explode('.', $server), -2));
    $subdomains = get_subdomains($domain);
    if (empty($subdomains)) $subdomains = [$server];
    
    $authDecoded = base64_decode($auth);
    list($method, $password) = explode(':', $authDecoded);
    
    $results = [];
    foreach ($subdomains as $sub) {
        $country = get_country($sub);
        for ($i=0; $i<$numPorts; $i++) {
            $port = $startPort + $i;
            $name = generate_country_code($country);
            $results[] = [
                'type' => 'ss',
                'name' => $name,
                'server' => $sub,
                'port' => $port,
                'link' => "ss://" . base64_encode("$method:$password") . "@$sub:$port#$name"
            ];
        }
    }
    return $results;
}

// 主处理逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['link'];
    $startPort = (int)$_POST['start_port'];
    $numPorts = (int)$_POST['num_ports'];
    
    try {
        if (strpos($input, 'vmess://') === 0) {
            $results = process_vmess($input, $startPort, $numPorts);
        } elseif (strpos($input, 'ss://') === 0) {
            $results = process_ss($input, $startPort, $numPorts);
        } else {
            throw new Exception("不支持的协议类型");
        }
        
        // 生成CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=subscriptions.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['类型', '名称', '服务器', '端口', '订阅链接']);
        foreach ($results as $row) {
            fputcsv($output, $row);
        }
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>国家识别生成器</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px }
        .form-box { border: 1px solid #ddd; padding: 20px; border-radius: 8px }
        .form-group { margin-bottom: 15px }
        label { display: block; margin-bottom: 5px; font-weight: bold }
        input[type="text"], input[type="number"] {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;
            box-sizing: border-box
        }
        button {
            background: #007bff; color: white; border: none;
            padding: 12px 25px; border-radius: 4px; cursor: pointer;
            transition: background 0.3s
        }
        button:hover { background: #0056b3 }
        .error { color: #dc3545; margin-top: 10px }
        .preview { margin-top: 20px; max-height: 400px; overflow: auto }
        table { width: 100%; border-collapse: collapse }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left }
        th { background: #f8f9fa }
    </style>
</head>
<body>
    <div class="form-box">
        <h2>订阅链接生成器</h2>
        <?php if (!empty($error)) echo "<div class='error'>错误：$error</div>"; ?>
        
        <form method="post">
            <div class="form-group">
                <label>原始链接：</label>
                <input type="text" name="link" required 
                    placeholder="输入 vmess:// 或 ss:// 链接"
                    value="<?= htmlspecialchars($_POST['link'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>起始端口：</label>
                <input type="number" name="start_port" 
                    value="<?= $_POST['start_port'] ?? 42121 ?>" required>
            </div>
            
            <div class="form-group">
                <label>生成数量：</label>
                <input type="number" name="num_ports" 
                    value="<?= $_POST['num_ports'] ?? 10 ?>" min="1" required>
            </div>
            
            <button type="submit">生成订阅文件</button>
        </form>
        
        <?php if (!empty($results)): ?>
        <div class="preview">
            <h3>生成预览（前10条）</h3>
            <table>
                <tr><th>类型</th><th>名称</th><th>服务器</th><th>端口</th><th>链接</th></tr>
                <?php foreach (array_slice($results, 0, 10) as $item): ?>
                <tr>
                    <td><?= $item['type'] ?></td>
                    <td><?= $item['name'] ?></td>
                    <td><?= $item['server'] ?></td>
                    <td><?= $item['port'] ?></td>
                    <td style="max-width:300px;overflow:hidden"><?= $item['link'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 