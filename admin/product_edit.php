<?php
require_once '../config/config.php';
checkAdminAuth();

$db = dbConnect();
$product = null;
$is_edit = false;

// 处理编辑模式
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $is_edit = true;
    
    try {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $error = '产品不存在';
            $is_edit = false;
        }
    } catch (PDOException $e) {
        $error = '获取产品信息失败：' . $e->getMessage();
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_code = cleanInput($_POST['product_code'] ?? '');
    $barcode = cleanInput($_POST['barcode'] ?? '');
    $name = cleanInput($_POST['name'] ?? '');
    $specification = cleanInput($_POST['specification'] ?? '');
    $model = cleanInput($_POST['model'] ?? '');
    $retail_price = (float)($_POST['retail_price'] ?? 0);
    $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = cleanInput($_POST['description'] ?? '');
    
    // 验证必填字段
    if (empty($product_code) || empty($name)) {
        $error = '货号和产品名称为必填项';
    } else {
        try {
            // 检查货号是否重复（编辑时排除自己）
            $check_sql = "SELECT COUNT(*) as count FROM products WHERE product_code = ?";
            $check_params = [$product_code];
            
            if ($is_edit) {
                $check_sql .= " AND id != ?";
                $check_params[] = $product_id;
            }
            
            $check_stmt = $db->prepare($check_sql);
            $check_stmt->execute($check_params);
            $count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $error = '货号已存在';
            } else {
                // 处理图片上传
                $image_url = $product['image_url'] ?? '';
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadFile($_FILES['image'], '../' . UPLOAD_PATH);
                    
                    if ($upload_result['success']) {
                        // 删除旧图片
                        if (!empty($product['image_url']) && file_exists('../' . UPLOAD_PATH . $product['image_url'])) {
                            unlink('../' . UPLOAD_PATH . $product['image_url']);
                        }
                        $image_url = $upload_result['filename'];
                    } else {
                        $error = '图片上传失败：' . $upload_result['message'];
                    }
                }
                
                if (!isset($error)) {
                    if ($is_edit) {
                        // 更新产品
                        $sql = "UPDATE products SET 
                                product_code = ?, barcode = ?, name = ?, specification = ?, 
                                model = ?, retail_price = ?, wholesale_price = ?, stock_quantity = ?, 
                                category_id = ?, description = ?, image_url = ? 
                                WHERE id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$product_code, $barcode, $name, $specification, $model, 
                                       $retail_price, $wholesale_price, $stock_quantity, 
                                       $category_id, $description, $image_url, $product_id]);
                        
                        $success = '产品更新成功';
                    } else {
                        // 新增产品
                        $sql = "INSERT INTO products (product_code, barcode, name, specification, model, 
                                retail_price, wholesale_price, stock_quantity, category_id, description, image_url) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$product_code, $barcode, $name, $specification, $model, 
                                       $retail_price, $wholesale_price, $stock_quantity, 
                                       $category_id, $description, $image_url]);
                        
                        $success = '产品添加成功';
                        
                        // 重置表单
                        $product = null;
                        $is_edit = false;
                    }
                }
            }
        } catch (PDOException $e) {
            $error = '保存失败：' . $e->getMessage();
        }
    }
}

// 获取分类列表
try {
    $categories_stmt = $db->query("SELECT * FROM categories ORDER BY name");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = '获取分类列表失败：' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? '编辑产品' : '添加产品'; ?> - <?php echo SITE_NAME; ?></title>
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
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            display: none;
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .current-image {
            margin-top: 1rem;
        }
        
        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container" style="max-width: 900px;">
            <div class="page-header">
                <h2><?php echo $is_edit ? '编辑产品' : '添加产品'; ?></h2>
                <a href="products.php" class="btn btn-secondary">返回列表</a>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="form-grid">
                <div class="form-group">
                    <label for="product_code">货号 *</label>
                    <input type="text" id="product_code" name="product_code" class="form-control" 
                           value="<?php echo htmlspecialchars($product['product_code'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="barcode">条码</label>
                    <input type="text" id="barcode" name="barcode" class="form-control" 
                           value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>">
                </div>
                
                <div class="form-group full-width">
                    <label for="name">产品名称 *</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="model">型号</label>
                    <input type="text" id="model" name="model" class="form-control" 
                           value="<?php echo htmlspecialchars($product['model'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="specification">规格</label>
                    <input type="text" id="specification" name="specification" class="form-control" 
                           value="<?php echo htmlspecialchars($product['specification'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category_id">产品分类</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">请选择分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($product['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="stock_quantity">库存数量</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" 
                           value="<?php echo htmlspecialchars($product['stock_quantity'] ?? '0'); ?>" min="0">
                </div>
                
                <div class="form-group">
                    <label for="retail_price">零售价</label>
                    <input type="number" id="retail_price" name="retail_price" class="form-control" 
                           value="<?php echo htmlspecialchars($product['retail_price'] ?? '0'); ?>" 
                           min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="wholesale_price">批发价</label>
                    <input type="number" id="wholesale_price" name="wholesale_price" class="form-control" 
                           value="<?php echo htmlspecialchars($product['wholesale_price'] ?? '0'); ?>" 
                           min="0" step="0.01">
                </div>
                
                <div class="form-group full-width">
                    <label for="image">产品图片</label>
                    <input type="file" id="image" name="image" class="form-control" accept="image/*">
                    
                    <?php if ($is_edit && !empty($product['image_url'])): ?>
                        <div class="current-image">
                            <p>当前图片：</p>
                            <img src="../<?php echo UPLOAD_PATH . htmlspecialchars($product['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="image-preview" id="imagePreview">
                        <img id="previewImg" src="" alt="预览图片">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="description">产品描述</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group full-width">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? '更新产品' : '添加产品'; ?>
                    </button>
                    <a href="products.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 图片预览功能
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>