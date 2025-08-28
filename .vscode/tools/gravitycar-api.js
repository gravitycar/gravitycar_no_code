#!/usr/bin/env node

const http = require('http');
const fs = require('fs');
const path = require('path');

// Token storage path
const TOKEN_FILE = path.join(process.cwd(), '.vscode', 'gravitycar-token.json');

// Gravitycar server configuration
const GRAVITYCAR_CONFIG = {
    hostname: 'localhost',
    port: 8081,
    timeout: 10000
};

/**
 * Load stored JWT token
 */
function loadToken() {
    try {
        if (fs.existsSync(TOKEN_FILE)) {
            const tokenData = JSON.parse(fs.readFileSync(TOKEN_FILE, 'utf8'));
            // Check if token is not too old (optional - remove if tokens don't expire quickly)
            const oneHour = 60 * 60 * 1000;
            if (Date.now() - tokenData.timestamp < oneHour) {
                return tokenData.token;
            }
        }
    } catch (e) {
        console.error('Warning: Failed to load token:', e.message);
    }
    return null;
}

/**
 * Save JWT token for future requests
 */
function saveToken(token) {
    try {
        const tokenDir = path.dirname(TOKEN_FILE);
        if (!fs.existsSync(tokenDir)) {
            fs.mkdirSync(tokenDir, { recursive: true });
        }
        fs.writeFileSync(TOKEN_FILE, JSON.stringify({ 
            token, 
            timestamp: Date.now() 
        }));
        console.log('Token saved successfully');
    } catch (e) {
        console.error('Warning: Failed to save token:', e.message);
    }
}

/**
 * Clear stored token
 */
function clearToken() {
    try {
        if (fs.existsSync(TOKEN_FILE)) {
            fs.unlinkSync(TOKEN_FILE);
            console.log('Token cleared');
        }
    } catch (e) {
        console.error('Warning: Failed to clear token:', e.message);
    }
}

/**
 * Make HTTP request to Gravitycar API
 */
async function makeGravitycarRequest(params) {
    const { 
        method = 'GET', 
        endpoint, 
        data, 
        headers = {},
        useAuth = true,
        saveAuthToken = false,
        timeout = GRAVITYCAR_CONFIG.timeout
    } = params;

    // Ensure endpoint starts with /
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;

    const requestHeaders = {
        'Content-Type': 'application/json',
        'User-Agent': 'VSCode-Gravitycar-Tool/1.0',
        ...headers
    };

    // Add authentication if required
    if (useAuth) {
        const token = loadToken();
        if (token) {
            requestHeaders['Authorization'] = `Bearer ${token}`;
        } else {
            console.log('Note: No authentication token found. Use saveAuthToken: true on login requests.');
        }
    }

    const options = {
        hostname: GRAVITYCAR_CONFIG.hostname,
        port: GRAVITYCAR_CONFIG.port,
        path: cleanEndpoint,
        method: method.toUpperCase(),
        headers: requestHeaders,
        timeout: timeout
    };

    return new Promise((resolve, reject) => {
        const req = http.request(options, (res) => {
            let responseData = '';
            
            res.on('data', (chunk) => {
                responseData += chunk;
            });
            
            res.on('end', () => {
                try {
                    const parsed = JSON.parse(responseData);
                    
                    // Handle authentication token saving for login requests
                    if (saveAuthToken && parsed.success) {
                        let token = null;
                        
                        // Handle different response formats
                        if (parsed.data && parsed.data.access_token) {
                            token = parsed.data.access_token;
                        } else if (parsed.access_token) {
                            token = parsed.access_token;
                        } else if (parsed.token) {
                            token = parsed.token;
                        }
                        
                        if (token) {
                            saveToken(token);
                        } else {
                            console.log('Warning: Login successful but no token found in response');
                        }
                    }
                    
                    resolve({
                        status: res.statusCode,
                        statusText: res.statusMessage,
                        headers: res.headers,
                        success: res.statusCode >= 200 && res.statusCode < 300,
                        data: parsed,
                        endpoint: cleanEndpoint,
                        method: method.toUpperCase()
                    });
                } catch (e) {
                    // Handle non-JSON responses
                    resolve({
                        status: res.statusCode,
                        statusText: res.statusMessage,
                        headers: res.headers,
                        success: res.statusCode >= 200 && res.statusCode < 300,
                        data: responseData,
                        endpoint: cleanEndpoint,
                        method: method.toUpperCase(),
                        parseError: e.message
                    });
                }
            });
        });

        req.on('error', (err) => {
            reject({
                error: err.message,
                code: err.code,
                endpoint: cleanEndpoint,
                method: method.toUpperCase()
            });
        });

        req.on('timeout', () => {
            req.destroy();
            reject({
                error: 'Request timeout',
                timeout: timeout,
                endpoint: cleanEndpoint,
                method: method.toUpperCase()
            });
        });

        // Write request body for POST/PUT/PATCH requests
        if (data && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
            const bodyData = typeof data === 'string' ? data : JSON.stringify(data);
            req.write(bodyData);
        }

        req.end();
    });
}

