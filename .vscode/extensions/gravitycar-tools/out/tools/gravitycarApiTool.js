"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.GravitycarApiTool = void 0;
const vscode = __importStar(require("vscode"));
const child_process_1 = require("child_process");
class GravitycarApiTool {
    constructor() {
        this.baseUrl = 'http://localhost:8081';
    }
    async invoke(options, token) {
        try {
            const { operation, data, headers } = options.input;
            // Map operations to actual API endpoints and methods
            const operationMap = {
                // Health endpoints
                'health_ping': { method: 'GET', endpoint: '/ping' },
                'health_detailed': { method: 'GET', endpoint: '/health' },
                // Authentication
                'auth_login': { method: 'POST', endpoint: '/auth/login' },
                // Users
                'get_users': { method: 'GET', endpoint: '/Users' },
                'get_user_by_id': { method: 'GET', endpoint: '/Users/{id}' },
                'create_user': { method: 'POST', endpoint: '/Users' },
                'update_user': { method: 'PUT', endpoint: '/Users/{id}' },
                'delete_user': { method: 'DELETE', endpoint: '/Users/{id}' },
                // Movies  
                'get_movies': { method: 'GET', endpoint: '/Movies' },
                'get_movie_by_id': { method: 'GET', endpoint: '/Movies/{id}' },
                'create_movie': { method: 'POST', endpoint: '/Movies' },
                'update_movie': { method: 'PUT', endpoint: '/Movies/{id}' },
                'delete_movie': { method: 'DELETE', endpoint: '/Movies/{id}' },
                // Movie Quotes
                'get_movie_quotes': { method: 'GET', endpoint: '/MovieQuotes' },
                'get_movie_quote_by_id': { method: 'GET', endpoint: '/MovieQuotes/{id}' },
                'create_movie_quote': { method: 'POST', endpoint: '/MovieQuotes' },
                'update_movie_quote': { method: 'PUT', endpoint: '/MovieQuotes/{id}' },
                'delete_movie_quote': { method: 'DELETE', endpoint: '/MovieQuotes/{id}' },
                // Metadata operations
                'get_all_models_metadata': { method: 'GET', endpoint: '/metadata' },
                'get_model_metadata': { method: 'GET', endpoint: '/metadata/{modelName}' },
                'get_users_metadata': { method: 'GET', endpoint: '/metadata/Users' },
                // Custom operations
                'custom_get': { method: 'GET', endpoint: data?.endpoint || '/' },
                'custom_post': { method: 'POST', endpoint: data?.endpoint || '/' },
                'custom_put': { method: 'PUT', endpoint: data?.endpoint || '/' },
                'custom_delete': { method: 'DELETE', endpoint: data?.endpoint || '/' }
            };
            const apiConfig = operationMap[operation];
            if (!apiConfig) {
                throw new Error(`Unknown operation: ${operation}`);
            }
            let endpoint = apiConfig.endpoint;
            const method = apiConfig.method;
            // Replace {id} placeholder if data contains id
            if (endpoint.includes('{id}') && data?.id) {
                endpoint = endpoint.replace('{id}', data.id.toString());
            }
            // Replace {modelName} placeholder if data contains modelName
            if (endpoint.includes('{modelName}') && data?.modelName) {
                endpoint = endpoint.replace('{modelName}', data.modelName);
            }
            // Build curl command
            let curlCmd = `curl -X ${method.toUpperCase()} "${this.baseUrl}${endpoint}"`;
            // Add headers
            if (headers) {
                for (const [key, value] of Object.entries(headers)) {
                    curlCmd += ` -H "${key}: ${value}"`;
                }
            }
            // Add data for POST/PUT/PATCH requests (excluding id field used for URL)
            if (data && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
                const requestData = { ...data };
                delete requestData.id; // Remove id from request body as it's in the URL
                delete requestData.endpoint; // Remove endpoint from request body for custom operations
                const jsonData = JSON.stringify(requestData);
                curlCmd += ` -d '${jsonData}'`;
                curlCmd += ` -H "Content-Type: application/json"`;
            }
            // Add common options
            curlCmd += ' -s -w "\\nHTTP_STATUS:%{http_code}"';
            console.log(`Executing: ${curlCmd}`);
            const output = (0, child_process_1.execSync)(curlCmd, {
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
            }
            catch {
                parsedResponse = responseBody;
            }
            const result = {
                success: httpStatus.startsWith('2'),
                status: httpStatus,
                data: parsedResponse,
                operation,
                endpoint,
                method: method.toUpperCase()
            };
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(result, null, 2))
            ]);
        }
        catch (error) {
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
exports.GravitycarApiTool = GravitycarApiTool;
//# sourceMappingURL=gravitycarApiTool.js.map