# 📋 VSCode Log Reading Tasks Documentation

This document describes the VSCode tasks specifically designed for AI coding agents to read and analyze log files efficiently. These tasks provide interactive parameter selection and robust error handling.

## 🤖 Tasks Overview

All tasks use helper scripts located in the `tasks_scripts/` directory for robust file handling and error management.

### Application Log Tasks

#### 1. Read Latest Log File
- **Purpose**: Interactive reading of application log files with parameter selection
- **Script**: `tasks_scripts/read_log.sh`

#### 2. **List Available Log Files**
- **Purpose**: Shows all log files with timestamps and sizes
- **Usage**: No prompts - immediate execution
- **Output**: Files sorted by modification time (newest first)
- **AI Agent Use**: Use this first to understand available log files

#### 3. **Quick Read Latest Log (20 lines)**
- **Purpose**: Fast access to main log file
- **Usage**: No prompts - reads last 20 lines from `gravitycar.log`
- **AI Agent Use**: Fastest way to check recent activity

#### 4. **Read Today's Log File**
- **Purpose**: Reads today's dated log file
- **Usage**: Prompts for number of lines to read
- **Format**: Looks for `gravitycar-YYYY-MM-DD.log`
- **AI Agent Use**: Good for daily log analysis

#### 5. **Search All Recent Logs**
- **Purpose**: Search for specific text across recent log files
- **Usage**: Prompts for search term
- **Scope**: Searches files modified within last 7 days
- **AI Agent Use**: Excellent for error tracking and pattern analysis

### Apache Server Log Tasks

#### 5. Read Apache Error Log  
- **Purpose**: Interactive reading of Apache error logs with automatic gzip handling
- **Script**: `tasks_scripts/read_apache_log.sh`
- **Parameters**: 
  - Log Index (0=current, 1=yesterday, etc.)
  - Number of lines (default: 20)
- **Features**: Handles both current logs and gzipped archives

#### 6. List Apache Error Logs
- **Purpose**: Quick listing of all available Apache error log files
- **Command**: Direct `sudo ls -lht /var/log/apache2/error.log*`
- **Output**: File sizes, dates, and archive status

#### 7. Quick Read Apache Error Log (20 lines)  
- **Purpose**: Fast access to current Apache error log
- **Command**: Direct `sudo tail -n 20 /var/log/apache2/error.log`
- **Features**: No prompts - instant results

#### 8. Search Apache Error Logs
- **Purpose**: Search across multiple Apache log files for specific terms
- **Script**: `tasks_scripts/search_apache_logs.sh`
- **Parameters**: Search term
- **Features**: Searches current and archived (gzipped) logs

#### 9. Apache Error Log Summary
- **Purpose**: Health assessment and overview of Apache error logs
- **Script**: `tasks_scripts/apache_log_summary.sh`
- **Features**: File sizes, error counts, latest entries

#### 7. **List Apache Error Logs**
- **Purpose**: Shows all Apache error log files with timestamps and sizes
- **Usage**: No prompts - immediate execution
- **Output**: Files sorted by modification time (newest first)
- **Special Features**: Shows both current (.log) and archived (.gz) files
- **AI Agent Use**: Use this first to understand available Apache log files

#### 8. **Quick Read Apache Error Log (20 lines)**
- **Purpose**: Fast access to current Apache error log
- **Usage**: No prompts - reads last 20 lines from current `error.log`
- **AI Agent Use**: Fastest way to check recent Apache errors

#### 9. **Search Apache Error Logs**
- **Purpose**: Search for specific text in Apache error logs
- **Usage**: Prompts for search term
- **Scope**: Searches current log, recent archived logs, and gzipped logs
- **Implementation**: Uses `search_apache_logs.sh` script
- **Special Features**: Automatically handles gzipped log decompression
- **AI Agent Use**: Excellent for Apache error tracking and troubleshooting

#### 10. **Apache Error Log Summary**
- **Purpose**: Provides comprehensive Apache error log analysis
- **Usage**: No prompts - immediate execution
- **Output**: File sizes, error pattern counts, latest entries, archive statistics
- **Implementation**: Uses `apache_log_summary.sh` script
- **AI Agent Use**: Best for quick Apache health assessment

## Log File Structure

### Application Log Files

#### File Naming Convention
- Main log: `gravitycar.log`
- Daily logs: `gravitycar-YYYY-MM-DD.log`
- Rotated logs: `gravitycar.log.1`, `gravitycar.log.2`, etc.

#### Log File Indexing
- **Index 0**: Most recent log file
- **Index 1**: Next most recent log file  
- **Index 2**: Third most recent, etc.

The indexing is based on file modification time, so the most recently updated file is always index 0.

### Apache Log Files

