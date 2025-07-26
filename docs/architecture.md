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
- **Purpose**: Represents an application in the framework. A module is a collection of models, fields, validation rules, relationships, and API controllers that work together to provide a specific functionality or feature set.
- **Location**: `src/modules/*` directory - every module has its own directory. Every module should extend an abstract ModuleBase class which contains features common to all modules.
- **Responsibility**: 
  - Implements the scope and functionality of the module
  - Provides a way to group related models to implement the feature set of the module.
  - Allows for modular development and extensibility
  - Handles saving and loading data from models and relationships. The fields in the models and relationships handle their own validation with the validation rules set in the model's metadata.
  - Defines how the data in the models and relationships should be displayed in the UI with a metadata file: `src/modules/<module_name>/metadata.php`.
- **UI**:
  - Each module will be accessible via a link in the main navigation menu of the React frontend.
  - The link in the main navigation menu will point to the module's list view.
  - The link in the main navigation will open a menu with links defined in the module's metadata file. The default links are:
    - Show a list of all models in the module
    - Create a new record for each model in the module
- **Metadata**: Each module should have a metadata file: `src/modules/<module_name>/metadata.php`. This file defines:
  - The name of the module
  - The description of the module
  - The models included in the module
  - The UI components for the module
  - The fields to display in the UI for the list view and the create view. These fields may come from more than one model, so the metadata must reflect which model each field comes from.
- **API Controllers**: Each module should have its own API controller class that extends the abstract ApiControllerBase class. This class handles the API requests for the module and provides methods for CRUD operations on the models in the module. The API controller class should be located in the `src/modules/<module_name>/api` directory. This API Controller class should define a registerRoutes() method which should list all of the routes this module will use for CRUD and other operations. 

### Models
- **Location**: 
  - Class File `src/models/<model_name>/<model_name>.php`. Every model should extend an abstract ModelBase class which contains features common to all models. 
  - Metadata File: `src/models/<model_name>/<model_name>_metadata.php`. This file defines the name, table name, fields, relationships, and validation rules for the model. See `Metadata Example` below.
- **Purpose**: Defines the structure, specific logic and specific features of each model
- **Function**: Defines models, fields, relationships, and validation rules
- **Format**: model metadata files will be PHP files. Model class files will be PHP files.
- **Responsibility**: Single source of truth for data structure
- **Principles**:
  - All fields that will be stored in the database and/or displayed in the UI should be defined in the model metadata file.
  - Model records are stored in a database table. The table name is set in the model's metadata file.
  - All model records must have a unique identifier field named `id`. Use composer package `ramsey/uuid` to generate unique identifiers.
  - All fields defined in the model's metadata file should be stored in a property on the model class called `fields`. These fields should NOT be stored as hard-coded properties in model classes.
  - Models should be extensible by adding new fields in metadata file.
  - Relationships between models will be represented by special field types in the model metadata file.
  - Models should validate their fields during create and update operations
  - Models should sanitize field values before saving to the database
  - Models should handle relationships and constraints
  - Models should provide methods for CRUD operations
  - Models should provide a method to return a human-readable name for the model, which will be used in the UI and API responses.
  - Models should provide methods for relationship handling (e.g., loading related records)
  - Deleting a model should not be a hard delete. Instead, it should set a `deleted_at` timestamp field to the current time, and set a `deleted_by` field to the ID of the currently authenticated user. This allows for soft deletes and data recovery.
  - Deleting a model should not delete related records.
  - Deleted records should not be returned in API responses or UI views unless a specific flag is set to include them.
  - Deleting a model should 'soft-delete' entries in join tables for many-to-many relationships. 'soft-delete' means setting a `deleted_at` timestamp field to the current time.
  - Models should provide a mechanism to undelete themselves and restore their entries in join tables by setting the `deleted_at` timestamp field to null.
  - All models should include these 'core' fields:
    - `id`: Unique identifier for the record
    - `name`: Name of the record (used for display purposes)
    - `created_at`: Timestamp of when the record was created
    - `updated_at`: Timestamp of when the record was last updated
    - `deleted_at`: Timestamp of when the record was soft-deleted (null if not deleted)
    - `created_by`: User ID of the user who created the record
    - `updated_by`: User ID of the user who last updated the record
    - `deleted_by`: User ID of the user who soft-deleted the record (null if not deleted)
