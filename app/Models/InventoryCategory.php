<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','parent_id','name','description','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(InventoryCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(InventoryCategory::class, 'parent_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}


