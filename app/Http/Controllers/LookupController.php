<?php

namespace App\Http\Controllers;

use App\Services\AccessService;
use Illuminate\Http\Request;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Role;
use Modules\Admin\Models\User;
use Modules\HRM\Models\Department;
use Modules\HRM\Models\Designation;
use Modules\HRM\Models\Employee;

class LookupController extends Controller
{
    public function index(Request $request, string $resource, AccessService $access)
    {
        abort_unless($access->effectiveAccesses($request->user())->contains(fn ($row) => $row['can_view']), 403, 'No module access has been assigned.');
        $data = match ($resource) {
            'users' => User::where('status', 'Active')->orderBy('name')->get(['id', 'name', 'employee_id']),
            'employees' => Employee::where('status', 'Active')->orderBy('name')->get(['id', 'name', 'code', 'department', 'designation']),
            'departments' => Department::where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            default => abort(404),
        };
        return response()->json(['success' => true, 'status' => 'Success', 'data' => $data]);
    }

    public function navigation(Request $request, AccessService $access)
    {
        abort_unless($access->can($request->user(), 'approval_workflow.view') || $access->can($request->user(), 'user_access.view') || $access->can($request->user(), 'menu.view') || $access->can($request->user(), 'child_menu.view'), 403);
        return response()->json(['success' => true, 'data' => [
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'menus' => Menu::where('is_active', true)->orderBy('sort_order')->get(['id', 'module_id', 'name', 'slug']),
            'child_menus' => ChildMenu::where('is_active', true)->orderBy('sort_order')->get(['id', 'menu_id', 'name', 'slug']),
        ]]);
    }
}
