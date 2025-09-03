import * as vscode from 'vscode';
import { execSync } from 'child_process';
import * as path from 'path';

interface TestRunInput {
    testType: string;
    filter?: string;
    testFile?: string;
    debug?: boolean;
    testdox?: boolean;
}

export class GravitycarTestTool implements vscode.LanguageModelTool<TestRunInput> {

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

    async invoke(
        options: vscode.LanguageModelToolInvocationOptions<TestRunInput>,
        token: vscode.CancellationToken
    ): Promise<vscode.LanguageModelToolResult> {
        let command = '';
        
        try {
            const { testType, filter, testFile, debug, testdox } = options.input as any;
            
            // Handle coverage test type 
            const isCoverage = testType === 'coverage';
            
            console.log(`Running ${testType} tests...`);
            switch (testType) {
                case 'unit':
                case 'coverage':
                    command = 'vendor/bin/phpunit Tests/Unit/';
                    if (testFile) {
                        command += testFile;
                    }
                    if (isCoverage) {
                        command += ' --coverage-text';
                    }
                    if (filter) {
                        command += ` --filter "${filter}"`;
                    }
                    if (debug) {
                        command += ' --debug';
                    }
                    if (testdox) {
                        command += ' --testdox';
                    }
                    break;
                    
                case 'integration':
                    command = 'vendor/bin/phpunit Tests/Integration/';
                    if (testFile) {
                        command += testFile;
                    }
                    if (filter) {
                        command += ` --filter "${filter}"`;
                    }
                    if (debug) {
                        command += ' --debug';
                    }
                    if (testdox) {
                        command += ' --testdox';
                    }
                    break;
                    
                case 'feature':
                    command = 'vendor/bin/phpunit Tests/Feature/';
                    if (testFile) {
                        command += testFile;
                    }
                    if (filter) {
                        command += ` --filter "${filter}"`;
                    }
                    if (debug) {
                        command += ' --debug';
                    }
                    if (testdox) {
                        command += ' --testdox';
                    }
                    break;
                    
                case 'specific':
                    if (!testFile) {
                        throw new Error('testFile parameter is required for specific test type');
                    }
                    command = `vendor/bin/phpunit ${testFile}`;
                    if (filter) {
                        command += ` --filter "${filter}"`;
                    }
                    if (debug) {
                        command += ' --debug';
                    }
                    if (testdox) {
                        command += ' --testdox';
                    }
                    break;
                    
                case 'all':
                default:
                    command = 'vendor/bin/phpunit';
                    if (filter) {
                        command += ` --filter "${filter}"`;
                    }
                    if (debug) {
                        command += ' --debug';
                    }
                    if (testdox) {
                        command += ' --testdox';
                    }
                    break;
            }
            
            console.log(`Executing: ${command}`);
            
            // Set up environment for coverage if needed
            const env = { ...process.env };
            if (isCoverage) {
                env.XDEBUG_MODE = 'coverage';
            }
            
            let output = '';
            let success = false;
            
            try {
                output = execSync(command, { 
                    encoding: 'utf8',
                    cwd: this.getWorkspaceRoot(),
                    timeout: 180000, // 3 minutes for coverage tests (they take longer)
                    maxBuffer: 4 * 1024 * 1024, // 4MB buffer for coverage output
                    env: env
                });
                success = true;
            } catch (error: any) {
                // execSync throws on non-zero exit codes, but we still want the output
                console.log(`Error occurred while running ${testType} tests: ` + error.message);
                if (error.stdout) {
                    output = error.stdout;
                } else if (error.stderr) {
                    output = error.stderr;
                } else {
                    output = error.message || String(error);
                }
                // Check if it's a test failure vs a real error
                success = !output.includes('FAILURES!') && !output.includes('ERRORS!') && !error.message.includes('Command failed');
                console.log(`Test ${success ? 'passed' : 'failed'}`);
            }
            
            const result = {
                success,
                testType,
                output: output.trim(),
                command
            };
            
            console.log("No problems...");
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(result, null, 2))
            ]);
            
        } catch (error: any) {
            console.log('error occurred while trying to run the command ' + command + ': ' + error.message);
            const errorResult = {
                success: false,
                error: error instanceof Error ? error.message : String(error),
                testType: options.input.testType,
                command: command || 'Command not constructed'
            };
            
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(errorResult, null, 2))
            ]);
        }
    }
}
