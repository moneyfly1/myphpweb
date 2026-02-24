<?php
// 简单的删除功能测试（生产环境请勿开启 display_errors）
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

echo "=== 删除功能测试 ===\n\n";

try {
    // 数据库连接
    $pdo = new PDO('mysql:host=148.135.4.81;port=3306;dbname=tempadmin;charset=utf8', 'tempadmin', 'XGzH6mCPHkiXswzf');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 数据库连接成功\n\n";
    
    // 查找一个测试记录
    $stmt = $pdo->query("SELECT id, qq FROM yg_short_dingyue WHERE status = 1 ORDER BY id DESC LIMIT 1");
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo "✗ 未找到可测试的记录\n";
        exit;
    }
    
    $test_id = $record['id'];
    $test_qq = $record['qq'];
    
    echo "找到测试记录:\n";
    echo "ID: $test_id\n";
    echo "QQ: $test_qq\n\n";
    
    // 检查记录是否存在
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM yg_short_dingyue WHERE id = ?");
    $stmt->execute([$test_id]);
    $before_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "删除前记录数: $before_count\n";
    
    if ($before_count == 0) {
        echo "✗ 记录不存在，无法测试\n";
        exit;
    }
    
    // 执行删除操作
    echo "\n开始删除操作...\n";
    $pdo->beginTransaction();
    
    try {
        // 删除订阅记录
        $stmt = $pdo->prepare("DELETE FROM yg_short_dingyue WHERE id = ?");
        $result = $stmt->execute([$test_id]);
        echo "删除订阅记录: " . ($result ? '成功' : '失败') . "\n";
        
        // 删除用户记录
        $stmt = $pdo->prepare("DELETE FROM yg_user WHERE username = ?");
        $stmt->execute([$test_qq]);
        
        // 删除其他相关记录
        $stmt = $pdo->prepare("DELETE FROM yg_order WHERE user_name = ?");
        $stmt->execute([$test_qq]);
        
        $stmt = $pdo->prepare("DELETE FROM yg_device_log WHERE qq = ?");
        $stmt->execute([$test_qq]);
        
        $stmt = $pdo->prepare("DELETE FROM yg_device_log WHERE dingyue_id = ?");
        $stmt->execute([$test_id]);
        
        $stmt = $pdo->prepare("DELETE FROM yg_email_queue WHERE to_email LIKE ?");
        $stmt->execute(['%' . $test_qq . '%']);
        
        $stmt = $pdo->prepare("DELETE FROM yg_short_dingyue_history WHERE qq = ?");
        $stmt->execute([$test_qq]);
        
        $stmt = $pdo->prepare("DELETE FROM yg_dingyue WHERE qq = ?");
        $stmt->execute([$test_qq]);
        
        // 提交事务
        $pdo->commit();
        echo "✓ 删除操作完成\n\n";
        
        // 验证删除结果
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM yg_short_dingyue WHERE id = ?");
        $stmt->execute([$test_id]);
        $after_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "删除后记录数: $after_count\n";
        
        if ($after_count == 0) {
            echo "✓ 删除测试成功！\n";
        } else {
            echo "✗ 删除测试失败！\n";
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo "✗ 删除操作失败: " . $e->getMessage() . "\n";
    }
    
} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "其他错误: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
?>
