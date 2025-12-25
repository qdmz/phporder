FROM php:8.1-apache

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libgd-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mysqli zip bcmath exif \
    && rm -rf /var/lib/apt/lists/*

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 启用Apache mod_rewrite 和其他有用的模块
RUN a2enmod rewrite \
    && a2enmod headers \
    && a2enmod expires

# 复制项目文件
COPY . /var/www/html/

# 设置权限 - 创建public/images目录（如果不存在）并设置权限
RUN mkdir -p /var/www/html/public/images \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/public/images

# 创建Apache虚拟主机配置
RUN echo "<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options Indexes FollowSymLinks\n\
    </Directory>\n\
    # 阻止访问敏感文件\n\
    <Files ~ \"^\.|composer\.(json|lock)$\">\n\
        Require all denied\n\
    </Files>\n\
    # 设置PHP配置\n\
    <Directory /var/www/html/config>\n\
        Require all denied\n\
    </Directory>\n\
    <Directory /var/www/html/includes>\n\
        Require all denied\n\
    </Directory>\n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# 配置PHP
RUN echo "upload_max_filesize = 50M\n\
post_max_size = 50M\n\
memory_limit = 256M\n\
max_execution_time = 300\n\
max_input_vars = 3000\n\
max_input_time = 300" > /usr/local/etc/php/conf.d/uploads.ini

# 暴露80端口
EXPOSE 80

# 设置启动命令
CMD ["apache2-foreground"]
