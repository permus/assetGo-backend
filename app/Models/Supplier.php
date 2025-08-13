<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'supplier_code',
        'name', // Business name
        'contact_person',
        'tax_registration_number',
        'email',
        'phone', // Primary phone
        'alternate_phone',
        'website',
        'street_address',
        'city',
        'state',
        'postal_code',
        'payment_terms',
        'terms',
        'currency',
        'credit_limit',
        'delivery_lead_time',
        'notes',
        'extra'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'delivery_lead_time' => 'integer',
        'extra' => 'array',
    ];

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}


