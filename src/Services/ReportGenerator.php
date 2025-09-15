<?php
namespace Gravitycar\Services;

use Psr\Log\LoggerInterface;
use Gravitycar\Core\Config;

class ReportGenerator {
    public function __construct(
        private LoggerInterface $logger,
        private Config $config,
        private string $reportType,
        private array $options = []
    ) {
        $this->logger->info("ReportGenerator created for type: $reportType");
    }

    public function generateReport(): void {
        $this->logger->info("Generating {$this->reportType} report");
        echo "📊 Generated {$this->reportType} report\n";
    }
}
