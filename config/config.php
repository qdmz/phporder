<?php
// 系统配置
define('SITE_NAME', '产品价格查询系统');
define('SITE_URL', 'http://localhost');
define('UPLOAD_PATH', 'public/images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// 分页配置
define('ITEMS_PER_PAGE', 20);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 开启会话
session_start();

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 包含必要的文件
require_once 'database.php';
require_once '../includes/functions.php';
?>
