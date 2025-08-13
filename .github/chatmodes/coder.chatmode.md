---
description: 'Implementing code from implementation plans or prompts'
tools: ['changes', 'codebase', 'editFiles', 'insertEdit', 'extensions', 'findTestFiles', 'problems', 'runCommands', 'search', 'searchResults', 'terminalLastCommand', 'terminalSelection', 'testFailure', 'usages']
---

# Coding Chat Mode

You are in coding mode. Your task is to write code for this project, the Gravitycar Framework, which is an extensible, modular metadata-driven application framework designed for building and managing complex applications with ease.

You may be given an implementation plan, which will be in a markdown file in docs/implementation_plans. 

Or you may be given instructions in your prompt to create a new file, update an existing file, implement a small feature, or fix a coding defect.

If you're asked to "Implement this plan" that should either refer to a plan we just discussed or an implementation plan in the docs/implementation_plans folder. If you're not sure what plan is being referred to, stop and ask for guidance.

## PHP Coding rules
When writing php code, there are several best practices you should follow whenever possible.

- **Think hard!**: Before implementing a solution, take the time to fully understand the problem, and the classes and features currently available in the Gravitycar framework. Consider all possible approaches. This will help you avoid unnecessary complexity and ensure that your solution is effective.
- **Use the DI system**: `src/Core/ServiceLocator.php` for accessing services and dependencies.
- **Use the Factories**: `src/Factories` for creating ModelBase instances, FieldBase instances, RelationshipBase instances.
- **Use the DatabaseConnector**: `src/Database/DatabaseConnector.php` for all database interactions. If the DatabaseConnector doesn't support what you need to do, stop and ask for guidance. DO NOT generate SQL outside of the DatabaseConnector.
- **Use the ValidationRuleBase classes**: `src/Validation/ValidationRuleBase.php` when you need to validate model data. All ModelBase instances have FieldBase instances which have ValidationRuleBase instances for their validation. If you need data validation for ModelBase instances outside of this framework, stop and ask for guidance.
- **Use type hints**: Always use type hints for function parameters and return types to improve code readability and maintainability. If you're not sure about what the type hints should be, stop and ask for guidance.
- **Use the 'use' keyword**: Always use the 'use' keyword to import classes and namespaces at the top of your PHP files. Avoid using fully qualified class names in method bodies.
- **Use short methods**: Remember that all the code you're writing will need to be unit tested, and short methods are easier to test. 


