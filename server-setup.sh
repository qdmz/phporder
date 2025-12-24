#!/bin/bash

# 服务器初始化脚本 - 在新的Debian服务器上安装必要环境

set -e

echo "=========================================="
echo "Debian服务器初始化脚本"
echo "=========================================="

# 更新系统
apt update && apt upgrade -y

# 安装基础工具
apt install -y curl wget git vim nano htop unzip

# 安装Docker
echo "安装Docker..."
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
systemctl start docker
systemctl enable docker

# 安装docker-compose
echo "安装docker-compose..."
curl -L "https://github.com/docker/compose/releases/download/v2.12.2/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# 安装Apache (作为备用方案)
apt install -y apache2

# 安装PHP和扩展 (作为备用方案)
apt install -y php php-cli php-mysql php-gd php-zip php-bcmath php-mbstring php-xml php-curl

# 安装MySQL客户端
apt install -y mysql-client

# 配置防火墙
if command -v ufw &> /dev/null; then
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow 8080/tcp
    ufw allow 8081/tcp
    ufw --force enable
fi

# 创建项目目录
mkdir -p /opt/phporder
mkdir -p /opt/phporder/backups

echo "=========================================="
echo "服务器初始化完成！"
echo "=========================================="
echo "现在可以运行 deploy.sh 脚本部署应用"