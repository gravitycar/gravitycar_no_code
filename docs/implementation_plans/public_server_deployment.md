# Implementation Plan: Public Server Deployment

## 1. Overview

This plan outlines the steps to deploy the Gravitycar Framework from local development to a public web server, including configuration management, environment variables, OAuth setup, and frontend URL handling.

### Current Architecture
- **Backend**: PHP 8+ with Apache on `localhost:8081`
- **Frontend**: React 18.x with Vite dev server on `localhost:3000`
- **Database**: MySQL with local credentials
- **APIs**: Google Books API, TMDB API, Google OAuth
- **Environment**: Development environment variables in `.env`

### Target Architecture
- **Production Backend**: PHP with Apache/Nginx on public domain
- **Production Frontend**: Built React app served statically
- **Production Database**: MySQL on production server or cloud service
- **Environment Management**: Secure environment variable handling
- **SSL/HTTPS**: Required for OAuth and security

## 2. Hosting Platform Requirements

### Server Technology Stack
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: Version 8.1+ with extensions:
  - `pdo_mysql` - Database connectivity
  - `curl` - API requests
  - `json` - JSON processing
  - `mbstring` - String handling
  - `xml` - XML processing
  - `zip` - Archive handling
  - `gd` or `imagick` - Image processing
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **SSL Certificate**: Required for HTTPS (Let's Encrypt recommended)
- **Composer**: PHP dependency management
- **Node.js**: For building React frontend (build-time only)

### Hosting Provider Recommendations
1. **VPS/Cloud Providers**:
   - DigitalOcean Droplet ($12-25/month)
   - Linode ($10-20/month)
   - AWS EC2 t3.small ($15-25/month)
   - Google Cloud Compute Engine

2. **Managed Hosting**:
   - SiteGround ($15-30/month)
   - A2 Hosting ($10-25/month)
   - InMotion Hosting ($15-35/month)

3. **Requirements**:
   - 2GB+ RAM
   - 25GB+ SSD storage
   - SSH access for deployment
   - Custom domain support
   - SSL certificate support

## 3. Pre-Deployment Tasks

### 3.1 Code Preparation

#### Frontend URL Configuration
**Current Issue**: Hardcoded URLs in React frontend
**Solution**: Environment-based configuration

1. **Create Frontend Environment Configuration**
   ```javascript
   // gravitycar-frontend/src/config/environment.js
   const getApiBaseUrl = () => {
     // Check if we're in development
     if (import.meta.env.DEV) {
       return 'http://localhost:8081';
     }
     
     // Production: Use same origin or environment variable
     return import.meta.env.VITE_API_BASE_URL || window.location.origin;
   };
   
   export const config = {
     apiBaseUrl: getApiBaseUrl(),
     environment: import.meta.env.MODE,
     isDevelopment: import.meta.env.DEV,
     isProduction: import.meta.env.PROD
   };
   ```

2. **Update Frontend Environment Files**
   ```bash
   # gravitycar-frontend/.env.development
   VITE_API_BASE_URL=http://localhost:8081
   
   # gravitycar-frontend/.env.production
   VITE_API_BASE_URL=https://yourdomain.com
   ```

3. **Replace Hardcoded URLs in Components**
   - Search for `localhost:8081` in frontend code
   - Replace with `config.apiBaseUrl`
   - Update API service files to use environment config

#### Backend Configuration Updates

1. **Create Production Config Override**
   ```php
   // config/production.php
   <?php
   return [
       'app' => [
           'debug' => false,
           'environment' => 'production'
       ],
       'logging' => [
           'level' => 'warning',
           'file' => '/var/log/gravitycar/app.log'
       ],
       'google' => [
           'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? 'https://yourdomain.com/auth/google/callback'
       ]
   ];
   ```

2. **Update Main Config to Support Environment Overrides**
   ```php
   // config.php - Add at the end
   $environment = $_ENV['APP_ENVIRONMENT'] ?? 'development';
   
   if ($environment === 'production' && file_exists(__DIR__ . '/config/production.php')) {
       $productionConfig = require __DIR__ . '/config/production.php';
       $config = array_merge_recursive($config, $productionConfig);
   }
   
   return $config;
   ```

### 3.2 Environment Variables Migration

#### Current .env Variables Audit
```bash
# Identify all environment variables used
grep -r "\$_ENV\[" src/
grep -r "getenv(" src/
```

#### Production Environment Variables Setup
```bash
# .env.production (template)
APP_ENVIRONMENT=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_HOST=localhost
DB_DATABASE=gravitycar_prod
DB_USERNAME=gravitycar_user
DB_PASSWORD=secure_random_password

# API Keys
GOOGLE_BOOKS_API_KEY=your_google_books_api_key
TMDB_API_KEY=your_tmdb_api_key
TMDB_API_READ_ACCESS_TOKEN=your_tmdb_read_token

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/auth/google/callback

# Security
APP_KEY=32_character_random_string
JWT_SECRET=another_32_character_random_string
```

### 3.3 Database Preparation

1. **Create Production Database Migration Script**
   ```bash
   # scripts/migrate-to-production.sh
   #!/bin/bash
   
   # Export development data (excluding sensitive data)
   mysqldump --no-data gravitycar_nc > schema.sql
   mysqldump --data-only --ignore-table=gravitycar_nc.users gravitycar_nc > data.sql
   
   # Create production import script
   echo "Production database files created:"
   echo "- schema.sql (structure only)"
   echo "- data.sql (data without users)"
   ```

2. **Sanitize Development Data**
   - Remove test users
   - Clear API keys from database
   - Remove development-specific records

### 3.4 Security Hardening

1. **File Permissions Script**
   ```bash
   # scripts/set-production-permissions.sh
   #!/bin/bash
   
   # Set secure file permissions
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   
   # Secure sensitive directories
   chmod 600 .env*
   chmod -R 750 logs/
   chmod -R 750 cache/
   chmod -R 750 tmp/
   
   # Make scripts executable
   chmod +x scripts/*.sh
   ```

2. **Apache Security Configuration**
   ```apache
   # .htaccess for public directory
   RewriteEngine On
   
   # Redirect to HTTPS
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   
   # Security headers
   Header always set X-Frame-Options DENY
   Header always set X-Content-Type-Options nosniff
   Header always set Referrer-Policy "strict-origin-when-cross-origin"
   Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
   
   # Hide sensitive files
   <Files ~ "^\.">
       Order allow,deny
       Deny from all
   </Files>
   
   <FilesMatch "\.(log|sql|md)$">
       Order allow,deny
       Deny from all
   </FilesMatch>
   ```

## 4. Google Cloud Console OAuth Configuration

### 4.1 Update OAuth Credentials for Production Domain

1. **Access Google Cloud Console**
   - Go to https://console.cloud.google.com/
   - Select your existing project (or create new production project)

2. **Navigate to OAuth Consent Screen**
   - Go to "APIs & Services" > "OAuth consent screen"
   - Update app information:
     - **App name**: Your production app name
     - **User support email**: Your support email
     - **App domain**: `https://yourdomain.com`
     - **Authorized domains**: Add `yourdomain.com`
     - **Developer contact**: Your email

3. **Update OAuth 2.0 Client IDs**
   - Go to "APIs & Services" > "Credentials"
   - Click on your existing OAuth 2.0 Client ID
   - Update **Authorized JavaScript origins**:
     - Add: `https://yourdomain.com`
     - Remove: `http://localhost:3000` (for production)
   - Update **Authorized redirect URIs**:
     - Add: `https://yourdomain.com/auth/google/callback`
     - Remove: `http://localhost:8081/auth/google/callback` (for production)

4. **Create Production-Specific Credentials** (Recommended)
   - Create separate OAuth credentials for production
   - Use different client ID/secret for production vs development
   - Benefits: Better security isolation, easier credential rotation

### 4.2 Google Books API Configuration

1. **Verify API Restrictions**
   - Go to "APIs & Services" > "Credentials"
   - Click on your Google Books API key
   - Under "Application restrictions":
     - Add production domain: `https://yourdomain.com/*`
   - Under "Website restrictions":
     - Add: `yourdomain.com`

2. **Create Production API Key** (Recommended)
   - Create separate API key for production
   - Apply appropriate restrictions for production domain
   - Update production environment variables

### 4.3 TMDB API Configuration

1. **Update API Key Restrictions** (if applicable)
   - Log into TMDB account
   - Go to Settings > API
   - Update any domain restrictions if configured

## 5. Deployment Process

### 5.1 Server Setup

1. **Initial Server Configuration**
   ```bash
   # Update system packages
   sudo apt update && sudo apt upgrade -y
   
   # Install required packages
   sudo apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-curl \
                      php8.1-json php8.1-mbstring php8.1-xml php8.1-zip \
                      php8.1-gd composer nodejs npm git
   
   # Secure MySQL installation
   sudo mysql_secure_installation
   
   # Configure Apache
   sudo a2enmod rewrite ssl headers
   sudo systemctl restart apache2
   ```

2. **Domain and SSL Setup**
   ```bash
   # Install Certbot for Let's Encrypt
   sudo apt install -y certbot python3-certbot-apache
   
   # Configure domain (replace yourdomain.com)
   sudo certbot --apache -d yourdomain.com
   ```

### 5.2 Application Deployment

1. **Clone Repository**
   ```bash
   cd /var/www/
   sudo git clone https://github.com/your-repo/gravitycar.git
   sudo chown -R www-data:www-data gravitycar/
   ```

2. **Install Dependencies**
   ```bash
   cd /var/www/gravitycar
   
   # PHP dependencies
   composer install --no-dev --optimize-autoloader
   
   # Build frontend
   cd gravitycar-frontend
   npm ci
   npm run build
   ```

3. **Environment Configuration**
   ```bash
   # Copy and configure environment variables
   cp .env.example .env.production
   sudo nano .env.production
   
   # Set up environment for Apache
   sudo cp .env.production /etc/environment
   ```

4. **Database Setup**
   ```bash
   # Create production database
   mysql -u root -p
   CREATE DATABASE gravitycar_prod;
   CREATE USER 'gravitycar_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL ON gravitycar_prod.* TO 'gravitycar_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   
   # Import schema and data
   mysql -u gravitycar_user -p gravitycar_prod < schema.sql
   mysql -u gravitycar_user -p gravitycar_prod < data.sql
   ```

5. **Apache Virtual Host Configuration**
   ```apache
   # /etc/apache2/sites-available/gravitycar.conf
   <VirtualHost *:80>
       ServerName yourdomain.com
       Redirect permanent / https://yourdomain.com/
   </VirtualHost>
   
   <VirtualHost *:443>
       ServerName yourdomain.com
       DocumentRoot /var/www/gravitycar/public
       
       SSLEngine on
       SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
       SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
       
       <Directory /var/www/gravitycar/public>
           Options -Indexes +FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       
       # Serve React app for frontend routes
       <Directory /var/www/gravitycar/gravitycar-frontend/dist>
           Options -Indexes
           AllowOverride All
           Require all granted
           
           # Handle React Router
           RewriteEngine On
           RewriteCond %{REQUEST_FILENAME} !-f
           RewriteCond %{REQUEST_FILENAME} !-d
           RewriteRule . /index.html [L]
       </Directory>
       
       # API routes
       Alias /api /var/www/gravitycar/public/api.php
       
       ErrorLog ${APACHE_LOG_DIR}/gravitycar_error.log
       CustomLog ${APACHE_LOG_DIR}/gravitycar_access.log combined
   </VirtualHost>
   ```

6. **Enable Site and Restart Apache**
   ```bash
   sudo a2ensite gravitycar
   sudo a2dissite 000-default
   sudo systemctl restart apache2
   ```

### 5.3 Post-Deployment Tasks

1. **Run Setup Script**
   ```bash
   cd /var/www/gravitycar
   php setup.php
   ```

2. **Test API Endpoints**
   ```bash
   curl https://yourdomain.com/health
   curl https://yourdomain.com/api/health
   ```

3. **Test OAuth Flow**
   - Navigate to `https://yourdomain.com`
   - Attempt Google OAuth login
   - Verify callback handling

4. **Monitor Logs**
   ```bash
   tail -f /var/log/apache2/gravitycar_error.log
   tail -f logs/gravitycar.log
   ```

## 6. Monitoring and Maintenance

### 6.1 Log Management
```bash
# Set up log rotation
sudo nano /etc/logrotate.d/gravitycar

/var/www/gravitycar/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 644 www-data www-data
}
```

### 6.2 Backup Strategy
```bash
# Create backup script
#!/bin/bash
# scripts/backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/gravitycar"

# Database backup
mysqldump -u gravitycar_user -p gravitycar_prod > $BACKUP_DIR/db_$DATE.sql

# File backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/gravitycar \
    --exclude='node_modules' --exclude='vendor' --exclude='logs'

# Clean old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 6.3 Performance Optimization
```bash
# Enable OPcache for PHP
sudo nano /etc/php/8.1/apache2/conf.d/99-opcache.ini

opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.validate_timestamps=0
```

## 7. Security Considerations

### 7.1 Environment Variables Security
- Store sensitive variables in server environment, not in files
- Use tools like Docker secrets or cloud provider secret management
- Regularly rotate API keys and OAuth secrets

### 7.2 Database Security
- Use strong, unique passwords
- Limit database user permissions
- Enable MySQL slow query log for monitoring
- Regular security updates

### 7.3 Application Security
- Keep PHP and dependencies updated
- Enable HTTPS everywhere
- Implement rate limiting for API endpoints
- Monitor for security vulnerabilities

## 8. Rollback Plan

### 8.1 Quick Rollback Procedure
```bash
# 1. Revert to previous git commit
git reset --hard <previous_commit_hash>

# 2. Restore database backup
mysql -u gravitycar_user -p gravitycar_prod < backup_file.sql

# 3. Clear caches
php setup.php --clear-cache-only

# 4. Restart services
sudo systemctl restart apache2
```

### 8.2 DNS Failover
- Keep development server accessible
- Use DNS TTL settings for quick switchover
- Document rollback procedures for team

## 9. Testing Checklist

### 9.1 Pre-Deployment Testing
- [ ] Frontend builds without errors
- [ ] All API endpoints respond correctly
- [ ] Database migrations work
- [ ] OAuth flow completes successfully
- [ ] SSL certificate is valid
- [ ] Environment variables are properly set

### 9.2 Post-Deployment Testing
- [ ] Website loads over HTTPS
- [ ] Google OAuth login works
- [ ] API responses are correct
- [ ] Database connectivity is working
- [ ] Logs are being written properly
- [ ] Email notifications work (if configured)
- [ ] Backup scripts execute successfully

## 10. Estimated Timeline

- **Day 1**: Server setup and basic configuration
- **Day 2**: Application deployment and database setup
- **Day 3**: OAuth configuration and frontend deployment
- **Day 4**: Testing and security hardening
- **Day 5**: Monitoring setup and documentation

**Total Estimated Time**: 5 days for complete deployment and testing

This plan provides a comprehensive roadmap for deploying the Gravitycar Framework to a public server while maintaining security, performance, and reliability standards.
