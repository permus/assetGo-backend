<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','purchase_order_id','part_id','part_number','description','ordered_qty','received_qty','unit_cost','line_total','notes'
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected $appends = ['location_info'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function part()
    {
        return $this->belongsTo(InventoryPart::class, 'part_id');
    }

    public function getLocationInfoAttribute()
    {
        // Get the first transaction for this item with location info
        $transaction = InventoryTransaction::where('related_id', $this->purchase_order_id)
            ->where('reference_type', 'PO Receipt')
            ->where('part_id', $this->part_id)
            ->with('location')
            ->first();
            
        return $transaction ? $transaction->location : null;
    }

    /**
     * Scope to exclude items with archived parts
     */
    public function scopeExcludeArchivedParts($query)
    {
        return $query->whereHas('part', function ($q) {
            $q->where('is_archived', false);
        })->orWhereNull('part_id');
    }
}