#### File Naming Convention
- Current error log: `/var/log/apache2/error.log`
- Archived logs: `/var/log/apache2/error.log.1`, `/var/log/apache2/error.log.2`, etc.
- Gzipped archives: `/var/log/apache2/error.log.3.gz`, `/var/log/apache2/error.log.4.gz`, etc.

#### Apache Log Indexing
- **Index 0**: Current error.log file
- **Index 1**: error.log.1 (most recent archived)
- **Index 2**: error.log.2.gz (older, gzipped)
- etc.

#### Permission Requirements
Apache logs require elevated permissions:
- Use `sudo` for direct access
- User must be in `adm` group, or
- Tasks automatically handle `sudo` requirements

## Helper Scripts

### Application Log Scripts

#### `read_log.sh`
The workspace includes a helper script that provides:
- ✅ Robust error handling
- ✅ Clear file information display
- ✅ Index validation
- ✅ User-friendly output formatting
- ✅ File existence checking

**Script Arguments:**
```bash
./read_log.sh [log_index] [number_of_lines]
```
- `log_index`: 0-based index (default: 0)
- `number_of_lines`: Lines to read (default: 20)

### Apache Log Scripts

#### `read_apache_log.sh`
Advanced Apache log reader with features:
- ✅ Handles both current and gzipped archived logs
- ✅ Automatic decompression for .gz files
- ✅ Permission validation and error handling
- ✅ Detailed file information display
- ✅ Index validation with available files list

**Script Arguments:**
```bash
./read_apache_log.sh [log_index] [number_of_lines]
```
- `log_index`: 0-based index (default: 0)
- `number_of_lines`: Lines to read (default: 20)

#### `search_apache_logs.sh`
Comprehensive Apache log search utility:
- ✅ Searches current and archived logs
- ✅ Automatic handling of gzipped files
- ✅ Clear result formatting with file names
- ✅ Line number references for matches

**Script Arguments:**
```bash
./search_apache_logs.sh <search_term>
```
- `search_term`: Text to search for (required)

#### `apache_log_summary.sh`
Apache log health and status analyzer:
- ✅ File size and statistics
- ✅ Error pattern analysis
- ✅ Archive file counting
- ✅ Latest entry preview
- ✅ Quick health assessment

## Usage Examples for AI Agents

### Quick Status Check

#### Application Logs
1. Run "Quick Read Latest Log (20 lines)" for immediate recent activity
2. If issues found, run "List Available Log Files" to see all options
3. Use "Read Latest Log File" with specific index/line count for deeper analysis

#### Apache Logs
1. Run "Quick Read Apache Error Log (20 lines)" for immediate Apache status
2. If issues found, run "List Apache Error Logs" to see all available files
3. Use "Apache Error Log Summary" for comprehensive health assessment
4. Use "Read Apache Error Log" with specific index/line count for detailed analysis

### Error Investigation

#### Application Errors
1. Run "Search All Recent Logs" with error keywords
2. Use "Read Latest Log File" on specific files where errors were found
3. Use "Read Today's Log File" to focus on current day's issues

#### Apache Errors
1. Run "Search Apache Error Logs" with error keywords or status codes
2. Use "Apache Error Log Summary" to identify error patterns
3. Use "Read Apache Error Log" on specific archived files for historical analysis
4. Search for specific Apache error codes (e.g., "AH00163", "Permission denied")

### Daily Monitoring

#### Combined Approach
1. Start with "Apache Error Log Summary" for server health
2. Follow with "Read Today's Log File" for application activity
3. Use "Quick Read Apache Error Log" and "Quick Read Latest Log" for current status
4. Use search tasks for specific monitoring keywords in both log types

### Server Troubleshooting Workflow
1. **Quick Assessment**: "Apache Error Log Summary" + "Quick Read Apache Error Log"
2. **Historical Analysis**: "List Apache Error Logs" → "Read Apache Error Log" with older indices
3. **Error Pattern Search**: "Search Apache Error Logs" with relevant error terms
4. **Cross-Reference**: Compare Apache errors with application logs using search tasks

## Task Output Format

All tasks provide:
- 📄 File identification and metadata
- 📅 File modification timestamps  
- 🔢 Index information for reference
- ✅ Success/error indicators
- 📊 Context about available files

### Apache-Specific Output Features
- 🚨 Apache-specific emoji indicators
- 📦 Gzipped file identification
- 🔍 Automatic decompression status
- 📈 Error pattern analysis
- 📁 Archive file statistics

## Integration with Gravitycar Framework

### Application Logs
These tasks are specifically designed for the Gravitycar Framework logging system:
- **Monolog Integration**: Reads structured log output
- **Multi-level Logging**: Supports INFO, WARNING, ERROR, DEBUG levels
- **Daily Rotation**: Handles automatic log file rotation
- **JSON Context**: Parses JSON context data in log entries

### Apache Logs
Apache log integration provides:
- **Server Error Monitoring**: Direct access to Apache error logs
- **Archive Handling**: Automatic decompression of gzipped logs
- **Pattern Analysis**: Error code and message pattern recognition
- **Cross-Reference**: Correlation between server and application errors

