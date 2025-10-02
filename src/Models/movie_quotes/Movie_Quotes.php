<?php
namespace Gravitycar\Models\movie_quotes;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * MovieQuotes model class for Gravitycar framework.
 */
class Movie_Quotes extends ModelBase {
    
    protected array $rolesAndActions = [
        'admin' => ['*'], // Admin can perform all actions
        'manager' => ['list', 'read', 'create', 'update', 'delete'],
        'user' => ['list', 'read', 'create', 'update', 'delete'],
        'guest' => ['list', 'read', 'create', 'update', 'delete'] 
    ];

    /**
     * Pure dependency injection constructor
     */
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }
}
