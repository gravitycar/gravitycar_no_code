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
        try {
            const { test_type, test_path, coverage, verbose } = options.input;
            let command = '';
            switch (test_type) {
                case 'unit':
                    command = 'vendor/bin/phpunit Tests/Unit/';
                    if (test_path) {
                        command += test_path;
                    }
                    if (coverage) {
                        command += ' --coverage-text';
                    }
                    if (verbose) {
                        command += ' --verbose';
                    }
                    break;
                case 'integration':
                    command = 'vendor/bin/phpunit Tests/Integration/';
                    if (test_path) {
                        command += test_path;
                    }
                    break;
                case 'feature':
                    command = 'vendor/bin/phpunit Tests/Feature/';
                    if (test_path) {
                        command += test_path;
                    }
                    break;
                case 'all':
                default:
                    command = 'vendor/bin/phpunit';
                    if (coverage) {
                        command += ' --coverage-text';
                    }
                    break;
            }
            console.log(`Executing: ${command}`);
            const output = (0, child_process_1.execSync)(command, {
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
        }
        catch (error) {
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
exports.GravitycarTestTool = GravitycarTestTool;
//# sourceMappingURL=gravitycarTestTool.js.map