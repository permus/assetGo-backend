<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;

class QRCodeService
{
    /**
     * Generate QR code for a location
     *
     * @param \App\Models\Location $location
     * @return string|null Path to the generated QR code
     */
    public function generateLocationQRCode($location)
    {
        try {
            // Create the QR code content (public URL to the location)
            $qrContent = $location->public_url;

            // Generate filename
            $filename = 'qrcodes/location-' . $location->id . '.png';

            // Generate QR code
            $qrCode = QrCode::format('png')
                ->size(300)
                ->margin(2)
                ->errorCorrection('M')
                ->backgroundColor(255, 255, 255)
                ->color(0, 0, 0)
                ->generate($qrContent);

            // Save to storage
            Storage::disk('public')->put($filename, $qrCode);

            return $filename;

        } catch (\Exception $e) {
            \Log::error('QR Code generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate QR code with custom size
     *
     * @param \App\Models\Location $location
     * @param int $size
     * @return string|null
     */
    public function generateLocationQRCodeWithSize($location, $size = 300)
    {
        try {
            $qrContent = $location->public_url;
            $filename = 'qrcodes/location-' . $location->id . '-' . $size . '.png';

            $qrCode = QrCode::format('png')
                ->size($size)
                ->margin(2)
                ->errorCorrection('M')
                ->backgroundColor(255, 255, 255)
                ->color(0, 0, 0)
                ->generate($qrContent);

            Storage::disk('public')->put($filename, $qrCode);

            return $filename;

        } catch (\Exception $e) {
            \Log::error('QR Code generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate printable QR code PDF
     *
     * @param \App\Models\Location $location
     * @return string|null Path to PDF file
     */
    public function generatePrintableQRCode($location)
    {
        try {
            // Generate QR code image first
            $qrPath = $this->generateLocationQRCodeWithSize($location, 400);
            if (!$qrPath) {
                return null;
            }

            $qrImagePath = Storage::disk('public')->path($qrPath);
            $qrImageData = base64_encode(file_get_contents($qrImagePath));

            // Create PDF
            $pdf = Pdf::loadView('pdf.qr-code', [
                'location' => $location,
                'qrImageData' => $qrImageData,
            ]);

            $pdfFilename = 'qrcodes/location-' . $location->id . '-printable.pdf';
            $pdfContent = $pdf->output();

            Storage::disk('public')->put($pdfFilename, $pdfContent);

            return $pdfFilename;

        } catch (\Exception $e) {
            \Log::error('Printable QR Code generation failed: ' . $e->getMessage());
            return null;
        }
    }
    /**
     * Delete QR code file
     *
     * @param string $path
     * @return bool
     */
    public function deleteQRCode($path)
    {
        try {
            if ($path && Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->delete($path);
            }
            return true;
        } catch (\Exception $e) {
            \Log::error('QR Code deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all QR codes for a location
     *
     * @param \App\Models\Location $location
     * @return bool
     */
    public function deleteAllQRCodes($location)
    {
        try {
            $files = Storage::disk('public')->files('qrcodes');
            $locationFiles = collect($files)->filter(function ($file) use ($location) {
                return str_contains($file, 'location-' . $location->id);
            });

            foreach ($locationFiles as $file) {
                Storage::disk('public')->delete($file);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('QR Code cleanup failed: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Regenerate QR code for a location
     *
     * @param \App\Models\Location $location
     * @return string|null
     */
    public function regenerateLocationQRCode($location)
    {
        // Delete old QR code
        $this->deleteAllQRCodes($location);

        // Generate new QR code
        return $this->generateLocationQRCode($location);
    }

    /**
     * Get QR code download response
     *
     * @param \App\Models\Location $location
     * @param string $format
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|null
     */
    public function getQRCodeDownload($location, $format = 'png')
    {
        try {
            if ($format === 'pdf') {
                $path = $this->generatePrintableQRCode($location);
                $filename = $location->slug . '-qr-code.pdf';
            } else {
                $path = $location->qr_code_path ?: $this->generateLocationQRCode($location);
                $filename = $location->slug . '-qr-code.png';
            }

            if (!$path || !Storage::disk('public')->exists($path)) {
                return null;
            }

            return Storage::disk('public')->download($path, $filename);
        } catch (\Exception $e) {
            \Log::error('QR Code download failed: ' . $e->getMessage());
            return null;
        }
    }

    public function generateAssetQRCode($asset)
    {
        try {
            // Use QuickChart.io for QR code generation
            $qrUrl = $asset->quick_chart_qr_url;
            
            // Generate filename for caching
            $filename = 'qrcodes/asset-' . $asset->id . '.png';
            
            // Download and cache the QR code
            $qrCodeContent = file_get_contents($qrUrl);
            
            if ($qrCodeContent === false) {
                throw new \Exception('Failed to generate QR code from QuickChart.io');
            }
            
            // Save to storage
            Storage::disk('public')->put($filename, $qrCodeContent);
            
            return $filename;
            
        } catch (\Exception $e) {
            \Log::error('QR Code generation failed: ' . $e->getMessage());
            return null;
        }
    }
}
