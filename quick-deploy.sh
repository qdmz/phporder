#!/bin/bash

# 快速部署脚本 - 通过SSH在远程服务器上执行一键部署

SERVER_IP=""
SERVER_USER="root"
SERVER_PASS=""

echo "=========================================="
echo "快速部署到远程服务器"
echo "服务器: ${SERVER_IP}"
echo "=========================================="

# 创建远程部署脚本
cat > remote-deploy.sh << 'EOF'
#!/bin/bash

# 远程服务器上的部署脚本
set -e

echo "开始远程部署..."

# 更新系统
apt update && apt upgrade -y

# 安装基础工具
apt install -y curl wget git vim unzip

# 安装Docker
if ! command -v docker &> /dev/null; then
    echo "安装Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    systemctl start docker
    systemctl enable docker
fi

# 安装docker-compose
if ! command -v docker-compose &> /dev/null; then
    echo "安装docker-compose..."
    curl -L "https://github.com/docker/compose/releases/download/v2.12.2/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
fi

# 创建项目目录
mkdir -p /opt/phporder
cd /opt/phporder

# 下载项目
if [ -d "phporder" ]; then
    rm -rf phporder
fi

git clone https://github.com/qdmz/phporder.git
cd phporder

# 设置权限
chmod -R 755 .
mkdir -p public/images
chmod -R 777 public/images

# 停止旧容器（如果存在）
docker-compose down

# 构建并启动新容器
docker-compose up -d --build

# 等待服务启动
echo "等待服务启动..."
sleep 45

# 检查服务状态
echo "检查服务状态..."
docker-compose ps

# 获取服务器IP
SERVER_IP=$(curl -s ifconfig.me || echo "42.194.226.146")

echo "=========================================="
echo "部署完成！"
echo "=========================================="
echo "访问地址:"
echo "前台: http://${SERVER_IP}:8080"
echo "后台: http://${SERVER_IP}:8080/admin"
echo "phpMyAdmin: http://${SERVER_IP}:8081"
echo ""
echo "默认管理员账户:"
echo "用户名: admin"
echo "密码: password"
echo ""
echo "管理命令:"
echo "/opt/phporder/manage.sh {start|stop|restart|status|logs|update|backup}"
echo "=========================================="

# 创建管理脚本
cat > /opt/phporder/manage.sh << 'MANAGE_EOF'
#!/bin/bash
cd /opt/phporder/phporder
case "$1" in
    start)
        docker-compose up -d
        echo "服务已启动"
        ;;
    stop)
        docker-compose down
        echo "服务已停止"
        ;;
    restart)
        docker-compose restart
        echo "服务已重启"
        ;;
    status)
        docker-compose ps
        ;;
    logs)
        docker-compose logs -f
        ;;
    update)
        git pull
        docker-compose down
        docker-compose up -d --build
        echo "服务已更新"
        ;;
    backup)
        BACKUP_DIR="/opt/phporder/backups"
        mkdir -p $BACKUP_DIR
        DATE=$(date +%Y%m%d_%H%M%S)
        docker-compose exec db mysqldump -u root -proot123456 phporder > $BACKUP_DIR/phporder_backup_$DATE.sql
        echo "数据库已备份到: $BACKUP_DIR/phporder_backup_$DATE.sql"
        ;;
    *)
        echo "用法: $0 {start|stop|restart|status|logs|update|backup}"
        exit 1
        ;;
esac
MANAGE_EOF

chmod +x /opt/phporder/manage.sh

echo "管理脚本已创建"
EOF

# 使用SSH执行远程部署
echo "正在连接到远程服务器并执行部署..."

if command -v sshpass &> /dev/null; then
    # 如果有sshpass，可以直接使用密码
    sshpass -p "${SERVER_PASS}" scp -o StrictHostKeyChecking=no remote-deploy.sh ${SERVER_USER}@${SERVER_IP}:/tmp/
    sshpass -p "${SERVER_PASS}" ssh -o StrictHostKeyChecking=no ${SERVER_USER}@${SERVER_IP} "chmod +x /tmp/remote-deploy.sh && /tmp/remote-deploy.sh"
else
    # 如果没有sshpass，手动复制脚本内容到远程服务器执行
    echo "请手动执行以下操作："
    echo "1. 连接到服务器: ssh ${SERVER_USER}@${SERVER_IP}"
    echo "2. 复制上面的remote-deploy.sh内容到服务器上执行"
    echo ""
    echo "或者使用以下命令复制脚本："
    echo "scp remote-deploy.sh ${SERVER_USER}@${SERVER_IP}:/tmp/"
    echo "ssh ${SERVER_USER}@${SERVER_IP}"
    echo "chmod +x /tmp/remote-deploy.sh && /tmp/remote-deploy.sh"
fi

# 清理本地文件
rm -f remote-deploy.sh

echo "=========================================="
echo "部署脚本执行完成！"
echo "请检查远程服务器上的部署结果。"
echo "=========================================="
