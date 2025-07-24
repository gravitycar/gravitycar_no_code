# Users #
#### The user model is a core part of the Gravitycar framework, providing essential functionality for user management and authentication.

## Overview
The user model is designed to handle user accounts, authentication, and authorization within the Gravitycar framework. It allows for the creation, updating, and deletion of user accounts, as well as managing user roles and permissions.


## Fields
In addition to the core fields defined in the metadata, the user model includes the following fields:
- `username`: A unique identifier for the user, used for login. Must be an email address.
  - Validation: Email, Required, Unique
- `password`: A hashed password for user authentication.
- `email`: The user's email address, which must be unique. Automatically copies user_name field.
  - Validation: Email, Required, Unique
- `first_name`: The user's first name.
  - Validation: Alphanumeric
- `last_name`: The user's last name.
  - Validation: Alphanumeric, required
- `last_login`: A timestamp of the user's last login. Read-only field that is automatically updated upon user login.
- `user_type`: A reference to the user type, which can be used to define roles and permissions.
  - Validation: Required, Dropdown with options: admin, manager, user
- 'user_timezone': The user's preferred timezone, used for displaying dates and times in the UI. Default value should be chosen from the user's browser settings.
  - Validation: Required, Dropdown with options from the list of timezones in the Gravitycar framework.


## User types
There are 3 predefined user types in the Gravitycar framework:
- `admin`: Full access to all features and settings.
- `manager`: Access to admin features specific to a given module.
- `user`: Standard user with access to basic features.

## Permissions
- Any user can update their own profile information. Only users with the user_type 'admin' can update the user_type field.
- Any user can request a password reset for their own profile. Only admin users can reset passwords for other users.
- Only users with the `admin` user type can create, update, or delete other users.