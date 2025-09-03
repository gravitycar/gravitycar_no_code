import * as vscode from 'vscode';
import { GravitycarApiTool } from './tools/gravitycarApiTool';
import { GravitycarTestTool } from './tools/gravitycarTestTool';
import { GravitycarServerTool } from './tools/gravitycarServerTool';
import { GravitycarCacheRebuildTool } from './tools/gravitycarCacheRebuildTool';

export function activate(context: vscode.ExtensionContext) {
    console.log('Activating Gravitycar Tools extension...');

    // Register all Gravitycar development tools
    registerTools(context);

    console.log('Gravitycar Tools extension activated successfully');
}

function registerTools(context: vscode.ExtensionContext) {
    // Register Gravitycar API tool
    const apiTool = new GravitycarApiTool();
    context.subscriptions.push(
        vscode.lm.registerTool('gravitycar_api_call', apiTool)
    );

    // Register Gravitycar Test Runner tool  
    const testTool = new GravitycarTestTool();
    context.subscriptions.push(
        vscode.lm.registerTool('gravitycar_test_runner', testTool)
    );

    // Register Gravitycar Server Control tool
    const serverTool = new GravitycarServerTool();
    context.subscriptions.push(
        vscode.lm.registerTool('gravitycar_server_control', serverTool)
    );

    // Register Gravitycar Cache Rebuild tool
    const cacheRebuildTool = new GravitycarCacheRebuildTool();
    context.subscriptions.push(
        vscode.lm.registerTool('gravitycar_cache_rebuild', cacheRebuildTool)
    );

    console.log('All Gravitycar tools registered successfully');
}

export function deactivate() {
    console.log('Gravitycar Tools extension deactivated');
}
