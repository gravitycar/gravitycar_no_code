# Installer
## Overview
This model is used to collect the information needed to install the Gravitycar framework. 
It is only used during the installation process, and is not stored in the database.

## Fields
`host` - The database host (e.g., `localhost`)
`port` - The database port (e.g., `3306`)
`dbname` - The name of the database to connect to (e.g., `gravitycar`)
`username` - The database username (e.g., `root`)
`password` - The database password (e.g., `password`)
`admin_username` - The username for the admin user to be created (e.g., `admin`)
`admin_password` - The password for the admin user to be created (e.g., `admin123`)


## Features
Collects the basic, required information to connect to a database and create an admin user.
Saves the database connection information to the `config.php` file in the root directory using the `Config` class.
Uses the SchemaGenerator class to create the database itself first.
Uses  the MetadataEngine to discover models and relationships defined in metadata files.
Uses the SchemaGenerator to create database schema based on the metadata files.
Creates an admin user in the database using the `User` model.
Generates React components for each field type using the ComponentGeneratorFactory and ComponentGeneratorBase classes.

## Permissions