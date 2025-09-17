# Gravitycar Framework - Production Deployment Guide

## Overview
This guide provides step-by-step instructions for deploying Gravitycar Framework to an Ubuntu 24 production environment with Apache 2.4 and MySQL 8, using standard ports 80/443 only.

## Prerequisites
- Ubuntu 24 server with Apache 2.4 and MySQL 8
- Domain name with subdomains already configured by hosting provider:
  - `api.gravitycar.com` (for backend API)
  - `react.gravitycar.com` (for frontend React app)
- SSH access to the server
- Ability to install Node.js and npm

**Note**: The subdomains `api.gravitycar.com` and `react.gravitycar.com` are already created and configured by your hosting provider, so you don't need to set up DNS records - just upload files to the correct directories:
- **Frontend files** go to: `~/public_html/react.gravitycar.com/`
- **Backend files** go to: `~/public_html/api.gravitycar.com/`

---

## Part 1: React Frontend Setup for Production

### 1.1 Build React Application for Production

Since your hosting provider doesn't support custom ports, we'll serve the frontend and backend on separate subdomains through Apache on standard ports. The subdomains `api.gravitycar.com` and `react.gravitycar.com` are already created and configured by your hosting provider.

**Step 1: Create production environment file**
```bash
cd gravitycar-frontend
nano .env.production
```

**Add the following content:**
```bash
# Production API Base URL (backend subdomain)
VITE_API_BASE_URL=https://api.gravitycar.com
```

**Step 2: Install dependencies and build**
```bash
# Install ALL dependencies (including dev dependencies needed for building)
npm ci

# Build for production
npm run build
```

**Step 3: Verify build output**
```bash
# Check file permissions (on server)
ls -la ~/public_html/api.gravitycar.com/
```

---

## Production Packages Ready ✅

Both production packages have been successfully created and are ready for deployment:

- **frontend-production.tar.gz** (127 KB) - Contains built React application  
- **backend-production.tar.gz** (325 KB) - Contains PHP backend with dependencies

### Package Contents Verification
```bash
# Verify frontend package contents
tar -tzf frontend-production.tar.gz | head -10

# Verify backend package contents  
tar -tzf backend-production.tar.gz | head -20
```

### Deployment Status
- ✅ TypeScript compilation issues resolved
- ✅ Frontend build successful (141 modules transformed)
- ✅ Production packages created
- ✅ Backend health check confirmed
- 🚀 **Ready for production deployment**

### Next Steps
1. Transfer both `.tar.gz` files to your hosting provider
2. Follow the deployment steps above to extract and configure
3. Set up SSL certificates for both subdomains (likely automatic)
4. Configure domain DNS to point to your hosting provider (if not done)

**You're now ready to deploy to production!** 🎉

# Verify API URL is embedded correctly
grep -r "api.gravitycar.com" dist/ || echo "API URL configured correctly"
```

### 1.2 Apache Configuration for React

Since your hosting provider has already configured the subdomains to serve from specific directories, you likely won't need to create custom Apache virtual hosts. The hosting provider's setup should automatically serve:
- `react.gravitycar.com` from `~/public_html/react.gravitycar.com/`
- `api.gravitycar.com` from `~/public_html/api.gravitycar.com/`

However, you may need to ensure proper `.htaccess` configuration for each directory:

**Frontend .htaccess (~/public_html/react.gravitycar.com/.htaccess):**
```apache
# React Router support - redirect all requests to index.html
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
```

**Backend .htaccess (~/public_html/api.gravitycar.com/.htaccess):**
```apache
RewriteEngine On

# Enable error logging for debugging
# RewriteLog logs/rewrite.log
# RewriteLogLevel 3

# Allow direct access to specific files
RewriteCond %{REQUEST_URI} ^/(index\.html|index\.php|phpinfo\.php)$ [NC]
RewriteRule ^ - [L]

# REST API Routes
# Handle requests to REST endpoints and redirect to rest_api.php
# Patterns: /Users, /Movies, /Books, /Articles, etc.
# Support both singular and plural model names
# Support ID parameters: /Users/123, /Movies/abc-def

# Capture the full path and set environment variables
RewriteCond %{REQUEST_URI} ^/([A-Za-z][A-Za-z0-9_]*)(/.+)?/?$ [NC]
RewriteRule ^([A-Za-z][A-Za-z0-9_]*)(/.+)?/?$ rest_api.php [E=ORIGINAL_PATH:%{REQUEST_URI},E=MODEL_NAME:$1,E=PATH_INFO:$2,QSA,L]

# Ensure Authorization header is passed through to PHP
# This is required for JWT authentication to work properly
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]

