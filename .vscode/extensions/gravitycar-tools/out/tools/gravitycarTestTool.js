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
exports.GravitycarTestTool = void 0;
const vscode = __importStar(require("vscode"));
const child_process_1 = require("child_process");
class GravitycarTestTool {
    async invoke(options, token) {
        let command = '';
        try {
            const { testType, filter, testFile, debug, testdox } = options.input;
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
                output = (0, child_process_1.execSync)(command, {
                    encoding: 'utf8',
                    cwd: '/mnt/g/projects/gravitycar_no_code',
                    timeout: 180000, // 3 minutes for coverage tests (they take longer)
                    maxBuffer: 4 * 1024 * 1024, // 4MB buffer for coverage output
                    env: env
                });
                success = true;
            }
            catch (error) {
                // execSync throws on non-zero exit codes, but we still want the output
                console.log(`Error occurred while running ${testType} tests: ` + error.message);
                if (error.stdout) {
                    output = error.stdout;
                }
                else if (error.stderr) {
                    output = error.stderr;
                }
                else {
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
        }
        catch (error) {
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
exports.GravitycarTestTool = GravitycarTestTool;
//# sourceMappingURL=gravitycarTestTool.js.map