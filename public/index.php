<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 头部 -->
    <header class="header">
        <div class="container">
            <h1><?php echo SITE_NAME; ?></h1>
            <p class="subtitle">专业的日用产品价格查询与订购平台</p>
        </div>
    </header>

    <!-- 导航 -->
    <nav class="nav">
        <div class="container">
            <ul class="nav-links">
                <li><a href="index.php">产品查询</a></li>
                <li><a href="#" onclick="showCartModal(); return false;">
                    购物车 (<span id="cartCount">0</span>)
                </a></li>
                <li><a href="admin/">后台管理</a></li>
            </ul>
        </div>
    </nav>

    <!-- 主要内容 -->
    <main class="container">
        <!-- 搜索区域 -->
        <section class="search-section">
            <form id="searchForm" class="search-form">
                <div class="form-group">
                    <label for="search">搜索产品</label>
                    <input type="text" id="search" class="form-control" placeholder="输入产品名称、货号、条码或型号...">
                </div>
                
                <div class="form-group">
                    <label for="category">产品分类</label>
                    <select id="category" class="form-control">
                        <option value="">所有分类</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="min_price">最低价格</label>
                    <input type="number" id="min_price" class="form-control" placeholder="0" min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="max_price">最高价格</label>
                    <input type="number" id="max_price" class="form-control" placeholder="999999" min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">搜索</button>
                </div>
            </form>
        </section>

        <!-- 产品展示区域 -->
        <section class="products-section">
            <div id="productsContainer" class="products-grid">
                <!-- 产品将通过JavaScript动态加载 -->
            </div>
            <div id="paginationContainer">
                <!-- 分页将通过JavaScript动态加载 -->
            </div>
        </section>
    </main>

    <!-- 购物车模态框 -->
    <div id="cartModal" class="order-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">购物车</h2>
                <button class="close-btn" onclick="hideCartModal()">&times;</button>
            </div>
            
            <div id="cartItems">
                <!-- 购物车内容将通过JavaScript动态生成 -->
            </div>
            
            <?php if (!empty($shoppingCart)): ?>
            <form id="orderForm" onsubmit="submitOrder(); return false;">
                <div class="form-group">
                    <label for="customerName">客户姓名 *</label>
                    <input type="text" id="customerName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="customerPhone">联系电话 *</label>
                    <input type="tel" id="customerPhone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="customerEmail">邮箱地址</label>
                    <input type="email" id="customerEmail" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="orderNotes">备注信息</label>
                    <textarea id="orderNotes" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%;">提交订单</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>