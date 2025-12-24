<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 安装步骤
$steps = [
    1 => '环境检查',
    2 => '数据库配置',
    3 => '系统配置',
    4 => '安装完成'
];

$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$current_step = max(1, min(4, $current_step));

// 检查是否已安装
if (file_exists('config/installed.lock')) {
    $installed_message = '系统已经安装完成！如需重新安装，请删除 config/installed.lock 文件。';
    $is_installed = true;
} else {
    $is_installed = false;
}

// 环境检查函数
function checkRequirements() {
    $requirements = [];
    
    // PHP版本
    $php_version = PHP_VERSION;
    $requirements['php_version'] = [
        'name' => 'PHP版本',
        'current' => $php_version,
        'required' => '>= 7.4',
        'status' => version_compare($php_version, '7.4.0', '>=')
    ];
    
    // PDO扩展
    $requirements['pdo'] = [
        'name' => 'PDO扩展',
        'current' => extension_loaded('pdo') ? '已安装' : '未安装',
        'required' => '必须',
        'status' => extension_loaded('pdo')
    ];
    
    // PDO MySQL扩展
    $requirements['pdo_mysql'] = [
        'name' => 'PDO MySQL扩展',
        'current' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
        'required' => '必须',
        'status' => extension_loaded('pdo_mysql')
    ];
    
    // JSON扩展
    $requirements['json'] = [
        'name' => 'JSON扩展',
        'current' => extension_loaded('json') ? '已安装' : '未安装',
        'required' => '必须',
        'status' => extension_loaded('json')
    ];
    
    // GD扩展
    $requirements['gd'] = [
        'name' => 'GD扩展',
        'current' => extension_loaded('gd') ? '已安装' : '未安装',
        'required' => '推荐',
        'status' => extension_loaded('gd')
    ];
    
    // 目录权限检查
    $writable_dirs = ['config/', 'uploads/', 'logs/'];
    foreach ($writable_dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $requirements['writable_' . str_replace('/', '_', trim($dir, '/'))] = [
            'name' => $dir . ' 目录可写',
            'current' => is_writable($dir) ? '可写' : '不可写',
            'required' => '必须',
            'status' => is_writable($dir)
        ];
    }
    
    return $requirements;
}

// 数据库连接测试
function testDatabaseConnection($host, $dbname, $username, $password) {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return ['success' => true, 'message' => '数据库连接成功！'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => '数据库连接失败：' . $e->getMessage()];
    }
}

// 创建数据库配置文件
function createDatabaseConfig($host, $dbname, $username, $password) {
    $config_content = "<?php\n";
    $config_content .= "class Database {\n";
    $config_content .= "    private \$host = '$host';\n";
    $config_content .= "    private \$db_name = '$dbname';\n";
    $config_content .= "    private \$username = '$username';\n";
    $config_content .= "    private \$password = '$password';\n";
    $config_content .= "    private \$charset = 'utf8mb4';\n";
    $config_content .= "    public \$conn;\n\n";
    $config_content .= "    public function getConnection() {\n";
    $config_content .= "        \$this->conn = null;\n";
    $config_content .= "        \n";
    $config_content .= "        try {\n";
    $config_content .= "            \$dsn = \"mysql:host={\$this->host};dbname={\$this->db_name};charset={\$this->charset}\";\n";
    $config_content .= "            \$this->conn = new PDO(\$dsn, \$this->username, \$this->password);\n";
    $config_content .= "            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
    $config_content .= "            \$this->conn->exec(\"set names utf8mb4\");\n";
    $config_content .= "        } catch(PDOException \$exception) {\n";
    $config_content .= "            echo \"Connection error: \" . \$exception->getMessage();\n";
    $config_content .= "        }\n";
    $config_content .= "        \n";
    $config_content .= "        return \$this->conn;\n";
    $config_content .= "    }\n";
    $config_content .= "}\n";
    $config_content .= "?>";
    
    return file_put_contents('config/database.php', $config_content) !== false;
}

