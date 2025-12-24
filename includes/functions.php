<?php
// 安全的数据库连接函数
function dbConnect() {
    $database = new Database();
    return $database->getConnection();
}

// 清理输入数据
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// 生成随机订单号
function generateOrderNo() {
    return 'ORD' . date('YmdHis') . rand(1000, 9999);
}

// 上传文件处理
function uploadFile($file, $target_dir) {
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif");
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return array('success' => false, 'message' => '只允许上传JPG, JPEG, PNG, GIF格式的文件');
    }
    
    if ($file["size"] > MAX_FILE_SIZE) {
        return array('success' => false, 'message' => '文件大小不能超过5MB');
    }
    
    $new_filename = time() . '_' . rand(1000, 9999) . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return array('success' => true, 'filename' => $new_filename, 'path' => $target_file);
    } else {
        return array('success' => false, 'message' => '文件上传失败');
    }
}

// 发送邮件通知
function sendEmailNotification($to, $subject, $message) {
    $db = dbConnect();
    $stmt = $db->prepare("INSERT INTO notifications (type, recipient, subject, message) VALUES ('email', ?, ?, ?)");
    $stmt->execute([$to, $subject, $message]);
    return $stmt->rowCount() > 0;
}

// 检查管理员登录状态
function checkAdminAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// 分页函数
function getPaginationData($page, $total_items, $items_per_page = ITEMS_PER_PAGE) {
    $total_pages = ceil($total_items / $items_per_page);
    $offset = ($page - 1) * $items_per_page;
    
    return array(
        'page' => $page,
        'total_pages' => $total_pages,
        'items_per_page' => $items_per_page,
        'offset' => $offset,
        'total_items' => $total_items
    );
}

// 获取系统设置
function getSetting($key) {
    $db = dbConnect();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : null;
}

// 更新系统设置
function updateSetting($key, $value) {
    $db = dbConnect();
    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    return $stmt->execute([$value, $key]);
}

// 密码加密
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 验证密码
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// 生成CSRF令牌
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 验证CSRF令牌
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>