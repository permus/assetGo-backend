<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','po_number','supplier_id','order_date','expected_date','status','subtotal','tax','shipping','total','created_by','approved_by','approved_at','reject_comment',
        'vendor_name','vendor_contact','actual_delivery_date','terms','notes','approval_threshold','requires_approval','approval_level','approval_history','email_status','last_email_sent_at','template_id'
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'actual_delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping' => 'decimal:2',
        'total' => 'decimal:2',
        'approved_at' => 'datetime',
        'requires_approval' => 'boolean',
        'approval_history' => 'array',
        'last_email_sent_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}


