import * as vscode from 'vscode';
import { execSync } from 'child_process';
import * as path from 'path';

interface PhpDebugScriptInput {
    scriptFile: string;
    verbose?: boolean;
    showErrors?: boolean;
    timeout?: number;
}

export class GravitycarPhpDebugScriptTool implements vscode.LanguageModelTool<PhpDebugScriptInput> {

    /**
     * Get the current workspace root path dynamically
     */
    private getWorkspaceRoot(): string {
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (workspaceFolders && workspaceFolders.length > 0) {
            return workspaceFolders[0].uri.fsPath;
        }
        
        // Fallback: if no workspace folders, try to determine from this extension's location
        // This extension is in .vscode/extensions/gravitycar-tools/out/tools, so go up 4 levels
        const extensionPath = __dirname;
        const projectRoot = path.resolve(extensionPath, '../../../..');
        return projectRoot;
    }

    /**
     * Validate script file path
     */
    private validateScriptPath(scriptFile: string, workspaceRoot: string): string {
        // Remove leading slash if present
        const cleanFile = scriptFile.startsWith('/') ? scriptFile.substring(1) : scriptFile;
        
        // Ensure it's in tmp/ directory
        if (!cleanFile.startsWith('tmp/')) {
            throw new Error('Script file must be in the tmp/ directory');
        }
        
        // Ensure it's a PHP file
        if (!cleanFile.endsWith('.php')) {
            throw new Error('Script file must be a PHP file (.php extension)');
        }
        
        // Build full path
        const fullPath = path.join(workspaceRoot, cleanFile);
        
        // Additional security check - ensure the resolved path is still within tmp/
        const tmpDir = path.join(workspaceRoot, 'tmp');
        const resolvedPath = path.resolve(fullPath);
        const resolvedTmpDir = path.resolve(tmpDir);
        
        if (!resolvedPath.startsWith(resolvedTmpDir)) {
            throw new Error('Script file must be within the tmp/ directory (no path traversal allowed)');
        }
        
        return fullPath;
    }

    async invoke(
        options: vscode.LanguageModelToolInvocationOptions<PhpDebugScriptInput>,
        token: vscode.CancellationToken
    ): Promise<vscode.LanguageModelToolResult> {
        let command = '';
        
        try {
            const { scriptFile, verbose, showErrors, timeout } = options.input as any;
            
            if (!scriptFile) {
                throw new Error('scriptFile parameter is required');
            }
            
            const workspaceRoot = this.getWorkspaceRoot();
            const fullScriptPath = this.validateScriptPath(scriptFile, workspaceRoot);
            
            // Check if file exists
            const fs = require('fs');
            if (!fs.existsSync(fullScriptPath)) {
                throw new Error(`Script file not found: ${scriptFile}`);
            }
            
            // Build the PHP command
            command = `php ${path.basename(fullScriptPath)}`;
            
            // Set up environment
            const env = { ...process.env };
            if (verbose) {
                env.VERBOSE = '1';
            }
            
            console.log(`Executing PHP debug script: ${scriptFile}`);
            console.log(`Full path: ${fullScriptPath}`);
            console.log(`Command: ${command}`);
            
            let output = '';
            let error = '';
            let success = false;
            let exitCode = 1;
            
            try {
                const result = execSync(command, { 
                    encoding: 'utf8',
                    cwd: path.dirname(fullScriptPath), // Run from tmp/ directory
                    timeout: timeout || 60000, // Default 60 second timeout
                    maxBuffer: 4 * 1024 * 1024, // 4MB buffer for debug output
                    env: env
                });
                output = result;
                success = true;
                exitCode = 0;
            } catch (execError: any) {
                // execSync throws on non-zero exit codes, but we still want the output
                console.log(`PHP script execution completed with non-zero exit code: ` + execError.message);
                
                if (execError.stdout) {
                    output = execError.stdout;
                }
                if (execError.stderr) {
                    error = execError.stderr;
                }
                if (!output && !error) {
                    error = execError.message || String(execError);
                }
                
                // Extract exit code if available
                if (execError.status !== undefined) {
                    exitCode = execError.status;
                } else if (execError.signal) {
                    exitCode = -1; // Signal termination
                } else {
                    exitCode = 1; // General error
                }
                
                // Consider it successful if we got output, even with non-zero exit
                success = output.length > 0;
            }
            
            const result = {
                success,
                scriptFile,
                exitCode,
                output: output.trim(),
                error: error.trim(),
                command,
                executionPath: path.dirname(fullScriptPath),
                options: {
                    verbose: verbose || false,
                    showErrors: showErrors || false,
                    timeout: timeout || 60000
                }
            };
            
            console.log(`PHP script execution completed. Exit code: ${exitCode}`);
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(result, null, 2))
            ]);
            
        } catch (error: any) {
            console.log('Error occurred while trying to run PHP script ' + command + ': ' + error.message);
            const errorResult = {
                success: false,
                error: error instanceof Error ? error.message : String(error),
                scriptFile: options.input.scriptFile || 'unknown',
                exitCode: -1,
                command: command || 'Command not constructed',
                options: options.input
            };
            
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(errorResult, null, 2))
            ]);
        }
    }
}