- **Features**:
  - Model definitions include fields, relationships, database indices and validation rules
  - Model definitions can overwrite default values in field objects by specifying them in the model metadata.
  - Model definitions can specify validation rules for fields that overwrite the default validation rules for that field type.
  - Support for various field types (text, number, date, etc.)
  - Relationships defined as foreign keys or many-to-many associations
  - Dynamic loading of model definitions based on metadata files
  - Models must validate their fields during create and update operations. Every field's value should also be sanitized before being saved to the database.
- **Metadata**: Each model should have a metadata file: `src/models/<model_name>/metadata.php`. This file defines:
  - The name of the model
  - The table name for the model in the database
  - The list of the fields in the model. Each entry in the list of fields will contain an associative array of metadata about that field, including:
    + The field name
    + The field type (e.g., text, number, date, datetime, enum, multi-enum, float, email, etc.) These types will match the class name of a FieldBase subclass defined in `src/fields`. For example, a field where the metadata['type'] = 'Text' will be matched to the TextField class, which extends the FieldBase class.
    + The field label for display purposes
    + The field description for documentation purposes
    + The default value for the field if appropriate
    + The maximum length of the field if appropriate
    + For enum and multi-enum fields, it will include a class name and the name of a method on that class that will return the list of options for the field. This list of options will be in the format of 'value_stored_in_db' => 'display_value_in_ui'. These methods can get their list from any source, including a hard-coded list in the class file or a dataabase query. 
    + The field validation rules. This list will overwrite the default validation rules for that field type. The validation rules will be defined in the metadata file as an array of validation rule names. Each validation rule name should match a class name in the `src/validation` directory. The validation rule class should extend an abstract ValidationBase class that contains features common to all validation rules.
    + The field's default value. This will overwrite the default value defined in the field class.
    + Whether the field is required
    + Whether the field is a non-db field. Non-db fields are fields that are not stored in the database, but are used for display purposes in the UI. Non-db fields should be defined in the model metadata file, but should not be included in the database schema.
    + Whether the field is unique
    + Whether the field is read-only
    + Whether the field is searchable in the UI
  - The relationships to other models, including foreign keys and many-to-many associations
  - The UI components for the model, including the fields to display in the list view and create view
