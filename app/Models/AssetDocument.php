<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'document_path',
        'document_name',
        'document_type',
        'file_size',
        'mime_type',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected $appends = ['document_url'];

    public function getDocumentUrlAttribute()
    {
        if ($this->document_path) {
            if (filter_var($this->document_path, FILTER_VALIDATE_URL)) {
                return $this->document_path;
            }
            
            if (\Storage::disk('public')->exists($this->document_path)) {
                return \Storage::disk('public')->url($this->document_path);
            }
        }
        return null;
    }

    protected static function booted()
    {
        static::deleting(function ($document) {
            if ($document->document_path && \Storage::disk('public')->exists($document->document_path)) {
                \Storage::disk('public')->delete($document->document_path);
            }
        });
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
