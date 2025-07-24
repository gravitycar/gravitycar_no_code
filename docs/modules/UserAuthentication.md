# User Authentication

# Overview
This document describes the user authentication mechanism in the Gravitycar framework. 
It is intended for developers who want to implement user authentication and authorization features in their applications.

The Gravitycar framework supports two methods of user authentication:
1) Google OAuth2 authentication
2) Username and password authentication

## Google OAuth2 Authentication
This is the preferred method of authentication for the Gravitycar framework.
If a user is authenticated with Google, but not authenticated for the Gravitycar framework, 
then the application will ask the user if they would like to create a Gravitycar user account 
using their Google account information. If they say "Yes", the application will create a new 
user in the database using the Google account information. If they say "No", the application 
will not create a user in the database, and the user will be redirected to the login page.

## Username and Password Authentication
If a user is not authenticated with Google and not authenticated by the Gravitycar framework, 
the application will redirect the user to the login page. The user must enter their username 
and password to log in.

If the user is authenticated, the application will create a session for the user and redirect 
the user to the home page.

Session management is handled by the framework, and the session will be stored using PHP's built-in session methods.
Sessions are created when a user logs in, and they are destroyed when the user logs out. If 
the user doesn't log out, their session will expire after 7 days of inactivity.