/**
 * Helper function to format successful responses
 */
function formatResponse(result) {
    const output = {
        request: {
            method: result.method,
            endpoint: result.endpoint,
            status: result.status
        },
        success: result.success
    };

    if (result.success) {
        output.data = result.data;
    } else {
        output.error = {
            status: result.status,
            statusText: result.statusText,
            data: result.data
        };
    }

    if (result.parseError) {
        output.parseError = result.parseError;
    }

    return output;
}

/**
 * Validate and normalize request parameters
 */
function validateParams(params) {
    if (!params.endpoint) {
        throw new Error('endpoint parameter is required');
    }

    // Normalize common Gravitycar endpoints
    let endpoint = params.endpoint;
    
    // Handle metadata requests
    if (endpoint.startsWith('metadata') || endpoint.startsWith('/metadata')) {
        endpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    }
    // Handle model requests - ensure model names are capitalized
    else if (endpoint.match(/^\/?(users|movies|moviequotes)/i)) {
        const parts = endpoint.replace(/^\//, '').split('/');
        if (parts[0]) {
            parts[0] = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
            // Special case for MovieQuotes
            if (parts[0].toLowerCase() === 'moviequotes') {
                parts[0] = 'MovieQuotes';
            }
        }
        endpoint = '/' + parts.join('/');
    }
    // Ensure it starts with /
    else if (!endpoint.startsWith('/')) {
        endpoint = '/' + endpoint;
    }

    return {
        ...params,
        endpoint: endpoint,
        method: (params.method || 'GET').toUpperCase()
    };
}

/**
 * Provide helpful examples for common operations
 */
function getExamples() {
    return {
        "Common Gravitycar API Examples": {
            "Login (save token)": {
                "method": "POST",
                "endpoint": "/auth/login",
                "data": {
                    "username": "admin",
                    "password": "password"
                },
                "saveAuthToken": true,
                "useAuth": false
            },
            "Get Users (paginated)": {
                "method": "GET",
                "endpoint": "/Users?page=1&limit=10"
            },
            "Get User by ID": {
                "method": "GET",
                "endpoint": "/Users/1"
            },
            "Search Users": {
                "method": "GET",
                "endpoint": "/Users?search=john&limit=5"
            },
            "Create User": {
                "method": "POST",
                "endpoint": "/Users",
                "data": {
                    "username": "newuser",
                    "email": "user@example.com",
                    "first_name": "John",
                    "last_name": "Doe"
                }
            },
            "Update User": {
                "method": "PUT",
                "endpoint": "/Users/1",
                "data": {
                    "first_name": "Jane",
                    "last_name": "Smith"
                }
            },
            "Delete User": {
                "method": "DELETE",
                "endpoint": "/Users/1"
            },
            "Get Movies": {
                "method": "GET",
                "endpoint": "/Movies"
            },
            "Get Movie Quotes": {
                "method": "GET",
                "endpoint": "/MovieQuotes"
            },
            "Get Users Metadata": {
                "method": "GET",
                "endpoint": "/metadata/models/Users"
            },
            "Get All Models Metadata": {
                "method": "GET",
                "endpoint": "/metadata/models"
            },
            "Clear Token": {
                "method": "CLEAR_TOKEN"
            }
        }
    };
}

/**
 * Main execution function
 */
async function main() {
    try {
        let rawData = '';

        // Read input from stdin
        process.stdin.setEncoding('utf8');
        
        for await (const chunk of process.stdin) {
            rawData += chunk;
        }

        if (!rawData.trim()) {
            console.log(JSON.stringify(getExamples(), null, 2));
            return;
        }

        const params = JSON.parse(rawData);
        
        // Show examples if no endpoint provided
        if (!params.endpoint && !params.method) {
            console.log(JSON.stringify(getExamples(), null, 2));
            return;
        }
        
        // Handle special commands
        if (params.method === 'CLEAR_TOKEN') {
            clearToken();
            console.log(JSON.stringify({
                success: true,
                message: "Authentication token cleared"
            }, null, 2));
            return;
        }

        if (params.method === 'EXAMPLES') {
            console.log(JSON.stringify(getExamples(), null, 2));
            return;
        }

        // Validate and normalize parameters
        const validatedParams = validateParams(params);
        
        // Make the API request
        const result = await makeGravitycarRequest(validatedParams);
        
        // Format and output the response
        const formattedResponse = formatResponse(result);
        console.log(JSON.stringify(formattedResponse, null, 2));
        
    } catch (error) {
        console.error(JSON.stringify({
            error: error.message,
            stack: error.stack,
            type: 'tool_error'
        }, null, 2));
        process.exit(1);
    }
}

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
    console.error(JSON.stringify({
        error: 'Uncaught exception: ' + error.message,
        stack: error.stack,
        type: 'uncaught_exception'
    }, null, 2));
    process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error(JSON.stringify({
        error: 'Unhandled rejection: ' + (reason?.message || reason),
        stack: reason?.stack,
        type: 'unhandled_rejection'
    }, null, 2));
    process.exit(1);
});

// Run the main function
main();
