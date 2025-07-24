# State Management

## Overview
This document provides an overview of state management pattern and practices used in the Gravitycar framework. State management is crucial for maintaining the integrity and consistency of data across the Gravitycar framework, especially in a metadata-driven environment.

### Metadata-Driven State Management
The Gravitycar framework uses a metadata-driven approach to manage state. This means that the structure and behavior of data models are defined through metadata files, which allows for dynamic changes without altering the underlying codebase.
- Metadata files define the fields, relationships, and validation rules for each model. The framework uses these definitions to create and manage the state of each model instance.
- State is managed through a combination of the backend (PHP) and frontend (React) components. The backend handles data persistence, while the frontend manages the user interface and interactions.
- Model definitions are stored in PHP files as associative arrays. These arrays define the fields, relationships, and validation rules for each model. The framework uses these definitions to create and manage the state of each model instance.
- Cached metadata, front-end and back-end, should be used to improve performance. The framework should cache metadata files to avoid repeated parsing and loading, especially for frequently accessed models.
- Cached metadata should be kept until a script to re-cache the metadata is run. This script can be triggered manually from a link in the application that is only accessible to users with user_type = 'admin',  or automatically during deployment.
- The framework should provide a mechanism to clear the cache when metadata files are updated, ensuring that the latest definitions are always used.
- The MetadataEngine class is responsible for loading and caching metadata files. It provides methods to retrieve model definitions, field definitions, and validation rules based on the metadata files. It will also compile the React components for each model based on the fields using the ComponentGeneratorBase subclass specifc to each field. The specific CompoentGeneratorBase subclass for any field is returned by the ComponentGeneratorFactory. Each CompoentGeneratorBase subclass has the methods necessary to produce the React components code. The MetadataEngine will cache the compiled React components in a format easily consumable by the frontend.

### Synchronization patterns
- The framework uses a synchronization pattern to ensure that the state of the frontend and backend remains consistent. This includes:
  - **Event-driven updates**: When a model instance is created, updated, or deleted, an event is triggered to notify the frontend of the change. The frontend then updates its state accordingly.
  - **Polling**: In cases where real-time updates are not feasible, the frontend may poll the backend for changes at regular intervals.
  - **Metadata updates**: When metadata files are updated, the framework should provide a mechanism to refresh the cached metadata and recompile the React components. This ensures that any changes to the model definitions are reflected in the user interface.

### Form validation and submission
- The framework provides a robust validation mechanism for form submissions:
  - **Validation rules**: Each field can have associated validation rules defined in the metadata files. These rules are applied both on the frontend (React) and backend (PHP) to ensure data integrity.
  - **Error handling**: If validation fails, appropriate error messages are displayed to the user, and the form submission is halted until the errors are resolved.
  - **Asynchronous validation**: For certain fields, such as those requiring uniqueness checks, asynchronous validation can be performed to ensure that the data meets all requirements before submission.
  - **Form submission**: Once all validation checks pass, the form data is submitted to the backend via a RESTful API endpoint. The backend processes the request, updates the database, and returns a response indicating success or failure. A 'Prcessing' message should be displayed during the time between the form being submitted and the response being received from the backend.
  - **Exception handling**: If an error occurs during form submission (e.g., database errors), the framework should handle exceptions gracefully, providing feedback to the user and logging the error for further investigation.

### Performance Patterns
- To optimize performance, the framework employs several strategies:
  - **Lazy loading**: Model instances and their related data are loaded on demand, reducing the initial load time and memory usage.
  - **Caching**: Frequently accessed data, such as model definitions and React components, are cached to reduce the overhead of repeated parsing and loading.

### Concurrency handling
- The framework should handle concurrent updates to model instances gracefully:
  - **Optimistic concurrency control**: When a model instance is updated, the framework checks if the instance has been modified since it was last fetched. If it has, an error is returned, and the user is prompted to refresh their data.
  - **Locking mechanisms**: For critical operations that require exclusive access to a model instance, the framework can implement locking mechanisms to prevent concurrent modifications.

### Metadata updates during active sessions
- When metadata files are updated while users are actively using the application, the framework should handle these changes without disrupting the user experience:
  - **Graceful degradation**: If a user attempts to access a model or field that has been modified, the framework should provide a clear error message and prompt the user to refresh their session.
