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
exports.GravitycarServerTool = void 0;
const vscode = __importStar(require("vscode"));
const child_process_1 = require("child_process");
class GravitycarServerTool {
    async invoke(options, token) {
        try {
            const { action, service = 'both' } = options.input;
            let command = '';
            let description = '';
            // Normalize action names (handle both hyphenated and underscore versions)
            const normalizedAction = action.replace(/-/g, '_');
            switch (normalizedAction) {
                case 'restart_apache':
                case 'restart-apache':
                    if (service === 'frontend') {
                        throw new Error('Cannot restart Apache when service is set to frontend');
                    }
                    command = './restart-apache.sh';
                    description = 'Restarting Apache server';
                    break;
                case 'restart_frontend':
                case 'restart-frontend':
                    if (service === 'backend') {
                        throw new Error('Cannot restart frontend when service is set to backend');
                    }
                    command = 'pkill -f "vite" && pkill -f "npm run dev" && cd gravitycar-frontend && npm run dev &';
                    description = 'Restarting frontend development server';
                    break;
                case 'status':
                    let statusCommand = '';
                    if (service === 'backend' || service === 'both') {
                        statusCommand += 'systemctl status apache2';
                    }
                    if (service === 'both') {
                        statusCommand += ' && ';
                    }
                    if (service === 'frontend' || service === 'both') {
                        statusCommand += 'ps aux | grep -E "(npm|node|vite)" | grep -v grep';
                    }
                    command = statusCommand;
                    description = 'Checking server status';
                    break;
                case 'logs':
                    if (service === 'backend' || service === 'both') {
                        command = 'tail -n 50 logs/gravitycar.log';
                        description = 'Showing recent Gravitycar backend logs';
                    }
                    else if (service === 'frontend') {
                        // Frontend logs are typically in the terminal where npm run dev is running
                        command = 'echo "Frontend logs are displayed in the development server terminal"';
                        description = 'Frontend development server logs location';
                    }
                    break;
                case 'health_check':
                case 'health-check':
                    let healthCommand = '';
                    if (service === 'backend' || service === 'both') {
                        healthCommand += 'curl -s http://localhost:8081/health';
                    }
                    if (service === 'both') {
                        healthCommand += ' && ';
                    }
                    if (service === 'frontend' || service === 'both') {
                        healthCommand += 'curl -s http://localhost:3000/';
                    }
                    command = healthCommand;
                    description = 'Performing health check';
                    break;
                default:
                    throw new Error(`Unknown action: ${action}. Available actions: restart-apache, restart-frontend, status, logs, health-check`);
            }
            console.log(`${description}: ${command}`);
            const output = (0, child_process_1.execSync)(command, {
                encoding: 'utf8',
                cwd: '/mnt/g/projects/gravitycar_no_code',
                timeout: 30000,
                maxBuffer: 1024 * 1024 // 1MB buffer
            });
            const result = {
                success: true,
                action,
                description,
                output: output.trim(),
                command
            };
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(result, null, 2))
            ]);
        }
        catch (error) {
            const errorResult = {
                success: false,
                action: options.input.action,
                error: error instanceof Error ? error.message : String(error)
            };
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(errorResult, null, 2))
            ]);
        }
    }
}
exports.GravitycarServerTool = GravitycarServerTool;
//# sourceMappingURL=gravitycarServerTool.js.map