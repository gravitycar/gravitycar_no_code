#To-Do's:

[X] Figure out if the RelationshipBase class can just use the same metadata property that ModelBase uses, and not relationshipMetadata.
[X] Change the relationship classes to use the DatabaseConnector to generate their queries.
    - [X] RelationshipBase->add() refactored to use DatabaseConnector->create()
    - [X] RelationshipBase->hasActiveRelation() refactored to use DatabaseConnector->recordExists()
    - [X] RelationshipBase->getActiveRelatedCount() refactored to use DatabaseConnector->getCount()
    - [X] RelationshipBase->getActiveRelationshipRecords() refactored to use DatabaseConnector->find()
    - [X] RelationshipBase->getDeletedRelationshipRecords() refactored to use DatabaseConnector->find()
    - [X] getRelatedRecords() methods consolidated into RelationshipBase using DatabaseConnector->find()
    - [X] Added DatabaseConnector->bulkSoftDeleteByFieldValue() and bulkSoftDeleteByCriteria() methods
    - [X] Removed all hardDeleteAllRelationships() and removeAllRelations() methods (replaced with soft delete)
    - [X] Updated CASCADE_CASCADE operations to use soft delete instead of hard delete
    - [X] Refactored RelationshipBase->bulkSoftDeleteRelationships() to use DatabaseConnector
    - [X] Refactored RelationshipBase->softDeleteRelationship() to use DatabaseConnector
    - [X] Refactored RelationshipBase->softDeleteExistingRelationships() to use DatabaseConnector->bulkSoftDeleteByFieldValue()
    - [X] Refactored OneToOne/OneToMany/ManyToMany->updateRelation() methods to use DatabaseConnector->update()
    - [X] Refactored ManyToManyRelationship->getRelatedWithData() to use DatabaseConnector->find()
    - [X] Moved ManyToManyRelationship->getRelatedPaginated() to RelationshipBase (available to all relationship types)
    - [X] Renamed ModelBase->getRelatedPaginated() to getRelatedWithPagination() to resolve method signature conflict
    - [X] Added DatabaseConnector->bulkUpdateByCriteriaWithFieldValues() as generic bulk update method
    - [X] Refactored bulkSoftDeleteByCriteria() and bulkSoftDeleteByFieldValue() to use generic method
    - [X] Added DatabaseConnector->bulkRestoreByCriteria() for restoring soft-deleted records
    - [X] Refactored RelationshipBase->restoreRelationship() to use DatabaseConnector->bulkRestoreByCriteria()
    - [X] Eliminated createTempModelForTable() method by refactoring DatabaseConnector methods to accept ModelBase objects directly
    - [X] Removed ManyToManyRelationship->batchAdd() methods (feature not supported)
    - [X] ALL raw SQL instances eliminated from relationship classes - 100% DatabaseConnector usage achieved
[X] Specify the ModelFactory.
[X] Build ModelFactory.
[X] Replace every instance in the code where we try to derive a fully qualified class name with a call to the ModelFactory.
[] Fix ModelBase::getRelationship() to throw an exception if it can't find the named relationship, so it never returns null.
[] Centralize retrieving the currently logged in user with the Gravitycar class.
[X] Centralize where we create GUID's. I thought this was in ModelBase but I don't see it.
[] Implement metadata loading logic in the MetadataEngine to allow Models and Relationships to retrieve their metadata from the MetaData engine instead of loading their own files.
[] Switch Models and Relationships over to using the MetaDataEngine to retrieve their metadata from the MetaDataEngine's cache.
[] Specify the APIBase class, a class parent class for all API controller classes to come. 
[] Build the MovieQuotesTrivaGame and MovieQuotesTriviaGameQuestions models.