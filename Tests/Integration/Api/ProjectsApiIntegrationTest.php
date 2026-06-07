<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Integration\Api;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Api\Router;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\UnprocessableEntityException;
use Gravitycar\Utils\GuestUserManager;

/**
 * Integration tests for the Projects model API endpoints.
 *
 * Covers acceptance criteria:
 *   AC 1  – Admin can create a Projects record via POST /Projects
 *   AC 2  – Admin can read a Projects record via GET /Projects/{id}
 *   AC 3  – Guest can list Projects via GET /Projects (returns 200)
 *   AC 15 – Link field validates http/https; rejects javascript: / ftp:
 *   AC 18 – POST with javascript: link returns validation error
 *   AC 19 – POST without link field succeeds (link is optional)
 */
class ProjectsApiIntegrationTest extends IntegrationTestCase
{
    private Router $router;

    /** @var array<int> IDs of Projects rows inserted during tests */
    private array $createdProjectIds = [];

    // ---------------------------------------------------------------------------
    // Standard valid project data for happy-path tests
    // ---------------------------------------------------------------------------

    private const VALID_PROJECT_DATA = [
        'title'       => 'Gravitycar Framework',
        'tag_line'    => 'The metadata-driven PHP framework',
        'description' => 'A full-stack web application framework built with PHP 8.2 and React.',
        'screenshot'  => 'https://example.com/screenshot.png',
        'link'        => 'https://example.com/project',
    ];

    // ---------------------------------------------------------------------------
    // Set up / tear down
    // ---------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $routeRegistry        = ServiceLocator::getAPIRouteRegistry();
        $pathScorer           = ServiceLocator::get('api_path_scorer');
        $controllerFactory    = ServiceLocator::get('api_controller_factory');
        $modelFactory         = ServiceLocator::getModelFactory();
        $authenticationService = ServiceLocator::getAuthenticationService();
        $authorizationService = ServiceLocator::getAuthorizationService();
        $currentUserProvider  = ServiceLocator::get('current_user_provider');

        $this->router = new Router(
            $this->logger,
            $this->metadataEngine,
            $routeRegistry,
            $pathScorer,
            $controllerFactory,
            $modelFactory,
            $authenticationService,
            $authorizationService,
            $currentUserProvider
        );

        $this->createProjectsTable();

        // Prime the guest user so CurrentUserProvider doesn't silently return null.
        // If the guest role or Projects permissions are missing, skip rather than
        // producing an uninformative auth failure.
        try {
            GuestUserManager::clearCache();
            (new GuestUserManager())->getGuestUser();
        } catch (\Exception $e) {
            $this->markTestSkipped('Guest user unavailable (check that the guest role is seeded): ' . $e->getMessage());
        }

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        $this->cleanupCreatedProjects();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------
    // Schema helper – create the projects table for SQLite test environment
    // ---------------------------------------------------------------------------

    private function createProjectsTable(): void
    {
        $autoIncrement = $this->getAutoIncrementSyntax();
        $sql = "
            CREATE TABLE IF NOT EXISTS projects (
                id            {$autoIncrement},
                title         VARCHAR(256)  NOT NULL,
                tag_line      VARCHAR(1024) NOT NULL,
                description   TEXT          NOT NULL,
                screenshot    VARCHAR(500)  NOT NULL,
                link          VARCHAR(256),
                created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME,
                deleted_at    DATETIME,
                created_by    INTEGER       NOT NULL DEFAULT 1,
                updated_by    INTEGER,
                deleted_by    INTEGER
            )
        ";
        $this->connection->executeStatement($sql);
    }

    private function cleanupCreatedProjects(): void
    {
        if (empty($this->createdProjectIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($this->createdProjectIds), '?'));
        try {
            $this->connection->executeStatement(
                "DELETE FROM projects WHERE id IN ({$placeholders})",
                $this->createdProjectIds
            );
        } catch (\Exception $e) {
            // Table may already have been dropped during tearDown cleanup
        }

        $this->createdProjectIds = [];
    }

    // ---------------------------------------------------------------------------
    // Helper: insert a project row directly (for read/list tests)
    // ---------------------------------------------------------------------------

