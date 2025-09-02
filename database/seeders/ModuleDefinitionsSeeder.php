<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ModuleDefinition;

class ModuleDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure old 'core' module is removed
        \App\Models\ModuleDefinition::where('key', 'core')->delete();

        $modules = [
            [
                'key' => 'dashboard',
                'display_name' => 'Dashboard',
                'description' => 'Main dashboard and analytics',
                'icon_name' => 'dashboard',
                'route_path' => '/dashboard',
                'sort_order' => 0,
                'is_system_module' => true,
            ],
            [
                'key' => 'settings',
                'display_name' => 'Settings',
                'description' => 'System configuration and settings',
                'icon_name' => 'settings',
                'route_path' => '/settings',
                'sort_order' => 5,
                'is_system_module' => true,
            ],
            [
                'key' => 'work_orders',
                'display_name' => 'Work Orders',
                'description' => 'Work orders and maintenance tasks',
                'icon_name' => 'wrench',
                'route_path' => '/work-orders',
                'sort_order' => 20,
                'is_system_module' => false,
            ],
            [
                'key' => 'maintenance',
                'display_name' => 'Maintenance',
                'description' => 'Preventive and corrective maintenance',
                'icon_name' => 'tools',
                'route_path' => '/maintenance',
                'sort_order' => 30,
                'is_system_module' => false,
            ],
            [
                'key' => 'facilities_locations',
                'display_name' => 'Facilities & Locations',
                'description' => 'Location and facility management',
                'icon_name' => 'map',
                'route_path' => '/locations',
                'sort_order' => 40,
                'is_system_module' => false,
            ],
            [
                'key' => 'teams',
                'display_name' => 'Teams',
                'description' => 'Team and roles management',
                'icon_name' => 'users',
                'route_path' => '/teams',
                'sort_order' => 50,
                'is_system_module' => false,
            ],
            [
                'key' => 'assets',
                'display_name' => 'Assets',
                'description' => 'Asset management and tracking',
                'icon_name' => 'asset',
                'route_path' => '/assets',
                'sort_order' => 15,
                'is_system_module' => false,
            ],
            [
                'key' => 'sla',
                'display_name' => 'SLA',
                'description' => 'Service Level Agreement tracking and management',
                'icon_name' => 'sla',
                'route_path' => '/sla',
                'sort_order' => 55,
                'is_system_module' => false,
            ],
            [
                'key' => 'reports',
                'display_name' => 'Reports',
                'description' => 'Analytics and reports',
                'icon_name' => 'chart-bar',
                'route_path' => '/reports',
                'sort_order' => 60,
                'is_system_module' => false,
            ],
            [
                'key' => 'sensors',
                'display_name' => 'Sensors',
                'description' => 'IoT sensor monitoring and management',
                'icon_name' => 'sensor',
                'route_path' => '/sensors',
                'sort_order' => 65,
                'is_system_module' => false,
            ],
            [
                'key' => 'ai_features',
                'display_name' => 'AI Features',
                'description' => 'AI-powered features and insights',
                'icon_name' => 'sparkles',
                'route_path' => '/ai',
                'sort_order' => 70,
                'is_system_module' => false,
            ],
            [
                'key' => 'eservices',
                'display_name' => 'eServices',
                'description' => 'Tenant services and portal management',
                'icon_name' => 'services',
                'route_path' => '/eservices',
                'sort_order' => 80,
                'is_system_module' => false,
            ],
            [
                'key' => 'tenant_portal',
                'display_name' => 'Tenant Portal',
                'description' => 'Tenant-facing services portal',
                'icon_name' => 'portal',
                'route_path' => '/tenant-portal',
                'sort_order' => 81,
                'is_system_module' => false,
            ],
            [
                'key' => 'maintenance_requests',
                'display_name' => 'Maintenance Requests',
                'description' => 'Tenant maintenance request management',
                'icon_name' => 'request',
                'route_path' => '/maintenance-requests',
                'sort_order' => 82,
                'is_system_module' => false,
            ],
            [
                'key' => 'amenity_bookings',
                'display_name' => 'Amenity Bookings',
                'description' => 'Community amenity booking system',
                'icon_name' => 'calendar',
                'route_path' => '/amenity-bookings',
                'sort_order' => 83,
                'is_system_module' => false,
            ],
            [
                'key' => 'move_in_out_requests',
                'display_name' => 'Move In/Out Requests',
                'description' => 'Move in and move out request management',
                'icon_name' => 'truck',
                'route_path' => '/move-requests',
                'sort_order' => 84,
                'is_system_module' => false,
            ],
            [
                'key' => 'fitout_requests',
                'display_name' => 'Fitout Requests',
                'description' => 'Tenant fitout and renovation requests',
                'icon_name' => 'construction',
                'route_path' => '/fitout-requests',
                'sort_order' => 85,
                'is_system_module' => false,
            ],
            [
                'key' => 'inhouse_services',
                'display_name' => 'Inhouse Services',
                'description' => 'Product sales and services to tenants',
                'icon_name' => 'store',
                'route_path' => '/inhouse-services',
                'sort_order' => 86,
                'is_system_module' => false,
            ],
            [
                'key' => 'parcel_management',
                'display_name' => 'Parcel Management',
                'description' => 'Package delivery and pickup management',
                'icon_name' => 'package',
                'route_path' => '/parcels',
                'sort_order' => 87,
                'is_system_module' => false,
            ],
            [
                'key' => 'visitor_management',
                'display_name' => 'Visitor Management',
                'description' => 'Visitor, gate and parking management',
                'icon_name' => 'visitor',
                'route_path' => '/visitors',
                'sort_order' => 88,
                'is_system_module' => false,
            ],
            [
                'key' => 'business_directory',
                'display_name' => 'Business Directory',
                'description' => 'Tenant business directory and networking',
                'icon_name' => 'directory',
                'route_path' => '/business-directory',
                'sort_order' => 89,
                'is_system_module' => false,
            ],
            [
                'key' => 'tenant_communication',
                'display_name' => 'Tenant Communication',
                'description' => 'Communication between tenants and management',
                'icon_name' => 'chat',
                'route_path' => '/tenant-communication',
                'sort_order' => 90,
                'is_system_module' => false,
            ],
            [
                'key' => 'inventory',
                'display_name' => 'Inventory',
                'description' => 'Parts and inventory management',
                'icon_name' => 'boxes',
                'route_path' => '/inventory',
                'sort_order' => 35,
                'is_system_module' => false,
            ],
        ];

        foreach ($modules as $data) {
            ModuleDefinition::updateOrCreate(['key' => $data['key']], $data);
        }
    }
}


