FROM php:8.2-apache

# OS deps + PHP extensions
RUN apt-get update && apt-get install -y \
    sudo \
    libmariadb-dev \
    mariadb-client \
    libonig-dev \
    pkg-config \
  && docker-php-ext-install mysqli mbstring \
  && rm -rf /var/lib/apt/lists/*

# Copy application source
COPY . /var/www/html/

# Users/dirs for labs
RUN useradd -m mssql_svc && \
    mkdir -p /var/www/html/labs/LFI/uploads/smb_share \
    && mkdir -p /var/www/html/labs/LFI/queries/default \
    && mkdir -p /var/www/html/labs/XssRceFileUpload/uploads \
	&& mkdir -p /var/www/html/labs/xssencodingRegression/uploads \
    && touch /var/www/html/labs/xssTextArea/data.txt \
    && touch /var/www/html/labs/xssDatabaseTransform/stored.txt \
    && touch /var/www/html/labs/BACtoo/data/banner-message

# Writable lab paths owned by www-data
RUN chown -R root:root /var/www/html \
    && chown -R www-data:www-data \
        /var/www/html/labs/LFI/uploads \
        /var/www/html/labs/XssRceFileUpload/uploads \
		/var/www/html/labs/xssencodingRegression/uploads \
    && chown www-data:www-data \
        /var/www/html/labs/xssTextArea/data.txt \
        /var/www/html/labs/xssDatabaseTransform/stored.txt \
        /var/www/html/labs/BACtoo/data/banner-message \
    && chmod -R 775 \
        /var/www/html/labs/LFI/uploads \
        /var/www/html/labs/XssRceFileUpload/uploads \
		/var/www/html/labs/xssencodingRegression/uploads \
    && chmod 664 \
        /var/www/html/labs/xssTextArea/data.txt \
        /var/www/html/labs/xssDatabaseTransform/stored.txt \
        /var/www/html/labs/BACtoo/data/banner-message

# Sudo rules
RUN echo "www-data ALL=(mssql_svc) NOPASSWD: ALL" > /etc/sudoers.d/crevice && \
    chmod 0440 /etc/sudoers.d/crevice

# Enable Apache mods
RUN a2enmod rewrite headers

# Apache config
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Ensure python script for OS command injection lab is executable
RUN chmod +x /var/www/html/labs/RCE/vulnlist || true

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]