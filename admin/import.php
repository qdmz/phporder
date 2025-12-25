<?php
require_once '../config/config.php';
checkAdminAuth();

$error = '';
$success = '';
$importResult = [];
$importType = 'orders';

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $db = dbConnect();
    $importType = $_POST['import_type'] ?? 'orders';
    
    try {
        $db->beginTransaction();
        
        $file = $_FILES['csv_file'];
        
        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('文件上传失败，错误代码：' . $file['error']);
        }
        
        // 检查文件类型
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($fileExt) !== 'csv') {
            throw new Exception('只支持 CSV 格式的文件');
        }
        
        // 打开CSV文件
        if (($handle = fopen($file['tmp_name'], 'r')) === false) {
            throw new Exception('无法打开上传的文件');
        }
        
        // 初始化统计
        $totalRows = 0;
        $successCount = 0;
        $skipCount = 0;
        $errors = [];
        
        // 是否包含标题行
        $hasHeader = isset($_POST['has_header']);
              // 根据导入类型处理数据
        switch ($importType) {
            case 'orders':
                // 导入订单数据
                $successCount = importOrders($db, $handle, $hasHeader, $totalRows, $skipCount, $errors, $_POST['update_stock'] ?? 'no');
                break;
                
            case 'products':
                // 导入产品数据
                $successCount = importProducts($db, $handle, $hasHeader, $totalRows, $skipCount, $errors);
                break;
                
            case 'customers':
                // 导入客户数据
                $successCount = importCustomers($db, $handle, $hasHeader, $totalRows, $skipCount, $errors);
                break;
                
            default:
                throw new Exception('不支持的导入类型');
        }
        
        fclose($handle);
        
        // 提交事务
        $db->commit();
        
        // 构建结果信息
        $importResult = [
            'type' => $importType,
            'total' => $totalRows - ($hasHeader ? 1 : 0),
            'success' => $successCount,
            'skipped' => $skipCount,
            'errors' => $errors
        ];
        
        if ($successCount > 0) {
            $success = "成功导入 {$successCount} 条{$importType}记录";
        } else {
            $error = "没有成功导入任何{$importType}记录";
        }
        
    } catch (Exception $e) {
        // 回滚事务
        if (isset($db)) {
            $db->rollBack();
        }
        $error = '导入失败：' . $e->getMessage();
    }
}
/**
 * 导入订单数据
 */
function importOrders($db, $handle, $hasHeader, &$totalRows, &$skipCount, &$errors, $updateStock) {
    $successCount = 0;
    
    while (($data = fgetcsv($handle)) !== false) {
        $totalRows++;
        
        // 跳过标题行
        if ($hasHeader && $totalRows === 1) {
            continue;
        }
        
        // 验证数据行
        if (count($data) < 8) {
            $errors[] = "第{$totalRows}行：数据列不足（需要至少8列）";
            $skipCount++;
            continue;
        }
        
        // 解析数据
        $order_no = trim($data[0] ?? '');
        $customer_name = trim($data[1] ?? '');
        $customer_phone = trim($data[2] ?? '');
        $customer_email = trim($data[3] ?? '');
        $product_id = intval($data[4] ?? 0);
        $quantity = intval($data[5] ?? 0);
        $price = floatval($data[6] ?? 0);
        $notes = trim($data[7] ?? '');
        
        // 验证必填字段
        if (empty($order_no) || empty($customer_name) || $product_id <= 0 || $quantity <= 0 || $price <= 0) {
            $errors[] = "第{$totalRows}行：必填字段缺失或无效";
            $skipCount++;
            continue;
        }
        
        // 检查订单号是否唯一
        $checkSql = "SELECT id FROM orders WHERE order_no = ?";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$order_no]);
        
        if ($checkStmt->rowCount() > 0) {
            $errors[] = "第{$totalRows}行：订单号 {$order_no} 已存在";
            $skipCount++;
            continue;
        }
        
        // 检查产品是否存在
        $productSql = "SELECT id, name, stock_quantity FROM products WHERE id = ?";
        $productStmt = $db->prepare($productSql);
        $productStmt->execute([$product_id]);
        
        if ($productStmt->rowCount() === 0) {
            $errors[] = "第{$totalRows}行：产品ID {$product_id} 不存在";
            $skipCount++;
            continue;
        }
        
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        // 检查库存（如果启用了库存更新）
        if ($updateStock === 'yes') {
            if ($product['stock_quantity'] < $quantity) {
                $errors[] = "第{$totalRows}行：产品 {$product['name']} 库存不足";
                $skipCount++;
                continue;
            }
        }
        
        // 计算金额
        $subtotal = $price * $quantity;
              try {
            // 插入订单主表
            $orderSql = "INSERT INTO orders (order_no, customer_name, customer_phone, customer_email, total_amount, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $orderStmt = $db->prepare($orderSql);
            $orderStmt->execute([
                $order_no,
                $customer_name,
                $customer_phone,
                $customer_email,
                $subtotal,
                $notes
            ]);
            
            $order_id = $db->lastInsertId();
            
            // 插入订单明细
            $itemSql = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                       VALUES (?, ?, ?, ?, ?)";
            $itemStmt = $db->prepare($itemSql);
            $itemStmt->execute([
                $order_id,
                $product_id,
                $quantity,
                $price,
                $subtotal
            ]);
            
            // 更新库存（如果启用了）
            if ($updateStock === 'yes') {
                $updateSql = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute([$quantity, $product_id]);
            }
            
            $successCount++;
            
        } catch (Exception $e) {
            $errors[] = "第{$totalRows}行：数据库插入失败";
            $skipCount++;
        }
    }
    
    return $successCount;
}
/**
 * 导入产品数据
 */
