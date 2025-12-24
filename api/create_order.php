<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = dbConnect();
    
    // 获取POST数据
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (!$data) {
        echo json_encode(array(
            'success' => false,
            'message' => '无效的JSON数据'
        ));
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        // 创建订单
        $order_no = generateOrderNo();
        $customer_name = cleanInput($data['customer_name'] ?? '');
        $customer_phone = cleanInput($data['customer_phone'] ?? '');
        $customer_email = cleanInput($data['customer_email'] ?? '');
        $notes = cleanInput($data['notes'] ?? '');
        $items = $data['items'] ?? array();
        
        if (empty($items)) {
            throw new Exception('订单项不能为空');
        }
        
        // 计算总金额
        $total_amount = 0;
        foreach ($items as $item) {
            $subtotal = $item['quantity'] * $item['price'];
            $total_amount += $subtotal;
        }
        
        // 插入订单
        $sql = "INSERT INTO orders (order_no, customer_name, customer_phone, customer_email, total_amount, notes) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$order_no, $customer_name, $customer_phone, $customer_email, $total_amount, $notes]);
        $order_id = $db->lastInsertId();
        
        // 插入订单项
        $sql = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        foreach ($items as $item) {
            $subtotal = $item['quantity'] * $item['price'];
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price'], $subtotal]);
        }
        
        // 发送邮件通知给管理员
        $admin_email = getSetting('admin_email');
        if ($admin_email && getSetting('email_notifications') === 'enabled') {
            $subject = "新订单通知 - {$order_no}";
            $message = "您有一个新订单：\n";
            $message .= "订单号：{$order_no}\n";
            $message .= "客户：{$customer_name}\n";
            $message .= "电话：{$customer_phone}\n";
            $message .= "邮箱：{$customer_email}\n";
            $message .= "总金额：¥{$total_amount}\n\n";
            $message .= "订单详情：\n";
            
            foreach ($items as $item) {
                $message .= "产品ID：{$item['product_id']}，数量：{$item['quantity']}，单价：¥{$item['price']}\n";
            }
            
            sendEmailNotification($admin_email, $subject, $message);
        }
        
        $db->commit();
        
        echo json_encode(array(
            'success' => true,
            'order_id' => $order_id,
            'order_no' => $order_no,
            'message' => '订单创建成功'
        ));
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(array(
            'success' => false,
            'message' => '订单创建失败：' . $e->getMessage()
        ));
    }
} else {
    echo json_encode(array(
        'success' => false,
        'message' => '不支持的请求方法'
    ));
}
?>