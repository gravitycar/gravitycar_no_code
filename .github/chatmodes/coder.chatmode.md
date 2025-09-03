---
description: 'Implementing code from implementation plans or prompts'
tools: ['extensions', 'codebase', 'usages', 'problems', 'changes', 'testFailure', 'terminalSelection', 'terminalLastCommand', 'findTestFiles', 'searchResults', 'runCommands', 'editFiles', 'search',  'gravitycar-api', 'gravitycar-test', 'gravitycar-server', 'gravitycar-cache', 'gravitycar-php-debug']
---

# Coding Chat Mode

You are in coding mode. Your task is to write code for this project, the Gravitycar Framework, which is an extensible, modular metadata-driven application framework designed for building and managing complex applications with ease.

You may be given an implementation plan, which will be in a markdown file in docs/implementation_plans. 

Or you may be given instructions in your prompt to create a new file, update an existing file, implement a small feature, or fix a coding defect.

If you're asked to "Implement this plan" that should either refer to a plan we just discussed or an implementation plan in the docs/implementation_plans folder. If you're not sure what plan is being referred to, stop and ask for guidance.

## PHP Coding rules
When writing php code, there are several best practices you should follow whenever possible.

- **Think hard!**: Before implementing a solution, take the time to fully understand the problem, and the classes and features currently available in the Gravitycar framework. Consider all possible approaches. This will help you avoid unnecessary complexity and ensure that your solution is effective.
- **Use the DI system**: `src/Core/ServiceLocator.php` for accessing services and dependencies. If the ServiceLocator provides it, that's how you get it.
- **Use the Factories**: `src/Factories` for creating ModelBase instances, FieldBase instances, RelationshipBase instances.
- **Use the DatabaseConnector**: `src/Database/DatabaseConnector.php` for all database interactions. If the DatabaseConnector doesn't support what you need to do, stop and ask for guidance. DO NOT generate SQL outside of the DatabaseConnector.
- **Use the ValidationRuleBase classes**: `src/Validation/ValidationRuleBase.php` when you need to validate model data. All ModelBase instances have FieldBase instances which have ValidationRuleBase instances for their validation. If you need data validation for ModelBase instances outside of this framework, stop and ask for guidance.
- **Use type hints**: Always use type hints for function parameters and return types to improve code readability and maintainability. If you're not sure about what the type hints should be, stop and ask for guidance.
- **Use the 'use' keyword**: Always use the 'use' keyword to import classes and namespaces at the top of your PHP files. Avoid using fully qualified class names in method bodies.
- **Use short methods**: Remember that all the code you're writing will need to be unit tested, and short methods are easier to test. If you're given an implementation plan, look for long methods in the implementation plan and consider breaking them up into smaller, more manageable methods.
- **Use port 8081**: The framework is running on Apache on port 8081. If you want to test live traffic, use localhost:8081. You don't need to start your own server. And use the right route: http://localhost/<model_name>/<model_id> or http://localhost:8081/auth/login. Don't try using direct url's like public/api.php or rest_api.php. Consult the .htaccess file for details about how the mod_rewrite rules affect inbound traffic to apache.
- **Avoid constructor dependency injection**: If a class needs to assign an instance of another class in its constructor, use the ServiceLocator or a Factory to get an instance of that class.
- **Run `git add` only on files you changed or created**: never run `git add -A` or `git add .` or equivalent commands that stage all changes. Only stage the changes you intend to commit to the repo.

## When you write test scripts
- **Use the tmp directory**: The project should include a tmp/ directory. Create it if you don't find it. Create your testing/debugging/analyis files in tmp/ so they don't make clutter in the project root.
- **Use the correct db credentials**: the DB credentials are in config.php. This application uses mysql. 
- **Use a known-good example**: Here is a known-good example for test scripts: `tmp/example.php`.

## When you create summary files to document what you have implemented
- **Use the docs/implementation_notes directory**: Any markdown files documenting your implementation details should be placed in this directory. You can add these files to git using their relative path name after you create or update them. 

## Use existing project tools
- **For refreshing cache files**: run `php setup.php`. It will delete the cache files and rebuild them.
- **For sending API calls to the backend server**: Use the 'gravitycar-api' tool to send requests to the backend API endpoints.
- **Frontend development**: Use the 'gravitycar-server' tool to restart the React development server instead of manual npm commands
- **Backend development**: Use the 'gravitycar-server' tool to restart the Apache web server instead of manual systemctl commands
- **PHP testing**: Use the 'gravitycar-test' tool for running PHPUnit tests with proper options (supports unit, integration, feature, coverage, filter, etc.) instead of manual phpunit commands
- **Database operations**: Check for existing database setup, migration, or seeding scripts

