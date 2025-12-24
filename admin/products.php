<?php
require_once '../config/config.php';
checkAdminAuth();

$db = dbConnect();

// 处理删除操作
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    
    try {
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        if ($stmt->rowCount() > 0) {
            $success = '产品删除成功';
        } else {
            $error = '产品不存在或已被删除';
        }
    } catch (PDOException $e) {
        $error = '删除失败：' . $e->getMessage();
    }
}

// 处理搜索和分页
$search = cleanInput($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(product_code LIKE ? OR barcode LIKE ? OR name LIKE ? OR model LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($category > 0) {
    $where_conditions[] = "category_id = ?";
    $params[] = $category;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // 获取总数
    $count_sql = "SELECT COUNT(*) as total FROM products {$where_clause}";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 分页数据
    $pagination = getPaginationData($page, $total_products, $limit);
    
    // 获取产品列表
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            {$where_clause} 
            ORDER BY p.created_at DESC 
            LIMIT {$pagination['offset']}, {$limit}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取分类列表
    $categories_stmt = $db->query("SELECT * FROM categories ORDER BY name");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = '获取产品列表失败：' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>产品管理 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .search-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .search-form .form-row {
            display: grid;
            grid-template-columns: 1fr 200px auto;
            gap: 1rem;
            align-items: end;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }
        
        .product-image-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
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
        
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container" style="max-width: none;">
            <div class="page-header">
                <h2>产品管理</h2>
                <a href="product_edit.php" class="btn btn-primary">添加产品</a>
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
                               placeholder="产品名称、货号、条码或型号">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">分类</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">所有分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-secondary">搜索</button>
                        <a href="products.php" class="btn btn-secondary">重置</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>图片</th>
                            <th>产品名称</th>
                            <th>货号</th>
                            <th>条码</th>
                            <th>型号</th>
                            <th>规格</th>
                            <th>分类</th>
                            <th>零售价</th>
                            <th>批发价</th>
                            <th>库存</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center; color: #666;">暂无产品数据</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="../<?php echo UPLOAD_PATH . htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             class="product-image-thumb">
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 0.8rem; border-radius: 4px;">无图片</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                                <td><?php echo htmlspecialchars($product['barcode'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($product['model'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($product['specification'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></td>
                                <td>¥<?php echo number_format($product['retail_price'], 2); ?></td>
                                <td>¥<?php echo number_format($product['wholesale_price'], 2); ?></td>
                                <td><?php echo number_format($product['stock_quantity']); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="product_edit.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-secondary btn-sm">编辑</a>
                                        <a href="?delete=<?php echo $product['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('确定要删除这个产品吗？')">删除</a>
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