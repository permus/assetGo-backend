<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SettingsAuditService
{
    /**
     * Log a settings change
     * 
     * @param string $settingType - Type of setting (company, currency, module, preference, logo)
     * @param mixed $oldValue - Previous value
     * @param mixed $newValue - New value
     * @param int $userId - User who made the change
     * @param string|null $ipAddress - IP address
     * @param array $additionalData - Any extra context
     */
    public function logChange(
        string $settingType,
        $oldValue,
        $newValue,
        int $userId,
        ?string $ipAddress = null,
        array $additionalData = []
    ): void {
        $logData = [
            'setting_type' => $settingType,
            'user_id' => $userId,
            'old_value' => $this->sanitizeValue($oldValue),
            'new_value' => $this->sanitizeValue($newValue),
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
            'additional_data' => $additionalData,
        ];

        // Log to Laravel log file
        Log::channel('single')->info('Settings changed', $logData);
        
        // Optional: Store in database for UI display (implement if needed)
        // SettingsAuditLog::create($logData);
    }

    /**
     * Log company info update
     */
    public function logCompanyUpdate(array $oldData, array $newData, int $userId, ?string $ipAddress = null): void
    {
        $changes = [];
        
        foreach ($newData as $key => $value) {
            if (isset($oldData[$key]) && $oldData[$key] != $value) {
                $changes[$key] = [
                    'old' => $oldData[$key],
                    'new' => $value
                ];
            }
        }

        if (!empty($changes)) {
            $this->logChange('company_update', $oldData, $newData, $userId, $ipAddress, [
                'changes' => $changes
            ]);
        }
    }

    /**
     * Log currency change
     */
    public function logCurrencyChange(string $oldCurrency, string $newCurrency, int $userId, ?string $ipAddress = null): void
    {
        $this->logChange('currency', $oldCurrency, $newCurrency, $userId, $ipAddress);
    }

    /**
     * Log module enable/disable
     */
    public function logModuleToggle(int $moduleId, string $moduleName, bool $enabled, int $userId, ?string $ipAddress = null): void
    {
        $this->logChange('module_toggle', !$enabled, $enabled, $userId, $ipAddress, [
            'module_id' => $moduleId,
            'module_name' => $moduleName,
            'action' => $enabled ? 'enabled' : 'disabled'
        ]);
    }

    /**
     * Log preference update
     */
    public function logPreferenceUpdate(array $oldPreferences, array $newPreferences, int $userId, ?string $ipAddress = null): void
    {
        $this->logChange('preferences', $oldPreferences, $newPreferences, $userId, $ipAddress);
    }

    /**
     * Log logo upload
     */
    public function logLogoUpload(string $logoPath, int $userId, ?string $ipAddress = null): void
    {
        $this->logChange('logo_upload', null, $logoPath, $userId, $ipAddress);
    }

    /**
     * Sanitize values for logging (remove sensitive data)
     */
    private function sanitizeValue($value)
    {
        if (is_array($value)) {
            // Remove any password fields if present
            $sanitized = $value;
            unset($sanitized['password'], $sanitized['token'], $sanitized['api_key']);
            return $sanitized;
        }

        return $value;
    }
}

