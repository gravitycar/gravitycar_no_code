<?php

declare(strict_types=1);

/**
 * CLI entry point for the email reminder cron job.
 *
 * Bootstraps the framework, creates the EmailReminderService, and
 * runs both phases: process pending reminders, then send queued emails.
 *
 * Crontab example (runs every minute):
 *   * * * * * /usr/bin/php /path/to/gravitycar/bin/process_email_queue.php >> /path/to/gravitycar/logs/email_cron_stdout.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Services\IcsGeneratorService;
use Gravitycar\Services\EmailSenderService;
use Gravitycar\Services\EmailReminderService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logDirPath = __DIR__ . '/../logs';
if (!is_dir($logDirPath)) {
    mkdir($logDirPath, 0755, true);
}

$logger = new Logger('email-cron');
$logger->pushHandler(new StreamHandler(
    $logDirPath . '/email_cron.log',
    Logger::INFO
));

try {
    $config = new Config();
    $modelFactory = new ModelFactory();
    $icsService = new IcsGeneratorService($logger, $config);
    $emailSender = new EmailSenderService(
        $logger,
        $config,
        $modelFactory,
        $icsService
    );

    $service = new EmailReminderService(
        $logger,
        $config,
        $modelFactory,
        $emailSender
    );

    $results = $service->run();

    $logger->info('Cron job finished', $results);
} catch (\Throwable $e) {
    $logger->critical('Cron job failed with exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}
