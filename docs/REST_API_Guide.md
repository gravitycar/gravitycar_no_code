# REST API Web Server Integration - Setup and Usage Guide

## Overview

The Gravitycar Framework now includes a complete REST API web server integration that allows you to access your models and data through standard HTTP requests. This implementation provides a production-ready REST API endpoint with Apache mod_rewrite integration.

## Features

- ✅ **Complete REST API**: Supports GET, POST, PUT, DELETE, PATCH operations
- ✅ **Apache Integration**: Uses mod_rewrite for clean URLs
- ✅ **ReactJS-Friendly**: Standardized JSON response format
- ✅ **Full Bootstrap**: Complete Gravitycar framework initialization
- ✅ **Error Handling**: Consistent error responses with proper HTTP status codes
- ✅ **CORS Support**: Basic CORS headers for browser compatibility
- ✅ **Production Ready**: Comprehensive logging and error handling

## URL Patterns

The REST API supports the following URL patterns:

```
GET    /Users           → List all users
GET    /Users/123       → Get user with ID 123
POST   /Users           → Create a new user
PUT    /Users/123       → Update user with ID 123
DELETE /Users/123       → Delete user with ID 123
PATCH  /Users/123       → Partially update user with ID 123

GET    /Movies          → List all movies
GET    /Movies/abc-def  → Get movie with ID abc-def
POST   /Movies          → Create a new movie
... (same pattern for all models)
```

## Installation

### Prerequisites

- Apache web server with mod_rewrite enabled
- PHP 7.4+ with JSON extension
- Gravitycar Framework installed and configured
- Database connection configured

### Setup Steps

1. **Ensure Apache mod_rewrite is enabled**:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. **Verify .htaccess file** (already created):
   ```apache
   RewriteEngine On
   
   # REST API Routes
   RewriteCond %{REQUEST_URI} ^/([A-Za-z][A-Za-z0-9_]*)(/.+)?/?$ [NC]
   RewriteRule ^([A-Za-z][A-Za-z0-9_]*)(/.+)?/?$ rest_api.php [E=ORIGINAL_PATH:%{REQUEST_URI},E=MODEL_NAME:$1,E=PATH_INFO:$2,QSA,L]
   
   # Fallback for exact model name matches
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteCond %{REQUEST_URI} ^/[A-Za-z][A-Za-z0-9_]*
   RewriteRule ^(.*)$ rest_api.php [E=ORIGINAL_PATH:%{REQUEST_URI},QSA,L]
   ```

3. **Verify REST API entry point** (already created):
   - File: `rest_api.php` in your web root (lightweight entry point)
   - File: `src/Api/RestApiHandler.php` (main processing logic)
   - Handles all REST API requests
   - Bootstraps Gravitycar framework
   - Delegates to Router class

4. **Test the installation**:
   ```bash
   # Run comprehensive tests
   php test_rest_api_comprehensive.php
   
   # Run web server simulation
   php test_web_server.php
   
   # Run unit tests
   ./vendor/bin/phpunit Tests/Feature/RestApiBootstrapTest.php
   ./vendor/bin/phpunit Tests/Integration/RestApiEntryPointTest.php
   ```

## Usage Examples

### Using cURL

```bash
# List all users
curl -X GET "http://yourdomain.com/Users"

# Get specific user
curl -X GET "http://yourdomain.com/Users/123"

# Create new user
curl -X POST "http://yourdomain.com/Users" \
  -H "Content-Type: application/json" \
  -d '{"username":"newuser@example.com","email":"newuser@example.com","first_name":"New","last_name":"User","password":"password123"}'

# Update user
curl -X PUT "http://yourdomain.com/Users/123" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Updated","last_name":"Name"}'

# Delete user
curl -X DELETE "http://yourdomain.com/Users/123"
```

### Using JavaScript/Fetch

```javascript
// List all users
fetch('/Users')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Users:', data.data);
      console.log('Count:', data.count);
    } else {
      console.error('Error:', data.error.message);
    }
  });

// Create new user
fetch('/Users', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    username: 'newuser@example.com',
    email: 'newuser@example.com',
    first_name: 'New',
    last_name: 'User',
    password: 'password123'
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    console.log('User created:', data.data);
  } else {
    console.error('Error:', data.error.message);
  }
});
```

### Using ReactJS

```jsx
import React, { useState, useEffect } from 'react';

function UsersList() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetch('/Users')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          setUsers(data.data);
        } else {
          setError(data.error.message);
        }
        setLoading(false);
      })
      .catch(err => {
        setError(err.message);
        setLoading(false);
      });
  }, []);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <ul>
      {users.map(user => (
        <li key={user.id}>
          {user.first_name} {user.last_name} ({user.email})
        </li>
      ))}
    </ul>
  );
}
```

