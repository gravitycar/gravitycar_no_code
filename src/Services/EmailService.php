<?php
namespace Gravitycar\Services;

use Monolog\Logger;
use Gravitycar\Core\Config;

class EmailService {
    public function __construct(
        private Logger $logger,
        private Config $config
    ) {
        $this->logger->info('EmailService created with auto-wired dependencies');
    }

    public function sendEmail(string $to, string $subject): void {
        $this->logger->info("Sending email to: $to, subject: $subject");
        echo "📧 Email sent to $to with subject: $subject\n";
    }
}
