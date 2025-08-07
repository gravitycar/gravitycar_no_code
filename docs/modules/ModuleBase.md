# ModuleBase Class

## Purpose
Represents an application module in the Gravitycar framework. A module is a collection of models, fields, validation rules, relationships, and API controllers that work together to provide specific functionality.

## Location
- **Base Class**: `src/modules/ModuleBase.php` (abstract class)
- **Module Implementations**: `src/modules/<module_name>/` directories
- **Module Metadata**: `src/modules/<module_name>/metadata.php`

## Module Structure
Each module should have its own directory containing:
- Module class file extending ModuleBase
- Metadata file defining module configuration
- API controller class in `api/` subdirectory

## Responsibilities
- Implements the scope and functionality of the module
- Groups related models to implement the feature set
- Handles saving and loading data from models and relationships
- Defines UI display configuration through metadata
- Provides modular development and extensibility

## Module Metadata File
Location: `src/modules/<module_name>/metadata.php`

### Required Metadata Properties
- **`name`**: The name of the module
- **`description`**: Description of the module's purpose
- **`models`**: Array of models included in the module
- **`ui`**: UI component definitions for the module

### UI Configuration
The metadata defines how the module appears in the UI:
- **Navigation Menu**: Each module accessible via main navigation link
- **List View**: Default view showing all models in the module
- **Create Views**: Forms for creating new records for each model
- **Field Display**: Which fields to show in list and create views
- **Cross-Model Fields**: Fields from multiple models can be displayed together

### Default UI Links
The module's navigation menu includes:
- Link to show list of all models in the module
- Links to create new records for each model in the module
- Custom links defined in the module metadata

## API Controller Requirements
Each module must have an API controller class:
- **Location**: `src/modules/<module_name>/api/`
- **Base Class**: Must extend `ApiControllerBase`
- **Route Registration**: Must implement `registerRoutes()` method
- **CRUD Operations**: Provides methods for Create, Read, Update, Delete operations

### registerRoutes() Method
This method should define all routes the module uses:
- CRUD endpoints for each model
- Custom operation endpoints
- Relationship management endpoints
- Module-specific functionality endpoints

## Field Validation
- Fields handle their own validation using validation rules from metadata
- Module coordinates validation across related models
- Ensures data consistency within the module scope

## Data Management
- Modules handle saving and loading data from their models
- Coordinate relationship updates between models
- Manage transaction boundaries for complex operations
- Handle cascading operations while maintaining data integrity

## Implementation Notes
- All modules extend the abstract ModuleBase class
- Modules are self-contained functional units
- Support for cross-module relationships and data sharing
- Metadata-driven UI generation for consistent user experience
