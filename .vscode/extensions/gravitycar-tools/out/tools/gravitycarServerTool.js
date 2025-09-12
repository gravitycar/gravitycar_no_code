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
const path = __importStar(require("path"));
class GravitycarServerTool {
    /**
     * Get configurable backend URL
     */
    getBackendUrl() {
        const config = vscode.workspace.getConfiguration('gravitycar');
        return config.get('backendUrl') || process.env.GRAVITYCAR_BACKEND_URL || 'http://localhost:8081';
    }
    /**
     * Get configurable frontend URL
     */
    getFrontendUrl() {
        const config = vscode.workspace.getConfiguration('gravitycar');
        return config.get('frontendUrl') || process.env.GRAVITYCAR_FRONTEND_URL || 'http://localhost:3000';
    }
    /**
     * Get the current workspace root path dynamically
     */
    getWorkspaceRoot() {
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (workspaceFolders && workspaceFolders.length > 0) {
            return workspaceFolders[0].uri.fsPath;
        }
        // Fallback: if no workspace folders, try to determine from this extension's location
        // This extension is in .vscode/extensions/gravitycar-tools, so go up 3 levels
        const extensionPath = __dirname;
        const projectRoot = path.resolve(extensionPath, '../../../../..');
        return projectRoot;
    }
    /**
     * Check if the frontend server is running on por                            }, null, 2))
                        ]);
                        }
                    }
                    break;
                    
                case 'status':
     */
    checkFrontendStatus() {
        try {
            const processes = (0, child_process_1.execSync)('lsof -Pi :3000 -sTCP:LISTEN', {
                encoding: 'utf8',
                cwd: this.getWorkspaceRoot()
            }).trim();
            return { isRunning: true, processes };
        }
        catch (error) {
            return { isRunning: false, processes: '' };
        }
    }
    /**
     * Check if the frontend server is responding to HTTP requests
     */
    checkFrontendPing() {
        try {
            const frontendUrl = this.getFrontendUrl();
            const httpCode = (0, child_process_1.execSync)(`curl -s -o /dev/null -w "%{http_code}" --connect-timeout 3 --max-time 5 ${frontendUrl}`, {
                encoding: 'utf8',
                cwd: this.getWorkspaceRoot(),
                timeout: 8000 // 8 second timeout for the entire operation
            }).trim();
            return { isResponding: httpCode === '200', httpCode };
        }
        catch (error) {
            return {
                isResponding: false,
                httpCode: '000',
                error: error instanceof Error ? error.message : String(error)
            };
        }
    }
    /**
     * Wait for frontend server to be HTTP ready (with retries)
     */
    waitForHttpReady(maxAttempts = 6) {
        for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            const ping = this.checkFrontendPing();
            if (ping.isResponding) {
                return { isReady: true, httpCode: ping.httpCode, attempts: attempt };
            }
            // Wait 1 second between attempts (except on the last attempt)
            if (attempt < maxAttempts) {
                try {
                    (0, child_process_1.execSync)('sleep 1', { cwd: this.getWorkspaceRoot() });
                }
                catch (e) {
                    // Ignore sleep errors
                }
            }
        }
        const finalPing = this.checkFrontendPing();
        return { isReady: false, httpCode: finalPing.httpCode, attempts: maxAttempts };
    }
    /**
     * Stop frontend server processes
     */
    stopFrontendProcesses() {
        try {
            // Step 1: Graceful termination
            try {
                (0, child_process_1.execSync)('lsof -ti:3000 | xargs kill -TERM 2>/dev/null || true', {
                    encoding: 'utf8',
                    cwd: this.getWorkspaceRoot(),
                    timeout: 5000
                });
            }
            catch (e) {
                // Ignore errors for graceful termination
            }
            // Step 2: Wait for graceful shutdown
            (0, child_process_1.execSync)('sleep 2', { cwd: this.getWorkspaceRoot() });
            // Step 3: Force kill if still running
            try {
                (0, child_process_1.execSync)('lsof -ti:3000 | xargs kill -9 2>/dev/null || true', {
                    encoding: 'utf8',
                    cwd: this.getWorkspaceRoot(),
                    timeout: 5000
                });
            }
            catch (e) {
                // Ignore errors for force kill
            }
            // Step 4: Clean up npm/vite processes
            try {
                (0, child_process_1.execSync)('pkill -f "vite" 2>/dev/null || true', {
                    encoding: 'utf8',
                    cwd: this.getWorkspaceRoot(),
                    timeout: 5000
                });
                (0, child_process_1.execSync)('pkill -f "npm run dev" 2>/dev/null || true', {
                    encoding: 'utf8',
                    cwd: this.getWorkspaceRoot(),
                    timeout: 5000
                });
            }
            catch (e) {
                // Ignore errors for process cleanup
            }
            return 'Frontend processes stopped successfully';
        }
        catch (error) {
            return `Warning: Some processes may not have been stopped cleanly: ${error}`;
        }
    }
    /**
     * Start frontend server in background
     */
    startFrontendServer() {
        try {
            (0, child_process_1.execSync)('cd gravitycar-frontend && nohup npm run dev > ../logs/frontend.log 2>&1 &', {
                encoding: 'utf8',
                cwd: this.getWorkspaceRoot()
            });
            // Wait longer for startup and verify multiple times
            let attempts = 0;
            const maxAttempts = 10; // 10 seconds total wait time
            while (attempts < maxAttempts) {
                (0, child_process_1.execSync)('sleep 1', { cwd: this.getWorkspaceRoot() });
                attempts++;
                const status = this.checkFrontendStatus();
                if (status.isRunning) {
                    // Server is running, now wait a bit more for HTTP readiness
                    (0, child_process_1.execSync)('sleep 2', { cwd: this.getWorkspaceRoot() });
                    return 'Frontend server started successfully';
                }
                // If we're halfway through attempts, give it more time
                if (attempts === 5) {
                    (0, child_process_1.execSync)('sleep 2', { cwd: this.getWorkspaceRoot() });
                }
            }
            throw new Error('Frontend server failed to start within expected time');
        }
        catch (error) {
            throw new Error(`Failed to start frontend server: ${error}`);
        }
    }
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
                case 'status_frontend':
                case 'status-frontend':
                    if (service === 'backend') {
                        throw new Error('Cannot check frontend status when service is set to backend');
                    }
                    const status = this.checkFrontendStatus();
                    const statusMessage = status.isRunning
                        ? `✅ Frontend server is running on port 3000\n${status.processes}`
                        : 'ℹ Frontend server is not running on port 3000';
                    return new vscode.LanguageModelToolResult([
                        new vscode.LanguageModelTextPart(JSON.stringify({
                            success: true,
                            action,
                            description: 'Frontend server status check',
                            isRunning: status.isRunning,
                            output: statusMessage
                        }, null, 2))
                    ]);
                case 'ping_frontend':
                case 'ping-frontend':
                    if (service === 'backend') {
                        throw new Error('Cannot ping frontend when service is set to backend');
                    }
                    const ping = this.checkFrontendPing();
                    const pingMessage = ping.isResponding
                        ? `✅ Frontend server is responding (HTTP ${ping.httpCode})`
                        : `❌ Frontend server is not responding (HTTP ${ping.httpCode})${ping.error ? ` - ${ping.error}` : ''}`;
                    return new vscode.LanguageModelToolResult([
                        new vscode.LanguageModelTextPart(JSON.stringify({
                            success: true,
                            action,
                            description: 'Frontend server ping test',
                            isResponding: ping.isResponding,
                            httpCode: ping.httpCode,
                            output: pingMessage
                        }, null, 2))
                    ]);
                case 'restart_frontend':
                case 'restart-frontend':
                    if (service === 'backend') {
                        throw new Error('Cannot restart frontend when service is set to backend');
                    }
                    let restartMessage = '';
                    const currentStatus = this.checkFrontendStatus();
                    if (currentStatus.isRunning) {
                        restartMessage += 'Port 3000 is in use, stopping existing processes...\n';
                        restartMessage += this.stopFrontendProcesses() + '\n';
                    }
                    restartMessage += 'Starting React development server...\n';
                    try {
                        restartMessage += this.startFrontendServer();
                        const finalStatus = this.checkFrontendStatus();
                        if (finalStatus.isRunning) {
                            restartMessage += '\nWaiting for HTTP readiness...';
                            const httpReady = this.waitForHttpReady(6); // 6 seconds max wait
                            if (httpReady.isReady) {
                                restartMessage += '\n✅ Frontend server started and responding successfully on port 3000';
                            }
                            else {
                                restartMessage += `\n⚠️ Frontend server started but not responding to HTTP requests after ${httpReady.attempts} attempts (HTTP ${httpReady.httpCode})`;
                            }
                            return new vscode.LanguageModelToolResult([
                                new vscode.LanguageModelTextPart(JSON.stringify({
                                    success: finalStatus.isRunning,
                                    action,
                                    description: 'Smart restart of frontend development server',
                                    isRunning: finalStatus.isRunning,
                                    isResponding: httpReady.isReady,
                                    httpCode: httpReady.httpCode,
                                    output: restartMessage
                                }, null, 2))
                            ]);
                        }
                        else {
                            restartMessage += '\n❌ Frontend server failed to start';
                            return new vscode.LanguageModelToolResult([
                                new vscode.LanguageModelTextPart(JSON.stringify({
                                    success: false,
                                    action,
                                    description: 'Smart restart of frontend development server',
                                    isRunning: false,
                                    isResponding: false,
                                    output: restartMessage
                                }, null, 2))
                            ]);
                        }
                    }
                    catch (error) {
                        restartMessage += `\n❌ Failed to start frontend server: ${error}`;
                        return new vscode.LanguageModelToolResult([
                            new vscode.LanguageModelTextPart(JSON.stringify({
                                success: false,
                                action,
                                description: 'Smart restart of frontend development server',
                                error: error instanceof Error ? error.message : String(error),
                                output: restartMessage
                            }, null, 2))
                        ]);
                    }
                    break;
                case 'stop_frontend':
                case 'stop-frontend':
                    if (service === 'backend') {
                        throw new Error('Cannot stop frontend when service is set to backend');
                    }
                    const stopStatus = this.checkFrontendStatus();
                    let stopMessage = '';
                    if (stopStatus.isRunning) {
                        stopMessage += 'Stopping frontend server on port 3000...\n';
                        stopMessage += this.stopFrontendProcesses();
                        const finalStopStatus = this.checkFrontendStatus();
                        if (!finalStopStatus.isRunning) {
                            stopMessage += '\n✅ Frontend server stopped successfully';
                        }
                        else {
                            stopMessage += '\n⚠️ Some frontend processes may still be running';
                        }
                        return new vscode.LanguageModelToolResult([
                            new vscode.LanguageModelTextPart(JSON.stringify({
                                success: !finalStopStatus.isRunning,
                                action,
                                description: 'Stop frontend development server',
                                wasRunning: stopStatus.isRunning,
                                isRunning: finalStopStatus.isRunning,
                                output: stopMessage
                            }, null, 2))
                        ]);
                    }
                    else {
                        stopMessage = 'ℹ Frontend server is not running on port 3000';
                        return new vscode.LanguageModelToolResult([
                            new vscode.LanguageModelTextPart(JSON.stringify({
                                success: true,
                                action,
                                description: 'Stop frontend development server',
                                wasRunning: false,
                                isRunning: false,
                                output: stopMessage
                            }, null, 2))
                        ]);
                    }
                    break;
                case 'start_frontend':
                case 'start-frontend':
                    if (service === 'backend') {
                        throw new Error('Cannot start frontend when service is set to backend');
                    }
                    const startStatus = this.checkFrontendStatus();
                    if (startStatus.isRunning) {
                        return new vscode.LanguageModelToolResult([
                            new vscode.LanguageModelTextPart(JSON.stringify({
                                success: false,
                                action,
                                description: 'Start frontend development server',
                                isRunning: true,
                                error: 'Port 3000 is already in use. Use restart-frontend to replace the running server.',
                                output: '❌ Port 3000 is already in use. Use restart-frontend to replace the running server.'
                            }, null, 2))
                        ]);
                    }
                    else {
                        let startMessage = 'Starting React development server...\n';
                        try {
                            startMessage += this.startFrontendServer();
                            const finalStartStatus = this.checkFrontendStatus();
                            if (finalStartStatus.isRunning) {
                                startMessage += '\nWaiting for HTTP readiness...';
                                const httpReady = this.waitForHttpReady(6); // 6 seconds max wait
                                if (httpReady.isReady) {
                                    startMessage += '\n✅ Frontend server started and responding successfully on port 3000';
                                }
                                else {
                                    startMessage += `\n⚠️ Frontend server started but not responding to HTTP requests after ${httpReady.attempts} attempts (HTTP ${httpReady.httpCode})`;
                                }
                                return new vscode.LanguageModelToolResult([
                                    new vscode.LanguageModelTextPart(JSON.stringify({
                                        success: finalStartStatus.isRunning,
                                        action,
                                        description: 'Start frontend development server',
                                        isRunning: finalStartStatus.isRunning,
                                        isResponding: httpReady.isReady,
                                        httpCode: httpReady.httpCode,
                                        output: startMessage
                                    }, null, 2))
                                ]);
                            }
                            else {
                                startMessage += '\n❌ Frontend server failed to start';
                                return new vscode.LanguageModelToolResult([
                                    new vscode.LanguageModelTextPart(JSON.stringify({
                                        success: false,
                                        action,
                                        description: 'Start frontend development server',
                                        isRunning: false,
                                        isResponding: false,
                                        output: startMessage
                                    }, null, 2))
                                ]);
                            }
                        }
                        catch (error) {
                            startMessage += `\n❌ Failed to start frontend server: ${error}`;
                            return new vscode.LanguageModelToolResult([
                                new vscode.LanguageModelTextPart(JSON.stringify({
                                    success: false,
                                    action,
                                    description: 'Start frontend development server',
                                    error: error instanceof Error ? error.message : String(error),
                                    output: startMessage
                                }, null, 2))
                            ]);
                        }
                    }
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
                        command = 'tail -n 50 logs/frontend.log';
                        description = 'Showing recent frontend development server logs';
                    }
                    break;
                case 'health_check':
                case 'health-check':
                    let healthCommand = '';
                    if (service === 'backend' || service === 'both') {
                        healthCommand += `curl -s ${this.getBackendUrl()}/health`;
                    }
                    if (service === 'both') {
                        healthCommand += ' && ';
                    }
                    if (service === 'frontend' || service === 'both') {
                        healthCommand += `curl -s ${this.getFrontendUrl()}/`;
                    }
                    command = healthCommand;
                    description = 'Performing health check';
                    break;
                default:
                    throw new Error(`Unknown action: ${action}. Available actions: restart-apache, restart-frontend, start-frontend, stop-frontend, status-frontend, ping-frontend, status, logs, health-check`);
            }
            console.log(`${description}: ${command}`);
            const output = (0, child_process_1.execSync)(command, {
                encoding: 'utf8',
                cwd: this.getWorkspaceRoot(),
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