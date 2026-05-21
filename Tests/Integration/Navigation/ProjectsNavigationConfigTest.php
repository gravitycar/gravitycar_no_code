<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Integration\Navigation;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests verifying that the Projects entry exists in navigation_config.php
 * with the correct values required by the specification.
 *
 * Spec §8: Navigation Bar Integration
 *   - key   : 'projects'
 *   - title : 'Projects'
 *   - url   : '/projects_showcase'
 *   - roles : ['*']  (visible to all users including guests)
 *
 * Acceptance Criterion 16:
 *   A "Projects" navigation link pointing to /projects_showcase SHALL appear in the
 *   top "Navigation" section of the sidebar for all users including guests.
 */
class ProjectsNavigationConfigTest extends TestCase
{
    private const NAV_CONFIG_FILE_PATH = __DIR__ . '/../../../src/Navigation/navigation_config.php';

    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = require self::NAV_CONFIG_FILE_PATH;
    }

    // ---------------------------------------------------------------------------
    // AC 16: navigation_config.php contains a 'projects' entry in custom_pages
    // ---------------------------------------------------------------------------

    public function testNavigationConfigFileExists(): void
    {
        $this->assertFileExists(
            self::NAV_CONFIG_FILE_PATH,
            'navigation_config.php must exist at src/Navigation/navigation_config.php'
        );
    }

    public function testNavigationConfigHasCustomPagesKey(): void
    {
        $this->assertArrayHasKey(
            'custom_pages',
            $this->config,
            "navigation_config.php must contain a 'custom_pages' key"
        );
        $this->assertIsArray(
            $this->config['custom_pages'],
            "'custom_pages' must be an array"
        );
    }

    public function testCustomPagesContainsProjectsEntry(): void
    {
        $keys = array_column($this->config['custom_pages'], 'key');
        $this->assertContains(
            'projects',
            $keys,
            "custom_pages must contain an entry with key 'projects'"
        );
    }

    public function testProjectsEntryHasCorrectUrl(): void
    {
        $entry = $this->findProjectsEntry();
        $this->assertNotNull($entry, "Projects entry not found in custom_pages");

        $this->assertArrayHasKey('url', $entry, "Projects entry must have a 'url' key");
        $this->assertEquals(
            '/projects_showcase',
            $entry['url'],
            "Projects entry url must be '/projects_showcase'"
        );
    }

    public function testProjectsEntryHasCorrectTitle(): void
    {
        $entry = $this->findProjectsEntry();
        $this->assertNotNull($entry, "Projects entry not found in custom_pages");

        $this->assertArrayHasKey('title', $entry, "Projects entry must have a 'title' key");
        $this->assertEquals(
            'Projects',
            $entry['title'],
            "Projects entry title must be 'Projects'"
        );
    }

    /**
     * AC 16: The entry is visible to ALL roles including guests.
     * roles must be ['*'] (wildcard = all roles).
     */
    public function testProjectsEntryIsVisibleToAllRoles(): void
    {
        $entry = $this->findProjectsEntry();
        $this->assertNotNull($entry, "Projects entry not found in custom_pages");

        $this->assertArrayHasKey('roles', $entry, "Projects entry must have a 'roles' key");
        $this->assertIsArray($entry['roles'], "'roles' must be an array");
        $this->assertContains(
            '*',
            $entry['roles'],
            "Projects roles must include '*' (all roles, including guests)"
        );
    }

    public function testProjectsEntryHasIconKey(): void
    {
        $entry = $this->findProjectsEntry();
        $this->assertNotNull($entry, "Projects entry not found in custom_pages");

        $this->assertArrayHasKey('icon', $entry, "Projects entry must have an 'icon' key");
        $this->assertNotEmpty($entry['icon'], "Projects entry icon must not be empty");
    }

    // ---------------------------------------------------------------------------
    // Structural: navigation_sections must also be present
    // ---------------------------------------------------------------------------

    public function testNavigationConfigHasNavigationSectionsKey(): void
    {
        $this->assertArrayHasKey(
            'navigation_sections',
            $this->config,
            "navigation_config.php must contain a 'navigation_sections' key"
        );
    }

    // ---------------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------------

    private function findProjectsEntry(): ?array
    {
        foreach ($this->config['custom_pages'] as $page) {
            if (($page['key'] ?? '') === 'projects') {
                return $page;
            }
        }
        return null;
    }
}
