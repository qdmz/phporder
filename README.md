# PHP产品价格查询系统

一个基于PHP开发的专业销售和批发日用产品价格查询系统，支持产品查询、订单管理、批量导入、图片管理等功能。

## 功能特性

### 前台功能
- 🔍 **产品查询** - 支持按名称、货号、条码、型号等多条件搜索
- 📱 **响应式设计** - 完美适配手机、平板和桌面设备
- 🛒 **购物车系统** - 添加产品到购物车，批量下单
- 📧 **订单通知** - 自动发送邮件通知给销售管理人员
- 📊 **产品展示** - 显示产品图片、价格、库存等详细信息

### 后台管理
- 👤 **管理员系统** - 安全的登录验证和权限管理
- 📦 **产品管理** - 增删改查产品信息，支持图片上传
- 📂 **分类管理** - 产品分类的创建和管理
- 📋 **订单管理** - 查看订单详情，更新订单状态
- 📤 **批量导入** - 支持Excel/CSV批量导入产品数据
- ⚙️ **系统设置** - 邮件配置、通知设置、密码修改
- 📈 **数据统计** - 产品概况、销售统计等信息

### 技术特性
- 🐳 **Docker支持** - 一键部署，包含完整环境
- 🔒 **安全防护** - SQL注入防护、XSS防护、CSRF保护
- 📧 **邮件通知** - 支持SMTP邮件发送
- 🗄️ **数据库** - MySQL数据库，支持备份和恢复
- 🎨 **现代UI** - 基于Material Design的美观界面

## 技术栈

- **后端**: PHP 8.1+, PDO, MySQL 8.0
- **前端**: HTML5, CSS3, JavaScript (ES6+)
- **服务器**: Apache 2.4
- **数据库**: MySQL 8.0
- **容器化**: Docker, Docker Compose
- **管理工具**: phpMyAdmin

## 快速开始

### 环境要求

- PHP 8.1 或更高版本
- MySQL 8.0 或更高版本
- Apache 2.4 或 Nginx
- Composer (可选，用于依赖管理)
- Docker (推荐，用于快速部署)

### Docker部署 (推荐)

1. **克隆项目**
```bash
git clone https://github.com/qdmz/chaxun.git
cd chaxun
```

2. **启动服务**
```bash
docker-compose up -d
```

3. **访问应用**
- 前台: http://localhost:8080
- 后台: http://localhost:8080/admin
- phpMyAdmin: http://localhost:8081

### 手动部署

1. **下载源码**
```bash
git clone https://github.com/qdmz/chaxun.git
cd chaxun
```

2. **配置数据库**
```bash
mysql -u root -p < database.sql
```

3. **修改配置**
编辑 `config/database.php` 文件，设置数据库连接信息：
```php
private $host = 'localhost';
private $db_name = 'phporder';
private $username = 'root';
private $password = 'your_password';
```

4. **设置权限**
```bash
chmod -R 755 public/
chmod -R 777 public/images/
```

5. **配置Web服务器**

**Apache配置示例:**
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/chaxun/public
    <Directory /path/to/chaxun/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx配置示例:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/chaxun/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## 配置说明

### 数据库配置

系统使用MySQL数据库，主要包含以下表：

- `products` - 产品信息表
- `categories` - 产品分类表
- `orders` - 订单表
- `order_items` - 订单详情表
- `admin_users` - 管理员用户表
- `settings` - 系统设置表
- `notifications` - 通知记录表

### 邮件配置

在后台管理 -> 系统设置中配置SMTP邮件服务器：

- SMTP服务器 (如: smtp.gmail.com)
- SMTP端口 (如: 587)
- SMTP用户名和密码
- 管理员邮箱地址

### 图片上传

- 支持JPG、JPEG、PNG、GIF格式
- 单个文件最大5MB
- 上传路径: `public/images/`

## 使用说明

### 默认账户

系统安装后会创建一个默认管理员账户：
- 用户名: `admin`
- 密码: `password`