- **Metadata Example**:
  - ```
    
    $metadata = [
    'name' => 'Users',
    'table' => 'users',
    fields => [
      'username' => [
          'name' => 'username',
          'label' => 'Username',
          'type' => 'Text',
          'required' => true,
          'maxLength' => 50,
          'validationRules' => [
              'Required',
          ],
      ],
      'password' => [
          'label' => 'Password',
          'name' => 'password',
          'type' => 'Password',
          'required' => false,
          'maxLength' => 100,
          'validationRules' => [
              'Password'
          ],
      ],
      'user_type' => [
          'label' => 'User Type',
          'name' => 'user_type',
          'type' => 'Enum',
          'defaultValue' => 'regular',
          'optionsClass' => '\Gravitycar\Gravitons\Users\Users',
          'optionsMethod' => 'getUserTypes',
          'validationRules' => [
                  'Options',
          ],
      ],
      'email' => [
          'name' => 'email',
          'label' => 'Email',
          'type' => 'Email',
          'required' => false,
          'maxLength' => 100,
          'validationRules' => [
              'Email',
          ],
      ],
      'last_login' => [
          'name' => 'last_login',
          'label' => 'Last Login',
          'type' => 'DateTime',
          'required' => false,
          'readOnly' => true,
          'validationRules' => [
              'DateTime',
          ],
      ],
    ],
];```
### Fields
- **Purpose**: Defines field types to represent specific data types and behaviors specific to each type, on the backend and in the UI.
- **Location**: `src/fields/*` directory - every field type has its own class file. Every field type should extend an abstract FieldBase class which contains features common to all field types.
- **Principles**: Fields are represented in classes that extend the FieldBase class. The properties of these FieldBase subclasses can be overwritten when the FieldBase object ingests metadata from a model or a relationship. This allows for dynamic field definitions based on metadata.
- **Properties**: 
  + name - the name of the field. This MUST be provided in the metadata. Throw a GCException subclass if the name is not provided.
  + PHP Data type - This should map to known PHP datatypes, i.e. string, int, float, bool etc. 
  + Database type - this should map to known MySQL datatypes, i.e. VARCHAR, INT, FLOAT, DATETIME, etc.
  + UI Data type
  + value - the value of the field. This is the value that will be stored in the database and returned in API responses. This is the value that will validated by the validation rules.
  + Validation rules - this is a list of strings that will match to the names of subclasses of the ValidationBase class in the `src/validation` directory. For example, if the validation rules contain a string 'Alphanumeric', that should map to a class named 'AlphanumericValidation' in `src/validation`. These validation rules will be applied to the field when it is set or updated. The validation rules will be defined in the metadata file as an array of validation rule names. Each validation rule name should match a class name in the `src/validation` directory.
  + Default values - can be null. If not null, this value will be returned until a new value is set.
  + Display Label for UI
  + Maximum length - an integer that defines the maximum length of the field value. This is used for validation and UI rendering.
  + Whether the field is required
  + Whether the field is unique
  + Whitelist of values (for select fields)
  + Blacklist of values 
  + Whether the field is read-only
  + Required user_type to view or edit the field
  + Whether the field is stored in the database. Non-database fields are only for display in the UI and are not stored in the database. Non-database fields should be defined in the model metadata file, but should not be included in the database schema.
- **Features**:
  - Dynamic field definitions based on metadata. But Fields can only set values on existing properties, and will skip properties specified in the metadata but not defined in the class. Skipped properties like this must be logged.
  - Support for various field types (text, number, date, etc.)
  - Custom validation rules
  - Dynamic UI rendering based on field type
  - Support for relationships (foreign keys, many-to-many, etc.)
  - Store the original value of the field when it is retrieved from the database, so that it can be compared to the current value when the field is updated. This allows for detecting changes to the field value and running validation rules when the value is changed.
  - When the value of a field is changed, run the validation rules for that field and return an error message if the value is invalid


### ValidationRules
- **Purpose**: Defines validation rules for fields to ensure data integrity and correctness
- **Location**: `src/validation/*` directory - every validation rule has its own class file. Every validation rule should extend an abstract ValidationBase class which contains features common to all validation rules.
- **Function**: 
  - Validate field values based on defined rules
  - Define the rules in such a way that they can be run on both the backend and frontend
  - Provide error messages for invalid data


### Exceptions
- **Purpose**: Specific exceptions for error handling in the framework
- **Location**: `src/exceptions/*` directory - every exception has its own class file
- **Principles**:
  - Use exceptions to handle errors gracefully
  - Define a base Exception class for the framework, GCException.
  - The GCException class should be extended by all other exceptions in the framework
  - The GCException class should provide a method to log the exception using the Monolog library
  - The GCException class should set the Monolog level to `ERROR` for all exceptions
  - All exceptions should log a backtrace for debugging purposes. But the backtrace should never be exposed in the UI.
  - Provide meaningful error messages for debugging and user feedback
  - All exceptions should be logged using the Monolog library
  - All error conditions should throw a GCException or one of its subclasses.
  - Every entry point in the framework should handle exceptions and return a meaningful error response to the user.
- **Function**: 
  - Define custom exceptions for specific error scenarios
  - Provide meaningful error messages for debugging and user feedback
  - All exceptions should extend the base Exception class
  - All exceptions should be logged using the Monolog library

