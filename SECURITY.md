# Security Documentation

This document outlines security measures implemented in the Mechanic Saint Augustine platform and provides guidance for maintaining security.

## Security Improvements Implemented

### 1. Database Security

#### Prepared Statements (SQL Injection Prevention)
All database queries now use prepared statements with parameterized queries:

**Files Updated:**
- `Mobile-mechanic/login.php` - User authentication
- `Mobile-mechanic/register.php` - Customer registration
- `Mobile-mechanic/mregister.php` - Mechanic registration

**Before (Vulnerable):**
```php
$result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
```

**After (Secure):**
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
```

#### Database Connection Security
- All database credentials moved to environment variables
- No hardcoded passwords in source code
- Connection errors no longer expose sensitive details to users
- UTF-8MB4 charset enforced to prevent encoding attacks

**Files Updated:**
- `Mobile-mechanic/connection.php`
- `Mobile-mechanic/database_connection.php`
- `crm/config/database.php`

### 2. Password Security

#### Modern Password Hashing
Replaced insecure MD5 hashing with PHP's `password_hash()` function using bcrypt:

**Before (Insecure - MD5):**
```php
$password = md5($_POST['password']);
```

**After (Secure - Bcrypt):**
```php
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
```

**Backward Compatibility:**
The login system supports both legacy MD5 and new bcrypt hashes during migration:
```php
if (strlen($stored_hash) === 32) {
    // Legacy MD5 hash
    $valid = (md5($password) === $stored_hash);
} else {
    // Modern bcrypt hash
    $valid = password_verify($password, $stored_hash);
}
```

**Password Requirements:**
- Minimum 8 characters
- Passwords verified during registration
- Password confirmation required

### 3. Input Validation & Sanitization

#### Email Validation
```php
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Reject invalid email
}
```

#### Phone Number Sanitization
```php
$phone = preg_replace('/[^0-9+\-\s()]/', '', $_POST['phone']);
```

#### Numeric Input Sanitization
```php
$pincode = preg_replace('/[^0-9]/', '', $_POST['pincode']);
$aadhar = preg_replace('/[^0-9]/', '', $_POST['aadhar']);
```

#### String Sanitization
```php
$name = trim($_POST['name']);
$address = trim($_POST['address']);
```

### 4. Environment Variable Security

All sensitive configuration moved to environment variables:

**Database Credentials:**
- `MM_DB_HOST`, `MM_DB_NAME`, `MM_DB_USER`, `MM_DB_PASSWORD`
- `CRM_DB_HOST`, `CRM_DB_NAME`, `CRM_DB_USER`, `CRM_DB_PASSWORD`

**API Credentials:**
- `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`
- `OPENAI_API_KEY`
- `CRM_API_URL`, `CRM_USERNAME`, `CRM_PASSWORD`

**Security Tokens:**
- `JWT_SECRET`
- `VOICE_RECORDINGS_TOKEN`
- `QUOTE_WORKFLOW_ADMIN_TOKEN`

**Configuration:**
See `.env.example` for complete list of required environment variables.

### 5. Error Handling

- Database errors logged server-side, not exposed to users
- Generic error messages shown to users
- Detailed errors written to server logs only

### 6. File Security

**Removed:**
- Backup files (`*.backup`, `*.bak`)
- Files with hardcoded credentials
- Merge conflict markers
- Deprecated code

**Protected via .gitignore:**
- `.env` and all environment files
- `*.backup*`, `*.bak`, `*.deprecated`
- Logs (`*.log`)
- Temporary files

## Security Checklist for Deployment

### Before Production Deployment:

- [ ] **Change all default passwords and tokens**
  ```bash
  openssl rand -hex 32  # Generate secure random tokens
  ```

- [ ] **Set strong database passwords**
  - Never use 'root' with no password
  - Use different credentials for each environment
  - Grant minimum required privileges

- [ ] **Enable HTTPS everywhere**
  - Use Caddy's automatic HTTPS
  - Force HTTPS redirects
  - Set secure cookie flags

- [ ] **Migrate legacy MD5 passwords** (see below)

- [ ] **Set appropriate file permissions**
  ```bash
  chmod 600 .env
  chmod 644 *.php
  chmod 755 directories
  ```

- [ ] **Enable error logging, disable error display**
  ```php
  ini_set('display_errors', 0);
  ini_set('log_errors', 1);
  error_log('/var/log/php_errors.log');
  ```

- [ ] **Configure PHP security settings**
  ```ini
  expose_php = Off
  session.cookie_httponly = On
  session.cookie_secure = On
  session.use_strict_mode = On
  ```

- [ ] **Set up database backups**
  - Automated daily backups
  - Encrypted backup storage
  - Test restore procedures

- [ ] **Review and update CRM permissions**
  - Ensure users have minimum required access
  - Audit user accounts regularly

- [ ] **Set up monitoring**
  - Failed login attempts
  - SQL errors
  - Unusual traffic patterns

## Migrating Legacy MD5 Passwords

Existing passwords hashed with MD5 need to be migrated to bcrypt. Two approaches:

### Option 1: Gradual Migration (Recommended)
Users' passwords are automatically upgraded on their next successful login:

```php
// Add to login.php after successful MD5 verification
if (strlen($data['password']) === 32) {
    // User logged in with MD5 hash - upgrade to bcrypt
    $new_hash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $new_hash, $user_id);
    $update->execute();
}
```

### Option 2: Force Password Reset
Require all users to reset their passwords:

```sql
-- Add migration flag to user tables
ALTER TABLE customer_reg ADD COLUMN password_migrated BOOLEAN DEFAULT FALSE;
ALTER TABLE mechanic_reg ADD COLUMN password_migrated BOOLEAN DEFAULT FALSE;
ALTER TABLE admin ADD COLUMN password_migrated BOOLEAN DEFAULT FALSE;
```

Then force password reset on next login for unmigrated accounts.

## Security Incident Response

If a security incident occurs:

1. **Immediately:**
   - Change all passwords and API keys
   - Revoke compromised credentials
   - Review access logs

2. **Investigation:**
   - Identify scope of breach
   - Check for unauthorized access
   - Review recent database changes

3. **Remediation:**
   - Patch vulnerabilities
   - Update affected users
   - Document incident

4. **Prevention:**
   - Update security measures
   - Conduct security audit
   - Train team members

## Reporting Security Issues

If you discover a security vulnerability:

1. **Do NOT** open a public GitHub issue
2. Email security concerns to: sodjacksonville@gmail.com
3. Include:
   - Description of vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if known)

## Additional Security Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [MySQL Security Guidelines](https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html)
- [Twilio Security Best Practices](https://www.twilio.com/docs/security)

## Regular Security Maintenance

**Monthly:**
- Review user access logs
- Check for failed login attempts
- Update dependencies
- Review file permissions

**Quarterly:**
- Security audit of codebase
- Password rotation for service accounts
- Review and update security policies
- Test backup restoration

**Annually:**
- Full penetration testing
- Third-party security audit
- Review and update incident response plan
- Security training for team

---

**Last Updated:** 2025-11-22
**Next Security Review:** 2026-02-22
