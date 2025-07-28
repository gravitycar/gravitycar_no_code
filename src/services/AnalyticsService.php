<?php
namespace Gravitycar\Services;

use Monolog\Logger;
use Gravitycar\Core\Config;
use Gravitycar\Core\Service;
use Gravitycar\Core\Inject;

#[Service(name: 'analytics_service', singleton: true)]
class AnalyticsService {
    public function __construct(
        #[Inject('logger')] private Logger $logger,
        #[Inject('config')] private Config $config
    ) {
        $this->logger->info('AnalyticsService initialized as singleton');
    }

    public function trackEvent(string $event): void {
        $this->logger->info("Tracking event: $event");
        echo "📈 Event tracked: $event\n";
    }
}
