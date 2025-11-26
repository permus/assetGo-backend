<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use App\Services\NotificationService;
use App\Jobs\ExportReportJob;
use App\Models\ReportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ReportExportController extends Controller
{
    protected $notificationService;

    public function __construct(private ReportExportService $exportService, NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Export report in specified format
     */
    public function export(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $request->validate([
                'report_key' => 'required|string',
                'format' => 'required|in:pdf,xlsx,csv,json',
                'params' => 'array'
            ]);

            $reportKey = $request->input('report_key');
            $format = $request->input('format');
            $params = $request->input('params', []);

            // Create report run record
            $reportRun = ReportRun::create([
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'report_key' => $reportKey,
                'params' => $params,
                'format' => $format,
                'status' => 'queued'
            ]);

            // Dispatch export job
            Log::info('Dispatching export job', [
                'run_id' => $reportRun->id,
                'report_key' => $reportKey,
                'format' => $format,
                'queue_connection' => config('queue.default'),
                'company_id' => $companyId,
                'user_id' => Auth::id()
            ]);

            // If queue connection is 'sync', process immediately; otherwise use queue
            if (config('queue.default') === 'sync') {
                // Process synchronously for development/testing
                Log::info('Processing export job synchronously (sync driver)', [
                    'run_id' => $reportRun->id
                ]);
                try {
                    $job = new ExportReportJob($reportRun->id);
                    $job->handle();
                    Log::info('Export job completed synchronously', [
                        'run_id' => $reportRun->id,
                        'status' => $reportRun->fresh()->status
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to process export job synchronously', [
                        'run_id' => $reportRun->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Status will be updated by the job handler on error
                    throw $e;
                }
            } else {
                // Use queue for production
                Log::info('Dispatching export job to queue', [
                    'run_id' => $reportRun->id,
                    'queue' => 'reports'
                ]);
                ExportReportJob::dispatch($reportRun->id)->onQueue('reports');
            }

            // Send notifications to admins and company owners
            $creator = Auth::user();
            try {
                $this->notificationService->createForAdminsAndOwners(
                    $companyId,
                    [
                        'type' => 'report',
                        'action' => 'generate_report',
                        'title' => 'Report Generated',
                        'message' => $this->notificationService->formatReportMessage('generate_report', $reportKey),
                        'data' => [
                            'runId' => $reportRun->id,
                            'reportKey' => $reportKey,
                            'format' => $format,
                            'createdBy' => [
                                'id' => $creator->id,
                                'name' => $creator->first_name . ' ' . $creator->last_name,
                                'userType' => $creator->user_type,
                            ],
                        ],
                        'created_by' => $creator->id,
                    ],
                    $creator->id
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to send report generation notifications', [
                    'run_id' => $reportRun->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Refresh the model to get updated status (in case sync processing completed)
            $reportRun->refresh();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'run_id' => $reportRun->id,
                    'status' => $reportRun->status, // Return actual status (may be 'success' if sync)
                    'message' => $reportRun->status === 'success' 
                        ? 'Export completed successfully' 
                        : 'Export job queued successfully'
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to queue export job', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to queue export job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get export status and download URL
     */
    public function show($id)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $reportRun = ReportRun::forCompany($companyId)->findOrFail($id);
            
            $data = [
                'id' => $reportRun->id,
                'report_key' => $reportRun->report_key,
                'format' => $reportRun->format,
                'status' => $reportRun->status,
                'status_label' => $reportRun->status_label,
                'progress' => $reportRun->progress ?? 0,
                'row_count' => $reportRun->row_count,
                'execution_time_ms' => $reportRun->execution_time_ms,
                'execution_time_formatted' => $reportRun->execution_time_formatted,
                'created_at' => $reportRun->created_at,
                'started_at' => $reportRun->started_at,
                'completed_at' => $reportRun->completed_at,
                'error_message' => $reportRun->error_message
            ];

            // Add download URL if successful
            if ($reportRun->status === 'success' && $reportRun->file_path) {
                $data['download_url'] = $reportRun->download_url;
                $data['file_size'] = $this->getFileSize($reportRun->file_path);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to get export status', [
                'company_id' => $companyId,
                'run_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get export status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download exported file
     */
    public function download($id)
    {
        try {
            $reportRun = ReportRun::where('status', 'success')
                ->findOrFail($id);

            if (!$reportRun->file_path || !\Storage::disk('local')->exists($reportRun->file_path)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Export file not found'
                ], 404);
            }

            $filePath = \Storage::disk('local')->path($reportRun->file_path);
            $fileName = $this->generateFileName($reportRun);

            return response()->download($filePath, $fileName);
            
        } catch (Exception $e) {
            Log::error('Failed to download export file', [
                'run_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to download export file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's export history
     */
    public function history(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $page = $request->get('page', 1);
            $pageSize = min($request->get('page_size', 20), 100);
            
            $query = ReportRun::forCompany($companyId)
                ->forUser(Auth::id())
                ->with(['template'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $query->byStatus($request->input('status'));
            }

            if ($request->has('format')) {
                $query->byFormat($request->input('format'));
            }

            if ($request->has('report_key')) {
                $query->byReportKey($request->input('report_key'));
            }

            $reportRuns = $query->paginate($pageSize, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => [
                    'runs' => $reportRuns->items(),
                    'pagination' => [
                        'current_page' => $reportRuns->currentPage(),
                        'per_page' => $reportRuns->perPage(),
                        'total' => $reportRuns->total(),
                        'last_page' => $reportRuns->lastPage(),
                        'from' => $reportRuns->firstItem(),
                        'to' => $reportRuns->lastItem(),
                        'has_more_pages' => $reportRuns->hasMorePages()
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to get export history', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get export history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel queued export
     */
    public function cancel($id)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $reportRun = ReportRun::forCompany($companyId)
                ->where('status', 'queued')
                ->findOrFail($id);

            $reportRun->update([
                'status' => 'failed',
                'error_message' => 'Cancelled by user',
                'completed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Export cancelled successfully'
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to cancel export', [
                'company_id' => $companyId,
                'run_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file size
     */
    private function getFileSize(string $filePath): ?int
    {
        try {
            return \Storage::disk('local')->size($filePath);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate file name for download
     */
    private function generateFileName(ReportRun $reportRun): string
    {
        $companyId = $reportRun->company_id;
        $reportKey = str_replace('.', '-', $reportRun->report_key);
        $date = $reportRun->created_at->format('Y-m-d');
        $extension = $reportRun->format;
        
        return "company-{$companyId}-{$reportKey}-{$date}.{$extension}";
    }
}
