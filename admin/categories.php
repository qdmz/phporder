<?php
require_once '../config/config.php';
checkAdminAuth();

$db = dbConnect();

// 处理删除操作
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    try {
        // 检查是否有产品使用此分类
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $check_stmt->execute([$category_id]);
        $count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $error = '该分类下还有产品，不能删除';
        } else {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = '分类删除成功';
            } else {
                $error = '分类不存在或已被删除';
            }
        }
    } catch (PDOException $e) {
        $error = '删除失败：' . $e->getMessage();
    }
}

// 处理表单提交（添加/编辑）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    if (empty($name)) {
        $error = '分类名称不能为空';
    } else {
        try {
            if ($category_id > 0) {
                // 更新分类
                $sql = "UPDATE categories SET name = ?, description = ?, parent_id = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $description, $parent_id, $category_id]);
                $success = '分类更新成功';
            } else {
                // 添加新分类
                $sql = "INSERT INTO categories (name, description, parent_id) VALUES (?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $description, $parent_id]);
                $success = '分类添加成功';
            }
        } catch (PDOException $e) {
            $error = '保存失败：' . $e->getMessage();
        }
    }
}

// 获取所有分类
try {
    $stmt = $db->query("SELECT * FROM categories ORDER BY parent_id, name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = '获取分类列表失败：' . $e->getMessage();
}

// 处理编辑模式
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($categories as $category) {
        if ($category['id'] == $edit_id) {
            $edit_category = $category;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分类管理 - <?php echo SITE_NAME; ?></title>
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
        
        .category-list {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .category-form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .category-item:last-child {
            border-bottom: none;
        }
        
        .category-info h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .category-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .category-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }
        
        .parent-category {
            color: #667eea;
            font-weight: 500;
        }
        
        .sub-category {
            margin-left: 2rem;
            border-left: 2px solid #e9ecef;
            padding-left: 1rem;
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container" style="max-width: none;">
            <div class="page-header">
                <h2>分类管理</h2>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="category-list">
                <h3>分类列表</h3>
                
                <?php if (empty($categories)): ?>
                    <p style="text-align: center; color: #666; margin-top: 2rem;">暂无分类数据</p>
                <?php else: ?>
                    <?php
                    // 构建分类树
                    $parent_categories = [];
                    $child_categories = [];
                    
                    foreach ($categories as $category) {
                        if ($category['parent_id'] == 0) {
                            $parent_categories[] = $category;
                        } else {
                            $child_categories[$category['parent_id']][] = $category;
                        }
                    }
                    
                    function displayCategory($category, $child_categories, $is_child = false) {
                        $class = $is_child ? 'category-item sub-category' : 'category-item';
                        echo '<div class="' . $class . '">';
                        echo '<div class="category-info">';
                        echo '<h4>' . htmlspecialchars($category['name']) . '</h4>';
                        if (!empty($category['description'])) {
                            echo '<p>' . htmlspecialchars($category['description']) . '</p>';
                        }
                        echo '</div>';
                        echo '<div class="category-actions">';
                        echo '<a href="categories.php?edit=' . $category['id'] . '" class="btn btn-secondary btn-sm">编辑</a>';
                        echo '<a href="categories.php?delete=' . $category['id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'确定要删除这个分类吗？\')">删除</a>';
                        echo '</div>';
                        echo '</div>';
                        
                        // 显示子分类
                        if (isset($child_categories[$category['id']])) {
                            foreach ($child_categories[$category['id']] as $child) {
                                displayCategory($child, $child_categories, true);
                            }
                        }
                    }
                    
                    foreach ($parent_categories as $parent) {
                        displayCategory($parent, $child_categories);
                    }
                    ?>
                <?php endif; ?>
            </div>
            
            <div class="category-form">
                <h3><?php echo $edit_category ? '编辑分类' : '添加分类'; ?></h3>
                
                <form method="POST">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">分类名称 *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="parent_id">父级分类</label>
                        <select id="parent_id" name="parent_id" class="form-control">
                            <option value="">顶级分类</option>
                            <?php foreach ($categories as $category): ?>
                                <?php if (!$edit_category || $category['id'] != $edit_category['id']): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($edit_category['parent_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">描述</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_category ? '更新分类' : '添加分类'; ?>
                    </button>
                    <?php if ($edit_category): ?>
                        <a href="categories.php" class="btn btn-secondary">取消</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</body>
</html>