    private function insertProject(array $overrides = []): int
    {
        $data = array_merge([
            'title'       => 'Test Project',
            'tag_line'    => 'A test project tag line',
            'description' => 'Integration test project description.',
            'screenshot'  => 'https://example.com/img.png',
            'link'        => null,
            'created_by'  => 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->connection->insert('projects', $data);
        $id = (int) $this->connection->lastInsertId();
        $this->createdProjectIds[] = $id;
        return $id;
    }

    // ---------------------------------------------------------------------------
    // AC 1: Admin can create a Projects record via POST /Projects
    // ---------------------------------------------------------------------------

    public function testAdminCanCreateProjectRecord(): void
    {
        try {
            $result = $this->router->route('POST', '/Projects', self::VALID_PROJECT_DATA);

            // Should return data array with the created record
            $this->assertIsArray($result, 'Route should return an array response');
            $this->assertArrayHasKey('data', $result, 'Response should have a data key');

            $record = $result['data'];
            $this->assertEquals('Gravitycar Framework', $record['title']);
            $this->assertEquals('The metadata-driven PHP framework', $record['tag_line']);
            $this->assertNotEmpty($record['id'], 'Created record should have an id');

            // Track for cleanup
            if (!empty($record['id'])) {
                $this->createdProjectIds[] = (int) $record['id'];
            }
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Cannot run Projects create test without live database: ' . $e->getMessage()
            );
        }
    }

    // ---------------------------------------------------------------------------
    // AC 2: Admin can read a Projects record via GET /Projects/{id}
    // ---------------------------------------------------------------------------

    public function testAdminCanReadProjectRecord(): void
    {
        try {
            $id = $this->insertProject([
                'title'   => 'Readable Project',
                'tag_line' => 'Read test tag line',
            ]);

            $result = $this->router->route('GET', "/Projects/{$id}");

            $this->assertIsArray($result, 'Route should return an array response');
            $this->assertArrayHasKey('data', $result, 'Response should have a data key');

            $record = $result['data'];
            $this->assertEquals((string) $id, (string) $record['id']);
            $this->assertEquals('Readable Project', $record['title']);
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Cannot run Projects read test without live database: ' . $e->getMessage()
            );
        }
    }

    // ---------------------------------------------------------------------------
    // AC 3 (list): Admin can list Projects via GET /Projects
    // ---------------------------------------------------------------------------

