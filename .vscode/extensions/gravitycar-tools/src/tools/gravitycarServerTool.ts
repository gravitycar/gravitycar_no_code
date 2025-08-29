import * as vscode from 'vscode';
import { execSync } from 'child_process';

interface ServerControlInput {
    action: string;
}

export class GravitycarServerTool implements vscode.LanguageModelTool<ServerControlInput> {

    async invoke(
        options: vscode.LanguageModelToolInvocationOptions<ServerControlInput>,
        token: vscode.CancellationToken
    ): Promise<vscode.LanguageModelToolResult> {
        try {
            const { action } = options.input as any;
            
            let command = '';
            let description = '';
            
            switch (action) {
                case 'restart_apache':
                    command = './restart-apache.sh';
                    description = 'Restarting Apache server';
                    break;
                    
                case 'restart_frontend':
                    command = './restart-frontend.sh';
                    description = 'Restarting frontend development server';
                    break;
                    
                case 'status':
                    command = 'systemctl status apache2 && ps aux | grep -E "(npm|node|vite)" | grep -v grep';
                    description = 'Checking server status';
                    break;
                    
                case 'logs':
                    command = 'tail -n 50 logs/gravitycar.log';
                    description = 'Showing recent Gravitycar logs';
                    break;
                    
                case 'health_check':
                    command = 'curl -s http://localhost:8081/health.php && curl -s http://localhost:5173/';
                    description = 'Performing health check on both servers';
                    break;
                    
                default:
                    throw new Error(`Unknown action: ${action}`);
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
