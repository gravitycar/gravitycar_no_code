# VSCode Custom Gravitycar API Tool

**Date**: August 28, 2025  
**Purpose**: Documentation for the custom VSCode tool that enables direct API communication with the Gravitycar framework

## Overview

This custom tool allows GitHub Copilot (and other AI agents) to make direct API calls to the local Gravitycar framework server at `localhost:8081`. The tool supports authentication, CRUD operations, metadata queries, and all standard Gravitycar API endpoints.

## Installation

The tool consists of two files:

1. **`.vscode/settings.json`** - Tool configuration for VSCode
2. **`.vscode/tools/gravitycar-api.js`** - Node.js script that handles API communication

### Files Created

```
.vscode/
├── settings.json          # Tool configuration
└── tools/
    └── gravitycar-api.js  # Tool implementation script
```

## Tool Configuration

In `.vscode/settings.json`:

```json
{
  "github.copilot.chat.tools": {
    "gravitycar-api": {
      "name": "Gravitycar API",
      "description": "Make API calls to the local Gravitycar framework server at localhost:8081. Supports authentication, CRUD operations, and metadata queries. Model names must be capitalized (e.g., Users, Movies). Examples: GET /Users, POST /Movies, GET /metadata/models/Users",
      "command": "node",
      "args": [".vscode/tools/gravitycar-api.js"],
      "input": "json"
    }
  }
}
```

## Usage

The tool accepts JSON input with the following parameters:

### Basic Parameters

- **`method`** (string, optional): HTTP method (GET, POST, PUT, DELETE, etc.). Default: "GET"
- **`endpoint`** (string, required): API endpoint path
- **`data`** (object, optional): Request body data for POST/PUT/PATCH requests
- **`headers`** (object, optional): Additional HTTP headers
- **`useAuth`** (boolean, optional): Whether to include authentication token. Default: true
- **`saveAuthToken`** (boolean, optional): Save token from login response. Default: false
- **`timeout`** (number, optional): Request timeout in milliseconds. Default: 10000

### Model Name Capitalization

The tool automatically capitalizes model names according to Gravitycar conventions:
- `users` → `Users`
- `movies` → `Movies`
- `moviequotes` → `MovieQuotes`

### Authentication Token Management

The tool automatically manages JWT tokens:
- **Token Storage**: Stored in `.vscode/gravitycar-token.json`
- **Auto-Include**: Automatically includes `Authorization: Bearer <token>` header
- **Auto-Save**: Can save tokens from login responses when `saveAuthToken: true`
- **Token Clearing**: Use `{"method": "CLEAR_TOKEN"}` to clear stored token

## Examples

### Basic Operations

#### Get All Users
```json
{
  "method": "GET",
  "endpoint": "/Users"
}
```

#### Get Users with Pagination
```json
{
  "method": "GET",
  "endpoint": "/Users?page=1&limit=10"
}
```

#### Search Users
```json
{
  "method": "GET",
  "endpoint": "/Users?search=john&limit=5"
}
```

#### Get User by ID
```json
{
  "method": "GET",
  "endpoint": "/Users/1"
}
```

