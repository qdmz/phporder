<?php
require_once '../config/config.php';

// 处理登录逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $db = dbConnect();
        
        try {
            $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && verifyPassword($password, $user['password'])) {
                // 登录成功，设置会话
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $user['role'];
                
                // 更新最后登录时间
                $update_stmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$user['id']]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } catch (PDOException $e) {
            $error = '登录失败，请稍后重试';
        }
    } else {
        $error = '请填写用户名和密码';
    }
}

// 如果已经登录，直接跳转到仪表板
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-card {
            background: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .login-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .login-form .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .login-form .btn {
            width: 100%;
            padding: 0.875rem;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>管理员登录</h1>
                <p><?php echo SITE_NAME; ?></p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">登录</button>
            </form>
            
            <div class="back-link">
                <a href="../public/index.php">← 返回查询页面</a>
            </div>
        </div>
    </div>
</body>
</html>