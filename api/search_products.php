<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = dbConnect();
    
    // 获取查询参数
    $search_term = isset($_GET['q']) ? cleanInput($_GET['q']) : '';
    $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
    $max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 999999;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : ITEMS_PER_PAGE;
    
    try {
        // 构建查询条件
        $where_conditions = array();
        $params = array();
        
        if (!empty($search_term)) {
            $where_conditions[] = "(p.product_code LIKE ? OR p.barcode LIKE ? OR p.name LIKE ? OR p.model LIKE ?)";
            $search_param = "%{$search_term}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($category > 0) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $category;
        }
        
        $where_conditions[] = "p.retail_price BETWEEN ? AND ?";
        $params[] = $min_price;
        $params[] = $max_price;
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // 获取总数
        $count_sql = "SELECT COUNT(*) as total FROM products p {$where_clause}";
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($params);
        $total_items = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 分页数据
        $pagination = getPaginationData($page, $total_items, $limit);
        
        // 获取产品数据
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                {$where_clause} 
                ORDER BY p.created_at DESC 
                LIMIT {$pagination['offset']}, {$limit}";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 处理图片路径
        foreach ($products as &$product) {
            if (!empty($product['image_url'])) {
                $product['image_url'] = SITE_URL . '/' . UPLOAD_PATH . $product['image_url'];
            }
        }
        
        echo json_encode(array(
            'success' => true,
            'products' => $products,
            'pagination' => $pagination,
            'total' => $total_items
        ));
        
    } catch (PDOException $e) {
        echo json_encode(array(
            'success' => false,
            'message' => '查询失败：' . $e->getMessage()
        ));
    }
} else {
    echo json_encode(array(
        'success' => false,
        'message' => '不支持的请求方法'
    ));
}
?>