## Response Format

### Success Response

```json
{
  "success": true,
  "status": 200,
  "data": [
    {
      "id": "123",
      "username": "user@example.com",
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe"
    }
  ],
  "count": 1,
  "timestamp": "2025-08-13T18:49:19+00:00"
}
```

### Error Response

```json
{
  "success": false,
  "status": 400,
  "error": {
    "message": "Model not found or cannot be instantiated",
    "type": "Gravitycar Exception",
    "code": 0,
    "context": {
      "model": "InvalidModel"
    }
  },
  "timestamp": "2025-08-13T18:49:19+00:00"
}
```

## Query Parameters

The REST API supports query parameters for filtering and pagination:

```bash
# Limit results
GET /Users?limit=10

# Pagination
GET /Users?limit=10&offset=20

# Filtering (if supported by the model)
GET /Users?user_type=admin

# Sorting (if supported by the model)
GET /Users?sort=last_name&order=asc
```

## Authentication

The current implementation includes basic CORS support but does not include authentication. For production use, you may want to add:

- JWT token authentication
- API key authentication
- OAuth integration
- Rate limiting

## Performance Considerations

### Optimization Tips

1. **Caching**: Enable metadata caching (already implemented)
2. **Database**: Use connection pooling for high traffic
3. **Apache**: Enable compression and caching headers
4. **PHP**: Use OpCache for better performance

### Apache Configuration

Add these to your virtual host or .htaccess for better performance:

```apache
# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE text/plain
</IfModule>

# Cache headers for static content
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType application/json "access plus 1 minute"
</IfModule>
```

## Troubleshooting

### Common Issues

1. **404 Not Found**: Check that mod_rewrite is enabled and .htaccess is in the correct location
2. **500 Internal Server Error**: Check PHP error logs and ensure all dependencies are installed
3. **Database Connection Error**: Verify database configuration in config.php
4. **Bootstrap Failures**: Run the setup script: `php setup.php`

### Debug Mode

To enable debug mode, set environment variable:

```bash
export GRAVITYCAR_ENV=development
```

Or modify config.php:

```php
'app' => [
    'debug' => true,
    'env' => 'development'
]
```

### Log Files

Check these log files for debugging:

- `logs/gravitycar.log` - Application logs
- Apache error log (usually `/var/log/apache2/error.log`)
- PHP error log

## Testing

### Automated Tests

Run the complete test suite:

```bash
# Unit tests
./vendor/bin/phpunit Tests/Feature/RestApiBootstrapTest.php

# Integration tests
./vendor/bin/phpunit Tests/Integration/RestApiEntryPointTest.php

# Comprehensive API tests
php test_rest_api_comprehensive.php

# Web server simulation
php test_web_server.php
```

### Manual Testing

Use these tools for manual testing:

- **cURL**: Command-line HTTP client
- **Postman**: GUI REST client
- **Browser**: For GET requests
- **Your frontend application**: React, Vue, etc.

## Security Considerations

### Production Checklist

- [ ] Enable HTTPS
- [ ] Implement authentication
- [ ] Add rate limiting
- [ ] Validate and sanitize inputs
- [ ] Enable security headers
- [ ] Configure CORS properly
- [ ] Use environment variables for sensitive data

### Security Headers

Add these headers to your Apache configuration:

```apache
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
```

## Integration with Frontend Frameworks

### ReactJS

The response format is designed to be ReactJS-friendly:

```jsx
// Use with React Query
import { useQuery } from 'react-query';

function useUsers() {
  return useQuery('users', async () => {
    const response = await fetch('/Users');
    const data = await response.json();
    if (!data.success) {
      throw new Error(data.error.message);
    }
    return data.data;
  });
}
```

### Vue.js

```javascript
// Use with Axios
import axios from 'axios';

export default {
  data() {
    return {
      users: [],
      loading: true,
      error: null
    };
  },
  async mounted() {
    try {
      const response = await axios.get('/Users');
      if (response.data.success) {
        this.users = response.data.data;
      } else {
        this.error = response.data.error.message;
      }
    } catch (err) {
      this.error = err.message;
    } finally {
      this.loading = false;
    }
  }
};
```

## Support

For issues and questions:

1. Check the troubleshooting section above
2. Review the log files
3. Run the test suite to verify functionality
4. Consult the Gravitycar Framework documentation

## Version Information

- **Implementation**: Complete
- **Status**: Production Ready
- **Tested**: ✅ Unit tests, Integration tests, Manual tests
- **Framework Version**: Compatible with current Gravitycar Framework
- **PHP Version**: 7.4+
- **Apache**: mod_rewrite required
