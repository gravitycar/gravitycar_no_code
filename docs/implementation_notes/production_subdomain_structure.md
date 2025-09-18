# Production Subdomain Structure Documentation

## Overview
The Gravitycar Framework production deployment uses a bifurcated subdomain structure on a single physical server to separate backend and frontend concerns.

## Subdomain Configuration

### Backend API Subdomain
- **URL**: `https://api.gravitycar.com`
- **Directory**: `/home/gravityc/public_html/api.gravitycar.com`
- **Purpose**: PHP backend API, REST endpoints, database operations
- **Key Files**:
  - `index.php` - Main entry point
  - `rest_api.php` - API routing
  - `config.php` - Configuration
  - `setup.php` - Framework setup script
  - `cache/` - Metadata and route cache
  - `logs/` - Application logs

### Frontend React Subdomain  
- **URL**: `https://react.gravitycar.com`
- **Directory**: `/home/gravityc/public_html/react.gravitycar.com`
- **Purpose**: React frontend application, static assets
- **Key Files**:
  - `index.html` - React app entry point
  - `assets/` - Built JavaScript and CSS files
  - Static React application files

## Deployment Script Updates

### Transfer Script (`scripts/deploy/transfer.sh`)
Updated all deployment operations to account for the bifurcated structure:

**Before:**
```bash
# Single directory deployment
cp -r '$REMOTE_TEMP_DIR/backend/'* /home/$PRODUCTION_USER/public_html/
```

**After:**
```bash
# Separate backend deployment
cp -r '$REMOTE_TEMP_DIR/backend/'* /home/$PRODUCTION_USER/public_html/api.gravitycar.com/

# Separate frontend deployment  
cp -r '$REMOTE_TEMP_DIR/frontend/'* /home/$PRODUCTION_USER/public_html/react.gravitycar.com/
```

### Backup Operations
Backups now preserve the subdomain structure:

```bash
# Backup both subdomains separately
cp -r /home/$PRODUCTION_USER/public_html/api.gravitycar.com '$backup_dir/'
cp -r /home/$PRODUCTION_USER/public_html/react.gravitycar.com '$backup_dir/'
```

### Setup and Configuration
Framework setup operations now target the backend subdomain:

```bash
# Run setup.php in backend directory
cd /home/$PRODUCTION_USER/public_html/api.gravitycar.com
php setup.php
```

### Verification Checks
Deployment verification checks both subdomain directories:

```bash
# Check backend files
if [ ! -f '/home/$PRODUCTION_USER/public_html/api.gravitycar.com/rest_api.php' ]; then
    echo 'ERROR: Critical backend files missing'
fi

# Check frontend files
if [ ! -f '/home/$PRODUCTION_USER/public_html/react.gravitycar.com/index.html' ]; then
    echo 'WARNING: Frontend index.html missing'
fi
```

## Health Check Integration

The health check script (`scripts/health-check.sh`) correctly uses the subdomain URLs:

```bash
DEFAULT_API_URL="https://api.gravitycar.com"
DEFAULT_FRONTEND_URL="https://react.gravitycar.com"
```

## GitHub Actions Integration

The GitHub Actions workflow (`/.github/workflows/deploy.yml`) properly references the production environment:

```yaml
environment: 
  name: production
  url: https://api.gravitycar.com
```

## Implementation Benefits

1. **Separation of Concerns**: Backend and frontend are completely isolated
2. **Independent Scaling**: Each subdomain can be optimized differently
3. **Security**: API and frontend can have different security configurations
4. **Maintenance**: Updates can be deployed to each component independently
5. **SSL/TLS**: Separate certificates and configurations per subdomain

## Production User Account

- **Username**: `gravityc`
- **Home Directory**: `/home/gravityc`
- **Public HTML**: `/home/gravityc/public_html/`
  - `api.gravitycar.com/` - Backend PHP application
  - `react.gravitycar.com/` - Frontend React application

## Backup Strategy

Backups include both subdomain directories and are timestamped:
```
/home/gravityc/backups/gravitycar_backup_YYYYMMDD_HHMMSS/
├── api.gravitycar.com/          # Backend backup
├── react.gravitycar.com/        # Frontend backup
└── database_backup.sql          # Database backup
```

## Updated Scripts

The following scripts have been updated to support the bifurcated structure:

1. ✅ `scripts/deploy/transfer.sh` - Complete subdomain deployment support
2. ✅ `scripts/health-check.sh` - Correct subdomain URL testing
3. ✅ `.github/workflows/deploy.yml` - Proper environment configuration

## Notes

- All deployment scripts now create the appropriate subdomain directories
- PHP setup operations specifically target the backend subdomain
- Health checks verify both API and frontend endpoints
- Backup and rollback procedures preserve the subdomain structure
- File permissions are set appropriately for each subdomain directory