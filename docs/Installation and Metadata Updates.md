# Installation and Metadata Updates

## Installer Model Changes

- The Installer model now uses the Config class to manage configuration file checks and updates.
- The Installer::runInstallation() method uses Config::configFileExists() to check if the config file exists and is writable, instead of using is_writable or undefined constants.
- The Config class is instantiated with no arguments; its constructor sets up the logger and loads configuration from the hard-coded config.php file.
- The installation workflow now:
  1. Instantiates Config and checks config file existence/writability with configFileExists().
  2. Sets and writes database credentials to the config file using Config::set() and Config::write().
  3. Creates the database if it does not exist using DatabaseConnector.
  4. Loads metadata and generates schema using MetadataEngine and SchemaGenerator.
  5. Creates the initial admin user using the Users model.
  6. Marks installation as complete in the config file.
- All references to config file checks and updates in the Installer model should use the Config class methods for consistency and reliability.

## Best Practices
- Always use the Config class for configuration file management in installation and setup scripts.
- Avoid using direct file system checks (e.g., is_writable) or undefined constants for config file validation.
- Ensure all installation logic is encapsulated in the Installer model and uses framework-provided classes for configuration, database, and metadata management.

## Overview
This document describes the installation process for the Gravitycar framework and 
how to trigger updates to the schema after metadata files are updated. It is intended 
for developers who want to set up the framework and customize it by adding or modifying metadata.

The installation process will look use the Config class to load the configuration file `/config.php` from the root directory. 
If the config file does not exist or does not contain valid database credentials, the application will enter "install mode" and prompt the user to enter the database credentials. The installation process will also use the MetadataEngine class to discover models and relationships defined in metadata files, generate React components for each field type, and create or update the database schema based on the metadata.

### Installation Checks
- Use the Config class to load the `config.php` file from the root directory.
- If the `config.php` file does not exist, or if it does not contain valid database credentials, the installation process will begin. See "Installation" below.
- When the database credentials are provided, the Config class must update the `config.php` file with the provided credentials and set the 'installed' flag to false.

### Installation
- We assume that the user has already cloned the repository and has a working LAMP/WAMP environment.
- Every step of the installation process will be logged using the Monolog library.
- The user will be prompted to enter the following information:
  - Database host
  - Database name
  - Database username
  - Database password
  - Admin username (optional, if the user is authenticated with Google, this will be ignored)
  - Admin user password (optional, if the user is authenticated with Google, this will be ignored)
- The application will validate the provided database credentials by attempting to connect to the database using the `DataBaseConnector` class.
- If the user provides valid credentials, the application will create a `config.php` file in the root directory with the provided credentials. It will use the Config classs to create this file. It will also set the 'installed' flag to false in the config file.
- If the user provides invalid credentials, the application will display an error message and prompt the user to try again.
- Once the `config.php` file is created with valid db credentials, the application will attempt to connect to the database.
- if the connection fails, the application will display an error message and prompt the user to try again.
- if the connection is successful, the application attempt to creat the database if it does not exist.
- Next the application will look for metadata files using the `MetadataEngine` class.
- The `MetadataEngine` will discover every model and every relationship. It will load the metadata files for those models and relationships. It will cache the metadata in memory.
- With all the models and relationships loaded and cached, the MetadataEngine will store the metadata in a cache directory for each model and relationship, `cache/model/<model_name>/<model_name>_metadata.php`.
- The MetadataEngine will also create a `cache/relationships/<relationship_name>/<relationship_name>metadata.php` directory to store the metadata for relationships.
- The MetadataEngine will then instantiate every child class of the `FieldBase` class and pass those instances to the ComponentGeneratorFactory class to get the ComponentGenerator class for that field type. 
- The MetadataEngine will then call the `generateFormComponent()` and `generateListViewComponent()` methods on each ComponentGenerator class to generate the React components for the form and list view for each field type.
- The generated components will be stored in the `cache/components/fields/<field_type>/` directory, where `<field_type>` is the name of the field type (e.g., `text`, `number`, `select`, etc.) in a format that is easily consumable by the React application.
- The MetadataEngine use the SchemaGenerator class to generate the database schema. It will paas an instance of every model and every relationship to the SchemaGenator to produce the database tables for those objects.
- The SchemaGenerator will use the `DataBaseConnector` class method `createTable()` to actually create the database tables in the database.
- Once the database schema is created, the application will display a success message. 
- Now the application will ask the user if they want to create an admin user. If the user is already authenticated with Google, the application will use the Google user information to create an admin user. If the user is not authenticated with Google, the application will prompt the user to enter a username and password for the admin user.
- If the user provides a username and password, the application will create an admin user in the database using the `User` model.
- After the admin user is created, the application will then set the 'installed' flag to true in the `config.php` file and update the config.php file using the Config class.
- The application is now ready to use. The user will be automatically routed to the application's home page.

### Metadata Updates
When a metadata file is updated, the application cannot easily detect this change wihout expensive file system checks. We want to avoid expensive file system checks, so we will use a simple mechanism to trigger metadata updates.
- An admin user (user_type == 'admin') can trigger a metadata update by clicking a button in the admin panel.
- When the button is clicked, the application will call the `MetadataEngine` class to reload the metadata files.
- The `MetadataEngine` will discover every model and every relationship again, and it will load the metadata files for those models and relationships.
- The MetadataEngine will cache the metadata in memory.
- The MetadataEngine will then instantiate every child class of the `FieldBase` class and pass those instances to the ComponentGeneratorFactory class to get the ComponentGenerator class for that field type.
- The MetadataEngine will then call the `generateFormComponent()` and `generateListViewComponent()` methods on each ComponentGenerator class to generate the React components for the form and list view for each field type.
- The generated components will be stored in the `cache/components/fields/<field_type>/` directory, where `<field_type>` is the name of the field type (e.g., `text`, `number`, `select`, etc.) in a format that is easily consumable by the React application.
- With all the models and relationships loaded and cached, the `MetadataEngine` will use the `SchemaGenerator` class to generate the database schema again.
- It will pass an instance of every model and every relationship to the `SchemaGenerator` to produce the database tables for those objects.
- For models and relationships where the table already exists, The `SchemaGenerator` will use the `DataBaseConnector` class method `syncTableWithModel()` to generate and execute the ALTER TABLE SQL statements in the database.
- For models and relationships where the table does not exist, the `SchemaGenerator` will use the `DataBaseConnector` class method `createTable()` to create the database tables in the database.
- If columns are dropped by the SchemaGenerator, they are lost and gone forever. The application will not prompt the user to confirm this action.
- Columns defined as 'core' fields in the metadata files will not be dropped from the database schema.
- Columns that are added to the metadata files will be added to the database schema and populated with the default value defined in the FieldBase object that represents the new field.

For now, there will be no schema versioning. Changes to the metadata files will be applied immediately to the database schema. This means that if a field is removed from the metadata file, it will be removed from the database schema without any confirmation. These changes can only be rolled back by restoring a backup of the database.