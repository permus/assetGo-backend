<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    use HasFactory;

    protected $table = 'inventory_stocks';

    protected $fillable = [
        'company_id','part_id','location_id','on_hand','reserved','available','average_cost'
    ];

    protected $casts = [
        'average_cost' => 'decimal:2',
    ];

    public function part()
    {
        return $this->belongsTo(InventoryPart::class, 'part_id');
    }

    public function location()
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}


