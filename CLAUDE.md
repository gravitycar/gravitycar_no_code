# CLAUDE.md

## Project Context

I am building full-stack web application framework, called "The Gravitycar Framework", using:

- **Frontend**: React (Next.js, Shadcn UI)
- **Backend**: PHP 8.2+
- **Database**: MySQL 8.0+
- **Testing**: PHPUnit, Selenium WebDriver
- **Logging**: Monolog
- **Dependency Management**: Composer
- **Version Control**: Git
- **Web Server**: Apache 2.4+
- **Database Abstraction**: Doctrine DBAL
- **Other Tools**: Node.js, npm/yarn

## IMPORTANT: Intended nature of the Gravitycar Framework:
The Gravitycar Framework is intended to be a highly extensible, metadata-driven web application 
framework that allows developers to define data models, fields, and relationships purely through 
metadata files. The framework should automatically generate CRUD operations and UI components 
based on this metadata. The framework should be modular, allowing developers to easily add new field types,
validation rules, and component generators without modifying core code. The framework should support a "fallback"
approach to API endpoints, where if a specific endpoint is not defined for a model, the framework will use a generic
endpoint that can handle any model. The framework should be designed with best practices in mind, including
separation of concerns, single responsibility principle, and SOLID principles.

Metadata in the framework will define models. A model's metadata will define the model's name, its display label, and most 
importantly the model's fields. The metadata for a model's fields will define each field, its name, its type, its validation rules,
and any additional properties that apply to that field. For example, a field's metadata may define whether the field is required, what its default value is, 
what its maximum length is, what label should be displayed for the field in the UI. Field types will have default values for many of these
properties, but they can all be overridden in the metadata. 

The metadata will also define relationships between models.
Relationships are a type of model, but they will have additional properties to define which two models are being related,
what type of relationship it is (1:1, 1:N, N:M), and any additional properties that apply to the relationship.

Finally, the metadata MUST be made available to the React frontend in a format easily consumed by React. The metadata from the server MUST include
all the React component generators for every field type. The React component generators will be responsible for 
generating the React components for each field type.

The modular nature of the metadata itself - the ability to define new models, add fields or relationships to 
existing models, or remove fields or relationships from existing models - is the key to the framework's extensibility. 


## IMPORTANT: when you build this application
- You will find model descriptions in the `docs/models` directory. Each model will have its own markdown file that 
describes the model, its fields, and any special behavior. You MUST build every model described in the `docs/models` 
directory. That means create a metadata file with the fields described in the .md file, and create a PHP class that 
extends ModelBase for that model. The PHP class should implement the features described in the .md file. If you
cannot work out how to implement a model, ask for help.


## Coding Best Practices
**Claude Code Instruction**: All classes defined in this project should be in the 'Gravitycar' namespace.

- **Follow PSR Standards:** Adhere to PSR-1, PSR-4, and PSR-12 for PHP code style and autoloading.
- **Type Declarations:** Use strict type declarations and type hints for all functions and methods.
- **Separation of Concerns:** Keep business logic, data access, and presentation layers separate.
- **Extensibility:** Design classes and modules to be easily extended via metadata and/or inheritance.
- **Documentation:** Document all public classes, methods, and properties using PHPDoc.
- **Error Handling:** Use exceptions for error handling. Catch and log exceptions using Monolog.
- **Logging:** IMPORTANT: EVERY class should have a logger property that is a Monolog/Logger instance. Log important events, errors, and warnings using Monolog. Avoid logging sensitive information. 
- **Security:** Sanitize all user input and use prepared statements for database queries.
- **Configuration:** Store configuration in a config file, never in code. Interact with config values via a Config class. IMPORTANT: every class that needs config values should have a config property that is an instance of the Config class.
- **Database Access:** Use Doctrine DBAL for all database interactions. Avoid raw SQL queries. Use the DatabaseConnector class to get a DBAL connection instance. 
- **Testing:** Write unit tests for all core classes and methods using PHPUnit. Aim for high code coverage.
- **Complexity Management:** Keep cyclomatic complexity low. Refactor complex methods into smaller, focused methods. Use early returns to reduce nesting.
- **Single Responsibility Principle:** Each class and method should have a single responsibility.
- **Dependency Injection:** Use dependency injection to manage class dependencies.
- **Avoid Global State:** Minimize the use of global variables and singletons.
- **Use Composer:** Manage dependencies using Composer. Avoid including libraries manually.
- **Naming Conventions:** Use descriptive names for classes, methods, and variables. Follow camelCase for variables/methods and PascalCase for classes.
- **Method signatures:** IMPORTANT: always use type hints for all method arguments. Keep method signatures simple. Avoid methods with more than 3 parameters. Use value objects or arrays to group related parameters.

