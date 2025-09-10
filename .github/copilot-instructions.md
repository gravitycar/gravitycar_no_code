# Gravitycar Framework - AI Coding Agent Instructions

## Architecture Overview
Gravitycar is a **metadata-driven** web application framework that dynamically generates database schemas, REST APIs, and React UI components from configuration files. No manual CRUD coding required.

### Core Components
- **Metadata Files**: `src/Models/{model_name}/{model_name}_metadata.php` - Define models, fields, relationships
- **Model Classes**: `src/Models/{model_name}/{ModelName}.php` - Extend `ModelBase` with custom logic
- **Dynamic Generation**: Database schema, API endpoints, and React components auto-generated from metadata
- **Caching System**: `cache/metadata_cache.php` and `cache/api_routes.php` - Performance optimization

## Essential Workflows

### Adding/Modifying Models
1. Create/update `src/Models/{model_name}/{model_name}_metadata.php` with field definitions
2. Create/update corresponding PHP class extending `ModelBase`
3. Add relationships to model metadata's `relationships` array (if needed)
4. **CRITICAL**: Run `php setup.php` to rebuild cache and database schema
5. Use custom tools: `gravitycar_cache_rebuild()` for cache refresh

### Development Server Management
- **Backend**: Apache serves PHP on `localhost:8081` (auto-configured)
- **Frontend**: React dev server on `localhost:3000`
- **Testing**: PHPUnit with coverage: `gravitycar_test_runner()` tool
- **API Testing**: Use `gravitycar_api_call()` tool instead of manual curl

### Debugging with Built-in Tools
- **PHP Debug Scripts**: Use `gravitycar_php_debug_scripts()` for safe execution in `tmp/` directory
- **Server Control**: `gravitycar_server_control()` for Apache/React management
- **API Testing**: Direct API calls via VSCode tools (see `.vscode/extensions/gravitycar-tools/`)

## Code Patterns & Conventions

### Metadata Structure
```php
return [
    'name' => 'ModelName',
    'table' => 'table_name',
    'displayColumns' => ['field1', 'field2'],
    'fields' => [
        'field_name' => [
            'type' => 'Text|Integer|DateTime|ID',
            'label' => 'Display Label',
            'required' => true,
            'validationRules' => ['Required', 'Alphanumeric']
        ]
    ]
];
```

### Model Class Pattern
```php
namespace Gravitycar\Models\model_name;
use Gravitycar\Models\ModelBase;

class ModelName extends ModelBase {
    // Custom business logic only
    // CRUD operations auto-generated
}
```

### Pure Dependency Injection Requirements
- **NO ServiceLocator usage** - All dependencies must be explicitly injected via constructor
- Use `Container->get('model_factory')` for accessing ModelFactory with proper DI
- All ModelBase subclasses require 7-parameter constructor with full dependency injection
- Type hint all method parameters and constructor dependencies
- Use early returns to reduce nesting (complexity target: <4/10)

### Model Creation Pattern
```php
// CORRECT: Use Container to get ModelFactory for new model creation
$container = \Gravitycar\Core\ContainerConfig::getContainer();
$factory = $container->get('model_factory');
$model = $factory->new('Users');

// CORRECT: Use ModelFactory for retrieving existing models
$existing = $factory->retrieve('Users', $userId);

// ALTERNATIVE: ServiceLocator convenience method (legacy compatibility)
$factory = ServiceLocator::getModelFactory();
$model = $factory->new('Users');

// ADVANCED: Direct container access for complex cases
$model = \Gravitycar\Core\ContainerConfig::createModel('Gravitycar\\Models\\Users\\Users');

// INCORRECT: Direct instantiation without dependencies
$model = new Users(); // Missing 7 required dependencies
```

### Required ModelBase Constructor Pattern
```php
namespace Gravitycar\Models\model_name;
use Gravitycar\Models\ModelBase;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

class ModelName extends ModelBase {
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }
    
    // Custom business logic only
    // CRUD operations auto-generated
}
```

### Testing Infrastructure
- Use direct dependency injection in tests - NO complex mock injection patterns
- Create mocks directly and inject via constructor
- NO TestableModelBase helper class needed for simple tests
- Container can be mocked for integration tests

```php
// CORRECT: Direct dependency injection in tests
protected function setUp(): void {
    $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
    $mockFieldFactory = $this->createMock(FieldFactory::class);
    // ... create all 7 mocks
    
    $this->model = new TestableModelForPureDI(
        $this->logger,
        $mockMetadataEngine,
        $mockFieldFactory,
        $mockDatabaseConnector,
        $mockRelationshipFactory,
        $mockModelFactory,
        $mockCurrentUserProvider
    );
}

// INCORRECT: ServiceLocator mock injection (deprecated)
$this->setupMockFieldFactoryForModel($this->model);
```

## Relationships System

### RelationshipBase Architecture
Gravitycar handles model relationships through a sophisticated inheritance system:

- **RelationshipBase**: Abstract base class extending ModelBase
- **OneToOneRelationship**: Single record relationships (Users ↔ Profiles)
- **OneToManyRelationship**: Parent-child relationships (Movies → Movie_Quotes)
- **ManyToManyRelationship**: Junction table relationships (Users ↔ Roles)

