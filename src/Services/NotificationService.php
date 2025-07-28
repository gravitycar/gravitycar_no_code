<?php
namespace Gravitycar\Services;

use Monolog\Logger;

class NotificationService {
    public function __construct(
        private Logger $logger,
        private EmailService $emailService  // This will be auto-wired too!
    ) {
        $this->logger->info('NotificationService created with auto-wired EmailService');
    }

    public function sendWelcomeNotification(string $email): void {
        $this->emailService->sendEmail($email, 'Welcome to the platform!');
        echo "🔔 Welcome notification sent\n";
    }
}
