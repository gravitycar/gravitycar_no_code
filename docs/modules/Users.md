# Users Module

## Overview
Provides the UI for managing the user accounts in the Gravitycar framework.

### Password Reset
Users with the admin user type can update the password for any user by using the password field  in the edit view.
Other users can request a password reset by providing their email address in the password reset view.
When a user requests a password reset, they will receive an email with a link to reset their password. 
The password reset link will contain a token that is valid for 24 hours. The token is generated when the user requests a password reset and is stored in the database along with the user's email address.
The password reset token will only allow the user to reset their own password, and it will not allow them to reset the password for any other user.
When the user clicks on the link, they will be taken to a page where they can enter their new password and confirm it.
The password reset view can only be accessed by via the email link, and not directly from the UI. 
The password reset links can only be used once. 
Users do not have to be logged in to request a password reset. 
Only users with an email address in the system can request a password reset.

### Models
- users 

### Menu Links
- Users (links to list view)
- Add User (links to edit view with empty form for creating a new user)
- Password Reset (links to password reset view)

### Views
- List
  - users.user_name (links to edit view)
  - users.first_name
  - users.last_name
  - users.email
  - users.user_type
  - users.last_login
- Edit
  - users.user_name
  - users.first_name
  - users.last_name
  - users.email
  - users.user_type (dropdown with options: admin, manager, user)
  - users.password (optional, for updating password)
- Password Reset Request
  - users.email (input field for email)
  - Text explaining that the user will receive an email with a link to reset their password
  - users.submit (button to submit the password reset request)
- Password Reset
  - users.email read-only (display the email address for which the password is being reset)
  - users.password (input field for new password)
  - users.confirm_password (input field for confirming new password)
