# ModelBaseAPIController

## Overview
This is a base class that will extend ApiControllerBase and will register default routes for all standard API operations that pertain to ModelBase classes. 

## Location: `src/Models/api/Api/ModelBaseAPIController.php`

## The routes
The routes will mostly contain wildcard characters. This will designate the ModelBaseAPIController to be the default API Controller for all REST API requests that would interact with a model. Specific model classes can implement their own API Controller classes (which must extend the ModelBaseAPI). Specific model classes that implement their own API Controller classes will register routes with more specificity (i.e. the path "/?/?" is less specific that "/Users/?"). The additional specificity in the model-specific API Controllers will cause them to score higher in the APIPathScorer->scoreRoute() method. With this mechanism, we don't have to register every possible route for every model. We only need to register routes that need special handling this ModelBaseAPIController class doesn't provide.

### GET routes - for retrieving records
- /?
    - get a list of records for a given model
    - parameters: 'modelName'
    - APIMethod: 'list'

- /?/?
    - get a single record by its ID
    - parameters: 'modelName', 'id'
    - APIMethod: 'retrieve'

- /?/deleted
    - list deleted records
    - parameters: 'modelName'
    - APIMethod: 'listDeleted'

- /?/?/link/?
    - get a list of records related to one record
    - parameters: 'modelName', 'id', '', 'relationshipName
    - APIMethod: 'listRelated'

### POST routes - for creating new records
- /?
    - create a new records for a given model
    - parameters: 'modelName'
    - APIMethod: 'create'

- /?/?/link/?
    - create a new record and link it to another record using the named relationship
    - parameters: 'modelName', 'id', '', 'relationshipName'
    - APIMethod: 'createAndLink'

### PUT routes - for updating existing records

- /?/?
    - update a record by its ID
    - parameters: 'modelName', 'id'
    - APIMethod: 'update'

- /?/?/restore
    - restore a soft-deleted record
    - parameters: 'modelName', 'id', ''
    - APIMethod: 'restore'

- /?/?/link/?/?
    - link an existing record to another record using the named relationship
    - parameters: 'modelName', 'id', '', 'relationshipName', 'idToLink'
    - APIMethod: 'link'


### DELETE routes - soft-deleting records only. DO NOT hard-delete records from the API.
- /?/?
    - soft-delete a record by its ID
    - parameters: 'modelName', 'id'
    - APIMethod: 'delete'
    

- /?/?/link/?/?
    - soft-delete the link between two records
    - parameters: 'modelName', 'id', '', 'relationshipName', 'idToLink'
    - APIMethod: 'unlink'


## The methods
- Every APIMethod from the above list needs to be implemented in this class. 
- Many of these methods can leverage existing methods in ModelBase, RelationshipBase or DatabaseConnector.
- Prefer ModelBase and RelationshipBase methods. 
- DO NOT write SQL in this class. If you can't find a method in ModelBase, RelationshipBase or DatabaseConnector to leverage for database operations, stop and ask for guidance. 
- Make use of the DI system and the Factory classes for instanting other classes. Get Model instances from the ModelFactory. ModelBase->getRelationship() should allow you to work with relationships.