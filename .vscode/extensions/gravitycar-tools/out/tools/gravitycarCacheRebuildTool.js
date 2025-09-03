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
exports.GravitycarCacheRebuildTool = void 0;
const vscode = __importStar(require("vscode"));
const child_process_1 = require("child_process");
const path = __importStar(require("path"));
class GravitycarCacheRebuildTool {
    /**
     * Get the current workspace root path dynamically
     */
    getWorkspaceRoot() {
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
    async invoke(options, token) {
        let command = '';
        try {
            const { fullSetup, clearOnly, skipDatabase, skipUsers, verbose } = options.input;
            // Build the PHP command
            command = 'php setup.php';
            // Add environment variables if needed
            const env = { ...process.env };
            // Add command line arguments based on input options
            if (clearOnly) {
                // For cache-only operations, we might need to create a separate script
                // For now, we'll run the full setup but document the limitation
                console.log('Note: clearOnly option not yet implemented in setup.php');
            }
            if (skipDatabase) {
                console.log('Note: skipDatabase option not yet implemented in setup.php');
            }
            if (skipUsers) {
                console.log('Note: skipUsers option not yet implemented in setup.php');
            }
            if (verbose) {
                env.VERBOSE = '1';
            }
            console.log(`Rebuilding Gravitycar cache and running setup...`);
            console.log(`Executing: ${command}`);
            let output = '';
            let success = false;
            try {
                output = (0, child_process_1.execSync)(command, {
                    encoding: 'utf8',
                    cwd: this.getWorkspaceRoot(),
                    timeout: 120000, // 2 minutes timeout for setup operations
                    maxBuffer: 2 * 1024 * 1024, // 2MB buffer for setup output
                    env: env
                });
                success = true;
            }
            catch (error) {
                // execSync throws on non-zero exit codes, but we still want the output
                console.log(`Error occurred while running setup: ` + error.message);
                if (error.stdout) {
                    output = error.stdout;
                }
                else if (error.stderr) {
                    output = error.stderr;
                }
                else {
                    output = error.message || String(error);
                }
                // Check if it's a setup failure vs a real error
                success = !output.includes('Setup failed') && !error.message.includes('Command failed');
                console.log(`Setup ${success ? 'completed with warnings' : 'failed'}`);
            }
            const result = {
                success,
                operation: fullSetup ? 'full_setup' : 'cache_rebuild',
                output: output.trim(),
                command,
                options: {
                    fullSetup: fullSetup || false,
                    clearOnly: clearOnly || false,
                    skipDatabase: skipDatabase || false,
                    skipUsers: skipUsers || false,
                    verbose: verbose || false
                }
            };
            console.log("Cache rebuild operation completed");
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(result, null, 2))
            ]);
        }
        catch (error) {
            console.log('Error occurred while trying to run setup command ' + command + ': ' + error.message);
            const errorResult = {
                success: false,
                error: error instanceof Error ? error.message : String(error),
                operation: 'cache_rebuild',
                command: command || 'Command not constructed',
                options: options.input
            };
            return new vscode.LanguageModelToolResult([
                new vscode.LanguageModelTextPart(JSON.stringify(errorResult, null, 2))
            ]);
        }
    }
}
exports.GravitycarCacheRebuildTool = GravitycarCacheRebuildTool;
//# sourceMappingURL=gravitycarCacheRebuildTool.js.map