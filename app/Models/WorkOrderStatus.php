<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderStatus extends Model
{
    use HasFactory;

    protected $table = 'work_order_status';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'is_management',
        'sort',
    ];

    protected $casts = [
        'is_management' => 'boolean',
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

    /**
     * Prevent deletion of management items
     */
    protected static function booted()
    {
        static::deleting(function ($model) {
            if ($model->is_management) {
                throw new \RuntimeException('This management item cannot be deleted.');
            }
        });
    }
}
