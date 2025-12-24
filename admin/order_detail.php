<?php
require_once '../config/config.php';
checkAdminAuth();

$db = dbConnect();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = (int)$_GET['id'];

try {
    // 获取订单信息
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $error = '订单不存在';
    } else {
        // 获取订单项
        $stmt = $db->prepare("SELECT oi.*, p.name, p.product_code, p.image_url 
                             FROM order_items oi 
                             LEFT JOIN products p ON oi.product_id = p.id 
                             WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 处理状态更新
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $status = cleanInput($_POST['status'] ?? '');
        
        if (in_array($status, ['pending', 'confirmed', 'shipped', 'cancelled'])) {
            $update_stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$status, $order_id]);
            
            if ($update_stmt->rowCount() > 0) {
                $order['status'] = $status;
                $success = '订单状态更新成功';
                
                // 发送邮件通知
                if (!empty($order['customer_email'])) {
                    $subject = "订单状态更新通知 - {$order['order_no']}";
                    $status_text = [
                        'pending' => '待确认',
                        'confirmed' => '已确认',
                        'shipped' => '已发货',
                        'cancelled' => '已取消'
                    ];
                    $message = "您的订单 {$order['order_no']} 状态已更新为：{$status_text[$status]}\n\n";
                    $message .= "订单金额：¥{$order['total_amount']}\n";
                    $message .= "更新时间：" . date('Y-m-d H:i:s') . "\n\n";
                    $message .= "感谢您的购买！";
                    
                    sendEmailNotification($order['customer_email'], $subject, $message);
                }
            }
        }
    }
    
} catch (PDOException $e) {
    $error = '获取订单信息失败：' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单详情 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: white;
            padding: 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 270px;
        }
        
        .order-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .detail-section h3 {
            margin-bottom: 1rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .product-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            background: #f8f9fa;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .product-details {
            color: #666;
            font-size: 0.9rem;
        }
        
        .product-price {
            text-align: right;
            min-width: 120px;
        }
        
        .price-unit {
            color: #666;
            font-size: 0.9rem;
        }
        
        .price-subtotal {
            font-weight: 600;
            color: #dc3545;
            font-size: 1.1rem;
        }
        
        .total-section {
            text-align: right;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #dc3545;
        }
        
        .status-update-form {
            margin-top: 1rem;
            padding: 1rem;
            background: #e7f3ff;
            border-radius: 8px;
        }
        
        .actions {
            margin-bottom: 2rem;
        }
        
        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container" style="max-width: 1200px;">
            <div class="actions">
                <a href="orders.php" class="btn btn-secondary">← 返回订单列表</a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($order)): ?>
                <div class="order-detail">
                    <div class="detail-section">
                        <h3>订单信息</h3>
                        <div class="detail-row">
                            <span class="detail-label">订单号：</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['order_no']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">订单状态：</span>
                            <span class="detail-value">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => '待确认',
                                        'confirmed' => '已确认',
                                        'shipped' => '已发货',
                                        'cancelled' => '已取消'
                                    ];
                                    echo $status_text[$order['status']] ?? $order['status'];
                                    ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">创建时间：</span>
                            <span class="detail-value"><?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">更新时间：</span>
                            <span class="detail-value"><?php echo date('Y-m-d H:i:s', strtotime($order['updated_at'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">订单金额：</span>
                            <span class="detail-value total-amount">¥<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                        
                        <?php if (!empty($order['notes'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">备注：</span>
                            <span class="detail-value"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 状态更新表单 -->
                        <div class="status-update-form">
                            <h4>更新订单状态</h4>
                            <form method="POST" onsubmit="return confirm('确定要更新订单状态吗？')">
                                <input type="hidden" name="update_status" value="1">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <select name="status" class="form-control" style="flex: 1;">
                                        <?php foreach ($status_text as $key => $text): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $order['status'] === $key ? 'selected' : ''; ?>>
                                                <?php echo $text; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">更新状态</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h3>客户信息</h3>
                        <div class="detail-row">
                            <span class="detail-label">姓名：</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">电话：</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                        </div>
                        <?php if (!empty($order['customer_email'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">邮箱：</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>订单明细</h3>
                    
                    <?php if (empty($order_items)): ?>
                        <p style="text-align: center; color: #666;">暂无订单明细</p>
                    <?php else: ?>
                        <?php foreach ($order_items as $item): ?>
                        <div class="product-item">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="../<?php echo UPLOAD_PATH . htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="product-image">
                            <?php else: ?>
                                <div class="product-image no-image">无图片</div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="product-details">
                                    货号：<?php echo htmlspecialchars($item['product_code']); ?>
                                </div>
                            </div>
                            
                            <div class="product-price">
                                <div class="price-unit">¥<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></div>
                                <div class="price-subtotal">¥<?php echo number_format($item['subtotal'], 2); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="total-section">
                            <div>订单总计：<span class="total-amount">¥<?php echo number_format($order['total_amount'], 2); ?></span></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>