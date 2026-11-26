<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnforceApiAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\Role;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserRole;
use Modules\Admin\Models\UserPermission;
use Modules\HRM\Models\Department;
use Modules\HRM\Models\Employee;
use Tests\TestCase;

class AdminHrmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // General route grants are covered separately; explicit sensitive-action gates remain enabled.
        $this->withoutMiddleware(EnforceApiAccess::class);
        Sanctum::actingAs(User::create(['code' => 'TEST-ADMIN', 'name' => 'Test Operator', 'email' => 'operator@example.test', 'password' => bcrypt('Strong-password-123'), 'status' => 'Active']));
    }

    public function test_department_columns_filter_the_complete_query_and_used_department_cannot_be_deleted(): void
    {
        $first = Department::create(['code' => 'D-1', 'name' => 'Finance', 'description' => 'Payroll accounts', 'status' => 'Active']);
        Department::create(['code' => 'D-2', 'name' => 'Operations', 'description' => 'Fleet planning', 'status' => 'Active']);
        Employee::create(['code' => 'E-1', 'name' => 'Accounts Employee', 'department' => $first->id, 'status' => 'Active']);
        $this->getJson('/api/hrm/departments?filter[description]=Payroll&filter[employees_count]=1')
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $first->id)->assertJsonPath('data.data.0.employees_count', 1);
        $this->deleteJson('/api/hrm/departments/'.$first->id)->assertUnprocessable();
        $this->assertDatabaseHas('departments', ['id' => $first->id]);
    }

    public function test_enabling_login_for_existing_employee_requires_a_password_without_partial_updates(): void
    {
        $employee = Employee::create(['code' => 'E-LOGIN', 'name' => 'Before', 'email' => 'employee@example.test', 'status' => 'Active']);
        $this->putJson('/api/hrm/employees/'.$employee->id, ['name' => 'After', 'email' => 'employee@example.test', 'create_user' => true, 'status' => 'Active'])
            ->assertUnprocessable()->assertJsonValidationErrors('user_password');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Before']);
        $this->assertDatabaseMissing('users', ['employee_id' => $employee->id]);
    }

    public function test_employee_designation_must_exist(): void
    {
        $this->postJson('/api/hrm/employees', ['name' => 'New Employee', 'designation' => 999999, 'create_user' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('designation');
        $this->assertDatabaseMissing('employees', ['name' => 'New Employee']);
    }

    public function test_employee_list_includes_login_and_filters_disabled_access(): void
    {
        $employee = Employee::create(['code' => 'E-DISABLED', 'name' => 'Disabled Login', 'status' => 'Active']);
        $user = User::create(['code' => 'U-DISABLED', 'employee_id' => $employee->id, 'name' => $employee->name, 'email' => 'disabled@example.test', 'password' => bcrypt('Strong-password-123'), 'status' => 'Inactive']);
        $this->getJson('/api/hrm/employees?filter[system_access]=Disabled')->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.user.id', $user->id)->assertJsonPath('data.data.0.user.status', 'Inactive');
    }

    public function test_roles_store_native_permission_links_without_spatie(): void
    {
        $permission = $this->permission('support.ticket.assign');
        $response = $this->postJson('/api/admin/roles', ['name' => 'Support Manager', 'is_active' => true, 'permissions' => [$permission->id]])
            ->assertCreated()->assertJsonPath('data.permissions.0.id', $permission->id);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $response->json('data.id'), 'permission_id' => $permission->id]);
    }

    public function test_role_assignment_requires_its_own_permission(): void
    {
        $role = Role::create(['name' => 'Support Agent', 'is_active' => true]);
        $target = User::create(['code' => 'U-TARGET', 'name' => 'Target', 'email' => 'target@example.test', 'password' => bcrypt('Strong-password-123'), 'status' => 'Active']);
        $this->putJson('/api/admin/users/'.$target->id, ['name' => 'Changed', 'email' => $target->email, 'status' => 'Active', 'role_ids' => [$role->id]])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Target']);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $target->id]);
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'Assigned Role', 'is_active' => true]);
        UserRole::create(['user_id' => auth()->id(), 'role_id' => $role->id]);
        $this->deleteJson('/api/admin/roles/'.$role->id)->assertUnprocessable();
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('user_roles', ['role_id' => $role->id]);
    }

    public function test_direct_permission_sync_can_revoke_all_without_missing_trait_methods(): void
    {
        $permission = $this->permission('meeting.report.view');
        $target = User::create(['code' => 'U-PERM', 'name' => 'Report User', 'email' => 'report@example.test', 'password' => bcrypt('Strong-password-123'), 'status' => 'Active']);
        $this->postJson('/api/user/assign-permission', ['user_id' => $target->id, 'permissions' => [$permission->id]])->assertOk();
        $this->assertDatabaseHas('user_permissions', ['user_id' => $target->id, 'permission_id' => $permission->id]);
        $this->postJson('/api/user/assign-permission', ['user_id' => $target->id, 'permissions' => []])->assertOk();
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $target->id]);
    }

    private function permission(string $key): Permission
    {
        $module = Module::firstOrCreate(['slug' => 'test-module'], ['code' => 'M-TEST', 'name' => 'Test Module', 'is_active' => true]);
        return Permission::create(['name' => $key, 'key' => $key, 'module_id' => $module->id, 'is_active' => true]);
    }
}
