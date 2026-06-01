<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Services\Admin;

use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Core\Config;
use Gravitycar\Exceptions\AdminServiceException;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Services\Admin\CacheComponent;
use Gravitycar\Services\Admin\CacheRebuilder;
use Gravitycar\Services\Admin\CacheStepResult;
use Gravitycar\Services\NavigationBuilder;
use Gravitycar\Services\OpenAPIGenerator;
use Gravitycar\Services\PermissionsBuilder;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheRebuilder.
 */
class CacheRebuilderTest extends TestCase
{
    private MockObject $mockLogger;
    private MockObject $mockConfig;
    private MockObject $mockMetadataEngine;
    private MockObject $mockApiRouteRegistry;
    private MockObject $mockOpenAPIGenerator;
    private MockObject $mockNavigationBuilder;
    private MockObject $mockSchemaGenerator;
    private MockObject $mockPermissionsBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger             = $this->createMock(Logger::class);
        $this->mockConfig             = $this->createMock(Config::class);
        $this->mockMetadataEngine     = $this->createMock(MetadataEngine::class);
        $this->mockApiRouteRegistry   = $this->createMock(APIRouteRegistry::class);
        $this->mockOpenAPIGenerator   = $this->createMock(OpenAPIGenerator::class);
        $this->mockNavigationBuilder  = $this->createMock(NavigationBuilder::class);
        $this->mockSchemaGenerator    = $this->createMock(SchemaGenerator::class);
        $this->mockPermissionsBuilder = $this->createMock(PermissionsBuilder::class);
    }

    private function makeRebuilder(): CacheRebuilder
    {
        $mockMetadataEngine     = $this->mockMetadataEngine;
        $mockApiRouteRegistry   = $this->mockApiRouteRegistry;
        $mockOpenAPIGenerator   = $this->mockOpenAPIGenerator;
        $mockNavigationBuilder  = $this->mockNavigationBuilder;
        $mockSchemaGenerator    = $this->mockSchemaGenerator;
        $mockPermissionsBuilder = $this->mockPermissionsBuilder;

        // Anonymous subclass overrides the protected lazy-getters so tests can
        // inject mocks without touching ContainerConfig.
        return new class(
            $this->mockLogger,
            $this->mockConfig,
            $mockMetadataEngine,
            $mockApiRouteRegistry,
            $mockOpenAPIGenerator,
            $mockNavigationBuilder,
            $mockSchemaGenerator,
            $mockPermissionsBuilder
        ) extends CacheRebuilder {
            private $me; private $ar; private $og; private $nb; private $sg; private $pb;
            public function __construct($l, $c, $me, $ar, $og, $nb, $sg, $pb) {
                parent::__construct($l, $c);
                $this->me = $me; $this->ar = $ar; $this->og = $og;
                $this->nb = $nb; $this->sg = $sg; $this->pb = $pb;
            }
            protected function getMetadataEngine(): \Gravitycar\Metadata\MetadataEngine { return $this->me; }
            protected function getApiRouteRegistry(): \Gravitycar\Api\APIRouteRegistry { return $this->ar; }
            protected function getOpenAPIGenerator(): \Gravitycar\Services\OpenAPIGenerator { return $this->og; }
            protected function getNavigationBuilder(): \Gravitycar\Services\NavigationBuilder { return $this->nb; }
            protected function getSchemaGenerator(): \Gravitycar\Schema\SchemaGenerator { return $this->sg; }
            protected function getPermissionsBuilder(): \Gravitycar\Services\PermissionsBuilder { return $this->pb; }
        };
    }

    // -------------------------------------------------------------------------
    // clear() — non-existent paths (no actual deletion needed)
    // -------------------------------------------------------------------------

    public function testClearMetadataDoesNothingWhenFileDoesNotExist(): void
    {
        $rebuilder = $this->makeRebuilder();
        // Should not throw — file simply doesn't exist
        $rebuilder->clear([CacheComponent::METADATA]);
        $this->addToAssertionCount(1);
    }

    public function testClearRoutesDoesNothingWhenFileDoesNotExist(): void
    {
        $rebuilder = $this->makeRebuilder();
        $rebuilder->clear([CacheComponent::ROUTES]);
        $this->addToAssertionCount(1);
    }

    public function testClearDocsDoesNothingWhenDirDoesNotExist(): void
    {
        $rebuilder = $this->makeRebuilder();
        $rebuilder->clear([CacheComponent::DOCS]);
        $this->addToAssertionCount(1);
    }

    public function testClearNavigationDoesNothingWhenNoFilesMatch(): void
    {
        $rebuilder = $this->makeRebuilder();
        $rebuilder->clear([CacheComponent::NAVIGATION]);
        $this->addToAssertionCount(1);
    }

    public function testClearMetadataDeletesExistingFile(): void
    {
        // Create a real file at the expected path
        $cacheFile = 'cache/metadata_cache.php';
        $originalExists = file_exists($cacheFile);

        if (!$originalExists) {
            @mkdir('cache', 0755, true);
            file_put_contents($cacheFile, '<?php return [];');
        }

        $rebuilder = $this->makeRebuilder();
        $rebuilder->clear([CacheComponent::METADATA]);

        $this->assertFileDoesNotExist($cacheFile);

        // Cleanup: don't need to restore since it was created for the test
    }

    // -------------------------------------------------------------------------
    // rebuild() — verifies engine service calls
    // -------------------------------------------------------------------------

    public function testRebuildCallsLoadAllMetadataForMetadataComponent(): void
    {
        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('loadAllMetadata')
            ->willReturn(['models' => []]);

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::METADATA], false, false, function (CacheStepResult $step): void {
        });
    }

    public function testRebuildCallsRebuildCacheForRoutesComponent(): void
    {
        $this->mockApiRouteRegistry
            ->expects($this->once())
            ->method('rebuildCache');

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::ROUTES], false, false, function (CacheStepResult $step): void {
        });
    }

    public function testRebuildCallsGenerateSpecificationForDocsComponent(): void
    {
        $this->mockOpenAPIGenerator
            ->expects($this->once())
            ->method('generateSpecification');

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::DOCS], false, false, function (CacheStepResult $step): void {
        });
    }

    public function testRebuildCallsBuildAllRoleNavigationCachesForNavigationComponent(): void
    {
        $this->mockNavigationBuilder
            ->expects($this->once())
            ->method('buildAllRoleNavigationCaches');

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::NAVIGATION], false, false, function (CacheStepResult $step): void {
        });
    }

    public function testRebuildEmitsInProgressAndSuccessStepsPerComponent(): void
    {
        $this->mockMetadataEngine->method('loadAllMetadata')->willReturn([]);

        $emittedSteps = [];
        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild(
            [CacheComponent::METADATA],
            false,
            false,
            function (CacheStepResult $step) use (&$emittedSteps): void {
                $emittedSteps[] = $step;
            }
        );

        $this->assertCount(2, $emittedSteps);
        $this->assertSame('in_progress', $emittedSteps[0]->getStatus());
        $this->assertSame('success', $emittedSteps[1]->getStatus());
    }

    public function testRebuildCallsSchemaGeneratorWhenUpdateSchemaAndMetadataIncluded(): void
    {
        $this->mockMetadataEngine->method('loadAllMetadata')->willReturn(['models' => []]);

        $this->mockSchemaGenerator
            ->expects($this->once())
            ->method('generateSchema');

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::METADATA], true, false, function (CacheStepResult $step): void {
        });
    }

    public function testRebuildDoesNotCallSchemaGeneratorWhenUpdateSchemaFalse(): void
    {
        $this->mockMetadataEngine->method('loadAllMetadata')->willReturn(['models' => []]);

        $this->mockSchemaGenerator
            ->expects($this->never())
            ->method('generateSchema');

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::METADATA], false, false, function (CacheStepResult $step): void {
        });
    }

    public function testRebuildCallsPermissionsBuilderWhenUpdatePermissions(): void
    {
        $this->mockMetadataEngine->method('loadAllMetadata')->willReturn(['models' => []]);

        $this->mockPermissionsBuilder
            ->expects($this->once())
            ->method('buildAllPermissions');

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::METADATA], false, true, function (CacheStepResult $step): void {
        });
    }

    public function testRebuildDoesNotCallSchemaGeneratorWhenMetadataNotInComponents(): void
    {
        $this->mockSchemaGenerator
            ->expects($this->never())
            ->method('generateSchema');

        $this->mockApiRouteRegistry->method('rebuildCache');

        $rebuilder = $this->makeRebuilder();
        $rebuilder->rebuild([CacheComponent::ROUTES], true, false, function (CacheStepResult $step): void {
        });
    }

    // -------------------------------------------------------------------------
    // validate() — throws AdminServiceException when php -l fails
    // -------------------------------------------------------------------------

    public function testValidateThrowsAdminServiceExceptionForInvalidPhpFile(): void
    {
        // Use the ROUTES cache file (cache/api_routes.php) because ContainerConfig
        // does NOT load it at startup, so writing invalid PHP there won't interfere
        // with GCException's ServiceLocator call when AdminServiceException is constructed.
        $cacheFile       = 'cache/api_routes.php';
        $originalContent = null;

        if (file_exists($cacheFile)) {
            $originalContent = file_get_contents($cacheFile);
        }

        @mkdir('cache', 0755, true);
        file_put_contents($cacheFile, '<?php invalid syntax here !! ===');

        $rebuilder = $this->makeRebuilder();

        try {
            $this->expectException(AdminServiceException::class);
            $rebuilder->validate([CacheComponent::ROUTES]);
        } finally {
            // Restore original content or remove
            if ($originalContent !== null) {
                file_put_contents($cacheFile, $originalContent);
            } else {
                @unlink($cacheFile);
            }
        }
    }

    public function testValidateDoesNotThrowForValidPhpFile(): void
    {
        $cacheFile       = 'cache/api_routes.php';
        $originalContent = null;

        if (file_exists($cacheFile)) {
            $originalContent = file_get_contents($cacheFile);
        }

        @mkdir('cache', 0755, true);
        file_put_contents($cacheFile, '<?php return [];');

        $rebuilder = $this->makeRebuilder();

        try {
            $rebuilder->validate([CacheComponent::ROUTES]);
            $this->addToAssertionCount(1); // no exception = pass
        } finally {
            if ($originalContent !== null) {
                file_put_contents($cacheFile, $originalContent);
            } else {
                @unlink($cacheFile);
            }
        }
    }

    public function testValidateDoesNothingWhenFileDoesNotExist(): void
    {
        // If the file doesn't exist, validate skips it (filterExisting returns empty)
        // Use NAVIGATION component — uses glob pattern, will be empty in a clean test run
        $rebuilder = $this->makeRebuilder();
        $rebuilder->validate([CacheComponent::NAVIGATION]);
        $this->addToAssertionCount(1); // no exception = pass
    }
}
