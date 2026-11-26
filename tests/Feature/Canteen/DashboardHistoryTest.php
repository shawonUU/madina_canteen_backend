<?php

namespace Tests\Feature\Canteen;

use App\Services\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\UserPermission;
use Modules\Canteen\Models\EmployeeMeal;
use Modules\Canteen\Models\MealType;
use Modules\HRM\Models\Employee;
use Tests\TestCase;

class DashboardHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;
    private User $creator;
    private EmployeeMeal $ownBooking;
    private EmployeeMeal $ownCancelledBooking;
    private EmployeeMeal $otherBooking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenuSeeder::class);

        $employee = Employee::create(['code' => 'E-DASH-OWN', 'name' => 'Dashboard Employee', 'status' => 'Active']);
        $otherEmployee = Employee::create(['code' => 'E-DASH-OTHER', 'name' => 'Other Employee', 'status' => 'Active']);
        $this->viewer = User::create(['code' => 'U-DASH-VIEWER', 'name' => 'Dashboard Viewer', 'email' => 'dashboard-viewer@example.test',
            'employee_id' => $employee->id, 'password' => bcrypt('Test-password-123'), 'status' => 'Active']);
        $this->creator = User::create(['code' => 'U-DASH-CREATOR', 'name' => 'Original Creator', 'email' => 'dashboard-creator@example.test',
            'employee_id' => $otherEmployee->id, 'password' => bcrypt('Test-password-123'), 'status' => 'Active']);

        $lunch = MealType::create(['code' => 'MT-DASH-LUNCH', 'name' => 'Lunch', 'slug' => 'dashboard-lunch', 'booking_cutoff_time' => '12:00', 'meal_rate' => 50, 'status' => 'Active']);
        $dinner = MealType::create(['code' => 'MT-DASH-DINNER', 'name' => 'Dinner', 'slug' => 'dashboard-dinner', 'booking_cutoff_time' => '17:00', 'meal_rate' => 70, 'status' => 'Active']);

        // A different actor creates every booking so creator access cannot mask an ownership error.
        Sanctum::actingAs($this->creator);
        $this->ownBooking = $this->booking('EM-DASH-OWN', $employee->id, $lunch->id, 'Selected', 50);
        $this->ownCancelledBooking = $this->booking('EM-DASH-CANCELLED', $employee->id, $dinner->id, 'Cancelled', 70);
        $this->otherBooking = $this->booking('EM-DASH-OTHER', $otherEmployee->id, $lunch->id, 'Served', 80);
        $this->booking('EM-DASH-YESTERDAY', $employee->id, $lunch->id, 'Served', 100, today()->subDay()->toDateString());
        Sanctum::actingAs($this->viewer);
    }

    public function test_ordinary_employee_dashboard_and_history_are_limited_to_own_bookings(): void
    {
        $response = $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.summary.total_bookings', 2)
            ->assertJsonPath('data.summary.served', 0)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.cancelled', 1)
            ->assertJsonPath('data.summary.total_employees', 1)
            ->assertJsonCount(2, 'data.recent_bookings');

        $this->assertEquals(50, $response->json('data.summary.total_amount'));
        $this->assertEqualsCanonicalizing([$this->ownBooking->id, $this->ownCancelledBooking->id], array_column($response->json('data.recent_bookings'), 'id'));
        $this->assertEquals(2, array_sum(array_column($response->json('data.meal_statistics'), 'total')));
        $this->assertEquals(50, array_sum(array_column($response->json('data.meal_statistics'), 'amount')));
        $this->getJson('/api/record-logs/employee_meals/'.$this->ownBooking->id)->assertOk()
            ->assertJsonPath('data.data.0.record_id', (string) $this->ownBooking->id)
            ->assertJsonPath('data.data.0.new_values.employee_id', $this->viewer->employee_id);
        $this->getJson('/api/record-logs/employee_meals/'.$this->otherBooking->id)->assertForbidden();
    }

    public function test_dashboard_view_all_grant_includes_other_employees_and_their_history(): void
    {
        $this->grantPermission('dashboard.view-all');

        $response = $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.summary.total_bookings', 3)
            ->assertJsonPath('data.summary.served', 1)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.cancelled', 1)
            ->assertJsonPath('data.summary.total_employees', 2)
            ->assertJsonCount(3, 'data.recent_bookings');

        $this->assertEquals(130, $response->json('data.summary.total_amount'));
        $this->assertEqualsCanonicalizing([$this->ownBooking->id, $this->ownCancelledBooking->id, $this->otherBooking->id], array_column($response->json('data.recent_bookings'), 'id'));
        $this->assertEquals(3, array_sum(array_column($response->json('data.meal_statistics'), 'total')));
        $this->assertEquals(130, array_sum(array_column($response->json('data.meal_statistics'), 'amount')));
        $this->getJson('/api/record-logs/employee_meals/'.$this->ownBooking->id)->assertOk();
        $this->getJson('/api/record-logs/employee_meals/'.$this->otherBooking->id)->assertOk()
            ->assertJsonPath('data.data.0.record_id', (string) $this->otherBooking->id);
    }

    public function test_on_behalf_manager_with_booking_view_can_read_another_creators_booking_history(): void
    {
        $context = app(AccessService::class)->context(config('access.resources')['meal_booking']);
        UserAccess::create(['user_id' => $this->viewer->id] + $context + ['can_view' => true]);
        $this->assertSame($this->creator->id, $this->otherBooking->fresh()->created_by);
        $this->getJson('/api/record-logs/employee_meals/'.$this->otherBooking->id)->assertForbidden();

        $this->grantPermission('meal_booking.manage-on-behalf');
        $this->getJson('/api/record-logs/employee_meals/'.$this->otherBooking->id)->assertOk()
            ->assertJsonPath('data.data.0.record_id', (string) $this->otherBooking->id)
            ->assertJsonPath('data.data.0.new_values.created_by', $this->creator->id);
    }

    private function grantPermission(string $key): void
    {
        $permission = Permission::where('key', $key)->firstOrFail();
        UserPermission::create(['user_id' => $this->viewer->id, 'permission_id' => $permission->id]);
        app(AccessService::class)->reset();
    }

    private function booking(string $code, int $employeeId, int $mealTypeId, string $status, int $amount, ?string $date = null): EmployeeMeal
    {
        return EmployeeMeal::create(['code' => $code, 'employee_id' => $employeeId, 'meal_type_id' => $mealTypeId,
            'meal_date' => $date ?? today()->toDateString(), 'meal_rate' => $amount, 'quantity' => 1, 'total_amount' => $amount, 'status' => $status]);
    }
}
