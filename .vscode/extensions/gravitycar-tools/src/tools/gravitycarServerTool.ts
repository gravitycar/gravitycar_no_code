import * as vscode from 'vscode';
import { execSync } from 'child_process';

interface ServerControlInput {
    action: string;
    service?: string;
}

export class GravitycarServerTool implements vscode.LanguageModelTool<ServerControlInput> {

    async invoke(
        options: vscode.LanguageModelToolInvocationOptions<ServerControlInput>,
        token: vscode.CancellationToken
    ): Promise<vscode.LanguageModelToolResult> {
        try {
            const { action, service = 'both' } = options.input as any;
            
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
                    } else if (service === 'frontend') {
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
            
            const output = execSync(command, { 
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
            
        } catch (error) {
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
