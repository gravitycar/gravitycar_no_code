import * as vscode from 'vscode';
import { execSync } from 'child_process';

interface TestRunInput {
    test_type: string;
    test_path?: string;
    coverage?: boolean;
    verbose?: boolean;
}

export class GravitycarTestTool implements vscode.LanguageModelTool<TestRunInput> {

    async invoke(
        options: vscode.LanguageModelToolInvocationOptions<TestRunInput>,
        token: vscode.CancellationToken
    ): Promise<vscode.LanguageModelToolResult> {
        try {
            const { test_type, test_path, coverage, verbose } = options.input as any;
            
            let command = '';
            
            switch (test_type) {
                case 'unit':
                    command = './run-phpunit.sh';
                    if (test_path) {
                        command += ` ${test_path}`;
                    }
                    if (coverage) {
                        command += ' --coverage-text';
                    }
                    if (verbose) {
                        command += ' --verbose';
                    }
                    break;
                    
                case 'integration':
                    command = './run-phpunit.sh Tests/Integration/';
                    if (test_path) {
                        command += test_path;
                    }
                    break;
                    
                case 'feature':
                    command = './run-phpunit.sh Tests/Feature/';
                    if (test_path) {
                        command += test_path;
                    }
                    break;
                    
                case 'all':
                default:
                    command = './run-phpunit.sh';
                    if (coverage) {
                        command += ' --coverage-text';
                    }
                    break;
            }
            
            console.log(`Executing: ${command}`);
            
            const output = execSync(command, { 
                encoding: 'utf8',
                cwd: '/mnt/g/projects/gravitycar_no_code',
                timeout: 120000, // 2 minutes for tests
                maxBuffer: 2 * 1024 * 1024 // 2MB buffer for test output
            });
            
            const result = {
                success: !output.includes('FAILURES!') && !output.includes('ERRORS!'),
                test_type,
                output: output.trim(),
                command
            };
            
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(result, null, 2))
            ]);
            
        } catch (error) {
            const errorResult = {
                success: false,
                error: error instanceof Error ? error.message : String(error),
                test_type: options.input.test_type
            };
            
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(errorResult, null, 2))
            ]);
        }
    }
}
