<?php

declare(strict_types=1);

namespace Gravitycar\Services;

use Gravitycar\Core\Config;
use Gravitycar\Exceptions\BadRequestException;
use Monolog\Logger;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

/**
 * Generates RFC 5545-compliant ICS calendar content for events.
 *
 * Encapsulates ICS generation logic so it can be reused by the
 * ICS export endpoint and email reminder attachments.
 */
class IcsGeneratorService
{
    /** @var string[] Fields required in the event data array. */
    private const REQUIRED_FIELDS = ['id', 'name', 'accepted_date'];

    /** @var int Default event duration in hours when none is specified. */
    private const DEFAULT_DURATION_HOURS = 3;

    /** @var string Product identifier for the PRODID property. */
    private const PRODUCT_IDENTIFIER = '-//Gravitycar//Event Organizer//EN';

    /** @var string Domain suffix for the UID property. */
    private const UID_DOMAIN = '@gravitycar.com';

    private ?Logger $logger;
    private ?Config $config;

    /**
     * @param Logger|null $logger Monolog logger instance.
     * @param Config|null $config Application configuration.
     */
    public function __construct(?Logger $logger = null, ?Config $config = null)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Generate ICS calendar content for an event.
     *
     * @param array $eventData Event data with keys: id, name, description,
     *                         location, accepted_date, duration_hours.
     * @return string Raw ICS string (text/calendar content).
     * @throws BadRequestException If required fields are missing.
     */
    public function generateIcsContent(array $eventData): string
    {
        $this->validateEventData($eventData);

        $dtStart = new \DateTimeImmutable(
            $eventData['accepted_date'],
            new \DateTimeZone('UTC')
        );
        $durationHours = $eventData['duration_hours'] ?? self::DEFAULT_DURATION_HOURS;
        $dtEnd = $dtStart->modify("+{$durationHours} hours");

        $uid = $eventData['id'] . self::UID_DOMAIN;

        $event = $this->buildEvent($eventData, $uid, $dtStart, $dtEnd);

        $calendar = Calendar::create()
            ->productIdentifier(self::PRODUCT_IDENTIFIER)
            ->event($event);

        $this->logger?->info('ICS content generated', [
            'event_id' => $eventData['id'],
            'dtstart' => $dtStart->format('c'),
            'dtend' => $dtEnd->format('c'),
        ]);

        return $calendar->get();
    }

    /**
     * Build the VEVENT component from event data.
     *
     * @param array $eventData The event data array.
     * @param string $uid The unique identifier for the event.
     * @param \DateTimeImmutable $dtStart The event start time.
     * @param \DateTimeImmutable $dtEnd The event end time.
     * @return Event The configured VEVENT component.
     */
    protected function buildEvent(
        array $eventData,
        string $uid,
        \DateTimeImmutable $dtStart,
        \DateTimeImmutable $dtEnd
    ): Event {
        $event = Event::create()
            ->name($eventData['name'])
            ->uniqueIdentifier($uid)
            ->startsAt($dtStart)
            ->endsAt($dtEnd);

        if (!empty($eventData['description'])) {
            $event->description($eventData['description']);
        }

        if (!empty($eventData['location'])) {
            $event->address($eventData['location']);
        }

        return $event;
    }

    /**
     * Validate that all required fields are present in event data.
     *
     * @param array $eventData The event data to validate.
     * @throws BadRequestException If a required field is missing or empty.
     */
    protected function validateEventData(array $eventData): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($eventData[$field])) {
                throw new BadRequestException(
                    "Event {$field} is required for ICS generation",
                    ['missing_field' => $field]
                );
            }
        }
    }
}
