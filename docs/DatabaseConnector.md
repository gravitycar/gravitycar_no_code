# Database Connector

## Overview
The Database Connector is a utility class that provides a connection to the 
database and allows for executing SQL queries. 
It is used by the framework to interact with the database, retrieve data, 
and perform CRUD operations on models and relationships. It's also used 
by the SchemaGenerator to generate the database schema based on the metadata 
files.
This should be a singleton class, meaning that there should only be one instance 
of this class in the application.
Other classes can generate SQL if they need to, but they MUST use the DatabaseConnector to execute those SQL statements. It's preferable to use the methods the Database Connector provides, such as `create`, `update`, `retrieve`, and `delete`, rather than executing raw SQL statements directly. If other classes do need to execute SQL statements, any values from the user must be properly sanitized and bound to the query to prevent SQL injection attacks. The DatabaseConnector should handle this sanitization and binding automatically.

IMPORTANT: remember that queries such as "SHOW TABLES LIKE 'my_table'" DO NOT allow for parameter binding. In these cases, you must sanitize the value using the `sanitizeValue` method before including it in the query.

## Transaction handling patterns
The DatabaseConnector should support "Unit of Work" transaction patterns, allowing for multiple operations to be executed as a single transaction.
The DatabaseConnector should be able to handle transactions, including committing and rolling back transactions as needed.
The DatabaseConnector should not manage transactions itself, but rather provide public methods to begin, commit and roll back transactions, and to execute queries within a transaction context.
Beginning, committing and rolling back transactions should be done using the Doctrine DBAL Connection class methods, which should be exposed by the DatabaseConnector.
Beginning, committing and rolling back transactions MUST be done by the classes that call the DatabaseConnector methods, not by the DatabaseConnector itself. Only these classes will have an understanding what a "Unit of Work" is in the context of their operations.

## Migration strategies for schema changes
When new fields are added to a model, the DatabaseConnector should be able to handle populating those fields with default values or null values as appropriate.
When fields are removed from a model, the DatabaseConnector should be able to handle the removal of those fields from the database schema without losing any data.
When fields are renamed, the DatabaseConnector should be able to handle the renaming of those fields in the database schema without losing any data.
When relationships are added or removed, the DatabaseConnector should be able to handle the creation or deletion of foreign keys and indexes as appropriate.


## Properties, their default values and descriptions
\Doctrine\DBAL\Connection `conn`: null - the database connection instance used to interact with the database. This should be a singleton instance of the Doctrine DBAL Connection class.
Config `config`: null - the configuration object that contains the database connection parameters. This should be an instance of the Config class, which is used to load the database configuration from a file or environment variables.
Logger `logger`: null - the logger instance used to log messages and errors. This should be an instance of the Monolog Logger class, which is used to log messages to a file or other logging destination.

## Methods, their return types and descriptions
- `__construct()`: function()
  - private method 
  - Constructor initializes the config property using the `Config::getInstance() method`. 
  - Checks the 'installed' config property. If it is false, DO NOT try to connect to the database. Just initialize the config property.
  - Checks the database.host and database.name config properties. If they are not set, DO NOT try to connect to the database. Just initialize the config property.
  - This method will use the Doctrine DBAL Connection class to create a new connection instance.
  - If the application is in install mode, it should not attempt to connect to the database until the connection parameters exist in the config file. Check the config property for the connection parameters. If they aren't present, do not try to connect.
  - If the connection fails, it should throw a GCException with a descriptive error message.
- `getDBConnectionParams()`: function(): array
  - Returns the database connection parameters from the config property.
  - This method retrieves the database connection parameters from the config property and returns them as an associative array.
  - The returned array should contain the following keys: 'host', 'port', 'dbname', 'username', 'password'.
  - If any of these parameters are missing, it should throw a GCException with a descriptive error message.
- `getInstance()`: function(): \DatabaseConnector
  - Returns the singleton instance of the DatabaseConnector class. If the instance does not exist, it will create a new instance using the configuration provided in the config file.
  - This method ensures that only one instance of the DatabaseConnector is created and used throughout the application.
  - If this method is called, and the connection property is not set or is null, call the `connect()` method to establish the connection.
  - If the connection fails, use the config->get('installed', false) property to determine if the application is in install mode. If it is, do not throw an exception, just return the instance with no connection. If the application is not in install mode, throw a GCException with a descriptive error message.
- `connectionIsValid()`: function(): bool
  - Checks if the current database connection is valid and active.
  - This method should return true if the connection is established and valid, and false otherwise.
- `getConnection()`: function(): \Doctrine\DBAL\Connection
  - Returns the database connection instance. If the connection is not already established, it will create a new connection using the configuration provided in the config file.
