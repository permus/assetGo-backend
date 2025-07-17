<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
            $filename = 'qr-codes/location-' . $location->id . '-' . time() . '.png';
            
            // Generate QR code
            $qrCode = QrCode::format('png')
                ->size(300)
                ->margin(2)
                ->errorCorrection('M')
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
     * Regenerate QR code for a location
     *
     * @param \App\Models\Location $location
     * @return string|null
     */
    public function regenerateLocationQRCode($location)
    {
        // Delete old QR code
        if ($location->qr_code_path) {
            $this->deleteQRCode($location->qr_code_path);
        }

        // Generate new QR code
        return $this->generateLocationQRCode($location);
    }
}