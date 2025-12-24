#!/bin/bash

# 部署脚本 - 用于在远程Linux服务器上部署PHP产品查询系统
# 服务器要求: Debian/Ubuntu系统，已安装Docker和docker-compose

set -e

echo "=========================================="
echo "PHP产品价格查询系统 - 自动部署脚本"
echo "=========================================="

# 检查是否以root权限运行
if [ "$EUID" -ne 0 ]; then
    echo "请以root权限运行此脚本"
    exit 1
fi

# 更新系统
echo "正在更新系统..."
apt update && apt upgrade -y

# 安装必要的软件
echo "正在安装Docker和docker-compose..."
if ! command -v docker &> /dev/null; then
    apt install -y apt-transport-https ca-certificates curl gnupg lsb-release
    curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    apt update
    apt install -y docker-ce docker-ce-cli containerd.io
fi

if ! command -v docker-compose &> /dev/null; then
    apt install -y docker-compose
fi

# 启动Docker服务
systemctl start docker
systemctl enable docker

# 创建项目目录
echo "正在创建项目目录..."
PROJECT_DIR="/opt/phporder"
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# 下载项目代码
echo "正在下载项目代码..."
if [ -d "phporder" ]; then
    rm -rf phporder
fi

# 使用wget下载或git clone
if command -v git &> /dev/null; then
    git clone https://github.com/qdmz/chaxun.git
    cd chaxun
else
    echo "Git未安装，请手动上传项目文件到 $PROJECT_DIR"
    exit 1
fi

# 设置权限
echo "正在设置文件权限..."
chmod -R 755 .
mkdir -p public/images
chmod -R 777 public/images

# 构建并启动容器
echo "正在构建Docker镜像并启动服务..."
docker-compose down
docker-compose up -d --build

# 等待服务启动
echo "等待服务启动..."
sleep 30

# 检查服务状态
echo "检查服务状态..."
docker-compose ps

# 初始化数据库
echo "正在初始化数据库..."
docker-compose exec db mysql -u root -proot123456 -e "USE phporder; SHOW TABLES;"

# 显示访问信息
echo "=========================================="
echo "部署完成！"
echo "=========================================="
echo "访问地址:"
echo "前台: http://$(curl -s ifconfig.me):8080"
echo "后台: http://$(curl -s ifconfig.me):8080/admin"
echo "phpMyAdmin: http://$(curl -s ifconfig.me):8081"
echo ""
echo "默认管理员账户:"
echo "用户名: admin"
echo "密码: password"
echo ""
echo "数据库信息:"
echo "主机: localhost"
echo "端口: 3306"
echo "数据库: phporder"
echo "用户: root"
echo "密码: root123456"
echo "=========================================="

# 创建管理脚本
cat > /opt/phporder/manage.sh << 'EOF'
#!/bin/bash
cd /opt/phporder/chaxun
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
EOF

chmod +x /opt/phporder/manage.sh

echo "管理脚本已创建: /opt/phporder/manage.sh"
echo "用法: /opt/phporder/manage.sh {start|stop|restart|status|logs|update|backup}"