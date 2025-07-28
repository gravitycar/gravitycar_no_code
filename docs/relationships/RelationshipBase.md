# RelationshipBase Class

## Purpose
Defines the base class for all relationship types in the Gravitycar framework, providing common functionality for managing relationships between models.

## Location
- **Base Class**: `src/relationships/RelationshipBase.php`
- **Relationship Implementations**: `src/relationships/` directory

## Relationship Types
- **OneToOne**: `src/relationships/OneToOneRelationship.php`
- **OneToMany**: `src/relationships/OneToManyRelationship.php`
- **ManyToMany**: `src/relationships/ManyToManyRelationship.php`

## Core Properties
- **`name`**: Unique identifier for the relationship
- **`type`**: The type of relationship (OneToOne, OneToMany, ManyToMany)
- **`fromModel`**: The source model in the relationship
- **`toModel`**: The target model in the relationship
- **`metadata`**: Relationship-specific configuration and constraints

## Metadata Structure
Relationships use their own metadata to define:
- **Model Mapping**: Which two models comprise the relationship
- **Required Fields**: Fields that must be present for the relationship to function
- **Optional Fields**: Additional fields that can be included in the relationship
- **Constraints**: Rules and validations specific to the relationship

### Required Metadata Fields
- `fromModel`: Class name of the source model
- `toModel`: Class name of the target model
- `type`: Relationship type identifier
- `name`: Human-readable name for the relationship

### Optional Metadata Fields
- `foreignKey`: Custom foreign key field name
- `pivotTable`: Custom junction table name (for ManyToMany)
- `constraints`: Additional database constraints
- `cascadeDelete`: Soft delete behavior rules
- `validationRules`: Relationship-specific validation

## Soft Delete Support
- Relationships support soft delete functionality
- When a model is soft-deleted, related entries in join tables are also soft-deleted
- Soft-delete sets `deleted_at` timestamp instead of hard deletion
- Undelete functionality restores relationship entries by setting `deleted_at` to null

## Key Methods
- `validateRelationship()`: Ensures relationship integrity
- `createRelationship()`: Establishes the relationship between models
- `removeRelationship()`: Soft-deletes the relationship
- `restoreRelationship()`: Restores a soft-deleted relationship
- `getRelatedRecords()`: Retrieves related model instances

## Implementation Notes
- All relationship types extend RelationshipBase
- Metadata-driven configuration allows for flexible relationship definitions
- Supports cascading operations while maintaining data integrity
- Integrates with model soft-delete functionality
