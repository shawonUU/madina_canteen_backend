<?php

namespace Tests\Feature;

use App\Models\RecordLog;
use App\Services\RecordCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\Role;
use Modules\Admin\Models\RolePermission;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\UserPermission;
use Modules\Admin\Models\UserRole;
use Modules\HRM\Models\Department;
use Tests\TestCase;

class SharedAccessAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenuSeeder::class);
        $this->operator = User::create([
            'code' => 'U-AUDIT-TEST', 'name' => 'Audit Operator',
            'email' => 'audit-operator@example.test', 'password' => 'Test-password-123', 'status' => 'Active',
        ]);
    }

    public function test_admin_api_requires_login_and_explicit_access(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
        Sanctum::actingAs($this->operator);
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->grant('users.view');
        $this->getJson('/api/admin/users')->assertOk();
    }

    public function test_login_returns_permissions_without_password_and_rejects_inactive_accounts(): void
    {
        $this->grant('department.view');
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->operator->email, 'password' => 'Test-password-123',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name'], 'accesses', 'permissions']);
        $this->assertNotEmpty($response->json('token'));
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertContains('department.view', $response->json('permissions'));
        $this->postJson('/api/auth/login', ['email' => $this->operator->email, 'password' => 'wrong-password'])
            ->assertUnauthorized()->assertJsonPath('message', 'Invalid email or password.');
        $this->operator->update(['status' => 'Inactive']);
        $this->postJson('/api/auth/login', ['email' => $this->operator->email, 'password' => 'Test-password-123'])
            ->assertForbidden();
    }

    public function test_preflight_accepts_configured_frontend_origin_and_bearer_headers(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/auth/login')->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_reseeding_preserves_navigation_ids_and_existing_permission_assignments(): void
    {
        $grant = $this->grant('department.view');
        $before = $this->navigationIds();
        $permissionCount = Permission::count();
        $this->seed(MenuSeeder::class);
        $this->assertSame($before, $this->navigationIds());
        $this->assertSame($permissionCount, Permission::count());
        $this->assertDatabaseHas('user_permissions', ['id' => $grant->id, 'user_id' => $this->operator->id, 'permission_id' => $grant->permission_id]);
    }

    public function test_record_lifecycle_keeps_actor_and_before_after_values_in_deleted_record_history(): void
    {
        Sanctum::actingAs($this->operator);
        foreach (['department.view', 'department.create', 'department.update', 'department.delete'] as $key) $this->grant($key);
        $id = $this->postJson('/api/hrm/departments', ['name' => 'Before name', 'description' => 'Before detail', 'status' => 'Active'])
            ->assertCreated()->json('data.id');
        $created = Department::findOrFail($id);
        $this->assertSame($this->operator->id, (int) $created->created_by);

        $editor = User::create(['code' => 'U-EDITOR-TEST', 'name' => 'Second Operator', 'email' => 'second-operator@example.test', 'password' => 'Test-password-123', 'status' => 'Active']);
        foreach (['department.view', 'department.update', 'department.delete'] as $key) $this->grant($key, $editor);
        Sanctum::actingAs($editor);
        $this->putJson('/api/hrm/departments/'.$id, ['name' => 'After name', 'description' => 'After detail', 'status' => 'Active'])
            ->assertOk();
        $updated = $created->fresh();
        $this->assertSame($this->operator->id, (int) $updated->created_by);
        $this->assertSame($editor->id, (int) $updated->updated_by);
        $log = RecordLog::where('resource', 'departments')->where('record_id', $id)->where('event', 'updated')->sole();
        $this->assertSame('Before name', $log->old_values['name']);
        $this->assertSame('After name', $log->new_values['name']);
        $this->assertSame($editor->id, (int) $log->actor_id);
        $this->assertSame('Second Operator', $log->actor_name);
        $this->assertContains('description', $log->changed_fields);

        $this->deleteJson('/api/hrm/departments/'.$id)->assertOk();
        $this->assertDatabaseMissing('departments', ['id' => $id]);
        $this->getJson('/api/record-logs/departments/'.$id)->assertOk()
            ->assertJsonPath('data.total', 3)->assertJsonPath('data.data.0.event', 'deleted')
            ->assertJsonPath('data.data.0.old_values.name', 'After name');
        $this->getJson('/api/record-logs')->assertForbidden();
        $this->grant('audit.view-all', $editor);
        $this->getJson('/api/record-logs?resource=departments&event=deleted&actor=Second')->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_failed_api_write_rolls_back_the_business_record_and_its_log(): void
    {
        Sanctum::actingAs($this->operator);
        Route::middleware(['api', 'auth:sanctum'])->post('/api/test-audit-rollback', function () {
            Department::create(['code' => 'D-ROLLBACK', 'name' => 'Must roll back', 'status' => 'Active']);
            return response()->json(['message' => 'A later business check failed.'], 422);
        });
        $before = RecordLog::where('resource', 'departments')->count();
        $this->postJson('/api/test-audit-rollback')->assertUnprocessable();
        $this->assertDatabaseMissing('departments', ['code' => 'D-ROLLBACK']);
        $this->assertSame($before, RecordLog::where('resource', 'departments')->count());
    }

    public function test_password_history_redacts_both_values(): void
    {
        Sanctum::actingAs($this->operator);
        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Test-password-123',
            'new_password' => 'Changed-password-456', 'new_password_confirmation' => 'Changed-password-456',
        ])->assertOk();
        $this->assertTrue(Hash::check('Changed-password-456', $this->operator->fresh()->password));
        $log = RecordLog::where('resource', 'users')->where('record_id', $this->operator->id)->where('event', 'updated')->sole();
        $this->assertSame('[REDACTED]', $log->old_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
    }

    public function test_role_grants_refresh_between_requests_and_provide_navigation(): void
    {
        Sanctum::actingAs($this->operator);
        $this->getJson('/api/hrm/departments')->assertForbidden();
        $role = Role::create(['name' => 'Department Reader', 'is_active' => true]);
        RolePermission::create(['role_id' => $role->id, 'permission_id' => Permission::where('key', 'department.view')->sole()->id]);
        UserRole::create(['user_id' => $this->operator->id, 'role_id' => $role->id]);
        $this->getJson('/api/hrm/departments')->assertOk();
        $response = $this->getJson('/api/auth/navigation')->assertOk()->assertJsonPath('success', true);
        $this->assertContains('department.view', $response->json('permissions'));
        $this->assertTrue(collect($response->json('accesses'))->contains(fn ($row) => $row['child_menu_slug'] === 'department-list' && $row['can_view']));
        $role->update(['is_active' => false]);
        $this->getJson('/api/hrm/departments')->assertForbidden();
    }

    public function test_activity_history_menu_view_and_explicit_permission_resolve_to_the_same_capability(): void
    {
        Sanctum::actingAs($this->operator);
        $child = ChildMenu::where('slug', 'activity-log')->sole();
        $menu = Menu::findOrFail($child->menu_id);
        $grant = UserAccess::create([
            'user_id' => $this->operator->id, 'module_id' => $menu->module_id, 'menu_id' => $menu->id,
            'child_menu_id' => $child->id, 'can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false,
        ]);
        $this->getJson('/api/record-logs')->assertOk();
        $grant->delete();
        $this->getJson('/api/record-logs')->assertForbidden();
        $this->grant('audit.view-all');
        $this->getJson('/api/record-logs')->assertOk();
        $navigation = $this->getJson('/api/auth/navigation')->assertOk();
        $this->assertTrue(collect($navigation->json('accesses'))->contains(fn ($row) => $row['child_menu_slug'] === 'activity-log' && $row['can_view']));
    }

    public function test_code_sequence_starts_after_existing_numeric_codes_and_rolls_back_with_write(): void
    {
        Department::create(['code' => 'DEP-19', 'name' => 'Imported department', 'status' => 'Active']);
        Department::create(['code' => 'DEP-CUSTOM', 'name' => 'Custom code', 'status' => 'Active']);
        $service = app(RecordCodeService::class);
        $this->assertSame('DEP-20', $service->next(Department::class, 'code', 'DEP'));
        DB::beginTransaction();
        $this->assertSame('DEP-21', $service->next(Department::class, 'code', 'DEP'));
        DB::rollBack();
        $this->assertSame('DEP-21', $service->next(Department::class, 'code', 'DEP'));
        $this->assertSame('DEP-22', $service->next(Department::class, 'code', 'DEP'));
    }

    private function grant(string $key, ?User $user = null): UserPermission
    {
        return UserPermission::firstOrCreate([
            'user_id' => ($user ?? $this->operator)->id,
            'permission_id' => Permission::where('key', $key)->sole()->id,
        ]);
    }

    private function navigationIds(): array
    {
        return [Module::orderBy('id')->pluck('slug', 'id')->all(), Menu::orderBy('id')->pluck('slug', 'id')->all(), ChildMenu::orderBy('id')->pluck('slug', 'id')->all()];
    }
}
