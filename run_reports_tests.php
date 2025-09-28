<?php

/**
 * Reports Module Test Runner
 * 
 * This script runs all tests for the Reports Module with proper setup and reporting.
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\TestRunner;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

// Set up environment
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Reports Module Test Runner\n";
echo "============================\n\n";

// Create test suite
$suite = new TestSuite('Reports Module Test Suite');

// Add unit tests
$suite->addTestSuite(\Tests\Unit\Reports\AssetReportServiceTest::class);
$suite->addTestSuite(\Tests\Unit\Reports\MaintenanceReportServiceTest::class);
$suite->addTestSuite(\Tests\Unit\Reports\ReportExportServiceTest::class);
$suite->addTestSuite(\Tests\Unit\Reports\AssetReportControllerTest::class);

// Add feature tests
$suite->addTestSuite(\Tests\Feature\Reports\AssetReportsApiTest::class);
$suite->addTestSuite(\Tests\Feature\Reports\ExportApiTest::class);

// Add E2E tests
$suite->addTestSuite(\Tests\E2E\Reports\ReportsModuleE2ETest::class);

// Create test result
$result = new TestResult();

// Add custom listener
$listener = new class implements TestListener {
    use TestListenerDefaultImplementation;

    private $testCount = 0;
    private $passCount = 0;
    private $failCount = 0;
    private $errorCount = 0;
    private $skipCount = 0;

    public function startTestSuite(\PHPUnit\Framework\TestSuite $suite): void
    {
        if ($suite->getName() === 'Reports Module Test Suite') {
            echo "🚀 Starting Reports Module Test Suite\n";
            echo "=====================================\n\n";
        }
    }

    public function endTestSuite(\PHPUnit\Framework\TestSuite $suite): void
    {
        if ($suite->getName() === 'Reports Module Test Suite') {
            echo "\n📊 Test Results Summary\n";
            echo "=======================\n";
            echo "Total Tests: {$this->testCount}\n";
            echo "Passed: {$this->passCount}\n";
            echo "Failed: {$this->failCount}\n";
            echo "Errors: {$this->errorCount}\n";
            echo "Skipped: {$this->skipCount}\n";
            echo "Success Rate: " . round(($this->passCount / $this->testCount) * 100, 2) . "%\n\n";
            
            if ($this->failCount > 0 || $this->errorCount > 0) {
                echo "❌ Some tests failed. Please check the output above.\n";
            } else {
                echo "✅ All tests passed! Reports Module is working correctly.\n";
            }
        }
    }

    public function startTest(\PHPUnit\Framework\Test $test): void
    {
        $this->testCount++;
        $testName = $this->getTestName($test);
        echo "🧪 Running: {$testName}\n";
    }

    public function addSkipped(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $this->skipCount++;
        $testName = $this->getTestName($test);
        echo "⏭️  Skipped: {$testName}\n";
    }

    public function addError(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $this->errorCount++;
        $testName = $this->getTestName($test);
        echo "❌ Error: {$testName} - {$t->getMessage()}\n";
    }

    public function addFailure(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\AssertionFailedError $e, float $time): void
    {
        $this->failCount++;
        $testName = $this->getTestName($test);
        echo "❌ Failed: {$testName} - {$e->getMessage()}\n";
    }

    public function addIncompleteTest(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $testName = $this->getTestName($test);
        echo "⚠️  Incomplete: {$testName} - {$t->getMessage()}\n";
    }

    public function addRiskyTest(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $testName = $this->getTestName($test);
        echo "⚠️  Risky: {$testName} - {$t->getMessage()}\n";
    }

    public function addWarning(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\Warning $e, float $time): void
    {
        $testName = $this->getTestName($test);
        echo "⚠️  Warning: {$testName} - {$e->getMessage()}\n";
    }

    public function endTest(\PHPUnit\Framework\Test $test, float $time): void
    {
        $this->passCount++;
        $testName = $this->getTestName($test);
        echo "✅ Passed: {$testName} ({$time}s)\n";
    }

    private function getTestName(\PHPUnit\Framework\Test $test): string
    {
        if ($test instanceof \PHPUnit\Framework\TestCase) {
            return $test->getName();
        }
        
        return $test->toString();
    }
};

$result->addListener($listener);

// Run tests
$runner = new TestRunner();
$exitCode = $runner->run($suite, $result);

// Exit with appropriate code
exit($exitCode);