**重要:** 首次登录后请立即修改密码！

### 产品管理

1. 登录后台管理系统
2. 进入"产品管理"页面
3. 点击"添加产品"或"编辑"现有产品
4. 填写产品信息并上传图片
5. 保存产品信息

### 订单管理

1. 客户在前台提交订单后，系统会自动发送邮件通知
2. 管理员在后台"订单管理"页面查看订单
3. 可以更新订单状态（待确认、已确认、已发货、已取消）
4. 状态变更时会自动发送邮件给客户

### 批量导入

1. 准备Excel或CSV文件，格式如下：
```
货号,产品名称,条码,型号,规格,零售价,批发价,库存数量,分类ID
P001,产品A,123456789,Model-A,规格A,10.00,8.00,100,1
```

2. 在后台"批量导入"页面上传文件
3. 系统会自动验证并导入数据

## 开发说明

### 项目结构

```
phporder/
├── admin/                  # 后台管理文件
│   ├── dashboard.php       # 管理仪表板
│   ├── login.php          # 登录页面
│   ├── products.php       # 产品管理
│   ├── orders.php         # 订单管理
│   ├── settings.php       # 系统设置
│   └── ...
├── api/                    # API接口文件
│   ├── search_products.php # 产品搜索API
│   ├── create_order.php    # 创建订单API
│   └── ...
├── assets/                 # 静态资源
│   ├── css/               # 样式文件
│   ├── js/                # JavaScript文件
│   └── images/            # 图片资源
├── config/                 # 配置文件
│   ├── database.php       # 数据库配置
│   └── config.php         # 系统配置
├── includes/               # 公共函数库
│   └── functions.php      # 工具函数
├── public/                 # Web根目录
│   ├── index.php          # 前台主页
│   └── images/            # 上传图片目录
├── database.sql           # 数据库初始化脚本
├── docker-compose.yml     # Docker编排文件
├── Dockerfile             # Docker镜像构建文件
└── README.md              # 项目说明文档
```

### API接口

系统提供RESTful API接口：

- `GET /api/search_products.php` - 搜索产品
- `GET /api/get_categories.php` - 获取分类列表
- `POST /api/create_order.php` - 创建订单

### 安全特性

- **SQL注入防护** - 使用PDO预处理语句
- **XSS防护** - 输入数据过滤和输出转义
- **CSRF保护** - 令牌验证机制
- **密码加密** - 使用PHP password_hash()函数
- **文件上传安全** - 文件类型和大小限制

## 常见问题

### Q: 如何修改上传文件大小限制？
A: 修改`php.ini`文件中的`upload_max_filesize`和`post_max_size`设置。

### Q: 如何备份数据库？
A: 使用以下命令备份数据库：
```bash
mysqldump -u username -p phporder > backup.sql
```

### Q: 如何恢复数据库？
A: 使用以下命令恢复数据库：
```bash
mysql -u username -p phporder < backup.sql
```

### Q: 忘记管理员密码怎么办？
A: 可以通过数据库直接修改密码：
```sql
UPDATE admin_users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';
```
这会将密码重置为`password`。

### Q: 如何开启错误调试？
A: 修改`config/config.php`文件中的错误设置：
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 更新日志

### v1.0.0 (2024-12-24)
- ✨ 初始版本发布
- 🚀 支持产品查询和订单管理
- 🐳 Docker容器化部署
- 📱 响应式前端界面
- 🔐 完善的后台管理系统

## 贡献指南

欢迎提交Issue和Pull Request来改进这个项目！

1. Fork 本项目
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 支持

如果您在使用过程中遇到问题，可以通过以下方式获取帮助：

- 📧 Email: admin@example.com
- 🐛 GitHub Issues: https://github.com/qdmz/chaxun/issues
- 📖 文档: https://github.com/qdmz/chaxun/wiki

## 致谢

感谢所有为本项目做出贡献的开发者和用户！

---

⭐ 如果这个项目对您有帮助，请给我们一个星标！