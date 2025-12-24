<?php
require_once '../config/config.php';
checkAdminAuth();

$db = dbConnect();

// 处理状态更新
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = cleanInput($_POST['status']);
    
    if (in_array($status, ['pending', 'confirmed', 'shipped', 'cancelled'])) {
        try {
            $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $order_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = '订单状态更新成功';
                
                // 发送状态变更通知给客户
                $order_stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
                $order_stmt->execute([$order_id]);
                $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($order && !empty($order['customer_email'])) {
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
            } else {
                $error = '订单不存在或状态未更新';
            }
        } catch (PDOException $e) {
            $error = '更新失败：' . $e->getMessage();
        }
    }
}

// 处理搜索和分页
$search = cleanInput($_GET['search'] ?? '');
$status = cleanInput($_GET['status'] ?? '');
$date_from = cleanInput($_GET['date_from'] ?? '');
$date_to = cleanInput($_GET['date_to'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(order_no LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // 获取总数
    $count_sql = "SELECT COUNT(*) as total FROM orders {$where_clause}";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 分页数据
    $pagination = getPaginationData($page, $total_orders, $limit);
    
    // 获取订单列表
    $sql = "SELECT o.*, 
                   COUNT(oi.id) as item_count,
                   (SELECT GROUP_CONCAT(CONCAT(p.name, ' x ', oi.quantity) SEPARATOR ', ') 
                    FROM order_items oi 
                    LEFT JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = o.id) as items_summary
            FROM orders o 
            LEFT JOIN order_items oi ON o.id = oi.order_id 
            {$where_clause} 
            GROUP BY o.id 
            ORDER BY o.created_at DESC 
            LIMIT {$pagination['offset']}, {$limit}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = '获取订单列表失败：' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单管理 - <?php echo SITE_NAME; ?></title>
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
        
        .search-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 150px 150px 150px auto;
            gap: 1rem;
            align-items: end;
        }
        
        .status-select {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .items-summary {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.9rem;
            color: #666;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .order-status-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-status-form select {
            padding: 0.25rem 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .order-status-form button {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container" style="max-width: none;">
            <div class="page-header">
                <h2>订单管理</h2>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="search-form">
                <form method="GET" class="form-row">
                    <div class="form-group">
                        <label for="search">搜索</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="订单号、客户姓名或电话">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">状态</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">所有状态</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>待确认</option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>已确认</option>
                            <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>已发货</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">开始日期</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">结束日期</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-secondary">搜索</button>
                        <a href="orders.php" class="btn btn-secondary">重置</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>客户信息</th>
                            <th>产品摘要</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #666;">暂无订单数据</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_no']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                    <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                                    <?php echo htmlspecialchars($order['customer_email'] ?? ''); ?>
                                </td>
                                <td>
                                    <div class="items-summary" title="<?php echo htmlspecialchars($order['items_summary']); ?>">
                                        <?php echo htmlspecialchars($order['items_summary']); ?>
                                    </div>
                                    <small>(<?php echo $order['item_count']; ?> 项)</small>
                                </td>
                                <td>
                                    <strong style="color: #dc3545;">¥<?php echo number_format($order['total_amount'], 2); ?></strong>
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-secondary btn-sm">查看详情</a>
                                        
                                        <form method="POST" class="order-status-form" onsubmit="return confirm('确定要更新订单状态吗？')">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <select name="status">
                                                <option value="">更新状态</option>
                                                <?php foreach ($status_text as $key => $text): ?>
                                                    <?php if ($key !== $order['status']): ?>
                                                        <option value="<?php echo $key; ?>"><?php echo $text; ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">更新</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['page'] - 1])); ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <?php
                        $query_params = $_GET;
                        $query_params['page'] = $i;
                        ?>
                        <a href="?<?php echo http_build_query($query_params); ?>" 
                           class="<?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['page'] + 1])); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>