### Including Relationships in Model Metadata
Models declare their relationships in the `relationships` section of their metadata:

```php
// String array format (only supported method)
'relationships' => ['movies_movie_quotes', 'users_roles'],
```

**Relationship Name Mapping:**
- Each string corresponds to a directory in `src/Relationships/{relationship_name}/`
- Contains `{relationship_name}_metadata.php` with relationship definition
- Example: `'movies_movie_quotes'` → `src/Relationships/movies_movie_quotes/movies_movie_quotes_metadata.php`

**Creating New Relationship Files:**
1. Create directory: `src/Relationships/{relationship_name}/`
2. Create metadata file: `{relationship_name}_metadata.php`
3. Define relationship type, models, and any additional fields
4. Add relationship name to relevant model metadata files

### Relationship Metadata Structure
Relationships are defined via metadata files in `src/Relationships/{relationship_name}/`:

```php
// OneToMany example: movies_movie_quotes_metadata.php
return [
    'name' => 'movies_movie_quotes',
    'type' => 'OneToMany',
    'modelOne' => 'Movies',        // Parent model
    'modelMany' => 'Movie_Quotes', // Child model
    'constraints' => [],
    'additionalFields' => []
];

// ManyToMany example: users_roles_metadata.php
return [
    'name' => 'users_roles',
    'type' => 'ManyToMany',
    'modelA' => 'Users',
    'modelB' => 'Roles',
    'additionalFields' => [
        'assigned_at' => [
            'type' => 'DateTime',
            'defaultValue' => 'CURRENT_TIMESTAMP'
        ]
    ]
];
```

### Database Schema Generation
- **OneToMany**: Junction table created (e.g., `rel_1_movies_M_movie_quotes`)
- **ManyToMany**: Junction table created (e.g., `rel_N_users_M_roles`)
- **Core Fields**: Auto-added (id, created_at, updated_at, deleted_at)
- **Additional Fields**: Custom fields in junction tables

### RelatedRecordField for Foreign Keys
The `RelatedRecordField` class handles direct foreign key relationships within model tables:

```php
// Example from core_fields_metadata.php
'created_by' => [
    'type' => 'RelatedRecord',
    'relatedModel' => 'Users',
    'relatedFieldName' => 'id',
    'displayFieldName' => 'created_by_name'
]
```

**Key Features:**
- **Foreign Key Storage**: Stores actual ID values in the model table
- **Automatic Validation**: Ensures referenced records exist
- **Display Integration**: Links to companion display fields for user-friendly names
- **React Component**: Auto-generates `RelatedRecordSelect` dropdown components
- **Audit Trail**: Commonly used for created_by, updated_by, deleted_by fields

### Retrieving Related Records
RelationshipBase provides methods for accessing related data:

```php
// In ModelBase classes
public function getRelated(string $relationshipName): array {
    $relationship = RelationshipFactory::get($relationshipName);
    return $relationship->getRelatedRecords($this);
}

// Relationship-specific methods
$oneToMany = new OneToManyRelationship('movies_movie_quotes');
$quotes = $oneToMany->getRelatedFromOne($movieModel);    // Array of quotes
$movie = $oneToMany->getRelatedFromMany($quoteModel);    // Single movie

$manyToMany = new ManyToManyRelationship('users_roles');
$roles = $manyToMany->getRelatedRecords($userModel);     // Array of roles
```

### Frontend Integration
React components auto-generate relationship UI:
- **RelatedRecordSelect**: Dropdown for selecting related records
- **RelatedItemsSection**: Manage one-to-many relationships
- **ManyToManyManager**: Dual-pane assignment interface

## Frontend & UI System

### React Integration
The Gravitycar framework uses **React 18.x** for the frontend with TypeScript support:

- **Metadata-Driven UI**: Components auto-generate from backend metadata
- **Generic CRUD Operations**: All models use consistent UI patterns
- **Real-time Updates**: Frontend consumes metadata changes dynamically
- **Component Library**: Reusable field components for all data types

### UI Metadata Configuration
Models define their UI behavior in the `ui` section of metadata:

```php
'ui' => [
    'listFields' => ['poster_url', 'name', 'release_year'],
    'createFields' => ['name'], 
    'editFields' => ['name', 'release_year', 'synopsis'],
    'relatedItemsSections' => [
        'quotes' => [
            'title' => 'Movie Quotes',
            'relationship' => 'movies_movie_quotes',
            'displayColumns' => ['quote'],
            'actions' => ['create', 'edit', 'delete']
        ]
    ]
]
```

### GenericCrudPage Component
**Location**: `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx`

The `GenericCrudPage` handles standard CRUD operations for all simple models:
- **Automatic Forms**: Generates create/edit forms from field metadata
- **Data Tables**: Displays records with sorting, filtering, and pagination  
- **Modal Dialogs**: Consistent UI for create/edit operations
- **Relationship Management**: Inline editing of related records
- **Responsive Design**: Adapts to different screen sizes

### Custom Pages for Complex Features
Multi-model operations require dedicated pages:

