<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','name','description','template_data','is_active','created_by'
    ];

    protected $casts = [
        'template_data' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}


