import * as vscode from 'vscode';
import { execSync } from 'child_process';

interface ApiCallInput {
    operation: string;
    parameters?: {
        id?: string;
        modelName?: string;
        data?: any;
        page?: number;
        limit?: number;
        search?: string;
        username?: string;
        password?: string;
        endpoint?: string;
    };
    headers?: Record<string, string>;
}

export class GravitycarApiTool implements vscode.LanguageModelTool<ApiCallInput> {
    private readonly baseUrl = 'http://localhost:8081';

    async invoke(
        options: vscode.LanguageModelToolInvocationOptions<ApiCallInput>,
        token: vscode.CancellationToken
    ): Promise<vscode.LanguageModelToolResult> {
        try {
            const { operation, parameters = {}, headers } = options.input;
            
            // Map operations to actual API endpoints and methods
            const operationMap: Record<string, { method: string; endpoint: string }> = {
                // Health endpoints
                'health_ping': { method: 'GET', endpoint: '/ping' },
                'health_detailed': { method: 'GET', endpoint: '/health' },
                
                // Authentication
                'auth_login': { method: 'POST', endpoint: '/auth/login' },
                
                // Generic CRUD operations for any model
                'get_list': { method: 'GET', endpoint: '/{modelName}' },
                'get_by_id': { method: 'GET', endpoint: '/{modelName}/{id}' },
                'create': { method: 'POST', endpoint: '/{modelName}' },
                'update': { method: 'PUT', endpoint: '/{modelName}/{id}' },
                'delete': { method: 'DELETE', endpoint: '/{modelName}/{id}' },
                
                // Legacy specific operations (deprecated - use generic operations instead)
                'get_users': { method: 'GET', endpoint: '/Users' },
                'get_user_by_id': { method: 'GET', endpoint: '/Users/{id}' },
                'create_user': { method: 'POST', endpoint: '/Users' },
                'update_user': { method: 'PUT', endpoint: '/Users/{id}' },
                'delete_user': { method: 'DELETE', endpoint: '/Users/{id}' },
                
                'get_movies': { method: 'GET', endpoint: '/Movies' },
                'get_movie_by_id': { method: 'GET', endpoint: '/Movies/{id}' },
                'create_movie': { method: 'POST', endpoint: '/Movies' },
                'update_movie': { method: 'PUT', endpoint: '/Movies/{id}' },
                'delete_movie': { method: 'DELETE', endpoint: '/Movies/{id}' },
                
                'get_movie_quotes': { method: 'GET', endpoint: '/Movie_Quotes' },
                'get_movie_quote_by_id': { method: 'GET', endpoint: '/Movie_Quotes/{id}' },
                'create_movie_quote': { method: 'POST', endpoint: '/Movie_Quotes' },
                'update_movie_quote': { method: 'PUT', endpoint: '/Movie_Quotes/{id}' },
                'delete_movie_quote': { method: 'DELETE', endpoint: '/Movie_Quotes/{id}' },
                
                // Metadata operations
                'get_all_models_metadata': { method: 'GET', endpoint: '/metadata' },
                'get_model_metadata': { method: 'GET', endpoint: '/metadata/model/{modelName}' },
                'get_users_metadata': { method: 'GET', endpoint: '/metadata/model/Users' },
                
                // Custom operations
                'custom_get': { method: 'GET', endpoint: parameters?.endpoint || '/' },
                'custom_post': { method: 'POST', endpoint: parameters?.endpoint || '/' },
                'custom_put': { method: 'PUT', endpoint: parameters?.endpoint || '/' },
                'custom_delete': { method: 'DELETE', endpoint: parameters?.endpoint || '/' }
            };
            
            const apiConfig = operationMap[operation];
            if (!apiConfig) {
                throw new Error(`Unknown operation: ${operation}. Available operations: ${Object.keys(operationMap).join(', ')}`);
            }
            
            let endpoint = apiConfig.endpoint;
            const method = apiConfig.method;
            
            // Replace {id} placeholder if parameters contains id
            if (endpoint.includes('{id}') && parameters.id) {
                endpoint = endpoint.replace('{id}', parameters.id.toString());
            }
            
            // Replace {modelName} placeholder if parameters contains modelName
            if (endpoint.includes('{modelName}') && parameters.modelName) {
                endpoint = endpoint.replace('{modelName}', parameters.modelName);
            }
            
            // Debug: Check if replacement is not happening
            if (endpoint.includes('{')) {
                console.log(`Warning: Unresolved placeholders in endpoint: ${endpoint}`);
                console.log(`Parameters:`, parameters);
            }
            
            // Build query parameters for GET requests
            const queryParams = new URLSearchParams();
            if (method === 'GET') {
                if (parameters.page) queryParams.append('page', parameters.page.toString());
                if (parameters.limit) queryParams.append('limit', parameters.limit.toString());
                if (parameters.search) queryParams.append('search', parameters.search);
            }
            
            const queryString = queryParams.toString();
            const fullUrl = `${this.baseUrl}${endpoint}${queryString ? `?${queryString}` : ''}`;
            
            // Build curl command
            let curlCmd = `curl -X ${method.toUpperCase()} "${fullUrl}"`;
            
            // Add headers
            if (headers) {
                for (const [key, value] of Object.entries(headers)) {
                    curlCmd += ` -H "${key}: ${value}"`;
                }
            }
            
            // Add data for POST/PUT/PATCH requests
            if (parameters.data && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
                const jsonData = JSON.stringify(parameters.data);
                curlCmd += ` -d '${jsonData}'`;
                curlCmd += ` -H "Content-Type: application/json"`;
            } else if (operation === 'auth_login') {
                // Special handling for auth_login
                const loginData = {
                    username: parameters.username || 'admin',
                    password: parameters.password || 'password'
                };
                const jsonData = JSON.stringify(loginData);
                curlCmd += ` -d '${jsonData}'`;
                curlCmd += ` -H "Content-Type: application/json"`;
            }
            
            // Add common options
            curlCmd += ' -s -w "\\nHTTP_STATUS:%{http_code}"';
            
            console.log(`Executing: ${curlCmd}`);
            
            const output = execSync(curlCmd, { 
                encoding: 'utf8',
                timeout: 30000,
                maxBuffer: 1024 * 1024 // 1MB buffer
            });
            
            // Parse output to separate response body and status
            const lines = output.trim().split('\n');
            const statusLine = lines[lines.length - 1];
            const responseBody = lines.slice(0, -1).join('\n');
            
            const httpStatus = statusLine.startsWith('HTTP_STATUS:') 
                ? statusLine.replace('HTTP_STATUS:', '') 
                : 'unknown';
            
            let parsedResponse;
            try {
                parsedResponse = JSON.parse(responseBody);
            } catch {
                parsedResponse = responseBody;
            }
            
            const result = {
                success: httpStatus.startsWith('2'),
                status: httpStatus,
                data: parsedResponse,
                operation,
                endpoint: fullUrl,
                method: method.toUpperCase()
            };
            
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(result, null, 2))
            ]);
            
        } catch (error) {
            const errorResult = {
                success: false,
                error: error instanceof Error ? error.message : String(error),
                operation: options.input.operation
            };
            
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(errorResult, null, 2))
            ]);
        }
    }
}