**Movie Quote Trivia Game**: `gravitycar-frontend/src/pages/TriviaPage.tsx`
- Custom game logic spanning Movies, Movie_Quotes, and trivia models
- Real-time scoring and question management
- Game state management with React hooks
- Complex UI flow beyond simple CRUD operations

**Development Pattern**:
- Simple models → Use GenericCrudPage with UI metadata
- Complex features → Create dedicated page components
- Shared logic → Extract to custom hooks and services

## Critical Rules

### File Structure
- All PHP classes in `Gravitycar` namespace
- Models: `src/Models/{snake_case}/{PascalCase}.php`
- Metadata: `{model_name}_metadata.php` alongside model class
- Tests: Mirror `src/` structure in `Tests/Unit/`, `Tests/Integration/`, `Tests/Feature/`

### Configuration & Environment
- **Never hardcode config** - Use `Config` class for all settings
- Database credentials in `config.php`
- TMDB API integration configured via `open_imdb_api_key`
- Debug mode controlled by `config.php` -> `app.debug`

### API Endpoints
- Auto-generated REST endpoints: `/ModelName`, `/ModelName/{id}`
- Custom endpoints in `src/Api/` controllers
- Fallback routing handles undefined endpoints generically
- Authentication required for most operations

## Advanced Routing System

### APIRouteRegistry & Route Discovery
The framework uses a sophisticated route registration and scoring system:

- **Auto-Discovery**: APIRouteRegistry automatically discovers all `ApiControllerBase` subclasses
- **Caching**: Routes cached in `cache/api_routes.php` for performance
- **Fallback Support**: Generic ModelBaseAPIController handles undefined model endpoints

### Route Registration Pattern
All API controllers must extend `ApiControllerBase` and implement `registerRoutes()`:

```php
class MyController extends ApiControllerBase {
    public function registerRoutes(): array {
        return [
            [
                'method' => 'GET',
                'path' => '/my/endpoint',
                'apiClass' => self::class,
                'apiMethod' => 'myMethod',
                'parameterNames' => []
            ]
        ];
    }
}
```

### Parameter Name Mapping
The `parameterNames` array maps route path components to named parameters available in the Request object:

**Component-to-Parameter Mapping:**
- Each element in `parameterNames` corresponds to the same position in the route path
- Values become accessible via `$request->get('parameterName')`
- Empty strings (`''`) in `parameterNames` indicate ignored path components

**Examples:**
```php
// Route: '/Users/123'
// parameterNames: ['modelName', 'id']
// Result: $request->get('modelName') = 'Users', $request->get('id') = '123'

// Route: '/Users/123/link/roles'  
// parameterNames: ['modelName', 'id', '', 'relationshipName']
// Result: $request->get('modelName') = 'Users', $request->get('id') = '123'
//         $request->get('relationshipName') = 'roles'
//         Note: 'link' component is ignored (empty string in parameterNames)

// Wildcard route: '/?/?'
// Client request: '/Movies/456'
// parameterNames: ['modelName', 'id'] 
// Result: $request->get('modelName') = 'Movies', $request->get('id') = '456'
```

**In Controller Methods:**
```php
public function retrieve(Request $request): array {
    $modelName = $request->get('modelName'); // 'Users'
    $id = $request->get('id');               // '123'
    // ... controller logic
}
```

### Wildcard Routing & Scoring
- **Wildcards**: Use `?` for dynamic path segments (e.g., `/?/?` matches `/Users/123`)
- **Scoring Algorithm**: `(pathLength - position) * (2 for exact, 1 for wildcard)`
- **Route Priority**: Exact matches score higher than wildcards
- **ModelBaseAPIController**: Provides 11 wildcard routes as fallback for all models

```php
// Example wildcard routes from ModelBaseAPIController:
'GET /?'           → list(modelName)
'GET /?/?'         → retrieve(modelName, id)  
'GET /?/?/link/?'  → listRelated(modelName, id, relationshipName)
```

### APIPathScorer Examples
- `/Users/123` vs route `/Users/?`: Score = (2×2) + (1×1) = 5
- `/Users/123` vs route `/?/?`: Score = (2×1) + (1×1) = 3
- Exact routes always beat wildcard routes for same path

### Testing Strategy
- **DON'T TEST LOGGING** - Test behavior that causes logs, not log output itself
- Use `gravitycar_test_runner()` tool for PHPUnit execution
- Mock external dependencies (TMDB API, database)
- Test metadata validation and cache regeneration

## Integration Points

### External Services
- **TMDB API**: Movie data enrichment via `MovieTMDBIntegrationService`
- **Google OAuth**: Token management for authentication
- **MySQL**: Doctrine DBAL for all database operations

### Frontend Communication
- React consumes metadata via REST API at runtime
- Component generators included in metadata responses
- Real-time UI updates when metadata changes

## Common Pitfalls
- Forgetting to run `setup.php` after metadata changes (breaks everything)
- Mixing business logic with generated CRUD (put custom logic in model classes)
- Hardcoding database fields (use metadata field definitions)
- Testing log output directly (test the conditions that create logs)
- File paths not absolute (always reference from project root)