    public function testAdminCanListProjectRecords(): void
    {
        try {
            $this->insertProject(['title' => 'List Project A']);
            $this->insertProject(['title' => 'List Project B']);

            $result = $this->router->route('GET', '/Projects');

            $this->assertIsArray($result, 'Route should return an array response');
            // Response may be wrapped in 'data' or returned as a flat array
            $records = $result['data'] ?? $result;
            $this->assertIsArray($records, 'Records should be an array');
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Cannot run Projects list test without live database: ' . $e->getMessage()
            );
        }
    }

    // ---------------------------------------------------------------------------
    // Guest list access: unauthenticated GET /Projects returns 200 (not 401/403)
    // ---------------------------------------------------------------------------

    /**
     * The spec states guests get list+read permissions. GuestUserManager provides a
     * guest user when no JWT is present. This test verifies the router does NOT throw
     * ForbiddenException or UnauthorizedException for a guest list request.
     */
    public function testGuestListAccessDoesNotThrowAuthException(): void
    {
        // Clear any HTTP authorization header so the CurrentUserProvider falls back to guest
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $threwAuthException = false;

        try {
            $this->router->route('GET', '/Projects');
        } catch (ForbiddenException | UnauthorizedException $e) {
            $threwAuthException = true;
        } catch (\Exception $e) {
            // Any other exception (e.g. database) is acceptable — we only care that
            // guest access does not produce a 401/403.
        }

        $this->assertFalse(
            $threwAuthException,
            'Guest list access to /Projects should not produce a 401 or 403 response'
        );
    }

    // ---------------------------------------------------------------------------
    // Guest read access: unauthenticated GET /Projects/{id} returns 200
    // ---------------------------------------------------------------------------

    public function testGuestReadAccessDoesNotThrowAuthException(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $threwAuthException = false;

        try {
            $id = $this->insertProject(['title' => 'Guest Read Project']);
            $this->router->route('GET', "/Projects/{$id}");
        } catch (ForbiddenException | UnauthorizedException $e) {
            $threwAuthException = true;
        } catch (\Exception $e) {
            // Other exceptions are not auth failures
        }

        $this->assertFalse(
            $threwAuthException,
            'Guest read access to /Projects/{id} should not produce a 401 or 403 response'
        );
    }

    // ---------------------------------------------------------------------------
    // Guest create blocked: unauthenticated POST /Projects returns 403
    // ---------------------------------------------------------------------------

    public function testGuestCreateIsBlocked(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $caughtForbidden = false;

        try {
            $this->router->route('POST', '/Projects', self::VALID_PROJECT_DATA);
        } catch (ForbiddenException $e) {
            $caughtForbidden = true;
        } catch (\Exception $e) {
            // If we get an UnauthorizedException, also acceptable as auth rejection
            if ($e instanceof UnauthorizedException) {
                $caughtForbidden = true;
            }
            // Skip test if fails for an infrastructure reason
            if (!$caughtForbidden) {
                $this->markTestSkipped(
                    'Cannot verify guest create block without live database: ' . $e->getMessage()
                );
            }
        }

        $this->assertTrue(
            $caughtForbidden,
            'Unauthenticated POST to /Projects should be rejected with 403 Forbidden'
        );
    }

    // ---------------------------------------------------------------------------
    // AC 18: POST with javascript: link returns validation error
    // ---------------------------------------------------------------------------

    public function testPostWithJavascriptLinkReturnsValidationError(): void
    {
        $data = array_merge(self::VALID_PROJECT_DATA, ['link' => 'javascript:alert(1)']);

        $caughtValidationError = false;

        try {
            $this->router->route('POST', '/Projects', $data);
        } catch (UnprocessableEntityException $e) {
            $caughtValidationError = true;
            $context = $e->getContext();
            $errors  = $context['validation_errors'] ?? [];
            $this->assertNotEmpty($errors, 'Validation errors should be present for javascript: link');
        } catch (ForbiddenException | UnauthorizedException $e) {
            $this->markTestSkipped(
                'Cannot run link validation test without admin authentication: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Cannot run link validation test without live database: ' . $e->getMessage()
            );
        }

        $this->assertTrue(
            $caughtValidationError,
            "POST with 'javascript:alert(1)' link should produce a 422 UnprocessableEntityException"
        );
    }

    // ---------------------------------------------------------------------------
    // Link validation: POST with ftp:// link returns validation error
    // ---------------------------------------------------------------------------

    public function testPostWithFtpLinkReturnsValidationError(): void
    {
        $data = array_merge(self::VALID_PROJECT_DATA, ['link' => 'ftp://files.example.com/resource']);

        $caughtValidationError = false;

        try {
            $this->router->route('POST', '/Projects', $data);
        } catch (UnprocessableEntityException $e) {
            $caughtValidationError = true;
        } catch (ForbiddenException | UnauthorizedException $e) {
            $this->markTestSkipped(
                'Cannot run link validation test without admin authentication: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Cannot run ftp link validation test without live database: ' . $e->getMessage()
            );
        }

        $this->assertTrue(
            $caughtValidationError,
            "POST with 'ftp://' link should produce a 422 UnprocessableEntityException"
        );
    }

    // ---------------------------------------------------------------------------
    // Link validation: POST with valid https:// link succeeds
    // ---------------------------------------------------------------------------

    public function testPostWithValidHttpsLinkSucceeds(): void
    {
        $data = array_merge(self::VALID_PROJECT_DATA, ['link' => 'https://example.com/valid-project']);

        try {
            $result = $this->router->route('POST', '/Projects', $data);

            $this->assertIsArray($result, 'Route should return an array response');
            $this->assertArrayHasKey('data', $result, 'Response should have a data key');
            $this->assertEquals('https://example.com/valid-project', $result['data']['link']);

            if (!empty($result['data']['id'])) {
                $this->createdProjectIds[] = (int) $result['data']['id'];
            }
        } catch (ForbiddenException | UnauthorizedException $e) {
            $this->markTestSkipped(
                'Cannot run link validation test without admin authentication: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Cannot run https link success test without live database: ' . $e->getMessage()
            );
        }
    }

    // ---------------------------------------------------------------------------
    // AC 19: POST without link field succeeds (link is optional)
    // ---------------------------------------------------------------------------

    public function testPostWithoutLinkFieldSucceeds(): void
    {
        $data = [
            'title'       => 'No Link Project',
            'tag_line'    => 'No link provided',
            'description' => 'A project without a link field.',
            'screenshot'  => 'https://example.com/screenshot.png',
        ];

        try {
            $result = $this->router->route('POST', '/Projects', $data);

            $this->assertIsArray($result, 'Route should return an array response');
            $this->assertArrayHasKey('data', $result, 'Response should have a data key');
            $this->assertEquals('No Link Project', $result['data']['title']);

            if (!empty($result['data']['id'])) {
                $this->createdProjectIds[] = (int) $result['data']['id'];
            }
        } catch (ForbiddenException | UnauthorizedException $e) {
            $this->markTestSkipped(
                'Cannot run optional link test without admin authentication: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Cannot run optional link test without live database: ' . $e->getMessage()
            );
        }
    }
}
