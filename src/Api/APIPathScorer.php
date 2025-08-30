<?php

namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * APIPathScorer
 * 
 * Implements the scoring algorithm to find the best matching route
 * for incoming API requests using wildcard path matching.
 */
class APIPathScorer
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
    }

    /**
     * Calculate the score for a route against client path components
     * 
     * @param array $clientPathComponents The components from the incoming request path
     * @param array $registeredPathComponents The components from the registered route path
     * @return int The calculated score (higher is better)
     */
    public function scoreRoute(array $clientPathComponents, array $registeredPathComponents): int
    {
        $this->logger->debug("Starting route scoring calculation", [
            'clientComponents' => $clientPathComponents,
            'registeredComponents' => $registeredPathComponents
        ]);

        if (count($clientPathComponents) !== count($registeredPathComponents)) {
            $this->logger->debug("Component count mismatch, score = 0");
            return 0;
        }

        $totalScore = 0;
        $pathLength = count($clientPathComponents);

        for ($i = 0; $i < $pathLength; $i++) {
            $componentScore = $this->calculateComponentScore(
                $clientPathComponents[$i],
                $registeredPathComponents[$i],
                $i,
                $pathLength
            );
            
            // If any component doesn't match, disqualify the entire route
            if ($componentScore === 0) {
                $this->logger->debug("Route disqualified due to component mismatch", [
                    'position' => $i,
                    'clientComponent' => $clientPathComponents[$i],
                    'registeredComponent' => $registeredPathComponents[$i]
                ]);
                return 0;
            }
            
            $totalScore += $componentScore;
        }

        $this->logger->debug("Route scoring calculation completed", [
            'totalScore' => $totalScore
        ]);

        return $totalScore;
    }

    /**
     * Find the best matching route from a collection of routes
     * 
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array $routes Array of route definitions
     * @return array|null The best matching route or null if none found
     */
    public function findBestMatch(string $method, string $path, array $routes): ?array
    {
        $this->logger->debug("Finding best route match", [
            'method' => $method,
            'path' => $path,
            'routeCount' => count($routes)
        ]);

        $clientComponents = $this->parsePathComponents($path);
        $bestRoute = null;
        $bestScore = 0; // Changed from -1 to 0, only positive scores indicate matches

        foreach ($routes as $route) {
            $score = $this->scoreRoute($clientComponents, $route['pathComponents']);
            
            $this->logger->debug("Route scored", [
                'route' => $route['path'],
                'score' => $score
            ]);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRoute = $route;
            }
        }

        if ($bestRoute) {
            $this->logger->debug("Best match found", [
                'route' => $bestRoute['path'],
                'score' => $bestScore
            ]);
        } else {
            $this->logger->debug("No matching route found");
        }

        return $bestRoute;
    }

    /**
     * Calculate the score for a single path component
     * 
     * Formula: ((pathLength - component_index) * (2 for exact match, 1 for wildcard, 0 for no match))
     * 
     * @param string $clientComponent Component from client request
     * @param string $registeredComponent Component from registered route
     * @param int $position Position index in the path
     * @param int $pathLength Total path length
     * @return int The calculated component score
     */
    public function calculateComponentScore(string $clientComponent, string $registeredComponent, int $position, int $pathLength): int
    {
        $positionWeight = $pathLength - $position;
        
        if ($clientComponent === $registeredComponent) {
            // Exact match
            return $positionWeight * 2;
        } elseif ($registeredComponent === '?') {
            // Wildcard match
            return $positionWeight * 1;
        } else {
            // No match
            return 0;
        }
    }

    /**
     * Parse a path string into components
     * 
     * @param string $path The path to parse (e.g., "/Users/123")
     * @return array Array of path components (e.g., ["Users", "123"])
     */
    public function parsePathComponents(string $path): array
    {
        if (empty($path) || $path === '/') {
            return [];
        }

        // Remove leading and trailing slashes, then split
        $path = trim($path, '/');
        return explode('/', $path);
    }
}
