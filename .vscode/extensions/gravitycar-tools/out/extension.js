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
exports.activate = activate;
exports.deactivate = deactivate;
const vscode = __importStar(require("vscode"));
const gravitycarApiTool_1 = require("./tools/gravitycarApiTool");
const gravitycarTestTool_1 = require("./tools/gravitycarTestTool");
const gravitycarServerTool_1 = require("./tools/gravitycarServerTool");
const gravitycarCacheRebuildTool_1 = require("./tools/gravitycarCacheRebuildTool");
const gravitycarPhpDebugScriptTool_1 = require("./tools/gravitycarPhpDebugScriptTool");
function activate(context) {
    console.log('Activating Gravitycar Tools extension...');
    // Register all Gravitycar development tools
    registerTools(context);
    console.log('Gravitycar Tools extension activated successfully');
}
function registerTools(context) {
    // Register Gravitycar API tool
    const apiTool = new gravitycarApiTool_1.GravitycarApiTool();
    context.subscriptions.push(vscode.lm.registerTool('gravitycar_api_call', apiTool));
    // Register Gravitycar Test Runner tool  
    const testTool = new gravitycarTestTool_1.GravitycarTestTool();
    context.subscriptions.push(vscode.lm.registerTool('gravitycar_test_runner', testTool));
    // Register Gravitycar Server Control tool
    const serverTool = new gravitycarServerTool_1.GravitycarServerTool();
    context.subscriptions.push(vscode.lm.registerTool('gravitycar_server_control', serverTool));
    // Register Gravitycar Cache Rebuild tool
    const cacheRebuildTool = new gravitycarCacheRebuildTool_1.GravitycarCacheRebuildTool();
    context.subscriptions.push(vscode.lm.registerTool('gravitycar_cache_rebuild', cacheRebuildTool));
    // Register Gravitycar PHP Debug Script tool
    const phpDebugScriptTool = new gravitycarPhpDebugScriptTool_1.GravitycarPhpDebugScriptTool();
    context.subscriptions.push(vscode.lm.registerTool('gravitycar_php_debug_scripts', phpDebugScriptTool));
    console.log('All Gravitycar tools registered successfully');
}
function deactivate() {
    console.log('Gravitycar Tools extension deactivated');
}
//# sourceMappingURL=extension.js.map