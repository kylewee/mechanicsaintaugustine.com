# Mechanic Saint Augustine

A comprehensive mobile mechanic service platform with quote management, voice recording, and CRM integration.

## Features

- **Quote System**: Online quote intake with SMS/email notifications
- **Voice System**: Call recording with AI transcription (OpenAI Whisper)
- **CRM Integration**: Rukovoditel CRM for lead management
- **Admin Tools**: Dispatch, leads approval, parts orders
- **Go Backend**: Modern REST API with clean architecture
- **Security**: Input validation, SQL injection protection, secure authentication
- **Monitoring**: Health check endpoints, structured logging, error tracking

## Tech Stack

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP 7.4+ (legacy), Go 1.19+ (modern API)
- **Database**: MySQL 5.7+/MariaDB 10.3+, PostgreSQL
- **Integrations**: Twilio (Voice/SMS), OpenAI (Transcription)
- **Security**: PDO prepared statements, input sanitization, CSRF protection

## Quick Start

### 1. Clone Repository

```bash
git clone <your-repo-url>
cd mechanicsaintaugustine.com
```

### 2. Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Edit .env with your credentials
nano .env
```

### 3. Set Up Database

```bash
mysql -u root -p <<EOF
CREATE DATABASE mm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE rating CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mechanic_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON mm.* TO 'mechanic_user'@'localhost';
GRANT ALL PRIVILEGES ON rating.* TO 'mechanic_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 4. Configure Web Server

Set environment variables in your web server configuration (Apache/Nginx).
See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed instructions.

### 5. Test Installation

```bash
# Health check
curl https://yourdomain.com/health.php

# Should return: {"status":"healthy", ...}
```

## Project Structure

```
.
├── api/                    # API endpoints (quote intake, SMS)
├── quote/                  # Quote system handlers
├── voice/                  # Voice recording & transcription
├── admin/                  # Admin dashboard (dispatch, leads)
├── Mobile-mechanic/        # Legacy customer portal
├── crm/                    # Rukovoditel CRM system
├── backend/                # Go REST API (modern)
├── lib/                    # Shared PHP libraries
│   ├── database/          # Database connection utilities
│   ├── services/          # Business logic services
│   └── utils/             # Helper utilities (validation, logging)
├── docs/                   # Technical documentation
├── health.php             # Health check endpoint
├── .env.example           # Environment template
└── DEPLOYMENT.md          # Deployment guide
```

## Core Components

### Shared Libraries (`/lib/`)

**New in this release**: Consolidated, secure, reusable code

- **Database.php**: Unified PDO-based database connections
  - Single source of truth for all DB access
  - SQL injection protection via prepared statements
  - Support for main, rating, and CRM databases

- **InputValidator.php**: Input sanitization and validation
  - XSS protection
  - Email, phone, URL validation
  - CSRF token generation and validation

- **PhoneNormalizer.php**: Phone number handling
  - E.164 format normalization
  - US/international phone support

- **Logger.php**: Structured logging
  - DEBUG, INFO, WARNING, ERROR, CRITICAL levels
  - Request logging, API call tracking
  - Automatic log rotation

### Health Monitoring

```bash
# Check system health
curl https://yourdomain.com/health.php
```

Returns:
- Database connectivity (main, rating, CRM)
- Environment variable configuration
- File system permissions
- PHP version and extensions

### Security Features

✅ Input validation on all user inputs
✅ SQL injection protection (PDO prepared statements)
✅ XSS protection (output escaping)
✅ CSRF token support
✅ Secure password handling
✅ Environment-based configuration (no hardcoded secrets)
✅ HTTPS enforcement
✅ Rate limiting ready (via web server)

## API Endpoints

### Quote System

- `POST /quote/quote_intake_handler.php` - Submit quote request
- `POST /quote/status_callback.php` - Twilio status updates

### Voice System

- `POST /voice/incoming.php` - Handle incoming calls
- `POST /voice/recording_callback.php` - Process recordings

### Health & Monitoring

- `GET /health.php` - System health check

## Environment Variables

Required environment variables (see `.env.example`):

```bash
# Database
DB_HOST=localhost
DB_USERNAME=mechanic_user
DB_PASSWORD=your_password
DB_NAME=mm

# Twilio
TWILIO_ACCOUNT_SID=ACxxxxx...
TWILIO_AUTH_TOKEN=your_token
TWILIO_SMS_FROM=+1234567890

# OpenAI
OPENAI_API_KEY=sk-xxxxx...

# CRM
CRM_USERNAME=admin
CRM_PASSWORD=crm_password
CRM_API_KEY=api_key
```

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for complete deployment instructions including:

- Server requirements
- Web server configuration (Apache/Nginx)
- SSL certificate setup
- Twilio webhook configuration
- Production security checklist
- Monitoring and maintenance

## Development

### Using Shared Libraries

```php
<?php
// Load autoloader
require_once __DIR__ . '/lib/autoload.php';

// Database access
$db = Database::getInstance('main');
$users = $db->fetchAll('SELECT * FROM users WHERE active = ?', [1]);

// Input validation
$email = InputValidator::sanitizeEmail($_POST['email']);
$phone = PhoneNormalizer::normalize($_POST['phone']);

// Logging
Logger::info('User registered', ['email' => $email], 'auth');
```

### Running Tests

```bash
# PHP tests
cd backend
go test ./...

# See TESTING_GUIDE.md for comprehensive testing instructions
```

## Monitoring & Logs

### Log Files

```bash
# Application logs (categorized by component)
tail -f logs/app-YYYY-MM-DD.log
tail -f logs/requests-YYYY-MM-DD.log
tail -f logs/api-YYYY-MM-DD.log
tail -f logs/database-YYYY-MM-DD.log

# Web server logs
tail -f /var/log/apache2/mechanic-error.log
tail -f /var/log/nginx/mechanic-error.log
```

### Health Checks

Set up automated health checks:

```bash
# Cron job example (every 5 minutes)
*/5 * * * * curl -sf https://yourdomain.com/health.php || echo "Health check failed" | mail -s "Alert" admin@example.com
```

## Troubleshooting

### Database Connection Issues

```bash
# Test database connection
php -r "new PDO('mysql:host=localhost;dbname=mm', 'user', 'pass');"
```

### Twilio Webhook Issues

- Ensure webhooks use HTTPS (not HTTP)
- Verify webhook URLs in Twilio Console
- Check web server logs for incoming requests

### Permission Issues

```bash
# Fix file permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 logs api quote voice
```

## Production Checklist

Before going live:

- [ ] All environment variables configured
- [ ] Database credentials are strong and unique
- [ ] SSL certificate installed and working
- [ ] Health check endpoint responding correctly
- [ ] Twilio webhooks configured and tested
- [ ] File permissions set correctly
- [ ] Logs directory is writable
- [ ] Backup system configured
- [ ] Monitoring/alerting set up
- [ ] Error logs being reviewed regularly

## Security

Report security vulnerabilities to: [your-security-email]

## Documentation

- [DEPLOYMENT.md](DEPLOYMENT.md) - Complete deployment guide
- [TESTING_GUIDE.md](TESTING_GUIDE.md) - Testing procedures
- [docs/](docs/) - Technical documentation

## License

[Your License]