## Examples of bad practices to avoid and good practices to follow
### Excessive Complexity Examples
#### Bad Examples - excessive nesting
```php
function areThreeThingsTrue($a, $b, $c) {
    if ($a) {
        if ($b) {
            if ($c) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}
```
**Claude Code Instruction**: When you see nested if statements, immediately refactor to early returns. Each level of nesting adds 2 points to complexity score.
#### Good Example - early returns
```php
function areThreeThingsTrue($a, $b, $c) {
    if (!$a) {
        return false;
    }
    if (!$b) {
        return false;
    }
    if (!$c) {
        return false;
    }
    return true;
}
```
**Claude Code Instruction**: Aim for complexity score under 4/10. Use early returns, throw specific errors, and extract helper functions.

### Separation of Concerns Examples
#### Bad Example - mixing concerns
```php
function processUserInput($input) {
    // Validate input
    if (empty($input)) {
        throw new Exception("Input cannot be empty");
    }
    // Save to database
    $db->query("INSERT INTO table (column) VALUES (?)", [$input]);
    // Render response
    echo "Input saved successfully!";
}
```
**Claude Code Instruction**: Avoid mixing validation, data access, and presentation in one function.
#### Good Example - separated concerns
```php
function validateInput($input) {
    if (empty($input)) {
        throw new InvalidArgumentException("Input cannot be empty");
    }
}

function saveToDatabase($input) {
    global $db;
    $db->query("INSERT INTO table (column) VALUES (?)", [$input]);
}

function renderResponse($message) {
    echo $message;
}

function processUserInput($input) {
    validateInput($input);
    saveToDatabase($input);
    renderResponse("Input saved successfully!");
}
``` 
**Claude Code Instruction**: Each function should have a single responsibility.

**Claude Code Instruction**: Classes get +3 complexity for validation logic, +3 for DB access, +2 for business logic, +2 for UI rendering. Target: 0/10.

**Claude Code Instruction**: Always treat any file path as if its from the root of the project. 

**Claude Code Instruction**: When you define an array in a method, and the contents of the array are only used in that method and not intended to be changed programmatically, define the array as a constant at the top of the class.

## Critical Rules

### 🚫 NEVER DO THESE

1. Create files over 300 lines
2. Nest code more than 3 levels deep
3. Mix HTTP concerns with business logic
4. Put business logic in UI components
5. Create abstractions with fewer than 3 use cases
6. Use generic error messages
7. Skip tests for business logic
8. Use circular dependencies
9. Hardcode configuration values in class files.

### ✅ ALWAYS DO THESE

1. One file = one purpose
2. Write tests before implementation
3. Use explicit, descriptive names
4. Document complex logic with examples
5. Handle errors with specific types
6. Handle Exceptions with specific Exception classes that extend from one base Exception class that offers logging and context.
7. Extract complex logic to separate functions
8. Put business logic in models or API services
9. Refer to file paths as "<some_name>FilePath" and directory names as "<some_name>DirPath" in code and comments.
10. Handle dependencies internally where possible either via dependency injection or by instantiating them in the constructor. Don't pass dependencies as constructor arguments unless absolutely necessary.



## Testing Best Practices

- **Unit Testing:** Write PHPUnit tests for all core classes, especially those in `src/core`, `src/factories`, and `src/fields`.
- **Test Coverage:** Aim for high code coverage, prioritizing critical and complex logic.
- **Mocking:** Use mocks/stubs for database and external dependencies in unit tests.
- **Integration Testing:** Write integration tests for API endpoints and database interactions.
- **Frontend Testing:** Use Selenium WebDriver for end-to-end UI tests.
- **Continuous Testing:** Run tests automatically before merging code (CI pipeline recommended).
- **Test Data:** Use fixtures or factories to generate test data, avoid hardcoding.
- **Error Scenarios:** Test for error conditions, edge cases, and invalid input.

## Code Review
- **Checklist:** Review for code style, test coverage, documentation, and security.

## Commit Messages

- **Format:** Use clear, concise commit messages. Reference issues or features where applicable.
- **Atomic Commits:** Make small, focused commits that are easy to review and revert.

