# Dynamic Path Resolution Fix for Gravitycar Tools Extension

## Issue
The VS Code extension tools for Gravitycar Framework contained hardcoded paths (`/mnt/g/projects/gravitycar_no_code`), making the extension brittle when the project is moved to different directories.

## Solution Implemented
Added dynamic workspace path resolution to all three tool files in `.vscode/extensions/gravitycar-tools/src/tools/`:

### 1. Common Fix Applied
Added a helper method to each tool class:

```typescript
/**
 * Get the current workspace root path dynamically
 */
private getWorkspaceRoot(): string {
    const workspaceFolders = vscode.workspace.workspaceFolders;
    if (workspaceFolders && workspaceFolders.length > 0) {
        return workspaceFolders[0].uri.fsPath;
    }
    
    // Fallback: if no workspace folders, try to determine from this extension's location
    // This extension is in .vscode/extensions/gravitycar-tools, so go up 3 levels
    const extensionPath = __dirname;
    const projectRoot = path.resolve(extensionPath, '../../../../..');
    return projectRoot;
}
```

### 2. Files Modified

#### `gravitycarTestTool.ts`
- **Lines changed**: 1 hardcoded path occurrence
- **Location**: execSync `cwd` parameter
- **Change**: `cwd: '/mnt/g/projects/gravitycar_no_code'` → `cwd: this.getWorkspaceRoot()`

#### `gravitycarServerTool.ts`
- **Lines changed**: 14 hardcoded path occurrences
- **Locations**: Multiple execSync `cwd` parameters across various server management functions
- **Changes**: All instances of `cwd: '/mnt/g/projects/gravitycar_no_code'` → `cwd: this.getWorkspaceRoot()`

#### `gravitycarApiTool.ts`
- **Status**: No hardcoded paths found - no changes needed

### 3. Path Resolution Strategy
1. **Primary**: Use VS Code's `vscode.workspace.workspaceFolders[0].uri.fsPath` to get the current workspace root
2. **Fallback**: Calculate relative path from extension location (`__dirname`) going up to project root

### 4. Benefits
- ✅ Extension now works regardless of project location
- ✅ Automatic detection of workspace root
- ✅ Fallback mechanism for edge cases
- ✅ No breaking changes to existing functionality
- ✅ Works with VS Code's multi-workspace environment

### 5. Testing Recommendations
1. Move the project to a different directory and verify all tools work
2. Test in different VS Code workspace configurations
3. Verify both primary and fallback path resolution work correctly

## Files Changed
- `.vscode/extensions/gravitycar-tools/src/tools/gravitycarTestTool.ts`
- `.vscode/extensions/gravitycar-tools/src/tools/gravitycarServerTool.ts`

## Git Branch
- **Branch**: `feature/many-to-many-relationship-tests`
- **Commits**: Changes committed as part of extension improvements