#### Create New User
```json
{
  "method": "POST",
  "endpoint": "/Users",
  "data": {
    "username": "newuser",
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

#### Update User
```json
{
  "method": "PUT",
  "endpoint": "/Users/1",
  "data": {
    "first_name": "Jane",
    "last_name": "Smith"
  }
}
```

#### Delete User
```json
{
  "method": "DELETE",
  "endpoint": "/Users/1"
}
```

### Metadata Operations

#### Get All Models
```json
{
  "method": "GET",
  "endpoint": "/metadata/models"
}
```

#### Get User Model Metadata
```json
{
  "method": "GET",
  "endpoint": "/metadata/models/Users"
}
```

#### Get Movies Model Metadata
```json
{
  "method": "GET",
  "endpoint": "/metadata/models/Movies"
}
```

### Authentication

#### Login and Save Token
```json
{
  "method": "POST",
  "endpoint": "/auth/login",
  "data": {
    "username": "admin@example.com",
    "password": "password"
  },
  "saveAuthToken": true,
  "useAuth": false
}
```

#### Clear Stored Token
```json
{
  "method": "CLEAR_TOKEN"
}
```

### Movie Operations

#### Get All Movies
```json
{
  "method": "GET",
  "endpoint": "/Movies"
}
```

#### Get Movie by ID
```json
{
  "method": "GET",
  "endpoint": "/Movies/1"
}
```

#### Get Movie Quotes
```json
{
  "method": "GET",
  "endpoint": "/MovieQuotes"
}
```

## Response Format

The tool returns a standardized response format:

### Successful Response
```json
{
  "request": {
    "method": "GET",
    "endpoint": "/Users",
    "status": 200
  },
  "success": true,
  "data": {
    "success": true,
    "status": 200,
    "data": [...],
    "timestamp": "2025-08-28T15:22:52+00:00",
    "count": 7
  }
}
```

### Error Response
```json
{
  "request": {
    "method": "GET",
    "endpoint": "/InvalidEndpoint",
    "status": 404
  },
  "success": false,
  "error": {
    "status": 404,
    "statusText": "Not Found",
    "data": {
      "success": false,
      "status": 404,
      "error": {
        "message": "Model not found",
        "code": 404
      }
    }
  }
}
```

## Special Features

### Automatic Model Name Handling
The tool automatically handles Gravitycar's model naming conventions:
- Ensures model names are properly capitalized
- Handles special cases like `MovieQuotes`
- Normalizes endpoint paths

### Endpoint Normalization
- Ensures endpoints start with `/`
- Handles both `/metadata` and `metadata` formats
- Validates and cleans up endpoint paths

### Error Handling
- Comprehensive error reporting
- Network timeout handling
- JSON parsing error detection
- Authentication failure detection

### Token Management
- Automatic token storage and retrieval
- Token expiration awareness
- Secure token file handling

## Troubleshooting

### Common Issues

#### "endpoint parameter is required"
- Ensure you provide an `endpoint` parameter in your JSON input
- Use empty object `{}` to see examples

#### Authentication Failures
- Check if you have a valid token stored
- Use `{"method": "CLEAR_TOKEN"}` to clear invalid tokens
- Verify your login credentials

#### Connection Refused
- Ensure the Gravitycar server is running on port 8081
- Check that `php setup.php` has been run recently

#### Invalid Model Names
- The tool automatically capitalizes model names
- Use the exact model names from `/metadata/models`

## Integration with AI Development

This tool enables AI agents to:

1. **Inspect Data Structures**: Query metadata to understand model fields and relationships
2. **Test API Endpoints**: Verify functionality during development
3. **Generate Implementation Plans**: Base plans on actual API responses
4. **Debug Issues**: Examine real data and responses
5. **Validate Changes**: Test modifications immediately

## Security Considerations

- **Local Development Only**: Tool is designed for localhost development
- **Token Storage**: Tokens stored in `.vscode/gravitycar-token.json` (add to .gitignore)
- **No Network Exposure**: Only communicates with local Gravitycar server
- **Request Logging**: All requests are logged with timestamps

## Future Enhancements

Potential improvements:
1. **Environment Support**: Multiple environment configurations
2. **Response Caching**: Cache frequently requested metadata
3. **Batch Operations**: Support multiple requests in one call
4. **Schema Validation**: Validate request data against model schemas
5. **Interactive Mode**: Step-by-step request building

## Testing

The tool has been tested with:
- ✅ Basic CRUD operations on Users model
- ✅ Metadata queries for model information
- ✅ Error handling and response formatting
- ✅ Endpoint normalization and model name capitalization
- ✅ Token management system

## Conclusion

This custom tool significantly enhances the development experience with the Gravitycar framework by providing direct API access from within VSCode. It enables rapid prototyping, debugging, and implementation planning with real-time access to the framework's capabilities and data structures.
