# Deployment Guide - Mechanic Saint Augustine

## Prerequisites

- **Web Server**: Apache 2.4+ or Nginx 1.18+ with PHP support
- **PHP**: Version 7.4+ (8.0+ recommended)
  - Required extensions: `mysqli`, `pdo`, `pdo_mysql`, `curl`, `json`, `mbstring`, `openssl`
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Go**: Version 1.19+ (for backend API)
- **SSL Certificate**: Required for production (Let's Encrypt recommended)

## Quick Start Deployment

### 1. Server Setup

```bash
# Clone the repository
git clone <your-repo-url> /var/www/mechanicsaintaugustine.com
cd /var/www/mechanicsaintaugustine.com

# Set proper permissions
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 775 api quote voice admin Mobile-mechanic
```

### 2. Environment Configuration

```bash
# Create environment file from template
cp .env.example .env

# Edit .env with your actual credentials
nano .env

# IMPORTANT: Set these environment variables in your web server config
# For Apache: /etc/apache2/envvars or virtual host config
# For Nginx: pass them via fastcgi_param
```

### 3. Database Setup

```bash
# Create databases
mysql -u root -p <<EOF
CREATE DATABASE mm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE rating CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mechanic_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON mm.* TO 'mechanic_user'@'localhost';
GRANT ALL PRIVILEGES ON rating.* TO 'mechanic_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import database schemas (if you have SQL files)
# mysql -u mechanic_user -p mm < database/schema.sql
# mysql -u mechanic_user -p rating < database/rating_schema.sql
```

### 4. CRM Setup (Rukovoditel)

The `/crm/` directory contains the Rukovoditel CRM system.

```bash
# Configure CRM database
cp crm/config/database.php.example crm/config/database.php
nano crm/config/database.php

# Access CRM admin panel at:
# https://yourdomain.com/crm/

# Create API key in CRM:
# 1. Login to CRM
# 2. Go to Configuration > API
# 3. Generate new API key
# 4. Add API key to .env file
```

### 5. Twilio Configuration

```bash
# Configure Twilio webhooks in Twilio Console:

# Voice Incoming Call:
# https://yourdomain.com/voice/incoming.php

# Recording Status Callback:
# https://yourdomain.com/voice/recording_callback.php

# SMS Incoming:
# https://yourdomain.com/api/sms/incoming.php

# Quote Status Callback:
# https://yourdomain.com/quote/status_callback.php
```

### 6. Go Backend Setup (Optional)

```bash
cd backend

# Build the Go application
go build -o bin/mechanic-api cmd/server/main.go

# Create systemd service
sudo tee /etc/systemd/system/mechanic-api.service > /dev/null <<EOF
[Unit]
Description=Mechanic Saint Augustine API
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/mechanicsaintaugustine.com/backend
Environment="DB_HOST=localhost"
Environment="DB_USER=mechanic_user"
Environment="DB_PASSWORD=your_secure_password"
Environment="DB_NAME=mm"
ExecStart=/var/www/mechanicsaintaugustine.com/backend/bin/mechanic-api
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF

# Start the service
sudo systemctl daemon-reload
sudo systemctl enable mechanic-api
sudo systemctl start mechanic-api
```

## Web Server Configuration

### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName mechanicsaintaugustine.com
    ServerAlias www.mechanicsaintaugustine.com

    DocumentRoot /var/www/mechanicsaintaugustine.com

    <Directory /var/www/mechanicsaintaugustine.com>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Set environment variables
    SetEnv DB_HOST localhost
    SetEnv DB_USERNAME mechanic_user
    SetEnv DB_PASSWORD your_secure_password
    SetEnv DB_NAME mm

    # Load from .env file
    PassEnv TWILIO_ACCOUNT_SID
    PassEnv TWILIO_AUTH_TOKEN
    PassEnv TWILIO_SMS_FROM
    PassEnv OPENAI_API_KEY
    PassEnv CRM_USERNAME
    PassEnv CRM_PASSWORD
    PassEnv CRM_API_KEY

    ErrorLog ${APACHE_LOG_DIR}/mechanic-error.log
    CustomLog ${APACHE_LOG_DIR}/mechanic-access.log combined

    # Redirect HTTP to HTTPS
    RewriteEngine on
    RewriteCond %{SERVER_NAME} =mechanicsaintaugustine.com [OR]
    RewriteCond %{SERVER_NAME} =www.mechanicsaintaugustine.com
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>

<VirtualHost *:443>
    ServerName mechanicsaintaugustine.com
    ServerAlias www.mechanicsaintaugustine.com

    DocumentRoot /var/www/mechanicsaintaugustine.com

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/mechanicsaintaugustine.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/mechanicsaintaugustine.com/privkey.pem

    # ... (same directory and environment config as above)
</VirtualHost>
```

### Nginx Configuration

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name mechanicsaintaugustine.com www.mechanicsaintaugustine.com;

    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name mechanicsaintaugustine.com www.mechanicsaintaugustine.com;

    root /var/www/mechanicsaintaugustine.com;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/mechanicsaintaugustine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mechanicsaintaugustine.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;

        # Pass environment variables
        fastcgi_param DB_HOST localhost;
        fastcgi_param DB_USERNAME mechanic_user;
        fastcgi_param DB_PASSWORD your_secure_password;
        fastcgi_param DB_NAME mm;
        fastcgi_param TWILIO_ACCOUNT_SID $TWILIO_ACCOUNT_SID;
        fastcgi_param TWILIO_AUTH_TOKEN $TWILIO_AUTH_TOKEN;
        fastcgi_param OPENAI_API_KEY $OPENAI_API_KEY;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    access_log /var/log/nginx/mechanic-access.log;
    error_log /var/log/nginx/mechanic-error.log;
}
```

## SSL Certificate Setup (Let's Encrypt)

```bash
# Install certbot
sudo apt install certbot python3-certbot-apache  # For Apache
# OR
sudo apt install certbot python3-certbot-nginx   # For Nginx

# Obtain certificate
sudo certbot --apache -d mechanicsaintaugustine.com -d www.mechanicsaintaugustine.com
# OR
sudo certbot --nginx -d mechanicsaintaugustine.com -d www.mechanicsaintaugustine.com

# Auto-renewal is configured automatically
# Test renewal:
sudo certbot renew --dry-run
```

## Environment Variables Reference

### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `DB_HOST` | Database host | `localhost` |
| `DB_USERNAME` | Database user | `mechanic_user` |
| `DB_PASSWORD` | Database password | `secure_password_here` |
| `DB_NAME` | Main database name | `mm` |
| `TWILIO_ACCOUNT_SID` | Twilio Account SID | `ACxxxxx...` |
| `TWILIO_AUTH_TOKEN` | Twilio Auth Token | `your_token` |
| `TWILIO_SMS_FROM` | Twilio phone number | `+1234567890` |
| `OPENAI_API_KEY` | OpenAI API key | `sk-xxxxx...` |
| `CRM_USERNAME` | CRM username | `admin` |
| `CRM_PASSWORD` | CRM password | `crm_password` |
| `CRM_API_KEY` | CRM API key | `generated_key` |

### Optional Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `TWILIO_FORWARD_TO` | Forward calls to this number | From config |
| `RATING_DB_HOST` | Rating DB host | Same as `DB_HOST` |
| `APP_ENV` | Environment (dev/production) | `production` |
| `APP_DEBUG` | Enable debug mode | `false` |

## Security Checklist

- [ ] All `.env` files are in `.gitignore`
- [ ] Database credentials are strong and unique
- [ ] SSL certificate is installed and auto-renewing
- [ ] File permissions are set correctly (755/644)
- [ ] PHP `display_errors` is OFF in production
- [ ] Twilio webhook URLs use HTTPS
- [ ] API keys are rotated regularly
- [ ] Backup system is configured
- [ ] Error logs are monitored
- [ ] Web server security headers are configured

## Post-Deployment Testing

```bash
# Test database connection
php -r "new PDO('mysql:host=localhost;dbname=mm', 'mechanic_user', 'password');"

# Test PHP configuration
php -v
php -m | grep -E '(mysqli|pdo|curl)'

# Test Twilio webhooks (use Twilio Console test feature)

# Test quote submission
curl -X POST https://yourdomain.com/quote/quote_intake_handler.php \
  -d "first_name=Test&last_name=User&phone=1234567890&email=test@example.com"

# Check health endpoint (if implemented)
curl https://yourdomain.com/health.php
```

## Monitoring & Maintenance

### Log Files to Monitor

```bash
# Apache logs
tail -f /var/log/apache2/mechanic-error.log
tail -f /var/log/apache2/mechanic-access.log

# Nginx logs
tail -f /var/log/nginx/mechanic-error.log
tail -f /var/log/nginx/mechanic-access.log

# PHP errors (if logged separately)
tail -f /var/log/php/error.log

# Application logs
tail -f api/quote_intake.log
```

### Backup Strategy

```bash
# Database backup script
#!/bin/bash
BACKUP_DIR="/backups/$(date +%Y%m%d)"
mkdir -p $BACKUP_DIR

mysqldump -u mechanic_user -p mm > $BACKUP_DIR/mm.sql
mysqldump -u mechanic_user -p rating > $BACKUP_DIR/rating.sql

# Keep last 30 days
find /backups -type d -mtime +30 -exec rm -rf {} \;
```

### Update Procedure

```bash
# 1. Backup current installation
tar -czf backup-$(date +%Y%m%d).tar.gz .

# 2. Pull latest code
git pull origin main

# 3. Run any database migrations (if applicable)
# php migrations/run.php

# 4. Clear caches (if applicable)
# php artisan cache:clear

# 5. Restart services
sudo systemctl restart apache2  # or nginx
sudo systemctl restart mechanic-api  # if using Go backend
```

## Troubleshooting

### Common Issues

**1. Database Connection Errors**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Check credentials
mysql -u mechanic_user -p

# Check PHP can connect
php -r "new PDO('mysql:host=localhost;dbname=mm', 'user', 'pass');"
```

**2. Twilio Webhooks Not Working**
- Ensure webhooks use HTTPS (not HTTP)
- Check firewall allows incoming traffic on port 443
- Verify webhook URLs in Twilio Console
- Check PHP error logs

**3. OpenAI Transcription Failing**
- Verify `OPENAI_API_KEY` is set correctly
- Check API key has credits
- Verify `curl` extension is enabled in PHP

**4. File Permission Issues**
```bash
# Reset permissions
sudo chown -R www-data:www-data /var/www/mechanicsaintaugustine.com
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;
```

## Support

For issues or questions:
- Check logs first: Apache/Nginx error logs, PHP error logs
- Review Twilio webhook logs in Twilio Console
- Check database connectivity and query logs
- Review application-specific logs in `api/` directory

## Security Contact

Report security vulnerabilities to: [your-security-email@example.com]