function importProducts($db, $handle, $hasHeader, &$totalRows, &$skipCount, &$errors) {
    $successCount = 0;
    
    while (($data = fgetcsv($handle)) !== false) {
        $totalRows++;
        
        // 跳过标题行
        if ($hasHeader && $totalRows === 1) {
            continue;
        }
        
        // 验证数据行
        if (count($data) < 9) {
            $errors[] = "第{$totalRows}行：数据列不足（需要至少9列）";
            $skipCount++;
            continue;
        }
        
        // 解析数据
        $product_code = trim($data[0] ?? '');
        $name = trim($data[1] ?? '');
        $specification = trim($data[2] ?? '');
        $model = trim($data[3] ?? '');
        $retail_price = floatval($data[4] ?? 0);
        $wholesale_price = floatval($data[5] ?? 0);
        $stock_quantity = intval($data[6] ?? 0);
        $category_id = intval($data[7] ?? 0);
        $description = trim($data[8] ?? '');
        
        // 验证必填字段
        if (empty($product_code) || empty($name)) {
            $errors[] = "第{$totalRows}行：产品编码和名称不能为空";
            $skipCount++;
            continue;
        }
        
        // 检查产品编码是否唯一
        $checkSql = "SELECT id FROM products WHERE product_code = ?";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$product_code]);
        
        if ($checkStmt->rowCount() > 0) {
            // 更新现有产品
            $updateSql = "UPDATE products SET 
                         name = ?, specification = ?, model = ?, 
                         retail_price = ?, wholesale_price = ?, 
                         stock_quantity = stock_quantity + ?, 
                         category_id = ?, description = ? 
                         WHERE product_code = ?";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                $name, $specification, $model,
                $retail_price, $wholesale_price,
                $stock_quantity,
                $category_id, $description,
                $product_code
            ]);
        } else {
            // 插入新产品
            $insertSql = "INSERT INTO products 
                         (product_code, name, specification, model, 
                         retail_price, wholesale_price, stock_quantity, 
                         category_id, description) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([
                $product_code,
                $name,
                $specification,
                $model,
                $retail_price,
                $wholesale_price,
                $stock_quantity,
                $category_id,
                $description
            ]);
        }
        
        $successCount++;
    }
    
    return $successCount;
}
?>
<style>
        .import-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .type-option {
            flex: 1;
            text-align: center;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .type-option:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .type-option.active {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .type-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .type-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .type-desc {
            color: #666;
            font-size: 0.9rem;
        }
        
        .format-guide {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .format-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
        }
        
        .format-tab {
            padding: 0.5rem 1.5rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .format-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .format-content {
            display: none;
        }
        
        .format-content.active {
            display: block;
        }
        
        .upload-area {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 3rem 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .result-details {
            margin-top: 2rem;
            display: none;
        }
        
        .result-details.show {
            display: block;
        }
        
        .error-list {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
        }
        
        .error-item {
            color: #dc3545;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: white;
            border-radius: 4px;
        }
        
        .template-download {
            margin-top: 1rem;
        }
        
        .template-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #28a745;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .template-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .template-icon {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
      <div class="container">
    <div class="header-info">
        <h1 class="welcome">批量数据导入</h1>
        <a href="dashboard.php" class="logout-btn">返回仪表板</a>
    </div>
    
    <div class="dashboard">
        <aside class="sidebar">
            <h3>管理菜单</h3>
            <ul>
                <li><a href="dashboard.php">仪表板</a></li>
                <li><a href="products.php">产品管理</a></li>
                <li><a href="orders.php">订单管理</a></li>
                <li><a href="categories.php">分类管理</a></li>
                <li><a href="import.php" class="active">批量导入</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="stats.php">数据统计</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                
                <?php if (!empty($importResult)): ?>
                <div class="result-summary">
                    <h4>导入结果统计</h4>
                    <div class="result-grid">
                        <div class="result-item result-total">
                            <div class="result-value"><?php echo $importResult['total']; ?></div>
                            <div class="result-label">处理总数</div>
                        </div>
                        <div class="result-item result-success">
                            <div class="result-value"><?php echo $importResult['success']; ?></div>
                            <div class="result-label">成功导入</div>
                        </div>
                        <div class="result-item result-skipped">
                            <div class="result-value"><?php echo $importResult['skipped']; ?></div>
                            <div class="result-label">跳过数量</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($importResult['errors'])): ?>
                    <div class="result-details show">
                        <h5>错误详情（共 <?php echo count($importResult['errors']); ?> 个错误）：</h5>
                        <div class="error-list">
                            <?php foreach (array_slice($importResult['errors'], 0, 10) as $errorMsg): ?>
                                <div class="error-item"><?php echo htmlspecialchars($errorMsg); ?></div>
                            <?php endforeach; ?>
                            <?php if (count($importResult['errors']) > 10): ?>
                                <div class="error-item">... 还有 <?php echo count($importResult['errors']) - 10; ?> 个错误未显示</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
<div class="import-container">
    <!-- 导入类型选择 -->
    <div class="type-selector">
        <div class="type-option <?php echo $importType == 'orders' ? 'active' : ''; ?>" data-type="orders">
            <div class="type-icon">📦</div>
            <div class="type-title">订单数据</div>
            <div class="type-desc">导入订单和订单明细</div>
        </div>
        <div class="type-option <?php echo $importType == 'products' ? 'active' : ''; ?>" data-type="products">
            <div class="type-icon">🛍️</div>
            <div class="type-title">产品数据</div>
            <div class="type-desc">导入产品信息</div>
        </div>
        <div class="type-option <?php echo $importType == 'customers' ? 'active' : ''; ?>" data-type="customers">
            <div class="type-icon">👥</div>
            <div class="type-title">客户数据</div>
            <div class="type-desc">导入客户信息</div>
        </div>
    </div>
    
    <!-- 上传区域 -->
    <div class="upload-area">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="import_type" id="import_type" value="<?php echo $importType; ?>">
            
            <div class="upload-icon" id="uploadIcon">
                <?php 
                $icons = [
                    'orders' => '📦',
                    'products' => '🛍️',
                    'customers' => '👥'
                ];
                echo $icons[$importType] ?? '📁';
                ?>
            </div>
            
            <h3 id="uploadTitle">
                <?php 
                $titles = [
                    'orders' => '上传订单数据 CSV 文件',
                    'products' => '上传产品数据 CSV 文件',
                    'customers' => '上传客户数据 CSV 文件'
                ];
                echo $titles[$importType] ?? '上传 CSV 文件';
                ?>
            </h3>
            
            <p>支持 CSV 格式文件，最大 10MB</p>
            
            <div style="margin: 2rem 0;">
                <input type="file" name="csv_file" id="csv_file" class="file-input" accept=".csv" required style="display: none;">
                <label for="csv_file" class="file-label" style="display: inline-block; padding: 1rem 2rem; background: #667eea; color: white; border-radius: 6px; cursor: pointer; font-size: 1.1rem;">
                    选择文件
                </label>
                <div id="selectedFile" class="selected-file" style="margin-top: 1rem; font-size: 0.9rem; color: #666;"></div>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group" style="display: inline-block; margin-right: 2rem;">
                    <input type="checkbox" name="has_header" id="has_header" value="1" checked>
                    <label for="has_header">CSV文件包含标题行</label>
                </div>
                
                <?php if ($importType == 'orders'): ?>
                <div class="checkbox-group" style="display: inline-block;">
                    <input type="checkbox" name="update_stock" id="update_stock" value="yes">
                    <label for="update_stock">导入时更新产品库存</label>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="template-download">
                <a href="#" class="template-btn" id="downloadTemplate">
                    <span class="template-icon">📥</span>
                    下载模板文件
                </a>
            </div>
            
            <button type="submit" class="btn-upload" style="margin-top: 2rem; padding: 1rem 3rem; font-size: 1.2rem;" id="uploadBtn">开始导入</button>
        </form>
    </div>
                <!-- 格式说明 -->
                <div class="format-guide">
                    <div class="format-tabs">
                        <div class="format-tab <?php echo $importType == 'orders' ? 'active' : ''; ?>" data-tab="orders">订单格式</div>
                        <div class="format-tab <?php echo $importType == 'products' ? 'active' : ''; ?>" data-tab="products">产品格式</div>
                        <div class="format-tab <?php echo $importType == 'customers' ? 'active' : ''; ?>" data-tab="customers">客户格式</div>
                    </div>
                    
                    <div id="ordersFormat" class="format-content <?php echo $importType == 'orders' ? 'active' : ''; ?>">
                        <h4>订单数据 CSV 格式</h4>
                        <p>CSV文件应包含以下列（按顺序）：</p>
                        <table class="table" style="width: 100%; margin: 1rem 0;">
                            <thead>
                                <tr>
                                    <th>列名</th>
                                    <th>说明</th>
                                    <th>示例</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>order_no</td><td>订单号，唯一</td><td>ORD2023001</td></tr>
                                <tr><td>customer_name</td><td>客户姓名，必填</td><td>张三</td></tr>
                                <tr><td>customer_phone</td><td>客户电话</td><td>13800138000</td></tr>
                                <tr><td>customer_email</td><td>客户邮箱</td><td>zhangsan@example.com</td></tr>
                                <tr><td>product_id</td><td>产品ID，必须存在</td><td>1</td></tr>
                                <tr><td>quantity</td><td>数量，大于0</td><td>2</td></tr>
                                <tr><td>price</td><td>单价，大于0</td><td>25.50</td></tr>
                                <tr><td>notes</td><td>备注</td><td>尽快发货</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="productsFormat" class="format-content <?php echo $importType == 'products' ? 'active' : ''; ?>">
                        <h4>产品数据 CSV 格式</h4>
                        <p>CSV文件应包含以下列（按顺序）：</p>
                        <table class="table" style="width: 100%; margin: 1rem 0;">
                            <thead>
                                <tr>
                                    <th>列名</th>
                                    <th>说明</th>
                                    <th>示例</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>product_code</td><td>产品编码，唯一</td><td>P001</td></tr>
                                <tr><td>name</td><td>产品名称，必填</td><td>商品A</td></tr>
                                <tr><td>specification</td><td>规格</td><td>500ml</td></tr>
                                <tr><td>model</td><td>型号</td><td>2023款</td></tr>
                                <tr><td>retail_price</td><td>零售价</td><td>29.90</td></tr>
                                <tr><td>wholesale_price</td><td>批发价</td><td>25.00</td></tr>
                                <tr><td>stock_quantity</td><td>库存数量</td><td>100</td></tr>
                                <tr><td>category_id</td><td>分类ID</td><td>1</td></tr>
                                <tr><td>description</td><td>描述</td><td>产品描述...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="customersFormat" class="format-content <?php echo $importType == 'customers' ? 'active' : ''; ?>">
                        <h4>客户数据 CSV 格式</h4>
                        <p>CSV文件应包含以下列（按顺序）：</p>
                        <table class="table" style="width: 100%; margin: 1rem 0;">
                            <thead>
                                <tr>
                                    <th>列名</th>
                                    <th>说明</th>
                                    <th>示例</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>name</td><td>客户姓名，必填</td><td>李四</td></tr>
                                <tr><td>phone</td><td>联系电话</td><td>13900139000</td></tr>
                                <tr><td>email</td><td>电子邮箱</td><td>lisi@example.com</td></tr>
                                <tr><td>address</td><td>地址</td><td>北京市朝阳区</td></tr>
                                <tr><td>company</td><td>公司名称</td><td>ABC公司</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
    <script>
        // 导入类型选择
        document.querySelectorAll('.type-option').forEach(option => {
            option.addEventListener('click', function() {
                const type = this.dataset.type;
                
                // 更新活动状态
                document.querySelectorAll('.type-option').forEach(opt => {
                    opt.classList.remove('active');
                });
                this.classList.add('active');
                
                // 更新隐藏字段
                document.getElementById('import_type').value = type;
                
                // 更新上传区域图标和标题
                const icons = {
                    'orders': '📦',
                    'products': '🛍️',
                    'customers': '👥'
                };
                const titles = {
                    'orders': '上传订单数据 CSV 文件',
                    'products': '上传产品数据 CSV 文件',
                    'customers': '上传客户数据 CSV 文件'
                };
                
                document.getElementById('uploadIcon').textContent = icons[type];
                document.getElementById('uploadTitle').textContent = titles[type];
                
                // 切换格式说明标签页
                document.querySelectorAll('.format-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelector(`.format-tab[data-tab="${type}"]`).classList.add('active');
                
                document.querySelectorAll('.format-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(type + 'Format').classList.add('active');
            });
        });
        
        // 格式标签页切换
        document.querySelectorAll('.format-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabType = this.dataset.tab;
                
                // 更新标签页状态
                document.querySelectorAll('.format-tab').forEach(t => {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                
                // 切换内容
                document.querySelectorAll('.format-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabType + 'Format').classList.add('active');
            });
        });
        
        // 文件选择
        document.getElementById('csv_file').addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                document.getElementById('selectedFile').textContent = 
                    `已选择：${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            }
        });
        
        // 下载模板文件
        document.getElementById('downloadTemplate').addEventListener('click', function(e) {
            e.preventDefault();
            const type = document.getElementById('import_type').value;
            
            // 创建模板数据
            let csvContent = '';
            let filename = '';
            
            switch(type) {
                case 'orders':
                    csvContent = "order_no,customer_name,customer_phone,customer_email,product_id,quantity,price,notes\nORD2023001,张三,13800138000,zhangsan@example.com,1,2,25.50,尽快发货\nORD2023002,李四,13900139000,lisi@example.com,3,1,120.00,需要发票";
                    filename = '订单导入模板.csv';
                    break;
                case 'products':
                    csvContent = "product_code,name,specification,model,retail_price,wholesale_price,stock_quantity,category_id,description\nP001,商品A,500ml,2023款,29.90,25.00,100,1,这是商品A的描述\nP002,商品B,1kg,标准款,49.90,42.00,50,2,这是商品B的描述";
                    filename = '产品导入模板.csv';
                    break;
                case 'customers':
                    csvContent = "name,phone,email,address,company\n张三,13800138000,zhangsan@example.com,北京市朝阳区,ABC公司\n李四,13900139000,lisi@example.com,上海市浦东新区,XYZ公司";
                    filename = '客户导入模板.csv';
                    break;
            }
            
            // 创建下载链接
            const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
        
        // 表单提交前检查
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('csv_file');
            if (fileInput.files.length === 0) {
                e.preventDefault();
                alert('请选择要上传的文件');
                return false;
            }
            
            const file = fileInput.files[0];
            if (!file.name.toLowerCase().endsWith('.csv')) {
                e.preventDefault();
                alert('只支持 CSV 格式的文件');
                return false;
            }
            
            if (file.size > 10 * 1024 * 1024) {
                e.preventDefault();
                alert('文件大小超过 10MB 限制');
                return false;
            }
            
            // 显示加载中状态
            const uploadBtn = document.getElementById('uploadBtn');
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<span style="margin-right: 0.5rem;">⏳</span>导入中，请稍候...';
        });
    </script>
</body>
</html>
