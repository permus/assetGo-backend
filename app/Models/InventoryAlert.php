<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','part_id','alert_type','alert_level','message','is_resolved','resolved_at','resolved_by'
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function part()
    {
        return $this->belongsTo(InventoryPart::class, 'part_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}


