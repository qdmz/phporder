<?php
require_once '../config/config.php';
checkAdminAuth();

$db = dbConnect();

// 设置默认时间范围（最近30天）
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');

// 获取筛选参数
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

// 获取所有统计数据
try {
    // 1. 核心指标
    $coreMetrics = getCoreMetrics($db, $start_date, $end_date);
    
    // 2. 销售数据
    $salesData = getSalesData($db, $start_date, $end_date);
    
    // 3. 产品数据
    $productData = getProductData($db, $start_date, $end_date);
    
    // 4. 订单数据
    $orderData = getOrderData($db, $start_date, $end_date);
    
    // 5. 客户数据
    $customerData = getCustomerData($db, $start_date, $end_date);
    
    // 6. 实时数据
    $realtimeData = getRealtimeData($db);
    
} catch (PDOException $e) {
    $error = '获取统计数据失败：' . $e->getMessage();
}

/**
 * 获取核心指标
 */
function getCoreMetrics($db, $start_date, $end_date) {
    $metrics = [];
    
    // 总销售额
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total_sales 
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ? 
            AND status != 'cancelled'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $metrics['total_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'];
    
    // 订单总数
    $sql = "SELECT COUNT(*) as total_orders 
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $metrics['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];
    
    // 产品总数
    $sql = "SELECT COUNT(*) as total_products FROM products";
    $stmt = $db->query($sql);
    $metrics['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];
    
    // 客户总数（基于邮箱）
    $sql = "SELECT COUNT(DISTINCT customer_email) as total_customers 
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ? 
            AND customer_email != ''";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $metrics['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_customers'];
    
    // 平均订单价值
    $metrics['avg_order_value'] = $metrics['total_orders'] > 0 ? 
        round($metrics['total_sales'] / $metrics['total_orders'], 2) : 0;
    
    // 今日数据
    $today = date('Y-m-d');
    $sql = "SELECT 
                COUNT(*) as today_orders,
                COALESCE(SUM(total_amount), 0) as today_sales
            FROM orders 
            WHERE DATE(created_at) = ?
            AND status != 'cancelled'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$today]);
    $todayData = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['today_orders'] = $todayData['today_orders'];
    $metrics['today_sales'] = $todayData['today_sales'];
    
    return $metrics;
}
/**
 * 获取销售数据
 */
function getSalesData($db, $start_date, $end_date) {
    $data = [];
    
    // 月度销售趋势
    $sql = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as total_sales
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND status != 'cancelled'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['monthly_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 每日销售趋势（最近30天）
    $dailyTrend = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $sql = "SELECT 
                    COALESCE(SUM(total_amount), 0) as sales
                FROM orders 
                WHERE DATE(created_at) = ?
                AND status != 'cancelled'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dailyTrend[$date] = $result['sales'];
    }
    $data['daily_trend'] = $dailyTrend;
    
    return $data;
}
/**
 * 获取产品数据
 */
