<?php

namespace Gravitycar\Tests\Unit\Services;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Services\IcsGeneratorService;
use Gravitycar\Exceptions\BadRequestException;

/**
 * Unit tests for IcsGeneratorService.
 *
 * Covers ICS content generation with various event data inputs.
 */
class IcsGeneratorServiceTest extends UnitTestCase
{
    private IcsGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IcsGeneratorService($this->logger, $this->config);
    }

    private function validEventData(): array
    {
        return [
            'id' => 'test-uuid-123',
            'name' => 'Book Club Meeting',
            'description' => 'Discussing the latest book',
            'location' => '123 Main St',
            'accepted_date' => '2026-06-15 19:00:00',
            'duration_hours' => 3,
        ];
    }

    public function testGenerateIcsContentReturnsValidIcs(): void
    {
        $ics = $this->service->generateIcsContent($this->validEventData());

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function testGenerateIcsContentIncludesEventName(): void
    {
        $ics = $this->service->generateIcsContent($this->validEventData());
        $this->assertStringContainsString('Book Club Meeting', $ics);
    }

    public function testGenerateIcsContentIncludesUid(): void
    {
        $ics = $this->service->generateIcsContent($this->validEventData());
        $this->assertStringContainsString('test-uuid-123@gravitycar.com', $ics);
    }

    public function testGenerateIcsContentIncludesLocation(): void
    {
        $ics = $this->service->generateIcsContent($this->validEventData());
        $this->assertStringContainsString('123 Main St', $ics);
    }

    public function testGenerateIcsContentIncludesDescription(): void
    {
        $ics = $this->service->generateIcsContent($this->validEventData());
        $this->assertStringContainsString('Discussing the latest book', $ics);
    }

    public function testGenerateIcsContentUsesDefaultDurationWhenNotProvided(): void
    {
        $data = $this->validEventData();
        unset($data['duration_hours']);

        // Should not throw - uses default 3 hours
        $ics = $this->service->generateIcsContent($data);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
    }

    public function testGenerateIcsContentThrowsWhenMissingId(): void
    {
        $data = $this->validEventData();
        unset($data['id']);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Event id is required');
        $this->service->generateIcsContent($data);
    }

    public function testGenerateIcsContentThrowsWhenMissingName(): void
    {
        $data = $this->validEventData();
        unset($data['name']);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Event name is required');
        $this->service->generateIcsContent($data);
    }

    public function testGenerateIcsContentThrowsWhenMissingAcceptedDate(): void
    {
        $data = $this->validEventData();
        unset($data['accepted_date']);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Event accepted_date is required');
        $this->service->generateIcsContent($data);
    }

    public function testGenerateIcsContentWorksWithoutOptionalFields(): void
    {
        $data = [
            'id' => 'test-uuid-123',
            'name' => 'Minimal Event',
            'accepted_date' => '2026-06-15 19:00:00',
        ];

        $ics = $this->service->generateIcsContent($data);
        $this->assertStringContainsString('Minimal Event', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
    }

    public function testGenerateIcsContentIncludesProductIdentifier(): void
    {
        $ics = $this->service->generateIcsContent($this->validEventData());
        $this->assertStringContainsString('PRODID', $ics);
    }
}
