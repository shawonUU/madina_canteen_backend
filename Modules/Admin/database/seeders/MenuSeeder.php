<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Permission;

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
                'extra_permissions' => [],

                'menus' => [
                    [
                        'name' => 'Approval Workflows',
                        'slug' => 'approval-workflow',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Pending Approvals',
                        'slug' => 'pending-approval',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 2,
                        'is_active' => true,
                        'extra_permissions' => [],
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
                'extra_permissions' => [],

                'menus' => [
                    [
                        'name' => 'Meal Type',
                        'slug' => 'meal-type',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Meal Menu',
                        'slug' => 'meal-menu',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 2,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Meal Booking',
                        'slug' => 'meal-booking',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 3,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Serve Meal',
                        'slug' => 'serve-meal',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 4,
                        'is_active' => true,
                        'extra_permissions' => [],
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
                'extra_permissions' => [],

                'menus' => [
                    [
                        'name' => 'Gate Pass',
                        'slug' => 'gatepass',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'Create Gate Pass Request',
                                'slug' => 'create-gatepass-request',
                                'route' => '/reception/gatepass/create-gatepass-request',
                                'permission' => 'gate_pass.create',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
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
                'extra_permissions' => [],

                'menus' => [
                    [
                        'name' => 'Configurations',
                        'slug' => 'configuration',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'Department List',
                                'slug' => 'department-list',
                                'route' => '',
                                'permission' => 'department.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Designation List',
                                'slug' => 'designation-list',
                                'route' => '',
                                'permission' => 'designation.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 2,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                        ],
                    ],

                    [
                        'name' => 'Employee Management',
                        'slug' => 'employee-management',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 2,
                        'is_active' => true,
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'Employee List',
                                'slug' => 'employee-list',
                                'route' => '/hrm/employees',
                                'permission' => 'employee.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                        ],
                    ],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Meeting Management
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Meeting Management',
                'slug' => 'meeting',
                'icon' => 'Users',
                'sort_order' => 5,
                'is_active' => true,
                'extra_permissions' => [],

                'menus' => [
                    [
                        'name' => 'Meeting Scheduling',
                        'slug' => 'meeting-scheduling',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'CalendarDays',
                        'sort_order' => 1,
                        'is_active' => true,
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'Meeting List',
                                'slug' => 'meeting-list',
                                'route' => '/meeting/meetings',
                                'permission' => 'meeting.view',
                                'icon' => 'List',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Create Meeting',
                                'slug' => 'create-meeting',
                                'route' => '/meeting/meetings/create',
                                'permission' => 'meeting.create',
                                'icon' => 'CalendarPlus',
                                'sort_order' => 2,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Calendar View',
                                'slug' => 'meeting-calendar',
                                'route' => '/meeting/calendar',
                                'permission' => 'meeting.view',
                                'icon' => 'Calendar',
                                'sort_order' => 3,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                        ],
                    ],

                    [
                        'name' => 'Invitations',
                        'slug' => 'invitations',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'UserRoundCheck',
                        'sort_order' => 2,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Meeting Minutes',
                        'slug' => 'meeting-minutes',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'FileText',
                        'sort_order' => 5,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Meeting Reports',
                        'slug' => 'meeting-reports',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'ChartNoAxesCombined',
                        'sort_order' => 6,
                        'is_active' => true,
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'Meeting Summary',
                                'slug' => 'meeting-summary',
                                'route' => '/meeting/reports/summary',
                                'permission' => 'meeting.report.view',
                                'icon' => 'ChartBar',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Attendance Report',
                                'slug' => 'attendance-report',
                                'route' => '/meeting/attendance/report',
                                'permission' => 'meeting.attendance.report',
                                'icon' => 'ChartNoAxesCombined',
                                'sort_order' => 2,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                        ],
                    ],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Support & Helpdesk
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Support & Helpdesk',
                'slug' => 'support',
                'icon' => 'Headset',
                'sort_order' => 6,
                'is_active' => true,
                'extra_permissions' => [],

                'menus' => [
                    [
                        'name' => 'Support Dashboard',
                        'slug' => 'support-dashboard',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'LayoutDashboard',
                        'sort_order' => 1,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Create Ticket',
                        'slug' => 'create-ticket',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'PlusCircle',
                        'sort_order' => 2,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'My Tickets',
                        'slug' => 'my-tickets',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Ticket',
                        'sort_order' => 3,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'All Tickets',
                        'slug' => 'all-tickets',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'ListChecks',
                        'sort_order' => 4,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'My Assigned Tickets',
                        'slug' => 'my-assigned-tickets',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'UserRoundCheck',
                        'sort_order' => 5,
                        'is_active' => true,
                        'extra_permissions' => [],
                    ],
                    [
                        'name' => 'Reports',
                        'slug' => 'support-reports',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'ChartNoAxesCombined',
                        'sort_order' => 6,
                        'is_active' => true,
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'Ticket Summary',
                                'slug' => 'ticket-summary',
                                'route' => null,
                                'permission' => null,
                                'icon' => 'ChartBar',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Resolution Report',
                                'slug' => 'resolution-report',
                                'route' => null,
                                'permission' => null,
                                'icon' => 'Clock',
                                'sort_order' => 2,
                                'is_active' => true,
                                'extra_permissions' => [],
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
                'sort_order' => 7,
                'is_active' => true,
                'extra_permissions' => [],

                'menus' => [
                    [
                        'name' => 'System Management',
                        'slug' => 'system-management',
                        'route' => null,
                        'permission' => null,
                        'icon' => 'Circle',
                        'sort_order' => 1,
                        'is_active' => true,
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'Module',
                                'slug' => 'module',
                                'route' => '/admin/modules',
                                'permission' => 'module.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Menu',
                                'slug' => 'menu',
                                'route' => '/admin/menus',
                                'permission' => 'menu.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 2,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Child Menu',
                                'slug' => 'child-menu',
                                'route' => '/admin/child-menus',
                                'permission' => 'child_menu.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 3,
                                'is_active' => true,
                                'extra_permissions' => [],
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
                        'extra_permissions' => [],

                        'child_menus' => [
                            [
                                'name' => 'User Access',
                                'slug' => 'user-access',
                                'route' => '/admin/user-access',
                                'permission' => 'user_access.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 1,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Permission',
                                'slug' => 'permission',
                                'route' => '/admin/permissions',
                                'permission' => 'permission.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 2,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                            [
                                'name' => 'Role',
                                'slug' => 'role',
                                'route' => '/admin/roles',
                                'permission' => 'role.view',
                                'icon' => 'CircleDot',
                                'sort_order' => 3,
                                'is_active' => true,
                                'extra_permissions' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($data as $moduleData) {

            $menus = $moduleData['menus'] ?? [];
            $modulePermissions = $moduleData['extra_permissions'] ?? [];

            unset(
                $moduleData['menus'],
                $moduleData['extra_permissions']
            );

            $moduleData['code'] = getGenerateCode(
                Module::class,
                'code',
                'MOD',
                8
            );

            $module = Module::create($moduleData);

            $this->saveExtraPermissions(
                $modulePermissions,
                $module->id
            );

            foreach ($menus as $menuData) {

                $childMenus = $menuData['child_menus'] ?? [];
                $menuPermissions = $menuData['extra_permissions'] ?? [];

                unset(
                    $menuData['child_menus'],
                    $menuData['extra_permissions']
                );

                $menuData['module_id'] = $module->id;

                $menuData['code'] = getGenerateCode(
                    Menu::class,
                    'code',
                    'MNU',
                    8
                );

                $menu = Menu::create($menuData);

                $this->saveExtraPermissions(
                    $menuPermissions,
                    $module->id,
                    $menu->id
                );

                foreach ($childMenus as $childMenuData) {

                    $childPermissions = $childMenuData['extra_permissions'] ?? [];

                    unset($childMenuData['extra_permissions']);

                    $childMenuData['menu_id'] = $menu->id;

                    $childMenuData['code'] = getGenerateCode(
                        ChildMenu::class,
                        'code',
                        'CMNU',
                        8
                    );

                    $childMenu = ChildMenu::create($childMenuData);

                    $this->saveExtraPermissions(
                        $childPermissions,
                        $module->id,
                        $menu->id,
                        $childMenu->id
                    );
                }
            }
        }
    }

    private function saveExtraPermissions(
        array $permissions,
        int $moduleId,
        ?int $menuId = null,
        ?int $childMenuId = null
    ): void {
        foreach ($permissions as $permissionData) {

            Permission::updateOrCreate(
                [
                    'key' => $permissionData['key'],
                ],
                [
                    'name' => $permissionData['name'],
                    'module_id' => $moduleId,
                    'menu_id' => $menuId,
                    'child_menu_id' => $childMenuId,
                    'description' => $permissionData['description'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
