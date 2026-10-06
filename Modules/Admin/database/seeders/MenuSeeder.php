<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\ChildMenu;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $data = [

            /*
            |--------------------------------------------------------------------------
            | Approval Management
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Approval Management',
                'slug' => 'approval',
                'icon' => 'ClipboardCheck',
                'sort_order' => 1,
                'is_active' => true,

                'menus' => [
                    [
                        'name' => 'Approval Workflows',
                        'slug' => 'approval-workflow',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Workflow List',
                                'slug' => 'workflow-list',
                                'route' => '/approval/workflows',
                                'permission' => 'approval_workflow.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],

                    [
                        'name' => 'Pending Approvals',
                        'slug' => 'pending-approval',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 2,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Pending Approval List',
                                'slug' => 'pending-approval-list',
                                'route' => '/approval/pending',
                                'permission' => 'pending_approval.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Canteen Management
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Canteen Management',
                'slug' => 'canteen',
                'icon' => 'Utensils',
                'sort_order' => 2,
                'is_active' => true,

                'menus' => [

                    [
                        'name' => 'Meal Type',
                        'slug' => 'meal-type',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Meal Type List',
                                'slug' => 'meal-type-list',
                                'route' => '/canteen/meal-types',
                                'permission' => 'meal_type.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],

                    [
                        'name' => 'Meal Menu',
                        'slug' => 'meal-menu',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 2,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Meal Menu List',
                                'slug' => 'meal-menu-list',
                                'route' => '/canteen/meal-menus',
                                'permission' => 'meal_menu.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],

                    [
                        'name' => 'Meal Booking',
                        'slug' => 'meal-booking',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 3,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Meal Booking List',
                                'slug' => 'meal-booking-list',
                                'route' => '/canteen/meal-bookings',
                                'permission' => 'meal_booking.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],

                    [
                        'name' => 'Serve Meal',
                        'slug' => 'serve-meal',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 4,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Serve Meal',
                                'slug' => 'serve-meal-list',
                                'route' => '/canteen/serve-meal',
                                'permission' => 'serve_meal.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],

                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Reception Management
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Reception Management',
                'slug' => 'reception',
                'icon' => 'Building2',
                'sort_order' => 3,
                'is_active' => true,

                'menus' => [

                    [
                        'name' => 'Gate Pass',
                        'slug' => 'gatepass',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Create Gate Pass Request',
                                'slug' => 'create-gatepass-request',
                                'route' => '/reception/gatepass/create-gatepass-request',
                                'permission' => 'gate_pass.create',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],

                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | HRM
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'HRM',
                'slug' => 'hrm',
                'icon' => 'Users',
                'sort_order' => 4,
                'is_active' => true,

                'menus' => [

                    [
                        'name' => 'Employee Management',
                        'slug' => 'employee-management',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,

                        'child_menus' => [
                            [
                                'name' => 'Employee List',
                                'slug' => 'employee-list',
                                'route' => '/hrm/employees',
                                'permission' => 'employee.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        ],
                    ],

                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Admin
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'icon' => 'Settings',
                'sort_order' => 5,
                'is_active' => true,

                'menus' => [

                    [
                        'name' => 'System Management',
                        'slug' => 'system-management',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,

                        'child_menus' => [

                            [
                                'name' => 'Module',
                                'slug' => 'module',
                                'route' => '/admin/modules',
                                'permission' => 'module.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],

                            [
                                'name' => 'Menu',
                                'slug' => 'menu',
                                'route' => '/admin/menus',
                                'permission' => 'menu.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 2,
                                'is_active' => true,
                            ],

                            [
                                'name' => 'Child Menu',
                                'slug' => 'child-menu',
                                'route' => '/admin/child-menus',
                                'permission' => 'child_menu.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 3,
                                'is_active' => true,
                            ],

                        ],
                    ],

                    [
                        'name' => 'Access Control',
                        'slug' => 'access-control',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 2,
                        'is_active' => true,

                        'child_menus' => [

                            [
                                'name' => 'User Access',
                                'slug' => 'user-access',
                                'route' => '/admin/user-access',
                                'permission' => 'user_access.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                            ],

                            [
                                'name' => 'Permission',
                                'slug' => 'permission',
                                'route' => '/admin/permissions',
                                'permission' => 'permission.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 2,
                                'is_active' => true,
                            ],

                            [
                                'name' => 'Role',
                                'slug' => 'role',
                                'route' => '/admin/roles',
                                'permission' => 'role.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 3,
                                'is_active' => true,
                            ],

                        ],
                    ],

                ],
            ],
        ];


        foreach ($data as $moduleData) {

            $menus = $moduleData['menus'] ?? [];

            unset($moduleData['menus']);

            $moduleData['code'] = getGenerateCode(
                Module::class,
                'code',
                'MOD',
                8
            );

            $module = Module::create($moduleData);


            foreach ($menus as $menuData) {

                $childMenus = $menuData['child_menus'] ?? [];

                unset($menuData['child_menus']);

                $menuData['module_id'] = $module->id;

                $menuData['code'] = getGenerateCode(
                    Menu::class,
                    'code',
                    'MNU',
                    8
                );

                $menu = Menu::create($menuData);


                foreach ($childMenus as $childMenuData) {

                    $childMenuData['menu_id'] = $menu->id;

                    $childMenuData['code'] = getGenerateCode(
                        ChildMenu::class,
                        'code',
                        'CMNU',
                        8
                    );

                    ChildMenu::create($childMenuData);
                }
            }
        }
    }
}