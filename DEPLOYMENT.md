# 远程服务器部署指南

本文档详细说明如何将PHP产品价格查询系统部署到远程Linux Debian服务器上。

## 服务器信息

- **服务器IP**: 42.194.226.146
- **操作系统**: Debian Linux
- **用户**: root
- **密码**: 

## 部署方式一：自动部署（推荐）

### 1. 连接到服务器

```bash
ssh root@42.194.226.146
```

### 2. 下载并运行部署脚本

```bash
# 下载项目部署脚本
wget https://raw.githubusercontent.com/qdmz/chaxun/main/deploy.sh
chmod +x deploy.sh

# 运行自动部署脚本
./deploy.sh
```

部署脚本会自动完成以下操作：
- 更新系统
- 安装Docker和docker-compose
- 下载项目代码
- 构建并启动服务
- 配置权限
- 初始化数据库

## 部署方式二：手动部署

### 1. 服务器环境准备

```bash
# 更新系统
apt update && apt upgrade -y

# 安装基础工具
apt install -y curl wget git vim

# 安装Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
systemctl start docker
systemctl enable docker

# 安装docker-compose
curl -L "https://github.com/docker/compose/releases/download/v2.12.2/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
```

### 2. 下载项目代码

```bash
# 创建项目目录
mkdir -p /opt/phporder
cd /opt/phporder

# 克隆项目代码
git clone https://github.com/qdmz/chaxun.git
cd chaxun
```

### 3. 配置和启动服务

```bash
# 设置文件权限
chmod -R 755 .
mkdir -p public/images
chmod -R 777 public/images

# 构建并启动Docker容器
docker-compose down
docker-compose up -d --build

# 查看服务状态
docker-compose ps
```

### 4. 数据库初始化

```bash
# 等待数据库启动（约30秒）
sleep 30

# 检查数据库连接
docker-compose exec db mysql -u root -proot123456 -e "SHOW DATABASES;"

# 如果需要重新初始化数据库
docker-compose exec db mysql -u root -proot123456 -e "USE phporder; SOURCE /docker-entrypoint-initdb.d/database.sql;"
```

## 部署后配置

### 1. 防火墙设置

```bash
# 如果使用UFW防火墙
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw allow 8080/tcp  # 应用端口
ufw allow 8081/tcp  # phpMyAdmin端口
```

### 2. 配置域名（可选）

如果您有域名，可以配置Nginx作为反向代理：

```bash
# 安装Nginx
apt install -y nginx

# 创建配置文件
vim /etc/nginx/sites-available/phporder
```

Nginx配置内容：
```nginx
server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    listen 80;
    server_name phpmyadmin.your-domain.com;

    location / {
        proxy_pass http://localhost:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

启用站点：
```bash
ln -s /etc/nginx/sites-available/phporder /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### 3. SSL证书配置（可选）

使用Let's Encrypt免费SSL证书：

```bash
# 安装Certbot
apt install -y certbot python3-certbot-nginx

# 获取SSL证书
certbot --nginx -d your-domain.com -d phpmyadmin.your-domain.com

# 设置自动续期
crontab -e
# 添加以下行：
0 12 * * * /usr/bin/certbot renew --quiet
```

## 访问应用

部署完成后，您可以通过以下地址访问：

- **前台应用**: http://42.194.226.146:8080
- **后台管理**: http://42.194.226.146:8080/admin
- **数据库管理**: http://42.194.226.146:8081

### 默认登录信息

- **管理员账户**:
  - 用户名: `admin`
  - 密码: `password`

- **数据库**:
  - 主机: `localhost` (在容器内为 `db`)
  - 端口: `3306`
  - 数据库: `phporder`
  - 用户名: `root`
  - 密码: `root123456`

## 管理脚本

项目包含一个管理脚本用于日常运维：

```bash
# 脚本位置：/opt/phporder/manage.sh

# 启动服务
/opt/phporder/manage.sh start

# 停止服务
/opt/phporder/manage.sh stop

# 重启服务
/opt/phporder/manage.sh restart

# 查看状态
/opt/phporder/manage.sh status

# 查看日志
/opt/phporder/manage.sh logs

# 更新应用
/opt/phporder/manage.sh update

# 备份数据库
/opt/phporder/manage.sh backup
```

## 备份策略

### 数据库备份

```bash
# 手动备份
docker-compose exec db mysqldump -u root -proot123456 phporder > backup_$(date +%Y%m%d_%H%M%S).sql

# 自动备份（每日凌晨2点）
crontab -e
# 添加：
0 2 * * * /opt/phporder/manage.sh backup
```

### 文件备份

```bash
# 备份上传的图片
tar -czf images_backup_$(date +%Y%m%d).tar.gz public/images/

# 备份配置文件
tar -czf config_backup_$(date +%Y%m%d).tar.gz config/
```

## 监控和维护

### 1. 日志监控

```bash
# 查看应用日志
docker-compose logs -f web

# 查看数据库日志
docker-compose logs -f db

# 查看系统日志
journalctl -u docker.service -f
```

### 2. 性能监控

```bash
# 查看容器资源使用情况
docker stats

# 查看系统资源
htop
df -h
free -h
```

### 3. 定期维护

```bash
# 清理Docker镜像和容器
docker system prune -f

# 更新系统
apt update && apt upgrade -y

# 重启服务（如需要）
/opt/phporder/manage.sh restart
```

## 故障排除

### 常见问题

1. **容器无法启动**
```bash
# 查看详细错误信息
docker-compose logs

# 检查端口占用
netstat -tlnp | grep 8080

# 重建容器
docker-compose down
docker-compose up -d --build
```

2. **数据库连接失败**
```bash
# 检查数据库容器状态
docker-compose ps db

# 进入数据库容器
docker-compose exec db mysql -u root -p

# 重启数据库
docker-compose restart db
```

3. **图片上传失败**
```bash
# 检查目录权限
ls -la public/images/

# 修复权限
chmod -R 777 public/images/
chown -R www-data:www-data public/images/
```

4. **页面无法访问**
```bash
# 检查防火墙设置
ufw status

# 检查Apache/Nginx配置
docker-compose exec web apache2ctl configtest
```

### 紧急恢复

如果遇到严重问题，可以使用备份恢复：

```bash
# 恢复数据库
docker-compose exec -T db mysql -u root -proot123456 phporder < backup_file.sql

# 恢复文件
tar -xzf images_backup.tar.gz
```

## 安全建议

1. **修改默认密码**
   - 修改数据库root密码
   - 修改管理员账户密码
   - 更新配置文件中的密码

2. **定期更新**
   - 定期更新系统包
   - 更新Docker镜像
   - 更新应用程序

3. **访问控制**
   - 限制SSH访问IP
   - 使用防火墙限制端口
   - 配置fail2ban防暴力破解

4. **监控告警**
   - 设置磁盘空间监控
   - 设置服务状态监控
   - 配置邮件告警

## 联系支持

如果在部署过程中遇到问题，请通过以下方式获取帮助：

- GitHub Issues: https://github.com/qdmz/chaxun/issues
- Email: admin@example.com

---

**注意**: 在生产环境中，请务必修改所有默认密码，并配置适当的安全措施。
