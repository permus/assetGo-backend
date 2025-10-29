<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryPart extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_parts';

    protected $fillable = [
        'company_id', 'user_id', 'part_number', 'name', 'description', 'manufacturer', 'maintenance_category',
        'uom', 'unit_cost', 'specifications', 'compatible_assets', 'category_id', 'reorder_point', 'reorder_qty',
        'minimum_stock', 'maximum_stock', 'is_consumable', 'usage_tracking', 'preferred_supplier_id',
        'barcode', 'image_path', 'status', 'is_archived', 'abc_class', 'extra'
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'specifications' => 'array',
        'compatible_assets' => 'array',
        'is_consumable' => 'boolean',
        'usage_tracking' => 'boolean',
        'is_archived' => 'boolean',
        'extra' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function preferredSupplier()
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function stocks()
    {
        return $this->hasMany(InventoryStock::class, 'part_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }
}


