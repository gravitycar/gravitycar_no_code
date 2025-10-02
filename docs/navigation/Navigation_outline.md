# A navigation system for the Gravitycar Framework

We want to create an extensible navigation system for the gravitycar framework that will:

- Show users all of the links their role allows them to see.
- Show users NONE of the links their role does not allow them to access.
- Govern whether or not to show buttons like "Add a New <Model Name>" or "Edit" or "Delete".
- Automatically add new links as new Models or other features are added to the framework.
	
## The Two Major Components:
In this context, 'components' are one or more classes in php for the backend, and one or more ts files for the frontend.

### The Backend Navigation Component

- This component will provide a role-appropriate list of api endpoints to a client based on the currently authenticated user.
- It will return this data in json format.
- It will pull this data from a cache for performance.
- The cache will be built/updated in a similar fashion to how we update the metedata_cache.php and api_routes.php files in cache with the setup.php script.
	
### Frontend Navigation Component

- This component is informed by the Backend Navigation Component. It will query the Backend component to get the list of links to display to the user.
- It will display a list of links the user can click on to navigate from one model or other page to another.
- It will only display the links the user has access to.
- It will completely replace the current navigation element for the framework.
	
	
## Expected frontend behavior	
- The Frontend Navigation Component should be displayed vertically on the left side of the application. Here is an example:
```
Movies
Books
Movie Quotes
Users
```	
- Clicking on a link will navigate to the list view of the Model, and open a model-specific menu below the link. 
- The menu should include at least a "Create" option if the user's role allows the 'create' action for that model. 
- Here is an example of how it would look if you clicked on the "Books" link:

```
Movies
Books
	-> Create Book
Movie Quotes
Users
```
- Clicking on the 'Create Book' link in the menu would open up the create view for the Books model, in the same way that the "Add New Book" does currently.


## Tools we have to work with
**The MetadataEngine**: 
This class provides a list of all Models. 
- `MetadataEngine->getAvailableModels()`
    
**The roles_permissions relationship**: 
This is a ManyToMany relationship that links each Roles model to one or more Permissions models for every model and every non-model endpoint.
We can use the Roles model to retrieve all Roles models, and then each Roles model to retrieve the linked Permissions models:
```php
$seedRole = ModelFactory->new('Roles');
$allRoles = $seedRole->find(['deleted_at' => null], ['id', 'name']); // find() returns an array of models.
foreach ($allRoles as $role) {
    $allPermissionsLinkedToRole = $role->getRelated('roles_permissions'); // getRelated() returns raw data, not models.
}
```
    
**The APIRouteRegistry**: 
This class provides many methods for retieving cached data about the available api endpoints.
- `APIRouteRegistry->loadFromCache()` // reads the cache/api_routes.php file into memory.
- `APIRouteRegistry->getAllEndpointPaths()` // returns the path for each registered route.
- `APIRouteRegistry->getAllRegisteredControllers()` returns instantiations of every APIControllerBase subclass

**The setup.php script**: This script is run whenever we want to update cached data. We will want to cache our navigation data. The class that builds that cache of navigation data should be called in setup.php.	
		
		
## Known Gaps we must address
**No comprehensive list of explicit endpoints**: The cache file cache/api_routes.php contains all registered routes. 
But because most model endpoints are handled by wildcards ('?' characters), explicit routes for models are rare in api_routes.php. 
There is no registered route for "Movies", for example. That route is handled with a wildcard. 
I.e. ModelBaseApiController registers this route, '/?/?'. The first wildcard character is expected to be the model name, the second is expected to be a record id for that model.
Mitigation: use the data in the `Permissions` model. The Permissions model has a field, 'component', which contains either the name of a model or the fully qualified class name of an ApiControllerBase subclass.
Every model and every api controller is represented in Permissions.  
We can use the data in the permissions table to get a list of all models. 


**Our ReactJS U.I. does not use the same api endpoints as the backend does.
I.e. http://localhost:3000/movies pulls data from http://localhost:8081/Movies
http://localhost:3000/quotes pulls data from http://localhost:8081/Movie_Quotes


**Models aren't the only thing user will navigate to**: 
Most links in the Frontend component will be just for models.
But some links, like http://localhost:3000/dashboard and http://localhost:3000/trivia, do not map neatly to any 1 model because their UI interacts with more than 1 model at a time.
Other links in the future may also not map neatly to any single model, so we need to think of ways to future-proof this product.

	
	