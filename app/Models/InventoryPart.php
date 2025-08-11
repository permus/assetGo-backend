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
        'company_id', 'user_id', 'part_number', 'name', 'description', 'uom', 'unit_cost',
        'category_id', 'reorder_point', 'reorder_qty', 'barcode', 'image_path', 'status', 'abc_class', 'extra'
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'extra' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stocks()
    {
        return $this->hasMany(InventoryStock::class, 'part_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}


