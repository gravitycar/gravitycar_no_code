# The Gravitycar Administration Panel

## Overview
The Gravitycar Application Framework has a number of adminstrative and setup tasks that we currently don't support well. We need to address these shortcoming so we can perform these tasks safely and efficiently.

## The Problems
Here are examples of adminstrative and setup tasks:
- Creating the database from scratch
- Updating the databse schema
- Updating cache files
- Updating RBAC permissions
- Creating default users and roles

### Unmet Use Cases
Our current implementation handles all of this in a catch-all script, `setup.php`, in the root directory. This file is fine for local development, but fails to address these two use cases:
1) As an admin user, I want to rebuild the cache files in production from the UI, without having to ssh into the server to run a script.
2) As a coding agent, I want to be able to update the database schema after making changes to the application's metadata.

### Setup.php script issues
The `setup.php` script has other issues:
1) It assumes the application is being set up in an empty DB every time. That means it tries to create the database, and then adds default users and roles. The code it calls is idempotent, so no real harm is done, but it's not a good practice to call `createDatabase()` when you know the database has already been created.
2) It lives in the root directory, and anyone, including bots, could access it. That's a little more dangerous. Again, idempotency protects us here but why should we leave this script laying around?
3) It uses the `ReflectionClass` instead of just calling methods on the classes themselves. This serves no purpose I can see. 
4) Sometimes it uses `ServiceLocator` to get various class instances, and sometimes it uses `ContainerConfig`. I think `ContainerConfig` is what we are trying to migrate to using.

### Legacy Considerations
Only one other process uses `setup.php`: our github deployment workflow. The calls are here:
- `scripts/build/build-backend.sh:319`
- `scripts/build/build-backend.sh:320`
- `scripts/build/build-backend.sh:326`
- `scripts/deploy/transfer.sh:232`
- `scripts/deploy/transfer.sh:246`


## The Approach
### What we want
- To update the application from a context that understands if the application is already installed and running. 
- To update the application without updating the application data (i.e. creating/updating users).
- A UI for an admin user to update the application.
- A script to allow coding agents and deployment scripts to update the application.
- To conceal our update tools from the public internet.
- To only update the aspects of the application we intend to update.
- To use consistent methodologies across our application.

### We should do
1) Move the setup.php script out of the root directory to a location where it can't be executed.
2) Replace setup.php with a coding-agent-friendly CLI-only script that follows our best-practices and can't be run from the public internet, and accepts options that dictate what specific updates it performs.
3) Create a user-interface and an API Controller that allows authenticated admin users access to perform these adminstrative tasks.
4) Centralize the actual calls to perform these administrative tasks in one file, so the behavior is the same for the API and the new update script.

## Primary Components
1. A new Admin API Controller for handling requests to clear and rebuild cache files or perform other administrative tasks.
2. A new custom view User Interface that will allow an admin user to send requests to the new Admin API Controller.
3. A new CLI-only script, `application-update.php`, that will allow coding agents and deployment scripts access to these administrative tasks.
4. A new Service that consolidates the logic for updating our application and provides those consolidated methods to the CLI update script and the API Controller.


## The Cache Rebuilding Process.
**NOTE**: The admin panel is intended to be extended with additional features not specified here at a future date. Make sure you leave room in the UI for us to add new features later.

The first admin panel feature we'll build is the cache rebuild feature. There are several files in the `cache/` directory:
- metadata_cache.php
- api_routes.php
- navigation_cache_*.php
- documenation/*.php

The admin panel should provide options for updating each of these individually, or all of them in a single call. So all of the operations below (archiving, clearing, rebuilding, validating) should support operating on any subset of the cache files.

**NOTE**: if the metadata cache file is rebuilt, this may require updates to the database schema and/or the permissions table. The admin panel should display options to update the schema and to update the permissions any time the metadata cache file will be rebuilt. These options should default to true.

Rebuilding the cache files will follow these steps:
1. Cache Archiving
2. Cache Clearing
3. Cache Rebuilding
4. Cache Validation

### Cache Archiving
Use the `tar` utility to create an archive of the `cache/` directory. Store the archive in the application's root directory. Name it: `cache_<YEAR>_<MONTH>_<DAY>_<24HOUR>_<MINUTE>_<SECOND>.tar`.

If the contents of the `cache/metadata_cache.php` file change, we may also need to update the database schema and update the appication's permissions. Since these are potentially very time-consuming, 

### Cache Clearing
Now that the cache files are archived, we can delelete them.
Remember that we must support the ability to delete, rebuild and verify one, some or all cache files. 

- Metadata cache file: cache/metadata_cache.php
- Navigation cache files: cache/navigation_cache_*.php
- Api Routes cache file: cache/api_routes.php
- Documentation cache files: cache/documenation/*

### Cache Rebuilding
- Use the `MetadataEngine::loadAllMetadata()` rebuild the metadata cache file.
- Use the `APIRouteRegistry::rebuildCache()` method to rebuild the api routes file.
- Use the `DocumentationCache::cacheOpenAPISpec()` to rebuild it. You can also look at `OpenAPIGenerator::generateSpecification()` to see how `cacheOpenAPISpec()` is called.
- Use the `NavigationBuilder::buildAllRoleNavigationCaches()` method to build the navigation cache files.

#### Updating Database Schema and Permissions
When the `metadata_cache.php` is rebuilt, we may need to update the database schema and/or the permissions table. The admin panel should pass in options to enable updating the schema and permissions. When those options are true, we update the accordinglly.

**Updating the Schema**: use the `SchemaGenerator::generateSchema()` method.
**Updating Permissions**: use the `PermissionsBuilder::buildAllPermissions()` method.

### Cache Validation
- For PHP files, we just want to check that PHP file has no syntax errors. 
Run `php -l <cache file path>` for each PHP file in the cache directory. 