### Metadata Engine
- **Location**: `src/metadata`
- **Function**: Parses metadata files, aggregates metadata for pre-caching, returns metadata either in full or partial formats
- **Format**: JSON or PHP arrays
- **Responsibility**:
  - Scan the file system for models and relationships
  - Locate and load metadata files from the filesystem
  - Load and validate metadata files
  - Provide metadata for API and frontend generation
  - Cache metadata for performance
- **Implementation Details**:
  - The MetadataEngine class hard-codes all directory paths:
    - `modelsDirPath` = 'src/models' (hard-coded)
    - `relationshipsDirPath` = 'src/relationships' (hard-coded)
    - `cacheDirPath` = 'cache/' (hard-coded)
  - The MetadataEngine constructor does not take any arguments. It instantiates its own Monolog logger internally.
  - The MetadataEngine automatically scans the hard-coded directories for metadata files when loadAllMetadata() is called.
  - Metadata files are cached in the 'cache/' directory for performance optimization.
- **Features**:
  - Caching for performance
  - Validation of metadata files
  - Dynamic loading of model definitions
  - Self-contained operation with no external dependencies required for instantiation

### Database Connector
- **Function**: 
  + Connects to MySQL database using Doctrine DBAL
  + Generates all SQL used by the framework, including DDL and DML statements,  using Doctrine DBAL
  + Models, Relationships and the Schema Generator use this connector to interact with the database. 
  + Executes SQL queries and returns results
  + Throws specific GCException subclasses for database errors

### The Config class and the config file
- **Purpose**: Provides a centralized configuration management system for the framework
- **Location**: 
   + Config Class: `src/config/Config.php`
   + config file: `/config.php` **Claude Code Instruction**: The config.php file should be located in the root directory of the project, not in the src directory.
- **Function**:
  - Loads configuration settings from a file
  - Provides access to configuration values throughout the framework
  - Allows for dynamic updates to configuration settings
  - Supports environment-specific configurations (e.g., development, production)
  - Provides methods to get and set configuration values
  - Provides a method to get the database connection parameters
  - Provides a method to write the configuration file
  - Provides a method to check if the config file exists and is writable
  - Throws a GCException subclass if the configuration file is not found, not readable or not writable
- **Implementation Details**:
  - The Config class hard-codes the config file location in its `configFilePath` property as 'config.php'.
  - The Config class constructor does not take any arguments. It instantiates its own Monolog logger internally.
  - The Config class loads configuration from the config file automatically when instantiated.
  - The Config class provides a public method `configFileExists()` that returns true if the config file exists and is writable, false otherwise.
- **Config file contents:**
  - The config.php file is a PHP file that returns an associative array with all the configuration settings for the framework.
  - The config file should include the following settings:
    - Database connection parameters (host, port, username, password, database name)
    - Application settings (e.g., debug mode, logging level)
    - API settings (e.g., base URL, authentication settings)
    - Frontend settings (e.g., theme)
    - Metadata cache settings (e.g., cache directory, cache expiration time)
    - Instance name and version information
    - Default list page size for paginated lists
    - Default number of related records to load per page when loading related records in a paginated manner



### Schema Generator
- **Function**: Converts metadata to MySQL DDL statements
- **Triggers**: Setup script and metadata changes
- **Location**: `src/schema`
- **Responsibility**: 
  - Create the database itself if it does not exist. The Config class will provide the database name. The SchemaGenerator MUST be able to do this during install, before a connection to the database is established. And it MUST provide a public method to do this.
  - Create and update database tables based on metadata for models and relationships
  - For any model or relationship, instantiate the class and use the class's fields to generate the database schema
  - If the table for a model or relationship does not exist, create it
  - If the table for a model or relationship exists, update it to match the metadata
  - Detect differences between the metadata for models or relationships and the existing schema
  - Create, update, and delete database tables based on metadata
  - Create join tables for many-to-many relationships
  - Handle relationships and constraints
  - Generate indexes for performance optimization
- **Features**: 
  - Dynamic table creation
  - Relationship constraints
  - Index optimization

