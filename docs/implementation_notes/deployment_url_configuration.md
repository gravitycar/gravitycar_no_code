# Gravitycar Framework - Deployment Configuration Guide

This guide explains how to configure the Gravitycar Framework for deployment to different environments using the new configurable URL system.

## Overview

The Gravitycar Framework has been updated to remove hardcoded `localhost` references and use configurable URLs for both the backend API and frontend application. This enables easy deployment to staging, production, and other environments.

## Configuration Methods

### 1. Environment Variables (Recommended)

The most flexible way to configure URLs is through environment variables:

**Backend Configuration (.env file in project root):**
```bash
# Application URLs
BACKEND_URL=https://api.yourapp.com
FRONTEND_URL=https://yourapp.com

# Database (if different from config.php)
DB_HOST=your-db-host
DB_PORT=3306
DB_NAME=your-db-name
DB_USER=your-db-user
DB_PASSWORD=your-db-password

# External API Keys
TMDB_API_KEY=your_tmdb_api_key
GOOGLE_BOOKS_API_KEY=your_google_books_api_key
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
```

**Frontend Configuration (.env file in gravitycar-frontend/ directory):**
```bash
# API Base URL (must start with VITE_ for Vite)
VITE_API_BASE_URL=https://api.yourapp.com
```

### 2. VSCode Extension Configuration

For development environments using VSCode, configure the extension settings:

**In .vscode/settings.json:**
```json
{
  "gravitycar.backendUrl": "http://localhost:8081",
  "gravitycar.frontendUrl": "http://localhost:3000"
}
```

**Or via VS Code UI:**
1. Open VS Code Settings (Ctrl+,)
2. Search for "gravitycar"
3. Set "Backend URL" and "Frontend URL" values

## Environment-Specific Examples

### Local Development (Default)
```bash
# Backend
BACKEND_URL=http://localhost:8081
FRONTEND_URL=http://localhost:3000

# Frontend
VITE_API_BASE_URL=http://localhost:8081
```

### Staging Environment
```bash
# Backend
BACKEND_URL=https://staging-api.yourapp.com
FRONTEND_URL=https://staging.yourapp.com

# Frontend
VITE_API_BASE_URL=https://staging-api.yourapp.com
```

### Production Environment
```bash
# Backend
BACKEND_URL=https://api.yourapp.com
FRONTEND_URL=https://yourapp.com

# Frontend
VITE_API_BASE_URL=https://api.yourapp.com
```

## Deployment Steps

### 1. Backend Deployment

1. **Copy environment template:**
   ```bash
   cp .env.example .env
   ```

2. **Edit .env file** with your production URLs and credentials

3. **Update web server configuration** to serve from your domain

4. **Test configuration:**
   ```bash
   php -r "
   require 'config.php';
   echo 'Backend URL: ' . $config['app']['backend_url'] . PHP_EOL;
   echo 'Frontend URL: ' . $config['app']['frontend_url'] . PHP_EOL;
   "
   ```

### 2. Frontend Deployment

1. **Copy environment template:**
   ```bash
   cd gravitycar-frontend
   cp .env.example .env
   ```

2. **Edit .env file** with your production API URL:
   ```bash
   VITE_API_BASE_URL=https://api.yourapp.com
   ```

3. **Build for production:**
   ```bash
   npm run build
   ```

4. **Deploy build files** to your web server

5. **Test configuration:**
   ```bash
   # Check that the API URL is embedded in the build
   grep -r "api.yourapp.com" dist/
   ```

### 3. VSCode Development Setup

If using VSCode for development on the deployed server:

1. **Update .vscode/settings.json:**
   ```json
   {
     "gravitycar.backendUrl": "https://your-backend-url",
     "gravitycar.frontendUrl": "https://your-frontend-url"
   }
   ```

2. **Reload VSCode** to apply the new settings

## Updated File Locations

The following files have been updated to use configurable URLs:

### Backend Files
- `config.php` - Added `app.backend_url` and `app.frontend_url` settings
- `src/Services/OpenAPIGenerator.php` - Uses configurable backend URL for API documentation

### Frontend Files
- `gravitycar-frontend/src/services/api.ts` - Uses `VITE_API_BASE_URL`
- `gravitycar-frontend/src/hooks/useModelMetadata.ts` - Uses `VITE_API_BASE_URL`
- `gravitycar-frontend/src/components/fields/RelatedRecordSelect.tsx` - Uses `VITE_API_BASE_URL`
- `gravitycar-frontend/src/components/movies/TMDBEnhancedCreateForm.tsx` - Uses `VITE_API_BASE_URL`
- `gravitycar-frontend/vite.config.ts` - Properly handles environment variables

### VSCode Extension Files
- `.vscode/extensions/gravitycar-tools/` - All tools now use configurable URLs
- `.vscode/settings.json` - Contains default URL configuration

## Environment Variable Precedence

Configuration is resolved in this order (highest priority first):

1. **Environment variables** (.env files)
2. **VSCode workspace settings** (.vscode/settings.json)
3. **Default values** (localhost URLs)

## Testing Your Configuration

### Backend Testing
```bash
# Test health endpoint
curl https://your-backend-url/health

# Test API endpoint
curl https://your-backend-url/Users?limit=1

# Test OpenAPI documentation
curl https://your-backend-url/docs/openapi.json
```

### Frontend Testing
1. Open your frontend URL in a browser
2. Check browser developer console for API calls
3. Verify API calls are going to the correct backend URL

### VSCode Extension Testing
1. Use the "Gravitycar API" tool in GitHub Copilot
2. Check the terminal output for API call URLs
3. Use server control commands and verify they target correct URLs

## Security Considerations

1. **HTTPS in Production:** Always use HTTPS URLs for production deployments
2. **CORS Configuration:** Ensure your backend allows requests from your frontend domain
3. **Environment Variables:** Never commit .env files with production credentials to version control
4. **API Keys:** Store sensitive API keys in environment variables, not in code

## Troubleshooting

### Common Issues

**Frontend shows "Network error" messages:**
- Check that `VITE_API_BASE_URL` is correctly set
- Verify the backend URL is accessible from the client browser
- Check browser developer console for CORS errors

**VSCode tools connect to wrong server:**
- Check `.vscode/settings.json` for correct URLs
- Reload VSCode after changing settings
- Use Command Palette > "Developer: Reload Window"

**OpenAPI documentation shows wrong server URL:**
- Verify `BACKEND_URL` environment variable is set
- Check that config.php is reading the environment variable correctly
- Clear cache and regenerate: `php setup.php`

### Debug Commands

**Check current configuration:**
```bash
# Backend
php -r "require 'config.php'; var_dump(\$config['app']);"

# Frontend (during development)
echo $VITE_API_BASE_URL

# VSCode extension
# Check Command Palette > "Developer: Show Running Extensions"
```

## Rollback to Localhost

If you need to revert to localhost URLs:

1. **Delete or rename .env files:**
   ```bash
   mv .env .env.backup
   mv gravitycar-frontend/.env gravitycar-frontend/.env.backup
   ```

2. **Update VSCode settings:**
   ```json
   {
     "gravitycar.backendUrl": "http://localhost:8081",
     "gravitycar.frontendUrl": "http://localhost:3000"
   }
   ```

3. **Rebuild frontend:**
   ```bash
   cd gravitycar-frontend
   npm run build
   ```

The system will automatically fall back to localhost defaults when no configuration is provided.
