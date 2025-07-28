# MetadataEngine Class

## Purpose
Parses metadata files, aggregates metadata for pre-caching, and returns metadata in full or partial formats for the Gravitycar framework.

## Location
- **Class File**: `src/metadata/MetadataEngine.php`

## Constructor
- The MetadataEngine constructor takes **no arguments**
- The constructor instantiates its own Monolog logger internally
- No external dependencies required for instantiation

## Properties (Hard-coded Paths)
- `modelsDirPath`: Hard-coded to `'src/models'`
- `relationshipsDirPath`: Hard-coded to `'src/relationships'`
- `cacheDirPath`: Hard-coded to `'cache/'`
- `logger`: Instantiated in constructor using `new Logger(static::class)`

## Responsibilities
- Scan the file system for models and relationships in the hard-coded directories
- Locate and load metadata files from the filesystem
- Load and validate metadata files (JSON or PHP arrays)
- Provide metadata for API and frontend generation
- Cache metadata for performance optimization
- Self-contained operation with no external configuration required

## Key Methods

### `loadAllMetadata()`
- Automatically scans the hard-coded directories for metadata files
- Loads all available metadata into memory
- Triggers caching mechanisms for performance

## Features
- **Caching**: Metadata files are cached in the `cache/` directory for performance optimization
- **Validation**: Validates metadata files for consistency and correctness
- **Dynamic Loading**: Supports dynamic loading of model definitions
- **Format Support**: Handles both JSON and PHP array formats
- **Self-Contained**: No external dependencies required for instantiation

## Implementation Details
- All directory paths are hard-coded within the class
- Automatic discovery of metadata files in designated directories
- Built-in caching system for improved performance
- Integrated validation to ensure metadata integrity
