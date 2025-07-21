# Gravitycar: 
### An extensible metadata driven web-based application framework.

## Purpose
The end goal of this framework is to allow developers to create any model,
or add new fields to a model, or remove existing fields from a model, 
or add relationships to a model just by creating or updating metadata 
files for that model.

The most important feature of the Gravitycar framework is its **extensibility**.


## Technology Stack
### Backend
- **PHP**: 8.2+ (Core language)
- **MySQL**: 8.0+ (Database)
- **Composer**: Dependency management
- **PHPUnit**: Testing framework
- **Doctrine DBAL**: Database abstraction layer
- **Monolog**: Logging library
- **Apache Web Server**: 2.4+ (Web server)

### Frontend
- **React**: 18.x (UI framework)
- **Node.js**: 18.x+ (Runtime for build tools)
- **npm/yarn**: Package management
- **selenium-webdriver**: For end-to-end testing

### Development Tools
- **Git**: Version control
- **PHPStorm/VS Code**: Recommended IDEs


## Installation
The framework can be installed by cloning the repository and running the setup script.
The setup script will look for a config files. If there is no config file, or if the config 
file doesn't contain correct db credentials, the setup script will prompt the user for db 
credentials and create a config file.
The setup script will rely on the metadata files to create the database schema.

## Functionality

The gravitycar framework should support:
- standard CRUD operations for all models defined in the metadata files
- relating records to other records (1:1, 1:N, N:M)
- Users and authentication
- User roles and permissions
- Any data model that can be defined in the metadata files

The gravitycar framework will be accessed by a RESTful API. 
User authentication should be optional, but some endpoints will require authentication.
