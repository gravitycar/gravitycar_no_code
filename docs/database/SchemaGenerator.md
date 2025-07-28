# SchemaGenerator Class

## Purpose
Generates and manages database schemas dynamically based on metadata definitions using Doctrine DBAL.

## Location
- **Class File**: `src/schema/SchemaGenerator.php`

## Function
- Generates database schemas from model metadata
- Creates and modifies database tables based on metadata changes
- Handles schema migrations and updates
- Integrates with DatabaseConnector for SQL execution

## Key Features
- **Dynamic Schema Generation**: Creates database schemas automatically from metadata
- **Schema Updates**: Detects changes in metadata and updates database schema accordingly
- **Doctrine DBAL Integration**: Uses Doctrine DBAL for cross-database compatibility
- **Migration Support**: Supports new tables, fields, and relationships on the fly

## Responsibilities
- Parse model metadata to determine database structure
- Generate CREATE TABLE statements for new models
- Generate ALTER TABLE statements for schema changes
- Handle field type mapping from metadata to database types
- Manage relationships and foreign key constraints
- Support database indices defined in metadata

## Implementation Notes
- Works in conjunction with DatabaseConnector for SQL execution
- Uses Doctrine DBAL schema management features
- Validates schema changes before applying them
- Supports rollback mechanisms for failed schema updates