// 执行数据库导入
function importDatabase($host, $dbname, $username, $password) {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 读取SQL文件
        $sql_file = 'database.sql';
        if (!file_exists($sql_file)) {
            return ['success' => false, 'message' => 'database.sql 文件不存在'];
        }
        
        $sql = file_get_contents($sql_file);
        
        // 移除注释和分割语句
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $pdo->beginTransaction();
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => '数据库导入成功！'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => '数据库导入失败：' . $e->getMessage()];
    }
}

// 创建系统配置文件
function createSystemConfig($site_name, $site_url, $admin_email) {
    $config_content = "<?php\n";
    $config_content .= "// 系统配置\n";
    $config_content .= "define('SITE_NAME', '$site_name');\n";
    $config_content .= "define('SITE_URL', '$site_url');\n";
    $config_content .= "define('UPLOAD_PATH', 'public/images/');\n";
    $config_content .= "define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB\n\n";
    $config_content .= "// 分页配置\n";
    $config_content .= "define('ITEMS_PER_PAGE', 20);\n\n";
    $config_content .= "// 时区设置\n";
    $config_content .= "date_default_timezone_set('Asia/Shanghai');\n\n";
    $config_content .= "// 开启会话\n";
    $config_content .= "session_start();\n\n";
    $config_content .= "// 错误报告 (生产环境建议关闭)\n";
    $config_content .= "error_reporting(0);\n";
    $config_content .= "ini_set('display_errors', 0);\n\n";
    $config_content .= "// 包含必要的文件\n";
    $config_content .= "require_once 'database.php';\n";
    $config_content .= "require_once 'functions.php';\n";
    $config_content .= "?>";
    
    return file_put_contents('config/config.php', $config_content) !== false;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($current_step === 2) {
        // 数据库配置步骤
        $host = $_POST['db_host'] ?? 'localhost';
        $dbname = $_POST['db_name'] ?? 'phporder';
        $username = $_POST['db_username'] ?? 'root';
        $password = $_POST['db_password'] ?? '';
        
        // 测试数据库连接
        $test_result = testDatabaseConnection($host, $dbname, $username, $password);
        $_SESSION['db_config'] = compact('host', 'dbname', 'username', 'password');
        
        if ($test_result['success']) {
            // 导入数据库
            $import_result = importDatabase($host, $dbname, $username, $password);
            
            if ($import_result['success']) {
                // 创建数据库配置文件
                if (createDatabaseConfig($host, $dbname, $username, $password)) {
                    header('Location: install.php?step=3');
                    exit;
                } else {
                    $error_message = '创建数据库配置文件失败';
                }
            } else {
                $error_message = $import_result['message'];
            }
        } else {
            $error_message = $test_result['message'];
        }
    } elseif ($current_step === 3) {
        // 系统配置步骤
        $site_name = $_POST['site_name'] ?? '产品价格查询系统';
        $site_url = $_POST['site_url'] ?? 'http://localhost';
        $admin_email = $_POST['admin_email'] ?? 'admin@example.com';
        
        if (createSystemConfig($site_name, $site_url, $admin_email)) {
            // 创建安装锁定文件
            file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
            header('Location: install.php?step=4');
            exit;
        } else {
            $error_message = '创建系统配置文件失败';
        }
    }
}

