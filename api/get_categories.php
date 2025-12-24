<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = dbConnect();
    
    try {
        $sql = "SELECT * FROM categories ORDER BY name";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(array(
            'success' => true,
            'categories' => $categories
        ));
        
    } catch (PDOException $e) {
        echo json_encode(array(
            'success' => false,
            'message' => '获取分类失败：' . $e->getMessage()
        ));
    }
} else {
    echo json_encode(array(
        'success' => false,
        'message' => '不支持的请求方法'
    ));
}
?>