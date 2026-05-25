<?php

declare(strict_types=1);

namespace Gravitycar\Models\projects;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * Projects Model
 *
 * Represents a portfolio project record. Provides title, tag line,
 * description, screenshot URL, and optional project link. Supports
 * admin CRUD and public (guest) read/list access.
 *
 * All CRUD operations are handled by ModelBase; no custom domain
 * logic is required for this model.
 */
class Projects extends ModelBase
{
    /**
     * Constructs a Projects model instance with full dependency injection.
     *
     * @param Logger                       $logger              Monolog logger instance
     * @param MetadataEngineInterface      $metadataEngine      Metadata resolver
     * @param FieldFactory                 $fieldFactory        Field instance factory
     * @param DatabaseConnectorInterface   $databaseConnector   Doctrine DBAL connector
     * @param RelationshipFactory          $relationshipFactory Relationship instance factory
     * @param ModelFactory                 $modelFactory        Model instance factory
     * @param CurrentUserProviderInterface $currentUserProvider Current authenticated user
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
