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
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;

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

        } catch (Throwable $e) {
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
        } elseif (str_starts_with($reportKey, 'inventory.')) {
            $service = app(\App\Services\InventoryReportService::class);
        } elseif (str_starts_with($reportKey, 'financial.')) {
            $service = app(\App\Services\FinancialReportService::class);
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
        // Use Laravel Excel (Maatwebsite\Excel) for proper XLSX generation
        $excelData = $this->prepareExcelData($data);
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setTitle('Report Data');
        
        // Add headers and data
        if (!empty($excelData)) {
            // Ensure all rows have the same keys
            $allKeys = [];
            foreach ($excelData as $row) {
                if (is_array($row)) {
                    $allKeys = array_merge($allKeys, array_keys($row));
                }
            }
            $allKeys = array_unique($allKeys);
            
            // Normalize all rows to have the same keys
            $normalizedData = array_map(function($row) use ($allKeys) {
                $normalized = [];
                foreach ($allKeys as $key) {
                    $normalized[$key] = $this->convertToScalar($row[$key] ?? null);
                }
                return $normalized;
            }, $excelData);
            
            $headers = array_map(function($key) {
                return ucwords(str_replace('_', ' ', $key));
            }, $allKeys);
            
            $sheet->fromArray([$headers], null, 'A1');
            
            // Style headers
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ];
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
            
            // Add data rows - convert to indexed arrays for fromArray
            $dataRows = array_map(function($row) use ($allKeys) {
                return array_map(function($key) use ($row) {
                    return $this->convertToScalar($row[$key] ?? null);
                }, $allKeys);
            }, $normalizedData);
            
            $sheet->fromArray($dataRows, null, 'A2');
            
            // Auto-size columns
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Add borders
            $styleArray = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ];
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow())->applyFromArray($styleArray);
        }
        
        // Save to file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);
        
        $xlsxPath = str_replace('.csv', '.xlsx', $filePath);
        Storage::disk('local')->put($xlsxPath, file_get_contents($tempFile));
        unlink($tempFile);
        
        return $xlsxPath;
    }
    
    /**
     * Prepare data for Excel export
     */
    private function prepareExcelData(array $data): array
    {
        // Handle different data structures
        if (isset($data['assets']) && is_array($data['assets'])) {
            return $this->flattenArray($data['assets']);
        } elseif (isset($data['work_orders']) && is_array($data['work_orders'])) {
            return $this->flattenArray($data['work_orders']);
        } elseif (isset($data['technicians']) && is_array($data['technicians'])) {
            return $this->flattenArray($data['technicians']);
        } elseif (isset($data['stocks']) && is_array($data['stocks'])) {
            // Inventory Current Stock report
            return $this->flattenArray($data['stocks']);
        } elseif (isset($data['items']) && is_array($data['items'])) {
            // Inventory ABC Analysis, Slow Moving, Reorder Analysis reports
            return $this->flattenArray($data['items']);
        } elseif (isset($data['cost_by_category'])) {
            // Financial Maintenance Cost Breakdown report
            $categories = is_array($data['cost_by_category']) 
                ? $data['cost_by_category'] 
                : (is_object($data['cost_by_category']) && method_exists($data['cost_by_category'], 'toArray')
                    ? $data['cost_by_category']->toArray()
                    : []);
            return $this->flattenArray($categories);
        } elseif (isset($data['summary'])) {
            // Financial TCO and Budget vs Actual reports - convert summary to rows
            $summary = $data['summary'];
            $rows = [];
            foreach ($summary as $key => $value) {
                $rows[] = [
                    'field' => ucwords(str_replace('_', ' ', $key)),
                    'value' => $this->convertToScalar($value)
                ];
            }
            return $rows;
        }
        
        // Default: try to flatten the entire data array
        return $this->flattenArray([$data]);
    }
    
    /**
     * Flatten nested arrays for Excel
     */
    private function flattenArray(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return array_map(function ($item) {
            // Convert objects to arrays
            if (is_object($item)) {
                if (method_exists($item, 'toArray')) {
                    $item = $item->toArray();
                } else {
                    $item = (array) $item;
                }
            }

            // Ensure item is an array
            if (!is_array($item)) {
                $item = ['value' => $item];
            }

            $flattened = [];
            foreach ($item as $key => $value) {
                $flattened[$key] = $this->convertToScalar($value);
            }
            return $flattened;
        }, $data);
    }

    /**
     * Convert any value to a scalar (string, number, or null) for Excel
     */
    private function convertToScalar($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            if (method_exists($value, 'toArray')) {
                return json_encode($value->toArray(), JSON_UNESCAPED_UNICODE);
            }
            return json_encode((array) $value, JSON_UNESCAPED_UNICODE);
        }

        // Return scalar values as-is (string, int, float)
        return $value;
    }

    /**
     * Export to PDF format
     */
    private function exportToPdf(array $data, string $filePath): string
    {
        $html = $this->buildPdfHtml($data);
        $pdfBinary = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->output();

        Storage::disk('local')->put($filePath, $pdfBinary);
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
            return $this->arrayToCsv($this->flattenArray($data['assets']));
        } elseif (isset($data['work_orders']) && is_array($data['work_orders'])) {
            return $this->arrayToCsv($this->flattenArray($data['work_orders']));
        } elseif (isset($data['technicians']) && is_array($data['technicians'])) {
            return $this->arrayToCsv($this->flattenArray($data['technicians']));
        } elseif (isset($data['stocks']) && is_array($data['stocks'])) {
            return $this->arrayToCsv($this->flattenArray($data['stocks']));
        } elseif (isset($data['items']) && is_array($data['items'])) {
            return $this->arrayToCsv($this->flattenArray($data['items']));
        } elseif (isset($data['cost_by_category'])) {
            // Financial Maintenance Cost Breakdown report
            $categories = is_array($data['cost_by_category']) 
                ? $data['cost_by_category'] 
                : (is_object($data['cost_by_category']) && method_exists($data['cost_by_category'], 'toArray')
                    ? $data['cost_by_category']->toArray()
                    : []);
            return $this->arrayToCsv($this->flattenArray($categories));
        } elseif (isset($data['summary'])) {
            // Financial TCO and Budget vs Actual reports - convert summary to rows
            $summary = $data['summary'];
            $rows = [];
            foreach ($summary as $key => $value) {
                $rows[] = [
                    'field' => ucwords(str_replace('_', ' ', $key)),
                    'value' => $this->convertToScalar($value)
                ];
            }
            return $this->arrayToCsv($rows);
        }

        // Default: try to flatten the entire data array
        return $this->arrayToCsv($this->flattenArray([$data]));
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
        
        // Ensure all rows are arrays and have consistent structure
        $normalizedData = [];
        $allKeys = [];
        
        // Collect all possible keys from all rows
        foreach ($data as $row) {
            if (is_object($row)) {
                if (method_exists($row, 'toArray')) {
                    $row = $row->toArray();
                } else {
                    $row = (array) $row;
                }
            }
            
            if (is_array($row)) {
                $allKeys = array_merge($allKeys, array_keys($row));
                $normalizedData[] = $row;
            }
        }
        
        $allKeys = array_unique($allKeys);
        
        if (empty($allKeys)) {
            fclose($output);
            return '';
        }
        
        // Write headers
        $headers = array_map(function($key) {
            return ucwords(str_replace('_', ' ', $key));
        }, $allKeys);
        fputcsv($output, $headers);
        
        // Write data rows - ensure all values are scalars
        foreach ($normalizedData as $row) {
            $values = [];
            foreach ($allKeys as $key) {
                $value = $row[$key] ?? null;
                $values[] = $this->convertToScalar($value);
            }
            fputcsv($output, $values);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Build comprehensive HTML for PDF rendering
     */
    private function buildPdfHtml(array $data): string
    {
        $title = 'Report Export';
        $subtitle = 'Generated: ' . now()->format('Y-m-d H:i:s');
        $contentHtml = '';

        // Asset Reports
        if (isset($data['assets']) && is_array($data['assets']) && !empty($data['assets'])) {
            $title = 'Asset Report';
            
            // Summary section
            if (isset($data['totals'])) {
                $totals = $data['totals'];
                $contentHtml .= '<h2>Summary</h2>';
                $contentHtml .= '<table class="summary-table">';
                $contentHtml .= '<tr><td>Total Assets</td><td>' . ($totals['total_count'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Total Value</td><td>$' . number_format($totals['total_value'] ?? 0, 2) . '</td></tr>';
                $contentHtml .= '<tr><td>Active Assets</td><td>' . ($totals['active_count'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>In Maintenance</td><td>' . ($totals['maintenance_count'] ?? 0) . '</td></tr>';
                $contentHtml .= '</table>';
            }
            
            // Asset details table
            $contentHtml .= '<h2>Asset Details</h2>';
            $contentHtml .= $this->buildAssetTable($data['assets']);
        }
        
        // Maintenance Reports
        elseif (isset($data['work_orders']) && is_array($data['work_orders']) && !empty($data['work_orders'])) {
            $title = 'Maintenance Report';
            
            // KPIs section
            if (isset($data['kpis'])) {
                $kpis = $data['kpis'];
                $contentHtml .= '<h2>Key Performance Indicators</h2>';
                $contentHtml .= '<table class="summary-table">';
                $contentHtml .= '<tr><td>Total Work Orders</td><td>' . ($kpis['total_work_orders'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Completed</td><td>' . ($kpis['completed_work_orders'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Completion Rate</td><td>' . ($kpis['completion_rate'] ?? 0) . '%</td></tr>';
                $contentHtml .= '<tr><td>Overdue</td><td>' . ($kpis['overdue_work_orders'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Avg Resolution Time</td><td>' . number_format($kpis['avg_resolution_time_hours'] ?? 0, 1) . ' hours</td></tr>';
                $contentHtml .= '</table>';
            }
            
            // Work orders table
            $contentHtml .= '<h2>Work Order Details</h2>';
            $contentHtml .= $this->buildWorkOrderTable($data['work_orders']);
        }
        
        // Technician Performance
        elseif (isset($data['technicians']) && is_array($data['technicians']) && !empty($data['technicians'])) {
            $title = 'Technician Performance Report';
            $contentHtml .= '<h2>Performance Metrics</h2>';
            $contentHtml .= $this->buildTechnicianTable($data['technicians']);
        }
        
        // Inventory Reports - Current Stock
        elseif (isset($data['stocks']) && is_array($data['stocks']) && !empty($data['stocks'])) {
            $title = 'Current Stock Levels Report';
            
            // Summary section
            if (isset($data['summary'])) {
                $summary = $data['summary'];
                $contentHtml .= '<h2>Summary</h2>';
                $contentHtml .= '<table class="summary-table">';
                $contentHtml .= '<tr><td>Total Items</td><td>' . ($summary['total_items'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Total On Hand</td><td>' . number_format($summary['total_on_hand'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Total Reserved</td><td>' . number_format($summary['total_reserved'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Total Available</td><td>' . number_format($summary['total_available'] ?? 0) . '</td></tr>';
                $contentHtml .= '<tr><td>Total Value</td><td>$' . number_format($summary['total_value'] ?? 0, 2) . '</td></tr>';
                $contentHtml .= '</table>';
            }
            
            // Stock details table
            $contentHtml .= '<h2>Stock Details</h2>';
            $contentHtml .= $this->buildInventoryStockTable($data['stocks']);
        }
        
        // Inventory Reports - ABC Analysis, Slow Moving, Reorder
        elseif (isset($data['items']) && is_array($data['items']) && !empty($data['items'])) {
            $title = 'Inventory Analysis Report';
            
            // Summary section
            if (isset($data['summary'])) {
                $summary = $data['summary'];
                $contentHtml .= '<h2>Summary</h2>';
                $contentHtml .= '<table class="summary-table">';
                
                // ABC Analysis specific fields
                if (isset($summary['count_a'])) {
                    $contentHtml .= '<tr><td>Total Value</td><td>$' . number_format($summary['total_value'] ?? 0, 2) . '</td></tr>';
                    $contentHtml .= '<tr><td>Class A Items</td><td>' . ($summary['count_a'] ?? 0) . '</td></tr>';
                    $contentHtml .= '<tr><td>Class B Items</td><td>' . ($summary['count_b'] ?? 0) . '</td></tr>';
                    $contentHtml .= '<tr><td>Class C Items</td><td>' . ($summary['count_c'] ?? 0) . '</td></tr>';
                }
                // Slow Moving specific fields
                elseif (isset($summary['total_slow_moving_items'])) {
                    $contentHtml .= '<tr><td>Slow Moving Items</td><td>' . ($summary['total_slow_moving_items'] ?? 0) . '</td></tr>';
                    $contentHtml .= '<tr><td>Value Tied Up</td><td>$' . number_format($summary['total_value_tied_up'] ?? 0, 2) . '</td></tr>';
                    $contentHtml .= '<tr><td>Days Threshold</td><td>' . ($summary['days_threshold'] ?? 0) . ' days</td></tr>';
                }
                // Reorder Analysis specific fields
                elseif (isset($summary['total_items_to_reorder'])) {
                    $contentHtml .= '<tr><td>Items to Reorder</td><td>' . ($summary['total_items_to_reorder'] ?? 0) . '</td></tr>';
                    $contentHtml .= '<tr><td>Total Estimated Cost</td><td>$' . number_format($summary['total_estimated_cost'] ?? 0, 2) . '</td></tr>';
                    $contentHtml .= '<tr><td>Total Recommended Quantity</td><td>' . number_format($summary['total_recommended_quantity'] ?? 0) . '</td></tr>';
                }
                
                $contentHtml .= '</table>';
            }
            
            // Items details table
            $contentHtml .= '<h2>Item Details</h2>';
            $contentHtml .= $this->buildInventoryItemsTable($data['items']);
        }
        
        // Financial Reports - TCO
        elseif (isset($data['summary']['tco'])) {
            $title = 'Total Cost of Ownership Report';
            $summary = $data['summary'];
            
            $contentHtml .= '<h2>Cost Breakdown</h2>';
            $contentHtml .= '<table class="summary-table">';
            $contentHtml .= '<tr><td>Acquisition Cost</td><td>$' . number_format($summary['acquisition'] ?? 0, 2) . '</td></tr>';
            $contentHtml .= '<tr><td>Maintenance Cost</td><td>$' . number_format($summary['maintenance'] ?? 0, 2) . '</td></tr>';
            $contentHtml .= '<tr><td>Disposal Cost</td><td>$' . number_format($summary['disposal'] ?? 0, 2) . '</td></tr>';
            $contentHtml .= '<tr class="total-row"><td><strong>Total Cost of Ownership</strong></td><td><strong>$' . number_format($summary['tco'] ?? 0, 2) . '</strong></td></tr>';
            $contentHtml .= '</table>';
        }
        
        // Financial Reports - Budget vs Actual
        elseif (isset($data['summary']['budget']) && isset($data['summary']['actual'])) {
            $title = 'Budget vs Actual Report';
            $summary = $data['summary'];
            
            $contentHtml .= '<h2>Period Summary</h2>';
            $contentHtml .= '<table class="summary-table">';
            $contentHtml .= '<tr><td>Period</td><td>' . ($summary['period_start'] ?? '') . ' to ' . ($summary['period_end'] ?? '') . '</td></tr>';
            $contentHtml .= '<tr><td>Months</td><td>' . ($summary['months'] ?? 0) . '</td></tr>';
            $contentHtml .= '</table>';
            
            $contentHtml .= '<h2>Budget Analysis</h2>';
            $contentHtml .= '<table class="summary-table">';
            $contentHtml .= '<tr><td>Budget</td><td>$' . number_format($summary['budget'] ?? 0, 2) . '</td></tr>';
            $contentHtml .= '<tr><td>Actual</td><td>$' . number_format($summary['actual'] ?? 0, 2) . '</td></tr>';
            $variance = $summary['variance'] ?? 0;
            $varianceClass = $variance > 0 ? 'over-budget' : 'under-budget';
            $contentHtml .= '<tr class="' . $varianceClass . '"><td>Variance</td><td>$' . number_format($variance, 2) . ' (' . number_format($summary['variance_pct'] ?? 0, 2) . '%)</td></tr>';
            $contentHtml .= '</table>';
        }
        
        // Financial Reports - Maintenance Cost Breakdown
        elseif (isset($data['cost_by_category'])) {
            $title = 'Maintenance Cost Breakdown Report';
            
            // Convert to array if it's a collection
            $categories = is_array($data['cost_by_category']) ? $data['cost_by_category'] : $data['cost_by_category']->toArray();
            $total = array_sum(array_column($categories, 'total'));
            
            $contentHtml .= '<h2>Summary</h2>';
            $contentHtml .= '<table class="summary-table">';
            $contentHtml .= '<tr><td>Total Maintenance Cost</td><td>$' . number_format($total, 2) . '</td></tr>';
            $contentHtml .= '<tr><td>Categories</td><td>' . count($categories) . '</td></tr>';
            $contentHtml .= '</table>';
            
            $contentHtml .= '<h2>Cost by Category</h2>';
            $contentHtml .= $this->buildFinancialCategoryTable($data['cost_by_category']);
        }

        // Build full HTML
        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { 
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif; 
            color: #111; 
            padding: 20px;
            font-size: 10px;
        }
        h1 { 
            font-size: 20px; 
            margin: 0 0 5px 0; 
            color: #4F46E5;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 8px;
        }
        h2 { 
            font-size: 14px; 
            margin: 16px 0 8px 0; 
            color: #4F46E5;
        }
        .subtitle { 
            color: #666; 
            font-size: 10px; 
            margin-bottom: 20px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 8px;
            margin-bottom: 16px;
            font-size: 9px;
        }
        th { 
            background: #4F46E5; 
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
        }
        td { 
            border: 1px solid #E5E7EB; 
            padding: 6px; 
        }
        tr:nth-child(even) { 
            background: #F9FAFB; 
        }
        .summary-table {
            width: 50%;
        }
        .summary-table td {
            padding: 6px 10px;
        }
        .summary-table td:first-child {
            font-weight: bold;
            width: 60%;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }
        .badge-info { background: #DBEAFE; color: #1E40AF; }
        .total-row td { 
            background: #f3f4f6; 
            font-size: 11px; 
            padding: 10px 8px;
        }
        .over-budget { 
            background: #fee2e2; 
        }
        .under-budget { 
            background: #dcfce7; 
        }
    </style>
    <title>{$title}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>
<body>
    <h1>{$title}</h1>
    <div class="subtitle">{$subtitle}</div>
    {$contentHtml}
</body>
</html>
HTML;
    }
    
    /**
     * Build asset data table
     */
    private function buildAssetTable(array $assets): string
    {
        if (empty($assets)) {
            return '<p>No assets found.</p>';
        }
        
        // Convert Eloquent models to arrays if needed
        if (is_object($assets[0])) {
            $assets = array_map(function($item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    return $item->toArray();
                }
                return is_object($item) ? (array)$item : $item;
            }, $assets);
        }
        
        $headers = array_keys($assets[0]);
        $html = '<table><thead><tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($assets as $asset) {
            $html .= '<tr>';
            foreach ($asset as $value) {
                if (is_array($value) || is_object($value)) {
                    $html .= '<td>' . htmlspecialchars(json_encode($value)) . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                }
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Build work order data table
     */
    private function buildWorkOrderTable(array $workOrders): string
    {
        if (empty($workOrders)) {
            return '<p>No work orders found.</p>';
        }
        
        // Convert Eloquent models to arrays if needed
        if (is_object($workOrders[0])) {
            $workOrders = array_map(function($item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    return $item->toArray();
                }
                return is_object($item) ? (array)$item : $item;
            }, $workOrders);
        }
        
        $headers = array_keys($workOrders[0]);
        $html = '<table><thead><tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($workOrders as $wo) {
            $html .= '<tr>';
            foreach ($wo as $value) {
                if (is_array($value) || is_object($value)) {
                    $html .= '<td>' . htmlspecialchars(json_encode($value)) . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                }
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Build technician performance table
     */
    private function buildTechnicianTable(array $technicians): string
    {
        if (empty($technicians)) {
            return '<p>No technician data found.</p>';
        }
        
        $html = '<table><thead><tr>';
        $html .= '<th>Name</th>';
        $html .= '<th>Total Work Orders</th>';
        $html .= '<th>Completed</th>';
        $html .= '<th>Completion Rate</th>';
        $html .= '<th>Avg Resolution Time</th>';
        $html .= '<th>Total Hours</th>';
        $html .= '<th>Efficiency Score</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($technicians as $tech) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($tech['name'] ?? '') . '</td>';
            $html .= '<td>' . ($tech['total_work_orders'] ?? 0) . '</td>';
            $html .= '<td>' . ($tech['completed_work_orders'] ?? 0) . '</td>';
            $html .= '<td>' . number_format($tech['completion_rate'] ?? 0, 1) . '%</td>';
            $html .= '<td>' . number_format($tech['avg_resolution_time_days'] ?? 0, 1) . ' days</td>';
            $html .= '<td>' . number_format($tech['total_hours_worked'] ?? 0, 1) . ' hrs</td>';
            $html .= '<td>' . number_format($tech['efficiency_score'] ?? 0, 2) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Build inventory stock table for Current Stock report
     */
    private function buildInventoryStockTable(array $stocks): string
    {
        if (empty($stocks)) {
            return '<p>No stock data found.</p>';
        }
        
        $html = '<table><thead><tr>';
        $html .= '<th>Part Number</th>';
        $html .= '<th>Part Name</th>';
        $html .= '<th>Location</th>';
        $html .= '<th>On Hand</th>';
        $html .= '<th>Reserved</th>';
        $html .= '<th>Available</th>';
        $html .= '<th>Unit Cost</th>';
        $html .= '<th>Avg Cost</th>';
        $html .= '<th>Total Value</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($stocks as $stock) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($stock['part_number'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($stock['part_name'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($stock['location'] ?? '') . '</td>';
            $html .= '<td>' . number_format($stock['on_hand'] ?? 0) . '</td>';
            $html .= '<td>' . number_format($stock['reserved'] ?? 0) . '</td>';
            $html .= '<td>' . number_format($stock['available'] ?? 0) . '</td>';
            $html .= '<td>$' . number_format($stock['unit_cost'] ?? 0, 2) . '</td>';
            $html .= '<td>$' . number_format($stock['average_cost'] ?? 0, 2) . '</td>';
            $html .= '<td>$' . number_format($stock['total_value'] ?? 0, 2) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Build inventory items table for ABC Analysis, Slow Moving, and Reorder reports
     */
    private function buildInventoryItemsTable(array $items): string
    {
        if (empty($items)) {
            return '<p>No items found.</p>';
        }
        
        $firstItem = $items[0];
        
        $html = '<table><thead><tr>';
        
        // Detect report type and build appropriate headers
        if (isset($firstItem['class'])) {
            // ABC Analysis
            $html .= '<th>Part Number</th>';
            $html .= '<th>Name</th>';
            $html .= '<th>Value</th>';
            $html .= '<th>Cumulative %</th>';
            $html .= '<th>Class</th>';
        } elseif (isset($firstItem['transaction_count'])) {
            // Slow Moving
            $html .= '<th>Part Number</th>';
            $html .= '<th>Name</th>';
            $html .= '<th>On Hand</th>';
            $html .= '<th>Total Value</th>';
            $html .= '<th>Last Transaction</th>';
            $html .= '<th>Transactions</th>';
            $html .= '<th>Days Since Last</th>';
        } elseif (isset($firstItem['reorder_point'])) {
            // Reorder Analysis
            $html .= '<th>Part Number</th>';
            $html .= '<th>Name</th>';
            $html .= '<th>Location</th>';
            $html .= '<th>Available</th>';
            $html .= '<th>Reorder Point</th>';
            $html .= '<th>Reorder Qty</th>';
            $html .= '<th>Recommended</th>';
            $html .= '<th>Est. Cost</th>';
        }
        
        $html .= '</tr></thead><tbody>';
        
        foreach ($items as $item) {
            $html .= '<tr>';
            
            if (isset($item['class'])) {
                // ABC Analysis rows
                $html .= '<td>' . ($item['part_id'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($item['name'] ?? '') . '</td>';
                $html .= '<td>$' . number_format($item['value'] ?? 0, 2) . '</td>';
                $html .= '<td>' . number_format(($item['cumulative'] ?? 0) * 100, 2) . '%</td>';
                $html .= '<td><strong>' . ($item['class'] ?? '') . '</strong></td>';
            } elseif (isset($item['transaction_count'])) {
                // Slow Moving rows
                $html .= '<td>' . htmlspecialchars($item['part_number'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($item['name'] ?? '') . '</td>';
                $html .= '<td>' . number_format($item['total_on_hand'] ?? 0) . '</td>';
                $html .= '<td>$' . number_format($item['total_value'] ?? 0, 2) . '</td>';
                $html .= '<td>' . ($item['last_transaction_date'] ?? 'Never') . '</td>';
                $html .= '<td>' . ($item['transaction_count'] ?? 0) . '</td>';
                $html .= '<td>' . ($item['days_since_last_transaction'] ?? 'N/A') . '</td>';
            } elseif (isset($item['reorder_point'])) {
                // Reorder Analysis rows
                $html .= '<td>' . htmlspecialchars($item['part_number'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($item['name'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($item['location'] ?? '') . '</td>';
                $html .= '<td>' . number_format($item['available'] ?? 0) . '</td>';
                $html .= '<td>' . number_format($item['reorder_point'] ?? 0) . '</td>';
                $html .= '<td>' . number_format($item['reorder_qty'] ?? 0) . '</td>';
                $html .= '<td><strong>' . number_format($item['recommended_order_qty'] ?? 0) . '</strong></td>';
                $html .= '<td>$' . number_format($item['estimated_cost'] ?? 0, 2) . '</td>';
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Build financial cost by category table
     */
    private function buildFinancialCategoryTable($categories): string
    {
        if (empty($categories)) {
            return '<p>No cost data found.</p>';
        }
        
        // Convert to array if it's a collection
        $categories = is_array($categories) ? $categories : $categories->toArray();
        
        $html = '<table><thead><tr>';
        $html .= '<th>Category</th>';
        $html .= '<th>Total Cost</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($categories as $cat) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($cat['category'] ?? 'Unknown') . '</td>';
            $html .= '<td>$' . number_format($cat['total'] ?? 0, 2) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
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
        } elseif (isset($data['stocks'])) {
            return count($data['stocks']);
        } elseif (isset($data['items'])) {
            return count($data['items']);
        } elseif (isset($data['cost_by_category'])) {
            return count($data['cost_by_category']);
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
    public function failed(Throwable $exception): void
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
