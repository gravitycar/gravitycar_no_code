<?php
/**
 * Convert JUnit XML files to JSON format for GitHub Actions
 * 
 * This script converts multiple JUnit XML files into a single JSON format
 * that can be consumed by GitHub Actions test reporters.
 */

function convertJunitXmlToJson(array $junitFiles, string $outputFile): bool {
    $testResults = [
        'version' => '1.0',
        'generator' => 'Gravitycar PHPUnit Converter',
        'timestamp' => date('c'),
        'summary' => [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'duration' => 0.0
        ],
        'testsuites' => []
    ];
    
    foreach ($junitFiles as $junitFile) {
        if (!file_exists($junitFile)) {
            fwrite(STDERR, "Warning: JUnit file not found: $junitFile\n");
            continue;
        }
        
        try {
            $xml = simplexml_load_file($junitFile);
            if ($xml === false) {
                fwrite(STDERR, "Error: Failed to parse XML file: $junitFile\n");
                continue;
            }
            
            // Process testsuites
            $suites = $xml->xpath('//testsuite');
            foreach ($suites as $suite) {
                $suiteName = (string)$suite['name'];
                $suiteData = [
                    'name' => $suiteName,
                    'tests' => (int)$suite['tests'],
                    'failures' => (int)$suite['failures'],
                    'errors' => (int)$suite['errors'],
                    'skipped' => (int)$suite['skipped'],
                    'time' => (float)$suite['time'],
                    'testcases' => []
                ];
                
                // Update summary
                $testResults['summary']['total'] += $suiteData['tests'];
                $testResults['summary']['failed'] += $suiteData['failures'];
                $testResults['summary']['errors'] += $suiteData['errors'];
                $testResults['summary']['skipped'] += $suiteData['skipped'];
                $testResults['summary']['duration'] += $suiteData['time'];
                
                // Process test cases
                foreach ($suite->testcase as $testcase) {
                    $testData = [
                        'name' => (string)$testcase['name'],
                        'classname' => (string)$testcase['classname'],
                        'time' => (float)$testcase['time'],
                        'status' => 'passed'
                    ];
                    
                    // Check for failures, errors, or skipped
                    if (isset($testcase->failure)) {
                        $testData['status'] = 'failed';
                        $testData['failure'] = [
                            'message' => (string)$testcase->failure['message'],
                            'text' => (string)$testcase->failure
                        ];
                    } elseif (isset($testcase->error)) {
                        $testData['status'] = 'error';
                        $testData['error'] = [
                            'message' => (string)$testcase->error['message'],
                            'text' => (string)$testcase->error
                        ];
                    } elseif (isset($testcase->skipped)) {
                        $testData['status'] = 'skipped';
                        $testData['skipped'] = [
                            'message' => (string)$testcase->skipped['message'],
                            'text' => (string)$testcase->skipped
                        ];
                    } else {
                        $testResults['summary']['passed']++;
                    }
                    
                    $suiteData['testcases'][] = $testData;
                }
                
                $testResults['testsuites'][] = $suiteData;
            }
            
        } catch (Exception $e) {
            fwrite(STDERR, "Error processing file $junitFile: " . $e->getMessage() . "\n");
            continue;
        }
    }
    
    // Write JSON output
    $jsonContent = json_encode($testResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonContent === false) {
        fwrite(STDERR, "Error: Failed to encode JSON\n");
        return false;
    }
    
    if (file_put_contents($outputFile, $jsonContent) === false) {
        fwrite(STDERR, "Error: Failed to write JSON file: $outputFile\n");
        return false;
    }
    
    echo "Successfully converted JUnit XML files to JSON: $outputFile\n";
    echo "Summary: {$testResults['summary']['total']} tests, ";
    echo "{$testResults['summary']['passed']} passed, ";
    echo "{$testResults['summary']['failed']} failed, ";
    echo "{$testResults['summary']['errors']} errors, ";
    echo "{$testResults['summary']['skipped']} skipped\n";
    
    return true;
}

// Main execution
if ($argc < 3) {
    fwrite(STDERR, "Usage: php convert-junit-to-json.php <output-file> <junit-file1> [junit-file2] [...]\n");
    exit(1);
}

$outputFile = $argv[1];
$junitFiles = array_slice($argv, 2);

if (empty($junitFiles)) {
    fwrite(STDERR, "Error: No JUnit XML files specified\n");
    exit(1);
}

$success = convertJunitXmlToJson($junitFiles, $outputFile);
exit($success ? 0 : 1);