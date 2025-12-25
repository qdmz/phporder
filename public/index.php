<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .nav {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .nav-links {
            display: flex;
            justify-content: center;
            list-style: none;
        }
        
        .nav-links li {
            margin: 0 1rem;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #333;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background-color: #f0f0f0;
        }
        
        .search-section {
            background: white;
            padding: 2rem;
            margin: 2rem 0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .products-section {
            margin: 2rem 0;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-title {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .product-code {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .price-info {
            margin: 1rem 0;
            display: flex;
            justify-content: space-between;
        }
        
        .price-retail {
            font-size: 1.3rem;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .price-wholesale {
            font-size: 1.1rem;
            color: #27ae60;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
            gap: 0.5rem;
        }
        
        .pagination a {
            display: inline-block;
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: #667eea;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            margin: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .cart-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 0.25rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .quantity-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .quantity-btn:hover {
            background: #5a6268;
        }
    </style>
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
                <li><a href="../admin/">后台管理</a></li>
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
    <div id="cartModal" class="cart-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">购物车</h2>
                <button class="close-btn" onclick="hideCartModal()">&times;</button>
            </div>
            
            <div id="cartItems">
                <!-- 购物车内容将通过JavaScript动态生成 -->
            </div>
            
            <div id="orderFormContainer" style="display: none; margin-top: 1.5rem;">
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
                    
                    <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 1rem;">提交订单</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 全局变量
        let currentPage = 1;
        let categories = [];
        let shoppingCart = [];
        let currentSearchData = {}; // 存储当前搜索参数

        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            initializeApp();
        });

        // 初始化应用
        async function initializeApp() {
            await loadCategories();
            setupEventListeners();
            // 初始加载所有产品
            searchProducts();
        }

        // 加载分类数据
        async function loadCategories() {
            try {
                const response = await fetch('../api/get_categories.php');
                const data = await response.json();
                
                if (data.success) {
                    categories = data.categories;
                    populateCategorySelect();
                } else {
                    console.error('加载分类失败:', data.message);
                }
            } catch (error) {
                console.error('加载分类失败:', error);
                showError('加载分类失败: ' + error.message);
            }
        }

        // 填充分类选择框
        function populateCategorySelect() {
            const categorySelect = document.getElementById('category');
            
            // 清空现有选项（保留第一个默认选项）
            categorySelect.innerHTML = '<option value="">所有分类</option>';
            
            // 添加分类选项
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                categorySelect.appendChild(option);
            });
        }

        // 设置事件监听器
        function setupEventListeners() {
            // 搜索表单提交
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                e.preventDefault();
                currentPage = 1;
                searchProducts();
            });
        }

        // 搜索产品
        async function searchProducts() {
            const searchTerm = document.getElementById('search').value.trim();
            const category = document.getElementById('category').value;
            const minPrice = document.getElementById('min_price').value || 0;
            const maxPrice = document.getElementById('max_price').value || 999999;
            
            // 存储当前搜索参数
            currentSearchData = {
                q: searchTerm,
                category: category,
                min_price: minPrice,
                max_price: maxPrice,
                page: currentPage,
                limit: 12
            };

            const params = new URLSearchParams(currentSearchData);
            
            showLoading();
            
            try {
                const response = await fetch(`../api/search_products.php?${params}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    displayProducts(data.products);
                    displayPagination(data.pagination);
                } else {
                    showError(data.message || '搜索失败');
                }
            } catch (error) {
                console.error('搜索错误:', error);
                showError('网络错误，请检查API连接: ' + error.message);
            }
        }

        // 显示产品列表
        function displayProducts(products) {
            const productsContainer = document.getElementById('productsContainer');
            
            if (!products || products.length === 0) {
                productsContainer.innerHTML = '<div class="alert alert-info">没有找到相关产品</div>';
                return;
            }
            
            const productsHTML = products.map(product => `
                <div class="product-card">
                    <img src="${product.image_url || '../assets/images/no-image.jpg'}" 
                         alt="${product.name}" 
                         class="product-image"
                         onerror="this.src='../assets/images/no-image.jpg'">
                    <div class="product-info">
                        <h3 class="product-title">${product.name}</h3>
                        <p class="product-code">货号: ${product.product_code}</p>
                        ${product.barcode ? `<p class="product-code">条码: ${product.barcode}</p>` : ''}
                        ${product.model ? `<p class="product-code">型号: ${product.model}</p>` : ''}
                        ${product.specification ? `<p class="product-code">规格: ${product.specification}</p>` : ''}
                        <div class="product-details">
                            <p>库存: ${product.stock_quantity} 件</p>
                            ${product.category_name ? `<p>分类: ${product.category_name}</p>` : ''}
                        </div>
                        <div class="price-info">
                            <div>
                                <span class="price-retail">¥${parseFloat(product.retail_price || 0).toFixed(2)}</span>
                                <div style="color: #666; font-size: 0.9rem;">零售价</div>
                            </div>
                            <div>
                                <span class="price-wholesale">¥${parseFloat(product.wholesale_price || 0).toFixed(2)}</span>
                                <div style="color: #666; font-size: 0.9rem;">批发价</div>
                            </div>
                        </div>
                        <button class="btn btn-primary" style="width: 100%;" 
                                onclick="addToCart(${product.id}, '${product.name.replace(/'/g, "\\'")}', ${product.retail_price || 0})">
                            加入购物车
                        </button>
                    </div>
                </div>
            `).join('');
            
            productsContainer.innerHTML = productsHTML;
        }

        // 显示分页
        function displayPagination(pagination) {
            const paginationContainer = document.getElementById('paginationContainer');
            
            if (!pagination || pagination.total_pages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }
            
            let paginationHTML = '<div class="pagination">';
            
            // 上一页
            if (pagination.page > 1) {
                paginationHTML += `<a href="#" onclick="changePage(${pagination.page - 1}); return false;">上一页</a>`;
            }
            
            // 页码
            const startPage = Math.max(1, pagination.page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.page ? 'active' : '';
                paginationHTML += `<a href="#" class="${activeClass}" onclick="changePage(${i}); return false;">${i}</a>`;
            }
            
            // 下一页
            if (pagination.page < pagination.total_pages) {
                paginationHTML += `<a href="#" onclick="changePage(${pagination.page + 1}); return false;">下一页</a>`;
            }
            
            paginationHTML += '</div>';
            paginationContainer.innerHTML = paginationHTML;
        }

        // 切换页面
        function changePage(page) {
            currentPage = page;
            searchProducts();
            // 滚动到顶部
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // 添加到购物车
        function addToCart(productId, productName, price) {
            const existingItem = shoppingCart.find(item => item.product_id === productId);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                shoppingCart.push({
                    product_id: productId,
                    name: productName,
                    price: price,
                    quantity: 1
                });
            }
            
            updateCartDisplay();
            showNotification('已添加到购物车');
        }

        // 更新购物车显示
        function updateCartDisplay() {
            const cartCount = document.getElementById('cartCount');
            if (cartCount) {
                const totalItems = shoppingCart.reduce((sum, item) => sum + item.quantity, 0);
                cartCount.textContent = totalItems;
            }
        }

        // 显示购物车模态框
        function showCartModal() {
            const modal = document.getElementById('cartModal');
            const cartItems = document.getElementById('cartItems');
            const orderFormContainer = document.getElementById('orderFormContainer');
            
            if (shoppingCart.length === 0) {
                cartItems.innerHTML = '<p style="padding: 1rem; text-align: center;">购物车是空的</p>';
                if (orderFormContainer) {
                    orderFormContainer.style.display = 'none';
                }
            } else {
                let itemsHTML = '';
                let totalAmount = 0;
                
                shoppingCart.forEach((item, index) => {
                    const subtotal = item.price * item.quantity;
                    totalAmount += subtotal;
                    
                    itemsHTML += `
                        <div class="cart-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4>${item.name}</h4>
                                    <p>单价: ¥${item.price.toFixed(2)}</p>
                                    <div class="quantity-control">
                                        <span>数量: </span>
                                        <button class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                                        <input type="number" value="${item.quantity}" min="1" max="999" class="quantity-input" onchange="updateQuantityInput(${index}, this.value)">
                                        <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
                                    </div>
                                    <p>小计: ¥${subtotal.toFixed(2)}</p>
                                </div>
                                <button class="btn" style="background: #dc3545;" onclick="removeFromCart(${index})">移除</button>
                            </div>
                        </div>
                    `;
                });
                
                itemsHTML += `<div style="padding: 1rem; text-align: right; border-top: 1px solid #eee;"><strong>总计: ¥${totalAmount.toFixed(2)}</strong></div>`;
                cartItems.innerHTML = itemsHTML;
                
                // 显示订单表单
                if (orderFormContainer) {
                    orderFormContainer.style.display = 'block';
                }
            }
            
            modal.style.display = 'flex';
        }

        // 从购物车移除
        function removeFromCart(index) {
            shoppingCart.splice(index, 1);
            updateCartDisplay();
            showCartModal(); // 刷新显示
        }
        
        // 更新购物车中商品数量
        function updateQuantity(index, change) {
            const item = shoppingCart[index];
            const newQuantity = item.quantity + change;
            
            // 确保数量在合理范围内
            if (newQuantity >= 1) {
                item.quantity = newQuantity;
                updateCartDisplay();
                showCartModal(); // 刷新显示
            }
        }
        
        // 通过输入框更新购物车中商品数量
        function updateQuantityInput(index, value) {
            const item = shoppingCart[index];
            const newQuantity = parseInt(value);
            
            if (!isNaN(newQuantity) && newQuantity >= 1) {
                item.quantity = newQuantity;
                updateCartDisplay();
                showCartModal(); // 刷新显示
            }
        }

        // 隐藏购物车模态框
        function hideCartModal() {
            document.getElementById('cartModal').style.display = 'none';
        }

        // 提交订单
        async function submitOrder() {
            if (shoppingCart.length === 0) {
                showNotification('购物车是空的');
                return;
            }
            
            const customerName = document.getElementById('customerName').value.trim();
            const customerPhone = document.getElementById('customerPhone').value.trim();
            const customerEmail = document.getElementById('customerEmail').value.trim();
            const notes = document.getElementById('orderNotes').value.trim();
            
            if (!customerName || !customerPhone) {
                showNotification('请填写客户姓名和电话');
                return;
            }
            
            const orderData = {
                customer_name: customerName,
                customer_phone: customerPhone,
                customer_email: customerEmail,
                notes: notes,
                items: shoppingCart.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    price: item.price
                }))
            };
            
            try {
                const response = await fetch('../api/create_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    shoppingCart = [];
                    updateCartDisplay();
                    hideCartModal();
                    showNotification(`订单创建成功！订单号: ${data.order_no}`);
                    // 重置表单
                    document.getElementById('orderForm').reset();
                } else {
                    showNotification(data.message || '订单创建失败');
                }
            } catch (error) {
                console.error('订单提交错误:', error);
                showNotification('网络错误，请稍后重试: ' + error.message);
            }
        }

        // 显示加载状态
        function showLoading() {
            const container = document.getElementById('productsContainer');
            container.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>搜索中...</p>
                </div>
            `;
        }

        // 显示错误消息
        function showError(message) {
            const container = document.getElementById('productsContainer');
            container.innerHTML = `<div class="alert alert-error">${message}</div>`;
        }

        // 显示通知消息
        function showNotification(message) {
            // 创建通知元素
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 1rem;
                border-radius: 5px;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            
            document.body.appendChild(notification);
            
            // 3秒后自动移除
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('cartModal');
            if (event.target === modal) {
                hideCartModal();
            }
        }
    </script>
</body>
</html>
