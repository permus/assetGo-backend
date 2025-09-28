<?php

namespace Tests;

use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

/**
 * Reports Module Test Suite
 * 
 * This test suite runs all tests for the Reports Module including:
 * - Unit tests for services and controllers
 * - Feature tests for API endpoints
 * - E2E tests for complete workflows
 */
class ReportsTestSuite extends TestSuite
{
    public static function suite()
    {
        $suite = new self('Reports Module Test Suite');

        // Unit Tests
        $suite->addTestSuite(\Tests\Unit\Reports\AssetReportServiceTest::class);
        $suite->addTestSuite(\Tests\Unit\Reports\MaintenanceReportServiceTest::class);
        $suite->addTestSuite(\Tests\Unit\Reports\ReportExportServiceTest::class);
        $suite->addTestSuite(\Tests\Unit\Reports\AssetReportControllerTest::class);

        // Feature Tests
        $suite->addTestSuite(\Tests\Feature\Reports\AssetReportsApiTest::class);
        $suite->addTestSuite(\Tests\Feature\Reports\ExportApiTest::class);

        // E2E Tests
        $suite->addTestSuite(\Tests\E2E\Reports\ReportsModuleE2ETest::class);

        return $suite;
    }
}

/**
 * Reports Test Listener
 * 
 * Custom test listener for Reports Module tests
 */
class ReportsTestListener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function startTestSuite(\PHPUnit\Framework\TestSuite $suite): void
    {
        if ($suite->getName() === 'Reports Module Test Suite') {
            echo "\n🚀 Starting Reports Module Test Suite\n";
            echo "=====================================\n\n";
        }
    }

    public function endTestSuite(\PHPUnit\Framework\TestSuite $suite): void
    {
        if ($suite->getName() === 'Reports Module Test Suite') {
            echo "\n✅ Reports Module Test Suite Completed\n";
            echo "=====================================\n\n";
        }
    }

    public function startTest(\PHPUnit\Framework\Test $test): void
    {
        $testName = $this->getTestName($test);
        echo "🧪 Running: {$testName}\n";
    }

    public function addSkipped(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $testName = $this->getTestName($test);
        echo "⏭️  Skipped: {$testName}\n";
    }

    public function addError(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $testName = $this->getTestName($test);
        echo "❌ Error: {$testName} - {$t->getMessage()}\n";
    }

    public function addFailure(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\AssertionFailedError $e, float $time): void
    {
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

    private function getTestName(\PHPUnit\Framework\Test $test): string
    {
        if ($test instanceof \PHPUnit\Framework\TestCase) {
            return $test->getName();
        }
        
        return $test->toString();
    }
}
