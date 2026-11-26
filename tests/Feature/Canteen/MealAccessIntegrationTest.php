<?php

namespace Tests\Feature\Canteen;

use App\Services\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\UserPermission;
use Modules\Canteen\Models\EmployeeMeal;
use Modules\Canteen\Models\MealMenu;
use Modules\Canteen\Models\MealType;
use Modules\Canteen\Models\MenuItem;
use Modules\HRM\Models\Employee;
use Tests\TestCase;

class MealAccessIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MealType $mealType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenuSeeder::class);
        $employee = Employee::create(['code' => 'E-ACCESS', 'name' => 'Booking User', 'status' => 'Active']);
        $this->user = User::create(['code' => 'U-ACCESS', 'employee_id' => $employee->id, 'name' => 'Booking User', 'email' => 'access-canteen@example.test', 'password' => bcrypt('Test-password-123'), 'status' => 'Active']);
        $this->mealType = MealType::create(['code' => 'MT-ACCESS', 'name' => 'Lunch', 'slug' => 'lunch', 'booking_cutoff_time' => '12:00', 'meal_rate' => 50, 'status' => 'Active']);
        Sanctum::actingAs($this->user);
    }

    public function test_booking_view_access_loads_approved_choices_without_menu_administration_access(): void
    {
        $this->grant('meal_booking');
        $approved = MealMenu::create(['code' => 'MM-APPROVED', 'meal_type_id' => $this->mealType->id, 'menu_date' => today()->toDateString(), 'status' => 'Approved']);
        MenuItem::create(['code' => 'MI-APPROVED', 'menu_id' => $approved->id, 'name' => 'Rice', 'item_type' => 'Main']);
        MealMenu::create(['code' => 'MM-PENDING', 'meal_type_id' => $this->mealType->id, 'menu_date' => today()->addDay()->toDateString(), 'status' => 'Pending']);

        $response = $this->getJson('/api/booking-menus')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id)
            ->assertJsonPath('data.0.items.0.name', 'Rice');
        $this->assertArrayNotHasKey('created_by', $response->json('data.0'));
        $this->getJson('/api/meal-types')->assertOk();
        $this->getJson('/api/my-booked-meals')->assertOk();
        $this->getJson('/api/meal-menus')->assertForbidden();
        $this->getJson('/api/todays-booked-meals')->assertForbidden();
    }

    public function test_serving_view_access_loads_bookings_and_history_without_booking_admin_access(): void
    {
        $this->grant('meal_serve');
        $booking = $this->booking();
        $this->getJson('/api/todays-booked-meals')->assertOk()->assertJsonPath('data.0.id', $booking->id);
        $this->getJson('/api/record-logs/employee_meals/'.$booking->id)->assertOk();
        $this->getJson('/api/meal-menus')->assertForbidden();
        $this->getJson('/api/my-booked-meals')->assertForbidden();
    }

    public function test_cost_report_view_access_matches_cost_action_and_record_history(): void
    {
        $this->grant('meal_report.cost');
        $booking = $this->booking();
        $this->getJson('/api/meal-reports/cost')->assertOk()->assertJsonPath('data.0.id', $booking->id);
        $this->getJson('/api/record-logs/employee_meals/'.$booking->id)->assertOk();
        $this->getJson('/api/meal-reports/employee-bookings')->assertForbidden();
        $this->getJson('/api/meal-reports/daily')->assertForbidden();
        $this->getJson('/api/meal-menus')->assertForbidden();
    }

    public function test_self_booking_access_cannot_create_or_edit_other_employees_bookings(): void
    {
        $this->grant('meal_booking', true);
        $other = Employee::create(['code' => 'E-OTHER', 'name' => 'Other Employee', 'status' => 'Active']);
        $payload = ['employee_id' => $other->id, 'meal_type_id' => $this->mealType->id, 'booking_date' => today()->toDateString(), 'items' => []];
        $this->postJson('/api/booking-meal', $payload)->assertForbidden();
        $this->postJson('/api/booking-meals', [
            'employee_id' => $other->id, 'meal_type_id' => $this->mealType->id,
            'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(),
            'bookings' => [['booking_date' => today()->toDateString(), 'items' => []]],
        ])->assertForbidden();
        $booking = $this->booking();
        $this->putJson('/api/booking-meal/'.$booking->id, $payload)->assertForbidden();
        $booking->update(['employee_id' => $other->id]);
        $payload['employee_id'] = $this->user->employee_id;
        $this->putJson('/api/booking-meal/'.$booking->id, $payload)->assertNotFound();
        $this->putJson('/api/meal-bookings/'.$booking->id.'/cancel')->assertNotFound();
        $this->assertDatabaseHas('employee_meals', ['id' => $booking->id, 'employee_id' => $other->id, 'status' => 'Selected']);
    }

    public function test_explicit_on_behalf_permission_preserves_manager_single_and_bulk_booking(): void
    {
        $this->grant('meal_booking', true);
        $permission = Permission::firstOrCreate(['key' => 'meal_booking.manage-on-behalf'], ['name' => 'Manage bookings for other employees', 'is_active' => true]);
        UserPermission::create(['user_id' => $this->user->id, 'permission_id' => $permission->id]);
        app(AccessService::class)->reset();
        $other = Employee::create(['code' => 'E-MANAGED', 'name' => 'Managed Employee', 'status' => 'Active']);
        $payload = ['employee_id' => $other->id, 'meal_type_id' => $this->mealType->id, 'booking_date' => today()->toDateString(), 'items' => []];
        $response = $this->postJson('/api/booking-meal', $payload)->assertCreated()->assertJsonPath('data.employee_id', $other->id);
        $id = $response->json('data.id');
        $this->putJson('/api/booking-meal/'.$id, $payload)->assertOk();
        $this->putJson('/api/meal-bookings/'.$id.'/cancel')->assertOk();
        $tomorrow = today()->addDay()->toDateString();
        $this->postJson('/api/booking-meals', [
            'employee_id' => $other->id, 'meal_type_id' => $this->mealType->id,
            'start_date' => $tomorrow, 'end_date' => $tomorrow,
            'bookings' => [['booking_date' => $tomorrow, 'items' => []]],
        ])->assertCreated()->assertJsonPath('data.0.employee_id', $other->id);
    }

    private function grant(string $resource, bool $manage = false): void
    {
        $context = app(AccessService::class)->context(config('access.resources')[$resource]);
        UserAccess::create(['user_id' => $this->user->id] + $context + ['can_view' => true, 'can_create' => $manage, 'can_update' => $manage, 'can_delete' => $manage]);
    }

    private function booking(): EmployeeMeal
    {
        return EmployeeMeal::create(['code' => 'EM-ACCESS', 'employee_id' => $this->user->employee_id, 'meal_type_id' => $this->mealType->id,
            'meal_date' => today()->toDateString(), 'meal_rate' => 50, 'quantity' => 1, 'total_amount' => 50, 'status' => 'Selected']);
    }
}