### API Engine
- **Function**: Generates RESTful endpoints for all models
- **Pattern**: `/api/{model}` endpoints
- **Operations**: CRUD operations, relationship handling
- **Authentication**: Optional JWT-based auth
- **Location**: `src/models/<model_name>/api` Every model has its own API controller class. Every api controller class should extend an abstract ApiControllerBase class which contains features common to all API controllers.
- **Features**:
  + Dynamic API class discovery - automatically finds and loads API controllers
  + Route registration - collects routes from API controllers
  + Registry caching - stores routes in a file for performance
  + Error handling - comprehensive exception handling for various failure scenarios
  + Logging - logs route conflicts when multiple handlers have the same score
- **Responsibility**: 
  - Handle API requests and responses
  - Return JSON responses
  - Validate input data against model definitions
  - Perform CRUD operations on the database
  - Return JSON responses

### APIRouteRegistry
- **Purpose**: Automatically discovers, registers, and matches API routes by scanning for API controller classes.
- **Location**: `src/api/APIRouteRegistry.php`
- **Principles**:
  + **Auto-discovery** - No manual route registration needed
  + **Performance** - Cached registry for fast lookupss
  + **Error handling** - Comprehensive validation and logging
  + **Maintainability** - Self-managing route system
- **Function**:
  - Scans the `src/models/*/api` directory for API controller classes
  - Registers routes based on the return value of each API controller's 'registerRoutes' method.
  - Caches the registered routes in a file for performance
  - Supports invalidation and reloading of routes when metadata changes
  - Returns the contents of the cache file as an array of routes that map back to their API controller class and method

