<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'work_order_categories';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    /**
     * Company scope (include global)
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where(function($q) use ($companyId) {
            $q->whereNull('company_id')->orWhere('company_id', $companyId);
        })->orderBy('sort');
    }

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
