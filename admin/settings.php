<?php
require_once '../config/config.php';
checkAdminAuth();

$db = dbConnect();
$success = '';
$error = '';

// 处理设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'site_name' => cleanInput($_POST['site_name'] ?? ''),
        'admin_email' => cleanInput($_POST['admin_email'] ?? ''),
        'email_notifications' => isset($_POST['email_notifications']) ? 'enabled' : 'disabled',
        'sms_notifications' => isset($_POST['sms_notifications']) ? 'enabled' : 'disabled',
        'smtp_host' => cleanInput($_POST['smtp_host'] ?? ''),
        'smtp_port' => cleanInput($_POST['smtp_port'] ?? '587'),
        'smtp_username' => cleanInput($_POST['smtp_username'] ?? ''),
        'smtp_password' => $_POST['smtp_password'] ?? ''
    ];
    
    try {
        foreach ($settings as $key => $value) {
            updateSetting($key, $value);
        }
        $success = '系统设置更新成功';
    } catch (Exception $e) {
        $error = '更新失败：' . $e->getMessage();
    }
}

// 处理密码更新
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = '请填写所有密码字段';
    } elseif ($new_password !== $confirm_password) {
        $error = '新密码和确认密码不匹配';
    } elseif (strlen($new_password) < 6) {
        $error = '新密码长度不能少于6位';
    } else {
        try {
            // 验证当前密码
            $stmt = $db->prepare("SELECT password FROM admin_users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && verifyPassword($current_password, $user['password'])) {
                $hashed_password = hashPassword($new_password);
                $update_stmt = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_password, $_SESSION['admin_id']]);
                
                $success = '密码修改成功';
            } else {
                $error = '当前密码错误';
            }
        } catch (PDOException $e) {
            $error = '密码修改失败：' . $e->getMessage();
        }
    }
}

// 获取当前设置
$current_settings = [];
$setting_keys = ['site_name', 'admin_email', 'email_notifications', 'sms_notifications', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'];
foreach ($setting_keys as $key) {
    $current_settings[$key] = getSetting($key);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?php echo SITE_NAME; ?></title>
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
        
        .settings-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .settings-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .settings-section h2 {
            margin-bottom: 1.5rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 100px;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            border-radius: 2px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container" style="max-width: none;">
            <h2 style="margin-bottom: 2rem;">系统设置</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="settings-container">
                <!-- 基本设置 -->
                <div class="settings-section">
                    <h2>基本设置</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="site_name">网站名称</label>
                            <input type="text" id="site_name" name="site_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>" required>
                            <div class="help-text">显示在网站标题和页眉中的名称</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_email">管理员邮箱</label>
                            <input type="email" id="admin_email" name="admin_email" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>" required>
                            <div class="help-text">用于接收订单通知的邮箱地址</div>
                        </div>
                        
                        <div class="form-group">
                            <label>通知设置</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="email_notifications" name="email_notifications" 
                                       <?php echo ($current_settings['email_notifications'] ?? '') === 'enabled' ? 'checked' : ''; ?>>
                                <label for="email_notifications">启用邮件通知</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="sms_notifications" name="sms_notifications" 
                                       <?php echo ($current_settings['sms_notifications'] ?? '') === 'enabled' ? 'checked' : ''; ?>>
                                <label for="sms_notifications">启用短信通知</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">保存基本设置</button>
                    </form>
                </div>
                
                <!-- 邮件设置 -->
                <div class="settings-section">
                    <h2>邮件服务器设置</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="smtp_host">SMTP服务器</label>
                            <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['smtp_host'] ?? ''); ?>" required>
                            <div class="help-text">例如：smtp.gmail.com</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_port">SMTP端口</label>
                            <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['smtp_port'] ?? '587'); ?>" required>
                            <div class="help-text">常用端口：587 (TLS), 465 (SSL), 25 (不加密)</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_username">SMTP用户名</label>
                            <input type="text" id="smtp_username" name="smtp_username" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['smtp_username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_password">SMTP密码</label>
                            <input type="password" id="smtp_password" name="smtp_password" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['smtp_password'] ?? ''); ?>">
                            <div class="help-text">留空则保持原密码不变</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">保存邮件设置</button>
                    </form>
                </div>
                
                <!-- 密码修改 -->
                <div class="settings-section">
                    <h2>修改密码</h2>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">当前密码</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">新密码</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                            <div class="help-text">密码长度不能少于6位</div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">确认新密码</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">修改密码</button>
                    </form>
                </div>
                
                <!-- 系统信息 -->
                <div class="settings-section">
                    <h2>系统信息</h2>
                    <div class="form-group">
                        <label>PHP版本</label>
                        <div><?php echo PHP_VERSION; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label>服务器时间</label>
                        <div><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库连接</label>
                        <div><?php 
                            try {
                                $db->query("SELECT 1");
                                echo '<span style="color: #28a745;">✓ 正常</span>';
                            } catch (PDOException $e) {
                                echo '<span style="color: #dc3545;">✗ 连接失败</span>';
                            }
                        ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label>上传目录权限</label>
                        <div><?php 
                            $upload_path = '../' . UPLOAD_PATH;
                            if (is_dir($upload_path) && is_writable($upload_path)) {
                                echo '<span style="color: #28a745;">✓ 可写</span>';
                            } else {
                                echo '<span style="color: #dc3545;">✗ 不可写</span>';
                            }
                        ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 密码强度检测
        document.getElementById('new_password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('passwordStrengthBar');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength-bar';
                return;
            }
            
            let strength = 0;
            
            // 长度检查
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            
            // 包含数字
            if (/\d/.test(password)) strength++;
            
            // 包含小写字母
            if (/[a-z]/.test(password)) strength++;
            
            // 包含大写字母
            if (/[A-Z]/.test(password)) strength++;
            
            // 包含特殊字符
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // 更新强度条
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // 确认密码验证
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = e.target.value;
            
            if (confirmPassword !== newPassword) {
                e.target.setCustomValidity('密码不匹配');
            } else {
                e.target.setCustomValidity('');
            }
        });
    </script>
</body>
</html>