## Error Handling

The tasks include comprehensive error handling:

### General Error Handling
- Missing log directory detection
- Invalid index range checking  
- File permission validation
- Empty result handling
- Clear error messages with suggestions

### Apache-Specific Error Handling
- **Permission Management**: Automatic sudo usage where required
- **Archive Decompression**: Transparent handling of gzipped files
- **File Type Detection**: Automatic switching between regular and compressed files
- **Access Validation**: Clear messaging for permission issues
- **Fallback Options**: Alternative access methods when primary fails

## Performance Considerations

- **File Size Limits**: Tasks use `tail` to avoid loading large files into memory
- **Search Scope**: Search tasks limit to recent files for performance
- **Index Caching**: Log file lists are generated on-demand for accuracy
- **Gzip Efficiency**: Compressed logs are streamed rather than fully decompressed
- **Memory Usage**: Large Apache logs are processed in chunks to prevent memory issues

## Security Considerations

### Apache Log Access
- **Sudo Requirements**: Apache logs require elevated permissions
- **Group Membership**: Alternative access via `adm` group membership
- **Permission Validation**: Pre-flight checks before attempting access
- **Secure Scripting**: All scripts validate permissions before processing

### File System Safety
- **Path Validation**: All scripts validate file paths to prevent directory traversal
- **Read-Only Access**: Scripts only read files, never modify them
- **Error Boundaries**: Graceful handling of permission and access errors

## Customization

To modify these tasks:
1. Edit `.vscode/tasks.json` for task definitions
2. Modify helper scripts for enhanced functionality:
   - `read_log.sh` - Application log reading
   - `read_apache_log.sh` - Apache log reading with gzip support
   - `search_apache_logs.sh` - Apache log searching
   - `apache_log_summary.sh` - Apache log analysis
3. Adjust search timeframes and line limits as needed
4. Add new tasks following the established patterns

### Adding New Log Sources
To add support for additional log sources:
1. Create a new helper script following the established patterns
2. Add corresponding tasks to `tasks.json`
3. Update input definitions if new parameters are needed
4. Update this README with documentation

### Script Customization Examples
```bash
# Extend search timeframe for application logs
# In search tasks, change "-mtime -7" to "-mtime -14" for 14 days

# Increase default line count
# Change default from "20" to "50" in input definitions

# Add new log directories
# Extend helper scripts to check multiple directories

# Custom error patterns
# Modify summary scripts to highlight specific error types
```

## File Inventory

### VSCode Configuration Files
- `.vscode/tasks.json` - Task definitions for VSCode
- `.vscode/LOG_TASKS_README.md` - This documentation file

### Helper Scripts (Application Logs)
- `read_log.sh` - Interactive log file reader
- Permissions: Executable (`chmod +x`)
- Dependencies: None (uses standard Unix tools)

### Helper Scripts (Apache Logs)
- `read_apache_log.sh` - Interactive Apache log reader with gzip support
- `search_apache_logs.sh` - Apache log search across all archives
- `apache_log_summary.sh` - Apache log health and analysis summary
- Permissions: All executable (`chmod +x`)
- Dependencies: `sudo` access for Apache logs
- Requirements: `zcat`, `zgrep` for gzipped file handling

### Log Directories
- `logs/` - Application log files (Gravitycar framework)
- `/var/log/apache2/` - Apache server logs (requires sudo)

---

**Note for AI Agents**: These tasks provide comprehensive log access for both application-level debugging and server-level troubleshooting. The Apache log tasks require elevated permissions but handle this automatically. All tasks are designed to be self-documenting and provide clear, actionable output for debugging and monitoring activities.

**Quick Reference for AI Agents**:
- 🤖 **Application Logs**: Use `Quick Read Latest Log` and `Search All Recent Logs`
- 🚨 **Apache Logs**: Use `Apache Error Log Summary` and `Search Apache Error Logs`  
- 📊 **Health Check**: Combine `Apache Error Log Summary` with `Read Today's Log File`
- 🔍 **Troubleshooting**: Use search tasks with specific error terms or status codes

## 📁 Project File Structure

```
.vscode/
├── tasks.json              # VSCode task definitions
└── LOG_TASKS_README.md     # This documentation

tasks_scripts/              # Helper scripts directory
├── read_log.sh            # Application log reader
├── read_apache_log.sh     # Apache log reader with gzip support
├── search_apache_logs.sh  # Multi-log search functionality
└── apache_log_summary.sh  # Apache log health assessment

logs/                      # Application logs
├── gravitycar-2025-09-11.log
├── gravitycar-2025-09-10.log
└── ... (date-based log files)
```

All helper scripts have been organized into the `tasks_scripts/` directory for better project organization. The VSCode tasks reference these scripts using relative paths from the workspace root.