# Alternative fallback for exact model name matches
# This ensures clean URLs work for common REST patterns
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/[A-Za-z][A-Za-z0-9_]*
RewriteRule ^(.*)$ rest_api.php [E=ORIGINAL_PATH:%{REQUEST_URI},QSA,L]

# CORS headers for API access
Header always set Access-Control-Allow-Origin "https://react.gravitycar.com"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
Header always set Access-Control-Allow-Credentials "true"

# Security headers
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"

# Block access to sensitive files
<Files ~ "^\.">
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "\.(log|sql|md|sh)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Don't display errors in output
php_flag display_errors off
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
Header always set Access-Control-Allow-Credentials "true"

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"

# Block access to sensitive files
<Files ~ "^\.">
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "\.(log|sql|md|sh)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

## Part 2: Environment Configuration

### 2.1 Backend Environment Variables

**Create the main .env file:**
```bash
nano .env
```

**Add production configuration:**
```bash
# Application Environment
APP_ENVIRONMENT=production
APP_DEBUG=false

# Application URLs (separate subdomains)
BACKEND_URL=https://api.gravitycar.com
FRONTEND_URL=https://react.gravitycar.com

# Database Configuration
DB_DRIVER=pdo_mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=gravitycar_production
DB_USER=gravitycar_user
DB_PASSWORD=YOUR_SECURE_DB_PASSWORD
DB_CHARSET=utf8mb4

# External API Keys
TMDB_API_KEY=19a9f496
GOOGLE_BOOKS_API_KEY=your_google_books_api_key

# Google OAuth Configuration
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://api.gravitycar.com/auth/google/callback

# Security Keys (generate random 32-character strings)
APP_KEY=your_32_character_random_string_here
JWT_SECRET=another_32_char_random_string_here
```

### 2.2 Frontend Environment Variables

**Create production frontend environment:**
```bash
cd gravitycar-frontend
nano .env.production
```

**Content:**
```bash
# API Base URL for production
VITE_API_BASE_URL=https://api.gravitycar.com
```

### 2.3 Production Config Override

**Create a production-specific config override:**
```bash
mkdir -p config
nano config/production.php
```

**Content:**
```php
<?php
return [
    'app' => [
        'debug' => false,
        'environment' => 'production',
        'backend_url' => $_ENV['BACKEND_URL'] ?? 'https://api.gravitycar.com',
        'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://react.gravitycar.com',
    ],
    'logging' => [
        'level' => 'warning',
        'file' => 'logs/gravitycar.log',
        'daily_rotation' => true,
        'max_files' => 30,
    ],
    'health' => [
        'expose_detailed_errors' => false,
        'enable_debug_info' => false
    ],
    'api' => [
        'authentication_required' => false,  // Adjust based on your security needs
        'allowed_origins' => ['https://react.gravitycar.com'],
        'enable_debug_info' => false,
    ]
];
```

---

## Part 3: Files and Directories to Exclude from Upload

### 3.1 Development-Only Files/Directories
**DO NOT upload these to production:**

```bash
# Development tools and configs
/.vscode/
/.git/
/.github/
/docs/
/examples/
/Tests/                    # NEVER upload tests to production
/tasks_scripts/

# Development dependencies and caches
/vendor/                   # Will be rebuilt on server
/node_modules/            # Will be rebuilt on server
/gravitycar-frontend/node_modules/
/.phpunit.result.cache
/.vscode/

# Logs and temporary files
/logs/                    # Will be created on server
/cache/                   # Will be created on server
/tmp/
*.log

# Development configuration
/.env.development
/.env.local
/config/development.php
.devdbrc

# Development scripts
restart-apache.sh
restart-frontend.sh
/gravitycar-frontend/scripts/

# Documentation and notes
CLAUDE.md
/docs/implementation_notes/
/docs/implementation_plans/
README-DEV-SERVER.md

# IDE and editor files
*.idea/
*.xml
*.swp
*.swo
*~

# Build artifacts (will be rebuilt on server)
/gravitycar-frontend/dist/
```

### 3.2 Production Upload Structure

**Frontend Deployment to ~/public_html/react.gravitycar.com/:**
```bash
# Upload the BUILT React files (after running npm run build)
index.html                 # From gravitycar-frontend/dist/
assets/                   # From gravitycar-frontend/dist/assets/
vite.svg                  # From gravitycar-frontend/dist/
.htaccess                 # Frontend .htaccess (see section 1.2)
# DO NOT upload the gravitycar-frontend/ folder itself
```

