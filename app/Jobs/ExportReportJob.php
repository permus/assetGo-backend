<?php

namespace App\Jobs;

use App\Models\ReportRun;
use App\Services\AssetReportService;
use App\Services\MaintenanceReportService;
use App\Services\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $runId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reportRun = ReportRun::findOrFail($this->runId);
        
        try {
            // Update status to running
            $reportRun->update([
                'status' => 'running',
                'started_at' => now()
            ]);

            // Generate report data
            $reportData = $this->generateReportData($reportRun);
            
            // Export to file
            $filePath = $this->exportToFile($reportRun, $reportData);
            
            // Update status to success
            $reportRun->update([
                'status' => 'success',
                'file_path' => $filePath,
                'row_count' => $this->getRowCount($reportData),
                'completed_at' => now(),
                'execution_time_ms' => $this->calculateExecutionTime($reportRun)
            ]);

            Log::info('Report export completed successfully', [
                'run_id' => $this->runId,
                'report_key' => $reportRun->report_key,
                'format' => $reportRun->format,
                'file_path' => $filePath
            ]);

        } catch (Exception $e) {
            // Update status to failed
            $reportRun->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'execution_time_ms' => $this->calculateExecutionTime($reportRun)
            ]);

            Log::error('Report export failed', [
                'run_id' => $this->runId,
                'report_key' => $reportRun->report_key,
                'format' => $reportRun->format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generate report data based on report key
     */
    private function generateReportData(ReportRun $reportRun): array
    {
        $reportKey = $reportRun->report_key;
        $params = $reportRun->params ?? [];

        // Determine which service to use based on report key
        if (str_starts_with($reportKey, 'assets.')) {
            $service = app(AssetReportService::class);
        } elseif (str_starts_with($reportKey, 'maintenance.')) {
            $service = app(MaintenanceReportService::class);
        } else {
            throw new Exception("Unknown report key: {$reportKey}");
        }

        // Generate the report
        $result = $service->generateReport($reportKey, $params);
        
        if (!$result['success']) {
            throw new Exception('Failed to generate report data');
        }

        return $result['data'];
    }

    /**
     * Export data to file based on format
     */
    private function exportToFile(ReportRun $reportRun, array $data): string
    {
        $format = $reportRun->format;
        $fileName = $this->generateFileName($reportRun);
        $filePath = "reports/{$fileName}";

        switch ($format) {
            case 'json':
                return $this->exportToJson($data, $filePath);
            case 'csv':
                return $this->exportToCsv($data, $filePath);
            case 'xlsx':
                return $this->exportToXlsx($data, $filePath);
            case 'pdf':
                return $this->exportToPdf($data, $filePath);
            default:
                throw new Exception("Unsupported export format: {$format}");
        }
    }

    /**
     * Export to JSON format
     */
    private function exportToJson(array $data, string $filePath): string
    {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Storage::disk('local')->put($filePath, $jsonData);
        return $filePath;
    }

    /**
     * Export to CSV format
     */
    private function exportToCsv(array $data, string $filePath): string
    {
        $csvData = $this->convertToCsv($data);
        Storage::disk('local')->put($filePath, $csvData);
        return $filePath;
    }

    /**
     * Export to XLSX format
     */
    private function exportToXlsx(array $data, string $filePath): string
    {
        // This would use PhpSpreadsheet library
        // For now, we'll create a simple CSV and rename it
        $csvData = $this->convertToCsv($data);
        $xlsxPath = str_replace('.csv', '.xlsx', $filePath);
        Storage::disk('local')->put($xlsxPath, $csvData);
        return $xlsxPath;
    }

    /**
     * Export to PDF format
     */
    private function exportToPdf(array $data, string $filePath): string
    {
        // This would use DomPDF or similar library
        // For now, we'll create a simple text file
        $pdfData = $this->convertToPdf($data);
        Storage::disk('local')->put($filePath, $pdfData);
        return $filePath;
    }

    /**
     * Convert data to CSV format
     */
    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        // Handle different data structures
        if (isset($data['assets']) && is_array($data['assets'])) {
            return $this->arrayToCsv($data['assets']);
        } elseif (isset($data['work_orders']) && is_array($data['work_orders'])) {
            return $this->arrayToCsv($data['work_orders']);
        } elseif (isset($data['technicians']) && is_array($data['technicians'])) {
            return $this->arrayToCsv($data['technicians']);
        }

        // Default: convert the entire data array
        return $this->arrayToCsv([$data]);
    }

    /**
     * Convert array to CSV string
     */
    private function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Write headers
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, array_values($row));
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Convert data to PDF format (simplified)
     */
    private function convertToPdf(array $data): string
    {
        $content = "Report Export\n";
        $content .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        if (isset($data['assets'])) {
            $content .= "Asset Report\n";
            $content .= "Total Assets: " . count($data['assets']) . "\n\n";
        } elseif (isset($data['work_orders'])) {
            $content .= "Maintenance Report\n";
            $content .= "Total Work Orders: " . count($data['work_orders']) . "\n\n";
        }
        
        return $content;
    }

    /**
     * Generate file name
     */
    private function generateFileName(ReportRun $reportRun): string
    {
        $companyId = $reportRun->company_id;
        $reportKey = str_replace('.', '-', $reportRun->report_key);
        $date = $reportRun->created_at->format('Y-m-d-H-i-s');
        $extension = $reportRun->format;
        
        return "company-{$companyId}-{$reportKey}-{$date}.{$extension}";
    }

    /**
     * Get row count from data
     */
    private function getRowCount(array $data): int
    {
        if (isset($data['assets'])) {
            return count($data['assets']);
        } elseif (isset($data['work_orders'])) {
            return count($data['work_orders']);
        } elseif (isset($data['technicians'])) {
            return count($data['technicians']);
        }
        
        return 1; // Default count
    }

    /**
     * Calculate execution time in milliseconds
     */
    private function calculateExecutionTime(ReportRun $reportRun): int
    {
        if (!$reportRun->started_at) {
            return 0;
        }
        
        return now()->diffInMilliseconds($reportRun->started_at);
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        $reportRun = ReportRun::find($this->runId);
        
        if ($reportRun) {
            $reportRun->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now()
            ]);
        }

        Log::error('ExportReportJob failed permanently', [
            'run_id' => $this->runId,
            'error' => $exception->getMessage()
        ]);
    }
}
