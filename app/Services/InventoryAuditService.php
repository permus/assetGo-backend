<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class InventoryAuditService
{
    /**
     * Log stock adjustment (receipt, issue, adjustment, return)
     *
     * @param int $transactionId
     * @param int $partId
     * @param string $partName
     * @param int $locationId
     * @param string $type
     * @param int $quantity
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logStockAdjustment(
        int $transactionId,
        int $partId,
        string $partName,
        int $locationId,
        string $type,
        int $quantity,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory stock adjustment', [
            'transaction_id' => $transactionId,
            'part_id' => $partId,
            'part_name' => $partName,
            'location_id' => $locationId,
            'type' => $type,
            'quantity' => $quantity,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log stock transfer between locations
     *
     * @param int $partId
     * @param string $partName
     * @param int $fromLocationId
     * @param int $toLocationId
     * @param int $quantity
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logStockTransfer(
        int $partId,
        string $partName,
        int $fromLocationId,
        int $toLocationId,
        int $quantity,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory stock transfer', [
            'part_id' => $partId,
            'part_name' => $partName,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'quantity' => $quantity,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log stock reservation
     *
     * @param int $partId
     * @param string $partName
     * @param int $locationId
     * @param int $quantity
     * @param string $action (reserve or release)
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logStockReservation(
        int $partId,
        string $partName,
        int $locationId,
        int $quantity,
        string $action,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info("Inventory stock {$action}", [
            'part_id' => $partId,
            'part_name' => $partName,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'action' => $action,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log physical stock count
     *
     * @param int $partId
     * @param string $partName
     * @param int $locationId
     * @param int $countedQuantity
     * @param int $adjustment
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logStockCount(
        int $partId,
        string $partName,
        int $locationId,
        int $countedQuantity,
        int $adjustment,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory physical count', [
            'part_id' => $partId,
            'part_name' => $partName,
            'location_id' => $locationId,
            'counted_quantity' => $countedQuantity,
            'adjustment' => $adjustment,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log purchase order creation
     *
     * @param int $purchaseOrderId
     * @param string $poNumber
     * @param string $vendorName
     * @param float $total
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPurchaseOrderCreated(
        int $purchaseOrderId,
        string $poNumber,
        string $vendorName,
        float $total,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Purchase order created', [
            'purchase_order_id' => $purchaseOrderId,
            'po_number' => $poNumber,
            'vendor_name' => $vendorName,
            'total' => $total,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log purchase order update
     *
     * @param int $purchaseOrderId
     * @param string $poNumber
     * @param array $changes
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPurchaseOrderUpdated(
        int $purchaseOrderId,
        string $poNumber,
        array $changes,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Purchase order updated', [
            'purchase_order_id' => $purchaseOrderId,
            'po_number' => $poNumber,
            'changes' => $changes,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log purchase order approval
     *
     * @param int $purchaseOrderId
     * @param string $poNumber
     * @param string $oldStatus
     * @param string $newStatus
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPurchaseOrderApproved(
        int $purchaseOrderId,
        string $poNumber,
        string $oldStatus,
        string $newStatus,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Purchase order approved', [
            'purchase_order_id' => $purchaseOrderId,
            'po_number' => $poNumber,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log purchase order receiving
     *
     * @param int $purchaseOrderId
     * @param string $poNumber
     * @param array $receivedItems
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPurchaseOrderReceived(
        int $purchaseOrderId,
        string $poNumber,
        array $receivedItems,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Purchase order received', [
            'purchase_order_id' => $purchaseOrderId,
            'po_number' => $poNumber,
            'received_items' => $receivedItems,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log inventory part creation
     *
     * @param int $partId
     * @param string $partNumber
     * @param string $name
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPartCreated(
        int $partId,
        string $partNumber,
        string $name,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory part created', [
            'part_id' => $partId,
            'part_number' => $partNumber,
            'name' => $name,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log inventory part update
     *
     * @param int $partId
     * @param string $partNumber
     * @param string $name
     * @param array $changes
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPartUpdated(
        int $partId,
        string $partNumber,
        string $name,
        array $changes,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory part updated', [
            'part_id' => $partId,
            'part_number' => $partNumber,
            'name' => $name,
            'changes' => $changes,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log inventory part deletion
     *
     * @param int $partId
     * @param string $partNumber
     * @param string $name
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPartDeleted(
        int $partId,
        string $partNumber,
        string $name,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory part deleted', [
            'part_id' => $partId,
            'part_number' => $partNumber,
            'name' => $name,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log inventory part archival
     *
     * @param int $partId
     * @param string $partNumber
     * @param string $name
     * @param array $affectedPurchaseOrders
     * @param bool $forced
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPartArchived(
        int $partId,
        string $partNumber,
        string $name,
        array $affectedPurchaseOrders,
        bool $forced,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory part archived', [
            'part_id' => $partId,
            'part_number' => $partNumber,
            'name' => $name,
            'affected_purchase_orders' => $affectedPurchaseOrders,
            'forced' => $forced,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log inventory part restoration
     *
     * @param int $partId
     * @param string $partNumber
     * @param string $name
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param string|null $ipAddress
     * @return void
     */
    public function logPartRestored(
        int $partId,
        string $partNumber,
        string $name,
        int $userId,
        string $userEmail,
        int $companyId,
        ?string $ipAddress = null
    ): void {
        Log::info('Inventory part restored', [
            'part_id' => $partId,
            'part_number' => $partNumber,
            'name' => $name,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log bulk parts import
     *
     * @param int $userId
     * @param string $userEmail
     * @param int $companyId
     * @param int $importedCount
     * @param int $failedCount
     * @param string|null $ipAddress
     * @return void
     */
    public function logPartsBulkImport(
        int $userId,
        string $userEmail,
        int $companyId,
        int $importedCount,
        int $failedCount,
        ?string $ipAddress = null
    ): void {
        Log::info('Parts bulk import', [
            'event' => 'parts_bulk_import',
            'user_id' => $userId,
            'user_email' => $userEmail,
            'company_id' => $companyId,
            'imported_count' => $importedCount,
            'failed_count' => $failedCount,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

