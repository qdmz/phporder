// 全局变量
let currentPage = 1;
let categories = [];
let shoppingCart = [];

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// 初始化应用
async function initializeApp() {
    await loadCategories();
    setupEventListeners();
    searchProducts();
}

// 加载分类数据
async function loadCategories() {
    try {
        const response = await fetch('api/get_categories.php');
        const data = await response.json();
        
        if (data.success) {
            categories = data.categories;
            populateCategorySelect();
        }
    } catch (error) {
        console.error('加载分类失败:', error);
    }
}

// 填充分类选择框
function populateCategorySelect() {
    const categorySelect = document.getElementById('category');
    
    // 添加默认选项
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = '所有分类';
    categorySelect.appendChild(defaultOption);
    
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
    
    // 重置按钮
    document.getElementById('resetBtn').addEventListener('click', function() {
        document.getElementById('searchForm').reset();
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
    
    const params = new URLSearchParams({
        q: searchTerm,
        category: category,
        min_price: minPrice,
        max_price: maxPrice,
        page: currentPage,
        limit: 12
    });
    
    showLoading();
    
    try {
        const response = await fetch(`api/search_products.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            displayProducts(data.products);
            displayPagination(data.pagination);
        } else {
            showError(data.message || '搜索失败');
        }
    } catch (error) {
        showError('网络错误，请稍后重试');
        console.error('搜索错误:', error);
    }
}

// 显示产品列表
function displayProducts(products) {
    const productsContainer = document.getElementById('productsContainer');
    
    if (products.length === 0) {
        productsContainer.innerHTML = '<div class="alert alert-info">没有找到相关产品</div>';
        return;
    }
    
    const productsHTML = products.map(product => `
        <div class="product-card">
            <img src="${product.image_url || 'assets/images/no-image.jpg'}" alt="${product.name}" class="product-image">
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
                        <span class="price-retail">¥${parseFloat(product.retail_price).toFixed(2)}</span>
                        <span style="color: #666; font-size: 0.9rem; margin-left: 0.5rem;">零售价</span>
                    </div>
                    <div>
                        <span class="price-wholesale">¥${parseFloat(product.wholesale_price).toFixed(2)}</span>
                        <span style="color: #666; font-size: 0.9rem; margin-left: 0.5rem;">批发价</span>
                    </div>
                </div>
                <button class="btn btn-primary" style="width: 100%;" onclick="addToCart(${product.id}, '${product.name}', ${product.retail_price})">
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
    
    if (pagination.total_pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let paginationHTML = '';
    
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
    
    paginationContainer.innerHTML = `<div class="pagination">${paginationHTML}</div>`;
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
    const cartTotal = document.getElementById('cartTotal');
    
    if (cartCount) {
        const totalItems = shoppingCart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
    }
    
    if (cartTotal) {
        const totalAmount = shoppingCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        cartTotal.textContent = `¥${totalAmount.toFixed(2)}`;
    }
}

// 显示购物车模态框
function showCartModal() {
    const modal = document.getElementById('cartModal');
    const cartItems = document.getElementById('cartItems');
    
    if (shoppingCart.length === 0) {
        cartItems.innerHTML = '<p>购物车是空的</p>';
    } else {
        let itemsHTML = '';
        let totalAmount = 0;
        
        shoppingCart.forEach((item, index) => {
            const subtotal = item.price * item.quantity;
            totalAmount += subtotal;
            
            itemsHTML += `
                <div class="cart-item" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid #e9ecef;">
                    <div>
                        <h4>${item.name}</h4>
                        <p>单价: ¥${item.price.toFixed(2)}</p>
                        <p>数量: ${item.quantity}</p>
                        <p>小计: ¥${subtotal.toFixed(2)}</p>
                    </div>
                    <button class="btn btn-danger" onclick="removeFromCart(${index})">移除</button>
                </div>
            `;
        });
        
        itemsHTML += `<div style="padding: 1rem; text-align: right;"><strong>总计: ¥${totalAmount.toFixed(2)}</strong></div>`;
        cartItems.innerHTML = itemsHTML;
    }
    
    modal.style.display = 'block';
}

// 从购物车移除
function removeFromCart(index) {
    shoppingCart.splice(index, 1);
    updateCartDisplay();
    showCartModal(); // 刷新显示
}

// 隐藏购物车模态框
function hideCartModal() {
    document.getElementById('cartModal').style.display = 'none';
}

// 提交订单
async function submitOrder() {
    if (shoppingCart.length === 0) {
        showError('购物车是空的');
        return;
    }
    
    const customerName = document.getElementById('customerName').value.trim();
    const customerPhone = document.getElementById('customerPhone').value.trim();
    const customerEmail = document.getElementById('customerEmail').value.trim();
    const notes = document.getElementById('orderNotes').value.trim();
    
    if (!customerName || !customerPhone) {
        showError('请填写客户姓名和电话');
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
        const response = await fetch('api/create_order.php', {
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
            showSuccess(`订单创建成功！订单号: ${data.order_no}`);
            // 重置表单
            document.getElementById('orderForm').reset();
        } else {
            showError(data.message || '订单创建失败');
        }
    } catch (error) {
        showError('网络错误，请稍后重试');
        console.error('订单错误:', error);
    }
}

// 显示加载状态
function showLoading() {
    const container = document.getElementById('productsContainer');
    container.innerHTML = '<div class="loading"><div class="spinner"></div><p>搜索中...</p></div>';
}

// 显示成功消息
function showSuccess(message) {
    showNotification(message, 'success');
}

// 显示错误消息
function showError(message) {
    showNotification(message, 'error');
}

// 显示通知消息
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'error' : type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        max-width: 400px;
        animation: slideIn 0.3s ease;
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