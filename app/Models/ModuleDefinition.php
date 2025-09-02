<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'display_name',
        'description',
        'icon_name',
        'route_path',
        'sort_order',
        'is_system_module',
    ];

    protected $casts = [
        'is_system_module' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function companyModules()
    {
        return $this->hasMany(CompanyModule::class, 'module_id');
    }
}