- `connect()`: function(): void
  - Should get the database connection parameters from the config property using the `getDBConnectionParams()` method.
  - This method should not accept any parameters. It should use the `getDBConnectionParams()` method to get the connection parameters.
  - Establishes a connection to the database using the configuration provided in the constructor.
  - If the connection is already established, this method should do nothing.
  - This method should be idempotent, meaning that calling it multiple times will not change the state of the connection.
  - This method should also handle any exceptions that may occur during the connection process, such as if the database server is unreachable or if the credentials are incorrect. Any exceptions should be converted to a GCException with a descriptive error message.
  - This method should be called before executing any queries to ensure that the connection is established.
  - If the connection fails, it should throw a GCException with a descriptive error message.
- `sanitizeValue(mixed $value): mixed`
  - Sanitizes the provided value to prevent SQL injection attacks.
  - This method should use the Doctrine DBAL Connection class's `quote` method to safely escape the value.
  - It should return the sanitized value that can be safely used in SQL queries.
  - If the value is an array, it should recursively sanitize each element in the array.
  - Every value from every field should be run through this method before being used in a SQL query to ensure that it is safe to use.
- `executeStatement(string $query, array $params = [], array $types): \Doctrine\DBAL\Driver\ResultStatement` : function()
  - Executes a SQL statement on the database. This method should prepare the statement, bind the parameters, and execute it.
  - The `$query` parameter is the SQL query to be executed.
  - The `$params` parameter is an optional associative array of parameters to bind to the query.
  - The `$types` parameter is an optional associative array of types for the parameters.
  - This method should return a ResultStatement object that contains the result of the query execution.
  - If the query fails, it should throw a GCException with a descriptive error message.
- `create(ModelBase $model): bool`
  - Uses DBAL's QueryBuilder to build the insert statement for a new record in the database for the given model.
  - The `$model` parameter is an instance of ModelBase. The $model must have its fields populated with FieldBase objects, which in turn must have their values set using the `set` method. The `set` method should validate the value of every field, so these values should be valid before calling create.
  - The model provides the name of the table to update with its `table` property, and the fields to insert with the array of FieldBase objects in its `fields` property.
  - Field objects that have `isDbField` set to false should not be included in the insert statement.
  - The method should prepare the SQL INSERT statement, bind the model's field values, and execute the statement.
  - If the insertion fails, it should throw a subclass of GCException with a descriptive error message.
- `update(ModelBase $model): bool`
  - Uses DBAL's QueryBuilder to build the update statement for an existing record in the database for the given model.
  - The `$model` parameter is an instance of ModelBase. The $model must have its fields populated with FieldBase objects, which in turn must have their values set using the `set` method. The `set` method should validate the value of every field, so these values should be valid before calling update.
  - The model provides the name of the table to update with its `table` property, and the fields to update with the array of FieldBase objects in its `fields` property.
  - Field objects that have `isDbField` set to false should not be included in the update statement.
  - If a Field object's `hasChanged` method returns false, it should not be included in the update statement.
  - The `id` field is used to identify the record to update, and it should be marked as a primary key in the model's metadata.
  - The method should prepare the SQL UPDATE statement, bind the model's field values, and execute the statement.
  - If the update fails, it should throw a subclass of GCException with a descriptive error message.
- `softDelete(ModelBase $model): bool`
  - Uses DBAL's QueryBuilder to build the update statement that performs a soft delete on an existing record in the database for the given model.
  - The `$model` parameter is an instance of ModelBase. 
  - The model's `table` property provides the name of the table to update, and the `fields` property contains the array of FieldBase objects. For `softDelete()` we are only interested in three fields: id, deleted_at, and deleted_by.
  - The `id` field is used to identify the record to soft-delete, and it should be marked as a primary key in the model's metadata.
  - The QueryBuilder should only update the `deleted_at` and `deleted_by` fields to mark the record as deleted, and use the 'id' field to identify the record to update.
- `retrieve(ModelBase $model): array`
  - Uses DBAL's QueryBuilder to build the select statement to retrieve a record from the database for the given model.
  - The `$model` parameter is an instance of ModelBase. The model provides the name of the table to query with its `table` property, and the fields to select with the array of FieldBase objects in its `fields` property.
  - The method should prepare the SQL SELECT statement, bind any parameters if necessary, and execute the statement.
  - If the record is found, it should return an associative array of field names and their values.
  - If the record is not found, it should return an empty array.
  - If the retrieval fails, it should throw a subclass of GCException with a descriptive error message.
- `createTable(ModelBase $model): bool`
  - Uses DBAL's SchemaManager to create a new table in the database based on the model's metadata.
  - If the table already exists, it should return false and log that the table already existed, without throwing an error.
  - The method should retrieve the model's metadata, including the table name and fields, and use this information to create the table.
  - If the table already exists, it should return false without throwing an error.
  - If the table is created successfully, it should return true.
  - If the creation fails, it should throw a subclass of GCException with a descriptive error message.
- `syncTableWithModel(ModelBase $model): bool`
  - Uses DBAL's SchemaManager to synchronize the database table with the model's metadata.
  - The method should compare the current state of the table with the model's metadata and make any necessary changes to the table structure, such as adding or removing fields, changing field types, etc.
  - If the table is already in sync with the model, it should return true without making any changes.
  - If the synchronization fails, it should throw a subclass of GCException with a descriptive error message.