**Backend Deployment to ~/public_html/api.gravitycar.com/:**
```bash
# Core application files
src/                      # Complete src/ directory
composer.json
config.php
config/production.php     # If created
setup.php
rest_api.php
index.html               # Existing index.html
.htaccess                # Backend .htaccess (see section 1.2)

# Environment files
.env                     # Production version only

# Directories that will be created
logs/                    # Create empty, will be populated
cache/                   # Create empty, will be populated
vendor/                  # Will be created by composer install
```

### 3.3 TAR Commands for Easy File Collection

**Create Frontend Package:**
```bash
# First, build the React application
cd gravitycar-frontend
npm ci
npm run build

# Create tar file with all frontend files
cd dist
tar -czf ../../frontend-production.tar.gz *

# This creates frontend-production.tar.gz containing:
# - index.html
# - assets/ directory
# - vite.svg
# - any other build artifacts
```

**Create Backend Package:**
```bash
# From the project root directory
tar -czf backend-production.tar.gz \
  src/ \
  composer.json \
  config.php \
  setup.php \
  rest_api.php \
  index.html \
  --exclude='*.log' \
  --exclude='cache/*' \
  --exclude='logs/*' \
  --exclude='vendor/*' \
  --exclude='Tests/*' \
  --exclude='.git*' \
  --exclude='.vscode*' \
  --exclude='docs/*' \
  --exclude='examples/*' \
  --exclude='tasks_scripts/*'

# Note: .env and config/production.php should be created directly on server
# for security reasons (don't include sensitive data in tar files)
```

**Verify Package Contents:**
```bash
# Check frontend package contents
tar -tzf frontend-production.tar.gz

# Check backend package contents  
tar -tzf backend-production.tar.gz
```

---

## Part 4: Step-by-Step Deployment Process

### 4.1 Server Preparation

**Step 1: Install required packages**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Node.js and npm (latest LTS)
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt-get install -y nodejs

# Install PHP packages if not already present
sudo apt install -y php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-zip php8.1-gd

# Install Composer if not present
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Enable Apache modules
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

### 4.2 Application Deployment

**Step 1: Prepare deployment packages locally**
```bash
# Build React frontend and create package
cd gravitycar-frontend
npm ci
npm run build
cd dist
tar -czf ../../frontend-production.tar.gz *
cd ../..

# Create backend package
tar -czf backend-production.tar.gz \
  src/ \
  composer.json \
  config.php \
  setup.php \
  rest_api.php \
  index.html \
  --exclude='*.log' \
  --exclude='cache/*' \
  --exclude='logs/*' \
  --exclude='vendor/*' \
  --exclude='Tests/*' \
  --exclude='.git*' \
  --exclude='.vscode*' \
  --exclude='docs/*' \
  --exclude='examples/*' \
  --exclude='tasks_scripts/*'

# Verify packages
echo "Frontend package contents:"
tar -tzf frontend-production.tar.gz
echo "Backend package contents:"
tar -tzf backend-production.tar.gz
```

**Step 2: Upload and extract packages**
```bash
# Upload frontend-production.tar.gz to server
# Upload backend-production.tar.gz to server

# SSH into server and extract frontend
cd ~/public_html/react.gravitycar.com/
tar -xzf ~/frontend-production.tar.gz

# Extract backend
cd ~/public_html/api.gravitycar.com/
tar -xzf ~/backend-production.tar.gz

# Create necessary directories
mkdir -p logs cache config
chmod 755 logs cache
```

**Step 3: Create configuration files on server**
```bash
# Create backend .env file (in ~/public_html/api.gravitycar.com/)
nano .env
# Add production configuration from section 2.1

# Create backend .htaccess file
nano .htaccess
# Add backend .htaccess content from section 1.2

# Create frontend .htaccess file (in ~/public_html/react.gravitycar.com/)
cd ~/public_html/react.gravitycar.com/
nano .htaccess
# Add frontend .htaccess content from section 1.2

# Create production config override (optional)
cd ~/public_html/api.gravitycar.com/
mkdir -p config
nano config/production.php
# Add production config from section 2.3
```

**Step 4: Install PHP dependencies on server**
```bash
# SSH into your server and navigate to the backend directory
cd ~/public_html/api.gravitycar.com/
composer install --no-dev --optimize-autoloader --no-interaction
```

**Step 5: Set file permissions**
```bash
# In the backend directory (~/public_html/api.gravitycar.com/)
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Secure sensitive files
chmod 600 .env
chmod -R 750 logs/ cache/
```

### 4.3 Database Setup

**Step 1: Create production database**
```bash
mysql -u root -p
```