// 获取当前URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$current_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>产品价格查询系统 - 安装向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .steps {
            display: flex;
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step.active .step-number,
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        
        .step.active .step-line,
        .step.completed .step-line {
            background: #28a745;
        }
        
        .step.completed .step-number {
            background: #28a745;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            position: relative;
            z-index: 2;
            font-weight: bold;
            font-size: 14px;
        }
        
        .step-title {
            font-size: 12px;
            color: #6c757d;
        }
        
        .step.active .step-title,
        .step.completed .step-title {
            color: #28a745;
            font-weight: 500;
        }
        
        .content {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .requirements-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-ok {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-error {
            color: #dc3545;
            font-weight: 500;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .success-icon {
            font-size: 48px;
            color: #28a745;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .btn-container {
            text-align: center;
            margin-top: 30px;
        }
        
        .btn-container .btn {
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="header">
            <h1>产品价格查询系统</h1>
            <p>安装向导 - 按照步骤完成系统安装配置</p>
        </div>
        
        <div class="steps">
            <?php foreach ($steps as $step_num => $step_title): ?>
                <div class="step <?php echo $step_num < $current_step ? 'completed' : ($step_num == $current_step ? 'active' : ''); ?>">
                    <div class="step-number"><?php echo $step_num < $current_step ? '✓' : $step_num; ?></div>
                    <div class="step-title"><?php echo $step_title; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="content">
            <?php if ($is_installed): ?>
                <div class="alert alert-error">
                    <?php echo $installed_message; ?>
                </div>
                <div class="btn-container">
                    <a href="admin/" class="btn">进入管理后台</a>
                    <a href="public/" class="btn btn-secondary">访问前台</a>
                </div>
            <?php else: ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($current_step == 1): ?>
                    <h2>环境检查</h2>
                    <p>系统正在检查服务器环境是否满足运行要求：</p>
                    
                    <table class="requirements-table">
                        <thead>
                            <tr>
                                <th>检查项目</th>
                                <th>当前状态</th>
                                <th>要求</th>
                                <th>结果</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (checkRequirements() as $req): ?>
                                <tr>
                                    <td><?php echo $req['name']; ?></td>
                                    <td><?php echo $req['current']; ?></td>
                                    <td><?php echo $req['required']; ?></td>
                                    <td class="<?php echo $req['status'] ? 'status-ok' : 'status-error'; ?>">
                                        <?php echo $req['status'] ? '✓ 通过' : '✗ 失败'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php
                    $all_passed = true;
                    foreach (checkRequirements() as $req) {
                        if (!$req['status']) {
                            $all_passed = false;
                            break;
                        }
                    }
                    ?>
                    
                    <div class="btn-container">
                        <?php if ($all_passed): ?>
                            <a href="install.php?step=2" class="btn">下一步</a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>请先解决环境问题</button>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif ($current_step == 2): ?>
                    <h2>数据库配置</h2>
                    <p>请填写数据库连接信息：</p>
                    
                    <form method="post" action="install.php?step=2">
                        <div class="form-group">
                            <label for="db_host">数据库主机</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">数据库名称</label>
                            <input type="text" id="db_name" name="db_name" value="phporder" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_username">数据库用户名</label>
                            <input type="text" id="db_username" name="db_username" value="root" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_password">数据库密码</label>
                            <input type="password" id="db_password" name="db_password">
                        </div>
                        
                        <div class="btn-container">
                            <a href="install.php?step=1" class="btn btn-secondary">上一步</a>
                            <button type="submit" class="btn">下一步</button>
                        </div>
                    </form>
                    
                <?php elseif ($current_step == 3): ?>
                    <h2>系统配置</h2>
                    <p>请配置系统基本信息：</p>
                    
                    <form method="post" action="install.php?step=3">
                        <div class="form-group">
                            <label for="site_name">网站名称</label>
                            <input type="text" id="site_name" name="site_name" value="产品价格查询系统" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_url">网站URL</label>
                            <input type="url" id="site_url" name="site_url" value="<?php echo $current_url; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_email">管理员邮箱</label>
                            <input type="email" id="admin_email" name="admin_email" value="admin@example.com" required>
                        </div>
                        
                        <div class="btn-container">
                            <a href="install.php?step=2" class="btn btn-secondary">上一步</a>
                            <button type="submit" class="btn">完成安装</button>
                        </div>
                    </form>
                    
                <?php elseif ($current_step == 4): ?>
                    <div class="success-icon">✓</div>
                    <h2 style="text-align: center; margin-bottom: 20px;">安装完成！</h2>
                    
                    <div class="alert alert-success">
                        恭喜！产品价格查询系统已成功安装完成。<br><br>
                        默认管理员账户：<br>
                        用户名：<strong>admin</strong><br>
                        密码：<strong>password</strong><br><br>
                        请及时登录后台修改默认密码以确保系统安全。
                    </div>
                    
                    <div class="btn-container">
                        <a href="admin/" class="btn">进入管理后台</a>
                        <a href="public/" class="btn btn-secondary">访问前台</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>