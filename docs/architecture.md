# Gravitycar Framework Architecture

## Overview
Gravitycar is a metadata-driven web application framework that dynamically generates database schemas, APIs, and user interfaces based on configuration files. The framework prioritizes extensibility and allows developers to modify data models without code changes.

## Core Principles
- **Metadata-Driven**: All models, fields, and relationships defined through metadata files
- **Dynamic Schema Generation**: Database schema created automatically from metadata
- **Extensible Design**: New models and fields added without framework modifications
- **RESTful API**: Consistent API endpoints for all defined models
- **Frontend Generation**: React components generated based on metadata for dynamic UI
- **Caching and Performance**: Metadata caching for improved performance
- Use base classes to define common functionality for models, fields, validation rules, and exceptions
- Practice clean code principles, including SOLID principles, DRY (Don't Repeat Yourself), and KISS (Keep It Simple, Stupid)

### Extensibility Mechanisms

- **Metadata-Driven Extensibility**: All models and relationships defined in external metadata files PHP files. This allows developers to add, modify, or remove models and fields without changing application code.
- **Dynamic Schema Updates**: The schema generator detects changes in metadata and updates the database schema accordingly, supporting new tables, fields, and relationships on the fly.
- **Pluggable Field and Validation Types**: New field types and validation rules can be added by creating new classes in the `src/fields` and `src/validation` directories, respectively. These are automatically discovered and made available for use in metadata.
- **API and UI Generation**: RESTful API endpoints and React UI components are generated dynamically based on metadata, so new models and fields are instantly available in both backend and frontend without manual coding.
- **Role and Permission Extensibility**: User roles and permissions are defined in metadata, allowing for flexible access control policies that can be updated without code changes.
- **Hot Reloading**: Changes to metadata files can be detected and reloaded at runtime (or with minimal downtime), enabling rapid iteration and deployment of new features.
- **Validation and Consistency**: The metadata engine validates new or updated metadata for consistency and correctness before applying changes, reducing the risk of runtime errors.

## System Architecture

### Three-Tier Architecture

| Tier     | Description                        | Technology |
|----------|------------------------------------|------------|
| Frontend | React UI (Dynamic forms/views)     | React      |
| Backend  | PHP REST API (Metadata processing) | PHP        |
| Database | MySQL (Dynamic schema)             | MySQL      |

## Core Components

### Modules
**Purpose**: Represents an application module - a collection of models, fields, validation rules, relationships, and API controllers that work together to provide specific functionality.

**Documentation**: See [docs/modules/ModuleBase.md](modules/ModuleBase.md) for detailed specifications.

### Models
**Purpose**: Defines the structure, logic, and features of each data model in the framework.

**Documentation**: 
- Class specifications: [docs/models/ModelBase.md](models/ModelBase.md)
- Metadata structure: [docs/models/ModelMetadata.md](models/ModelMetadata.md)

### Fields
**Purpose**: Defines field types to represent specific data types and behaviors on the backend and in the UI.

**Documentation**: See [docs/fields/FieldBase.md](fields/FieldBase.md) for detailed specifications.

### Relationships
**Purpose**: Manages relationships between models with their own metadata to define structure and constraints.

**Documentation**: See [docs/relationships/RelationshipBase.md](relationships/RelationshipBase.md) for detailed specifications.

### Exception Handling
**Purpose**: Provides consistent error handling throughout the framework.

**Documentation**: See [docs/exceptions/GCException.md](exceptions/GCException.md) for detailed specifications.

### Core System Classes

#### Metadata Engine
**Purpose**: Parses metadata files, aggregates metadata for caching, and provides metadata for API and frontend generation.

**Documentation**: See [docs/core/MetadataEngine.md](core/MetadataEngine.md) for detailed specifications.

#### Database Connector
**Purpose**: Manages database connections and SQL generation using Doctrine DBAL.

**Documentation**: See [docs/DatabaseConnector.md](DatabaseConnector.md) for detailed specifications.

#### Schema Generator
**Purpose**: Generates and manages database schemas dynamically based on metadata.

**Documentation**: See [docs/database/SchemaGenerator.md](database/SchemaGenerator.md) for detailed specifications.

#### Configuration Management
**Purpose**: Provides centralized configuration management for the framework.

**Documentation**: See [docs/core/Config.md](core/Config.md) for detailed specifications.