```sql
CREATE DATABASE gravitycar_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gravitycar_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_DB_PASSWORD';
GRANT ALL PRIVILEGES ON gravitycar_production.* TO 'gravitycar_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Step 2: Run setup script**
```bash
# SSH to server and navigate to backend directory
cd ~/public_html/api.gravitycar.com/
php setup.php
```

### 4.4 Configure Apache

**Note**: Since your hosting provider has pre-configured subdomain directories, you likely won't need to create custom Apache virtual hosts. However, ensure the `.htaccess` files are properly configured as shown in section 1.2.

**Step 1: Verify .htaccess files are in place**
```bash
# Check frontend .htaccess
ls -la ~/public_html/react.gravitycar.com/.htaccess

# Check backend .htaccess  
ls -la ~/public_html/api.gravitycar.com/.htaccess
```

**Step 2: Test Apache configuration (if you have access)**
```bash
# Test Apache syntax (if you have sudo access)
sudo apache2ctl -t

# If you don't have sudo access, just test the URLs in the next section
```

### 4.5 SSL Setup (if not already configured)

**Note**: Since your subdomains are pre-configured by the hosting provider, SSL certificates should already be in place and managed automatically. Test the HTTPS URLs to verify SSL is working correctly.

---

## Part 5: Testing and Verification

### 5.1 Test Backend API

```bash
# Test health endpoint
curl https://api.gravitycar.com/health

# Test a model endpoint
curl https://api.gravitycar.com/Users?limit=1

# Test OpenAPI documentation
curl https://api.gravitycar.com/docs/openapi.json
```

### 5.2 Test Frontend

1. Open browser to `https://react.gravitycar.com`
2. Check browser developer console for any errors
3. Verify API calls are going to `https://api.gravitycar.com` (not localhost)
4. Test navigation and CRUD operations

### 5.3 Monitor Logs

```bash
# Application logs (on server)
tail -f ~/public_html/api.gravitycar.com/logs/gravitycar.log

# Apache logs (if you have access)
sudo tail -f /var/log/apache2/error.log
sudo tail -f /var/log/apache2/access.log
```

---

## Part 6: URL Structure in Production

Your production URLs will be:

- **Frontend**: `https://react.gravitycar.com/`
- **Backend API**: `https://api.gravitycar.com/` (root level)
- **Health Check**: `https://api.gravitycar.com/health`
- **Users API**: `https://api.gravitycar.com/Users`
- **Movies API**: `https://api.gravitycar.com/Movies`
- **OpenAPI Docs**: `https://api.gravitycar.com/docs/openapi.json`

---

## Part 7: Security Considerations

1. **Environment Variables**: Never commit `.env` files to version control
2. **File Permissions**: Ensure logs and cache directories are not web-accessible
3. **Database**: Use strong passwords and limited privileges
4. **SSL**: Always use HTTPS in production
5. **Updates**: Keep system packages and dependencies updated

---

## Troubleshooting

### Common Issues:

1. **React app shows blank page**: Check browser console, verify build process completed
2. **API calls fail**: Check CORS settings and SSL certificates
3. **Database connection errors**: Verify credentials and database exists
4. **Permission errors**: Check file ownership and permissions
5. **Infinite redirect loops (500 errors)**: Check .htaccess configuration for missing RewriteRule statements

### Debug Commands:

```bash
# Check PHP configuration
php -r "require 'config.php'; var_dump(\$config['app']);"

# Check React build (locally before upload)
grep -r "api.gravitycar.com" gravitycar-frontend/dist/

# Test .htaccess syntax (on server, if you have access)
sudo apache2ctl -t

# Check for infinite redirects - look for this error:
# "Request exceeded the limit of 10 internal redirects due to probable configuration error"
tail -f /var/log/apache2/error.log
```

### .htaccess Redirect Loop Fix

If you see the error "Request exceeded the limit of 10 internal redirects", the issue is likely an incomplete .htaccess file. Make sure your .htaccess has the complete fallback rule:

```apache
# This section MUST be complete - missing RewriteRule causes infinite loops:
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/[A-Za-z][A-Za-z0-9_]*
RewriteRule ^(.*)$ rest_api.php [E=ORIGINAL_PATH:%{REQUEST_URI},QSA,L]
```

Use the complete .htaccess configuration provided in section 1.2 - do not use partial configurations.

# Check Apache configuration (on server, if you have access)
sudo apache2ctl -t

# Check file permissions (on server)
ls -la ~/public_html/api.gravitycar.com/
ls -la ~/public_html/react.gravitycar.com/
```

This guide should get you successfully deployed to production with a clean, secure setup that works within your hosting provider's constraints.