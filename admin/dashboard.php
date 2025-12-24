<?php
require_once '../config/config.php';
checkAdminAuth();

// 获取统计数据
$db = dbConnect();

try {
    // 产品总数
    $stmt = $db->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 订单总数
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 今日订单数
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
    $today_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 总销售额
    $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
    $total_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
    // 库存不足的产品数量
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity < 10");
    $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 最近的订单
    $stmt = $db->query("SELECT o.*, COUNT(oi.id) as item_count 
                       FROM orders o 
                       LEFT JOIN order_items oi ON o.id = oi.order_id 
                       GROUP BY o.id 
                       ORDER BY o.created_at DESC 
                       LIMIT 5");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = '获取统计数据失败：' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理仪表板 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }
        
        .sidebar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            height: fit-content;
        }
        
        .sidebar h3 {
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .sidebar ul {
            list-style: none;
        }
        
        .sidebar li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar a {
            display: block;
            padding: 0.75rem 1rem;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .sidebar a:hover,
        .sidebar a.active {
            background: #667eea;
            color: white;
        }
        
        .main-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #ffc107 0%, #ff6b6b 100%);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .stat-card:nth-child(5) {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .recent-orders {
            margin-top: 2rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .welcome {
            font-size: 1.5rem;
            color: #333;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-info">
            <h1 class="welcome">欢迎，<?php echo htmlspecialchars($_SESSION['admin_username']); ?>！</h1>
            <a href="logout.php" class="logout-btn">退出登录</a>
        </div>
        
        <div class="dashboard">
            <aside class="sidebar">
                <h3>管理菜单</h3>
                <ul>
                    <li><a href="dashboard.php" class="active">仪表板</a></li>
                    <li><a href="products.php">产品管理</a></li>
                    <li><a href="orders.php">订单管理</a></li>
                    <li><a href="categories.php">分类管理</a></li>
                    <li><a href="import.php">批量导入</a></li>
                    <li><a href="settings.php">系统设置</a></li>
                    <li><a href="stats.php">数据统计</a></li>
                </ul>
            </aside>
            
            <main class="main-content">
                <h2>系统概览</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($total_products); ?></div>
                        <div class="stat-label">产品总数</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($total_orders); ?></div>
                        <div class="stat-label">订单总数</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($today_orders); ?></div>
                        <div class="stat-label">今日订单</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number">¥<?php echo number_format($total_sales, 2); ?></div>
                        <div class="stat-label">总销售额</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($low_stock); ?></div>
                        <div class="stat-label">库存不足</div>
                    </div>
                </div>
                
                <div class="recent-orders">
                    <h3>最近订单</h3>
                    
                    <?php if (empty($recent_orders)): ?>
                        <p style="text-align: center; color: #666; margin-top: 2rem;">暂无订单记录</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>订单号</th>
                                    <th>客户</th>
                                    <th>金额</th>
                                    <th>状态</th>
                                    <th>创建时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_no']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td>¥<?php echo number_format($order['total_amount'], 2); ?></td>
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
                                    <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">查看</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>