### API Router
- **Function**: Routes API requests to appropriate handlers
- **Location**: `src/api/Router.php`
- **Principles**:
  - **Simple interface** - Single entry point for routing. From the browser or clients, routes should look like `http(s)://<domain>/#<model_name>(/model_id)`. Use the hash (#) to separate the model name and id from the base URL. The API router will parse the URL and route the request to the appropriate API controller. You may need apache mod_rewrite to route all requests to the rest.php file.
  - **Smart matching** - Handles dynamic route segments
  - **Error handling** - Comprehensive validation and logging
  - **Maintainability** - Self-managing route system
  - **Single Responsibility**: Handles routing logic only
  - **Separation of Concerns**: 
    + Relies on the APIRouteRegistry for route discovery
    + Delegates request handling to API controllers
  - **Error Handling**: Catches exceptions and returns appropriate HTTP responses
- **Performance**: Uses APIRouteRegistry for fast route lookups
- **Responsibility**: 
  - Match the request path and method to a registered route 
  - If an exact match is found, return the API controller class and method to handle the request
  - If no exact match is found, attempt to find a partial match based on the request method and path
  - If tokens are found (i.e. {id}, {model}), parse the request path and set properties on the API controller with the actual values from the request path
  - Once the API controller class and method are found, call the registered method to handle the request
  - Return a JSON response with the result of the API controller method
  - If no matching route is found, throw a GCException subclass indicating the error
- **Features**:
  - Parses request paths and methods
  - Matches routes using APIRouteRegistry
  - Delegates request handling to API controllers
  - Returns JSON responses with appropriate HTTP status codes
  - Handles errors gracefully and returns meaningful error messages
  - Supports partial route matching based on request method and path to provide default behavior for unknown routes
  - Uses a scoring system to determine the best match based on request method, path, and number of components in the path
  - When a matching route is found, it returns the API controller class and method to handle the request. It will also parse the registered route for tokens like {id} and {model}, and then set properties on the API controller named for the tokens with the actual values from the request path.
  - Partial matches scored by component position weights
  - Logs conflicts when multiple routes have same score
  - Throws a GCException subclass if no matching route is found

### Frontend Generator
- **Function**: Dynamic React components based on metadata
- **Features**:
  - Auto-generated forms
  - Data grids and listings
  - Every data listing should be paginated
  - Every data list should provide a "show deleted records" toggle to show or hide deleted records
  - Every data listing should provide a "search" input to filter records by any field
  - Every data listing should allow sorting by any field
  - All select input fields should support searching for values
  - Relationship pickers

### Relationships
- **Purpose**: Define relationships between models to represent associations (e.g., one-to-one, one-to-many, many-to-many).
- **Location**: `src/relationships/*` directory -
  + every relationship type (e.g. one-to-one (1_1), one-to-many (1_M) many-to-many (N_M)) has its own class file. Every relationship class should extend a RelationshipBase class that defines logic common to all relationships.
  + Every relationship between to models should have a metadata file: `src/relationships/<relationship_name>/metadata.php`.
- **Metadata-Driven Relationships**: Relationships between models are defined in dedicated metadata files (e.g., `src/relationships/<relationship_name>/metadata.php`). Each relationship metadata file specifies:
  - The two models involved in the relationship (e.g., `model_a` and `model_b`).
  - The name of the relationship so it may be referred to in other metadata files or in code.
  - The type of relationship (one-to-one, one-to-many, many-to-many).
  - Required fields for the relationship (e.g., foreign keys referencing each model).
  - Optional additional fields (e.g., attributes specific to the relationship such as `status`, `role`, or `created_at`).

- **Including Relationships in Models**:
  - Models can include relationships by referencing the relationship by name and using a special `relatedRecords` field in the model metadata.
  - The relatedRecords field specifies:
    + the relationship name
    + the number of records to load when the model is instantiated. 0 is valid entry here to load no records.
    + the number of record to per page when loading related records in a paginated manner.
  - Relationships are automatically loaded when the model is instantiated, allowing for easy access to related records.
  - Related records will not be loaded by default

- **Core Relationship Fields**:
  - `id`: Unique identifier for the relationship record. This is typically a UUID generated by the framework. Use the composer package `ramsey/uuid` to generate unique identifiers.
  - `model_a_id`: Foreign key referencing the first model
  - `model_b_id`: Foreign key referencing the second model
  - `created_at`, `updated_at`, `deleted_at`: Timestamps for lifecycle management
  - `created_by`, `updated_by`, `deleted_by`: User IDs for audit tracking

- **Extensible Relationship Attributes**:
  - Relationship metadata can define additional fields beyond the core fields, allowing for custom attributes (e.g., `notes`, `priority`, `active`, etc.).
  - These fields are included in the relationship's join table and exposed via API and UI.

- **Dynamic Schema Generation**:
  - The schema generator reads relationship metadata and creates the necessary join tables, including all required and optional fields.
  - Supports soft-deletion of relationship records by setting `deleted_at` and `deleted_by` fields.

- **API & UI Integration**:
  - Relationship endpoints and UI components are generated dynamically based on relationship metadata, supporting CRUD operations and custom attributes.
  - Related records can be queried, filtered, and updated using both core and custom relationship fields.

- **Validation & Constraints**:
  - Relationship metadata can specify validation rules and constraints for both core and custom fields, ensuring data integrity.


### Authentication & Authorization

- **Authentication Provider**: Gravitycar uses the Google Identity Platform (OAuth 2.0/OpenID Connect) for user authentication.
- **User Flow**: Users log in via Google; upon successful authentication, a JWT (JSON Web Token) is issued and used for subsequent API requests.
- **Backend Integration**: The backend verifies the JWT on protected endpoints, extracting user identity and roles from the token claims.
- **Frontend Integration**: The React frontend uses Google Sign-In to authenticate users and stores the JWT in local/session storage for API calls.
- **User Management**: User roles and permissions are managed in the backend database and linked to the authenticated Google account (by email or Google user ID).
- **Authorization**: Endpoints and UI components check user roles/permissions before granting access to protected resources or actions.
- **Optional Authentication**: Some API endpoints are public, while others require authentication and specific roles/permissions as defined in metadata.
- **Security**: All authentication tokens are validated using Google public keys; sensitive operations require HTTPS.


### Error Handling and Logging
- **Error Handling**: 
  - All errors are handled using custom exceptions that extend the base `GCException` class.
  - Errors are logged using the Monolog library with appropriate severity levels.
  - API responses include meaningful error messages and HTTP status codes.
  - Frontend components display user-friendly error messages based on API responses.
- **Logging**:
  - All classes should include debugging logging calls to track application flow and state.
  - Monolog is used for logging, with different log levels (DEBUG, INFO, WARNING, ERROR).
  - All logging channels should be configured in the `config.php` file. These configs should include:
    - Log file path
    - Log level
    - Log format
    - Log rotation settings
- **UI**
  - The UI should provide a mechanism to change the logging level for any channel at runtime.
  - This mechanism should be accessible only to users with the appropriate role/permission.
  - The logging level should be persisted in the config file so that it is applied on the next application startup.
  - The logging level should be represented as a dropdown in the UI, allowing users to select from predefined log levels (DEBUG, INFO, WARNING, ERROR).
  - The validation for the selected log level should ensure that it is one of the predefined log levels.


## Deployment
- **Pre-Deployment Checks**: The framework checks to see if the root directory is writable. If not, it throws a `GCException` indicating that the application cannot be installed.
- **Installation Trigger**: The framework checks to see if the config file exists. If it exists, does it define "installed" as true? If either of these checks fail, the framework enters installation mode.
- **Installation UI**: The user is presented with a setup UI that prompts for database credentials (host, port, username, password, database name) and an initial administrator username. Credentials are validated before proceeding.
- **Config File Generation**: Upon successful validation, the framework writes the provided credentials to the configuration file. The config file installation will also set default values for any config settings the framework users, including all the Monologger logging channels. The initial user information will only be stored as a "Users" model. 
- **Schema Generation**: The schema generator creates all necessary tables and relationships based on the metadata files. This includes core tables for models, relationships, users, roles, and permissions.
- **Initial User Creation**: The initial administrator user is created and assigned the highest permission level. The user is linked to authentication via Google Identity Platform if enabled.
- **Post-Installation Validation**: After installation, the framework re-tests the database connection and table existence to confirm successful setup. Any errors are reported in the UI with actionable feedback.
- **Re-Installation Safeguards**: If the application is already installed, the setup UI is disabled to prevent accidental overwrites. Reinstallation requires explicit confirmation and must include backup options.
- **Environment Support**: The deployment process supports multiple environments (development, staging, production) with environment-specific configuration files and database credentials.
- **Logging**: All deployment actions, errors, and user inputs are logged using Monolog for audit and troubleshooting purposes.




## Testing Strategy

- **Unit Testing**: All core backend logic, including model handlers, metadata parsers, and utility functions, are covered by PHPUnit unit tests. Frontend components are tested using Jest and React Testing Library.
- **Integration Testing**: End-to-end API flows, including authentication, CRUD operations, and relationship management, are validated using PHPUnit and selenium-webdriver. Integration tests ensure correct interaction between backend, database, and frontend.
- **Metadata Validation**: Automated tests verify that metadata files are correctly parsed, schema generation is accurate, and invalid metadata is handled gracefully.
- **Role & Permission Testing**: Tests ensure that user roles and permissions are enforced for all protected endpoints and UI actions.
- **Continuous Integration**: All tests are run automatically on each commit via CI pipelines (e.g., GitHub Actions), with code coverage reports generated.
- **Error Handling**: Custom exceptions and error responses are tested to ensure meaningful feedback and proper logging.
- **Mocking & Stubbing**: External dependencies (e.g., Google Identity Platform, database) are mocked or stubbed in unit and integration tests to isolate test cases.
- **Test Data Management**: Test databases and mock metadata files are used to ensure repeatable and isolated test runs.
- **Frontend E2E Testing**: Selenium-based tests simulate user interactions, verifying UI behavior, authentication flows, and error handling.



## Data Flow

### 1. Model Definition Flow
Metadata Files → Schema Generator → Database Tables -> Model Classes -> API Controllers → API Endpoints → Frontend Components

### 2. Request Flow
React UI → API Request → API Router → API Controller Handler → Database → Response → UI Update