function getProductData($db, $start_date, $end_date) {
    $data = [];
    
    // 热销产品TOP 10
    $sql = "SELECT 
                p.id,
                p.name,
                p.product_code,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.subtotal) as total_sales,
                COUNT(DISTINCT oi.order_id) as order_count
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY p.id
            ORDER BY total_sales DESC
            LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 库存状况
    $sql = "SELECT 
                CASE 
                    WHEN stock_quantity <= 0 THEN '缺货'
                    WHEN stock_quantity < 10 THEN '库存低'
                    WHEN stock_quantity < 50 THEN '库存正常'
                    ELSE '库存充足'
                END as stock_level,
                COUNT(*) as product_count,
                SUM(stock_quantity) as total_stock
            FROM products 
            GROUP BY stock_level";
    $stmt = $db->query($sql);
    $data['stock_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 分类销售统计
    $sql = "SELECT 
                c.name as category_name,
                COUNT(DISTINCT o.id) as order_count,
                SUM(oi.subtotal) as total_sales
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            JOIN orders o ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY total_sales DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['category_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $data;
}
/**
 * 获取订单数据
 */
function getOrderData($db, $start_date, $end_date) {
    $data = [];
    
    // 订单状态分布
    $sql = "SELECT 
                status,
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as total_amount
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY status
            ORDER BY order_count DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['status_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 订单时间分布
    $sql = "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as order_count
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY HOUR(created_at)
            ORDER BY hour";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['hourly_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 订单金额分布
    $sql = "SELECT 
                CASE 
                    WHEN total_amount < 100 THEN '0-100'
                    WHEN total_amount < 500 THEN '100-500'
                    WHEN total_amount < 1000 THEN '500-1000'
                    WHEN total_amount < 5000 THEN '1000-5000'
                    ELSE '5000以上'
                END as amount_range,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY amount_range
            ORDER BY MIN(total_amount)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['amount_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $data;
}

/**
 * 获取客户数据
 */
function getCustomerData($db, $start_date, $end_date) {
    $data = [];
    
    // 活跃客户TOP 10
    $sql = "SELECT 
                customer_email,
                customer_name,
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as total_spent,
                MAX(created_at) as last_order_date
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND customer_email != ''
            GROUP BY customer_email
            ORDER BY total_spent DESC
            LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 新客户趋势
    $sql = "SELECT 
                DATE(created_at) as date,
                COUNT(DISTINCT customer_email) as new_customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND customer_email != ''
            AND customer_email NOT IN (
                SELECT DISTINCT customer_email 
                FROM orders 
                WHERE DATE(created_at) < DATE(created_at)
            )
            GROUP BY DATE(created_at)
            ORDER BY date";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['new_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 客户地域分布（基于电话区号）
    $sql = "SELECT 
                SUBSTRING(customer_phone, 1, 3) as area_code,
                COUNT(*) as order_count,
                COUNT(DISTINCT customer_phone) as customer_count
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND customer_phone LIKE '1%'
            AND LENGTH(customer_phone) = 11
            GROUP BY SUBSTRING(customer_phone, 1, 3)
            ORDER BY order_count DESC
            LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $data['customer_location'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $data;
}
/**
 * 获取实时数据
 */
function getRealtimeData($db) {
    $data = [];
    
    // 实时订单统计（最近1小时）
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $sql = "SELECT 
                COUNT(*) as orders_last_hour,
                COALESCE(SUM(total_amount), 0) as sales_last_hour
            FROM orders 
            WHERE created_at >= ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$oneHourAgo]);
    $data['realtime_orders'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 待处理订单
    $sql = "SELECT COUNT(*) as pending_orders FROM orders WHERE status = 'pending'";
    $stmt = $db->query($sql);
    $data['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_orders'];
    
    // 低库存产品
    $sql = "SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity < 10";
    $stmt = $db->query($sql);
    $data['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'];
    
    // 今日活跃客户
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(DISTINCT customer_email) as today_customers 
            FROM orders 
            WHERE DATE(created_at) = ?
            AND customer_email != ''";
    $stmt = $db->prepare($sql);
    $stmt->execute([$today]);
    $data['today_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_customers'];
    
    return $data;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据统计大屏 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        .dashboard-container {
            padding: 1rem;
        }
        
        /* 大屏布局 */
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            color: white;
        }
        
        .header-title {
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-title .icon {
            font-size: 2.5rem;
        }
        
        .time-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .time-selector input {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .time-selector button {
            background: white;
            color: #667eea;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .time-selector button:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        /* 核心指标卡片 */
        .core-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            border-left: 6px solid #667eea;
        }
        
        .metric-card:nth-child(2) {
            border-left-color: #28a745;
        }
        
        .metric-card:nth-child(3) {
            border-left-color: #ffc107;
        }
        
        .metric-card:nth-child(4) {
            border-left-color: #17a2b8;
        }
        
        .metric-card:nth-child(5) {
            border-left-color: #dc3545;
        }
        
        .metric-card:nth-child(6) {
            border-left-color: #6f42c1;
        }
        
        .metric-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 3rem;
            opacity: 0.1;
            z-index: 0;
        }
        
        .metric-content {
            position: relative;
            z-index: 1;
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .metric-label {
            font-size: 1rem;
            color: #666;
            font-weight: 500;
        }
        
        .metric-change {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .metric-change.positive {
            color: #28a745;
        }
        
        .metric-change.negative {
            color: #dc3545;
        }
        
        /* 图表区域 */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .chart-icon {
            font-size: 1.5rem;
        }
        
        .chart-canvas {
            height: 300px;
            width: 100%;
        }
        
        /* 表格区域 */
        .tables-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }
        
        .table-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        
        .table-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .table-icon {
            font-size: 1.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 1rem;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e9ecef;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table tr:hover td {
            background: #f8f9fa;
        }
        
        /* 状态标签 */
        .status-badge {
            padding: 0.25rem 1rem;
            border-radius: 20px;
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
        
        /* 排名标签 */
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .rank-1 {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }
        
        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0 0%, #A9A9A9 100%);
        }
        
        .rank-3 {
            background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%);
        }
        
        /* 实时数据 */
        .realtime-widgets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .realtime-widget {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid;
            transition: all 0.3s ease;
        }
        
        .realtime-widget:hover {
            transform: translateY(-4px);
        }
        
        .realtime-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .realtime-value {
            font-size: 2rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .realtime-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }
        
        /* 响应式设计 */
        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .tables-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .time-selector {
                flex-direction: column;
                width: 100%;
            }
            
            .time-selector input,
            .time-selector button {
                width: 100%;
            }
            
            .core-metrics {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .core-metrics {
                grid-template-columns: 1fr;
            }
        }
        
        /* 动画效果 */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .metric-card:hover {
            animation: pulse 0.5s ease;
        }
        
        /* 打印样式 */
        @media print {
            .stats-header,
            .time-selector,
            .realtime-widgets {
                display: none;
            }
        }
        
        /* 深色模式支持 */
        @media (prefers-color-scheme: dark) {
            .metric-card,
            .chart-container,
            .table-container,
            .realtime-widget {
                background: #1e1e1e;
                color: #ffffff;
            }
            
            .metric-value {
                color: #ffffff;
            }
            
            .table th {
                background: #2d2d2d;
                color: #ffffff;
            }
            
            .table tr:hover td {
                background: #2d2d2d;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="dashboard">
        <aside class="sidebar">
            <h3>管理菜单</h3>
            <ul>
                <li><a href="dashboard.php">仪表板</a></li>
                <li><a href="products.php">产品管理</a></li>
                <li><a href="orders.php">订单管理</a></li>
                <li><a href="categories.php">分类管理</a></li>
                <li><a href="import.php">批量导入</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="stats.php" class="active">数据统计</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="dashboard-container">
                <!-- 头部区域 -->
                <div class="stats-header">
                    <div class="header-title">
                        <span class="icon">📊</span>
                        <span>数据统计大屏</span>
                    </div>
                    
                    <form method="GET" class="time-selector">
                        <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" required>
                        <span style="color: white;">至</span>
                        <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" required>
                        <button type="submit">筛选</button>
                        <button type="button" id="exportBtn">导出报告</button>
                    </form>
                </div>
                
                <!-- 实时数据 -->
                <div class="realtime-widgets">
                    <div class="realtime-widget" style="border-top-color: #667eea;">
                        <div class="realtime-icon">⏰</div>
                        <div class="realtime-value"><?php echo $realtimeData['realtime_orders']['orders_last_hour'] ?? 0; ?></div>
                        <div class="realtime-label">最近1小时订单</div>
                    </div>
                    
                    <div class="realtime-widget" style="border-top-color: #28a745;">
                        <div class="realtime-icon">💰</div>
                        <div class="realtime-value">¥<?php echo number_format($realtimeData['realtime_orders']['sales_last_hour'] ?? 0, 2); ?></div>
                        <div class="realtime-label">最近1小时销售额</div>
                    </div>
                    
                    <div class="realtime-widget" style="border-top-color: #ffc107;">
                        <div class="realtime-icon">⏳</div>
                        <div class="realtime-value"><?php echo $realtimeData['pending_orders'] ?? 0; ?></div>
                        <div class="realtime-label">待处理订单</div>
                    </div>
                    
                    <div class="realtime-widget" style="border-top-color: #dc3545;">
                        <div class="realtime-icon">📦</div>
                        <div class="realtime-value"><?php echo $realtimeData['low_stock'] ?? 0; ?></div>
                        <div class="realtime-label">低库存产品</div>
                    </div>
                </div>
<!-- 核心指标 -->
<div class="core-metrics">
    <div class="metric-card">
        <div class="metric-icon">💰</div>
        <div class="metric-content">
            <div class="metric-value">¥<?php echo number_format($coreMetrics['total_sales'] ?? 0, 2); ?></div>
            <div class="metric-label">总销售额</div>
            <div class="metric-change positive">
                <span>↑</span>
                <span>今日：¥<?php echo number_format($coreMetrics['today_sales'] ?? 0, 2); ?></span>
            </div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-icon">📦</div>
        <div class="metric-content">
            <div class="metric-value"><?php echo number_format($coreMetrics['total_orders'] ?? 0); ?></div>
            <div class="metric-label">总订单数</div>
            <div class="metric-change positive">
                <span>↑</span>
                <span>今日：<?php echo number_format($coreMetrics['today_orders'] ?? 0); ?></span>
            </div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-icon">🏷️</div>
        <div class="metric-content">
            <div class="metric-value"><?php echo number_format($coreMetrics['total_products'] ?? 0); ?></div>
            <div class="metric-label">产品总数</div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-icon">👥</div>
        <div class="metric-content">
            <div class="metric-value"><?php echo number_format($coreMetrics['total_customers'] ?? 0); ?></div>
            <div class="metric-label">客户总数</div>
            <div class="metric-change positive">
                <span>↑</span>
                <span>今日：<?php echo $realtimeData['today_customers'] ?? 0; ?></span>
            </div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-icon">📊</div>
        <div class="metric-content">
            <div class="metric-value">¥<?php echo number_format($coreMetrics['avg_order_value'] ?? 0, 2); ?></div>
            <div class="metric-label">平均订单价值</div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-icon">🎯</div>
        <div class="metric-content">
            <div class="metric-value"><?php echo count($productData['top_products'] ?? []); ?></div>
            <div class="metric-label">活跃产品</div>
        </div>
    </div>
</div>
<!-- 图表区域 -->
<div class="charts-section">
    <!-- 销售趋势图 -->
    <div class="chart-container">
        <div class="chart-header">
            <h3 class="chart-title">
                <span class="chart-icon">📈</span>
                销售趋势
            </h3>
            <select id="trendType" style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ddd;">
                <option value="daily">每日趋势</option>
                <option value="monthly">月度趋势</option>
            </select>
        </div>
        <canvas id="salesTrendChart" class="chart-canvas"></canvas>
    </div>
    
    <!-- 产品类别分布 -->
    <div class="chart-container">
        <div class="chart-header">
            <h3 class="chart-title">
                <span class="chart-icon">📊</span>
                产品类别销售分布
            </h3>
        </div>
        <canvas id="categoryChart" class="chart-canvas"></canvas>
    </div>
    
    <!-- 订单状态分布 -->
    <div class="chart-container">
        <div class="chart-header">
            <h3 class="chart-title">
                <span class="chart-icon">📋</span>
                订单状态分布
            </h3>
        </div>
        <canvas id="statusChart" class="chart-canvas"></canvas>
    </div>
    
    <!-- 库存状况 -->
    <div class="chart-container">
        <div class="chart-header">
            <h3 class="chart-title">
                <span class="chart-icon">📦</span>
                库存状况分析
            </h3>
        </div>
        <canvas id="stockChart" class="chart-canvas"></canvas>
    </div>
</div>
                <!-- 数据表格区域 -->
                <div class="tables-section">
                    <!-- 热销产品TOP 10 -->
                    <div class="table-container">
                        <h3 class="table-title">
                            <span class="table-icon">🔥</span>
                            热销产品 TOP 10
                        </h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>产品名称</th>
                                    <th>销售量</th>
                                    <th>销售额</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productData['top_products'] as $index => $product): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-<?php echo $index + 1; ?>">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($product['product_code']); ?></small>
                                    </td>
                                    <td><?php echo number_format($product['total_quantity']); ?></td>
                                    <td style="font-weight: 600; color: #28a745;">
                                        ¥<?php echo number_format($product['total_sales'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- 活跃客户TOP 10 -->
                    <div class="table-container">
                        <h3 class="table-title">
                            <span class="table-icon">👑</span>
                            活跃客户 TOP 10
                        </h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>客户信息</th>
                                    <th>订单数</th>
                                    <th>总消费</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customerData['top_customers'] as $index => $customer): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-<?php echo $index + 1; ?>">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($customer['customer_name'] ?: '匿名客户'); ?></strong>
                                        <br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($customer['customer_email']); ?></small>
                                    </td>
                                    <td><?php echo number_format($customer['order_count']); ?></td>
                                    <td style="font-weight: 600; color: #667eea;">
                                        ¥<?php echo number_format($customer['total_spent'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script>
    // 图表数据准备
    const salesData = <?php echo json_encode($salesData); ?>;
    const productData = <?php echo json_encode($productData); ?>;
    const orderData = <?php echo json_encode($orderData); ?>;
    const customerData = <?php echo json_encode($customerData); ?>;
    
    // 每日销售趋势
    const dailyTrend = <?php echo json_encode($salesData['daily_trend']); ?>;
    const dailyDates = Object.keys(dailyTrend);
    const dailySales = Object.values(dailyTrend);
    
    // 月度销售趋势
    const monthlyTrend = <?php echo json_encode($salesData['monthly_trend']); ?>;
    const monthlyDates = monthlyTrend.map(item => item.month);
    const monthlySales = monthlyTrend.map(item => parseFloat(item.total_sales));
    
    // 产品类别销售
    const categoryData = <?php echo json_encode($productData['category_sales']); ?>;
    const categoryLabels = categoryData.map(item => item.category_name);
    const categorySales = categoryData.map(item => parseFloat(item.total_sales));
    
    // 订单状态分布
    const statusData = <?php echo json_encode($orderData['status_distribution']); ?>;
    const statusLabels = statusData.map(item => {
        const labels = {
            'pending': '待处理',
            'confirmed': '已确认',
            'shipped': '已发货',
            'cancelled': '已取消'
        };
        return labels[item.status] || item.status;
    });
    const statusCounts = statusData.map(item => item.order_count);
    
    // 库存状况
    const stockData = <?php echo json_encode($productData['stock_status']); ?>;
    const stockLabels = stockData.map(item => item.stock_level);
    const stockCounts = stockData.map(item => item.product_count);
    
    // 销售趋势图表
    let salesChart;
    function createSalesChart(type = 'daily') {
        const ctx = document.getElementById('salesTrendChart').getContext('2d');
        
        if (salesChart) {
            salesChart.destroy();
        }
        
        const dates = type === 'daily' ? dailyDates : monthlyDates;
        const sales = type === 'daily' ? dailySales : monthlySales;
        const label = type === 'daily' ? '每日销售额' : '月度销售额';
        
        salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: label,
                    data: sales,
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ¥${context.raw.toLocaleString('zh-CN', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                })}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '¥' + value.toLocaleString('zh-CN', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                });
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });
    }
    
    // 产品类别图表
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categorySales,
                backgroundColor: [
                    '#667eea', '#28a745', '#ffc107', '#17a2b8',
                    '#dc3545', '#6f42c1', '#fd7e14', '#20c997',
                    '#e83e8c', '#6c757d'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ¥${value.toLocaleString('zh-CN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            })} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
        // 订单状态图表
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: [
                        '#ffc107', '#17a2b8', '#28a745', '#dc3545'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // 库存状况图表
        const stockCtx = document.getElementById('stockChart').getContext('2d');
        new Chart(stockCtx, {
            type: 'bar',
            data: {
                labels: stockLabels,
                datasets: [{
                    label: '产品数量',
                    data: stockCounts,
                    backgroundColor: [
                        '#dc3545', '#ffc107', '#28a745', '#667eea'
                    ],
                    borderColor: [
                        '#dc3545', '#ffc107', '#28a745', '#667eea'
                    ],
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // 初始化销售趋势图表
        createSalesChart('daily');
        
        // 趋势类型切换
        document.getElementById('trendType').addEventListener('change', function() {
            createSalesChart(this.value);
        });
        
        // 导出报告
        document.getElementById('exportBtn').addEventListener('click', function() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            // 创建导出数据
            const exportData = {
                period: `${startDate} 至 ${endDate}`,
                coreMetrics: <?php echo json_encode($coreMetrics); ?>,
                productData: <?php echo json_encode($productData); ?>,
                orderData: <?php echo json_encode($orderData); ?>,
                generated_at: new Date().toLocaleString('zh-CN')
            };
            
            // 创建下载链接
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportData, null, 2));
            const downloadAnchor = document.createElement('a');
            downloadAnchor.setAttribute("href", dataStr);
            downloadAnchor.setAttribute("download", `数据统计报告_${startDate}_${endDate}.json`);
            document.body.appendChild(downloadAnchor);
            downloadAnchor.click();
            document.body.removeChild(downloadAnchor);
        });
        
        // 设置日期限制
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const today = new Date().toISOString().split('T')[0];
        
        startDateInput.max = today;
        endDateInput.max = today;
        
        startDateInput.addEventListener('change', function() {
            endDateInput.min = this.value;
        });
        
        endDateInput.addEventListener('change', function() {
            startDateInput.max = this.value;
        });
        
        // 页面刷新定时器（每5分钟刷新一次数据）
        setInterval(() => {
            const refreshBtn = document.createElement('button');
            refreshBtn.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                border: none;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
                z-index: 1000;
                transition: all 0.3s ease;
            `;
            refreshBtn.innerHTML = '🔄';
            refreshBtn.title = '刷新数据';
            refreshBtn.onclick = function() {
                this.style.transform = 'rotate(360deg)';
                setTimeout(() => {
                    location.reload();
                }, 300);
            };
            
            document.body.appendChild(refreshBtn);
        }, 5 * 60 * 1000); // 5分钟
        
        // 图表自适应
        window.addEventListener('resize', function() {
            if (salesChart) {
                salesChart.resize();
            }
        });
    </script>
</body>
</html>
