<?php
require_once 'config/config.php';
?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 首页</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            padding: 3rem 0;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .section {
            margin-bottom: 2rem;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .feature-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .feature-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            text-align: center;
            min-width: 200px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .footer {
            text-align: center;
            color: white;
            padding: 2rem 0;
            margin-top: 2rem;
        }
        
        .footer p {
            margin: 0.5rem 0;
            opacity: 0.8;
        }
        
        .contact {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .contact a {
            color: #fff;
            text-decoration: none;
            opacity: 0.9;
        }
        
        .contact a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .links {
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>专业的日用产品价格查询与订购平台</p>
        </div>
        
        <div class="content">
            <div class="section">
                <h2>项目简介</h2>
                <p>这是一个功能完整的日用产品价格查询与订购系统，集成了产品管理、订单处理、数据统计等功能。系统采用现代化的Web技术构建，提供直观易用的用户界面和强大的后台管理功能。</p>
            </div>
            
            <div class="section">
                <h2>主要功能</h2>
                <div class="features">
                    <div class="feature-card">
                        <h3>产品查询</h3>
                        <p>支持按产品名称、货号、条码、型号、价格范围等多种条件进行精确搜索，快速定位所需产品信息。</p>
                    </div>
                    <div class="feature-card">
                        <h3>在线订购</h3>
                        <p>用户可以将产品添加到购物车，支持数量调整，一键提交订单，简化采购流程。</p>
                    </div>
                    <div class="feature-card">
                        <h3>后台管理</h3>
                        <p>提供完善的后台管理系统，包括产品管理、订单管理、分类管理、数据统计等功能。</p>
                    </div>
                    <div class="feature-card">
                        <h3>数据统计</h3>
                        <p>可视化图表展示销售数据、产品分析、客户统计等，帮助了解业务状况。</p>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2>使用说明</h2>
                <p>本系统分为前台用户界面和后台管理界面：</p>
                <ul style="margin: 1rem 0 1rem 2rem;">
                    <li style="margin: 0.5rem 0;">前台界面：用于产品查询、浏览和下单订购</li>
                    <li style="margin: 0.5rem 0;">后台管理：用于产品管理、订单处理、数据统计等管理功能</li>
                </ul>
            </div>
            
            <div class="links">
                <a href="public/index.php" class="btn btn-primary">访问前台系统</a>
                <a href="admin/login.php" class="btn btn-secondary">后台管理系统</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 <?php echo SITE_NAME; ?>. 版权所有.</p>
            <p>联系我们</p>
            <div class="contact">
                <a href="mailto:admin@yuvps.com">邮箱: admin@yuvps.com</a>
                <a href="tencent://message/?uin=278910201">QQ: 278910201</a>
            </div>
        </div>
    </div>
</body>
</html>
