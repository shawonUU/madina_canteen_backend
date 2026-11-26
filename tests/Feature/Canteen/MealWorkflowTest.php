<?php

namespace Tests\Feature\Canteen;

use App\Http\Middleware\EnforceApiAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Models\User;
use Modules\Canteen\Models\EmployeeMeal;
use Modules\Canteen\Models\EmployeeMealItem;
use Modules\Canteen\Models\MealMenu;
use Modules\Canteen\Models\MealType;
use Modules\Canteen\Models\MealRate;
use Modules\Canteen\Services\MealRateService;
use Modules\Canteen\Models\MenuItem;
use Modules\HRM\Models\Employee;
use Tests\TestCase;

class MealWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;
    private MealType $mealType;

    protected function setUp(): void
    {
        parent::setUp();
        // Access grants have separate integration coverage; these tests exercise domain invariants.
        $this->withoutMiddleware(EnforceApiAccess::class);
        $this->employee = Employee::create(['code' => 'E-TEST', 'name' => 'Test Employee', 'status' => 'Active']);
        $user = User::create(['code' => 'U-TEST', 'employee_id' => $this->employee->id, 'name' => 'Test User', 'email' => 'canteen@example.test', 'password' => bcrypt('Test-password-123'), 'status' => 'Active']);
        Sanctum::actingAs($user);
        $this->mealType = MealType::create(['code' => 'MT-TEST', 'name' => 'Test Lunch', 'slug' => 'test-lunch', 'booking_cutoff_time' => '12:00', 'meal_rate' => 50, 'status' => 'Active']);
    }

    public function test_creating_meal_type_supplies_slug_and_effective_rate(): void
    {
        $this->postJson('/api/meal-types', ['name' => 'Dinner', 'booking_cutoff_time' => '17:00', 'meal_rate' => 75, 'status' => 'Active'])
            ->assertCreated()->assertJsonPath('data.slug', 'dinner');
        $this->assertDatabaseHas('meal_rates', ['rate' => 75, 'effective_date' => now()->toDateString()]);
    }

    public function test_cancelled_booking_can_be_rebooked_without_duplicate_unique_key(): void
    {
        $booking = $this->booking('Cancelled');
        $this->postJson('/api/booking-meal', $this->payload())->assertCreated()->assertJsonPath('data.id', $booking->id);
        $this->assertDatabaseCount('employee_meals', 1);
        $this->assertDatabaseHas('employee_meals', ['id' => $booking->id, 'status' => 'Selected']);
    }

    public function test_pending_menu_cannot_be_selected_for_a_booking(): void
    {
        $menu = MealMenu::create(['code' => 'MM-PENDING', 'meal_type_id' => $this->mealType->id, 'menu_date' => now()->toDateString(), 'status' => 'Pending']);
        $this->postJson('/api/booking-meal', $this->payload() + ['menu_id' => $menu->id])->assertUnprocessable();
        $this->assertDatabaseCount('employee_meals', 0);
    }

    public function test_deleting_a_menu_never_deletes_existing_bookings(): void
    {
        $menu = MealMenu::create(['code' => 'MM-USED', 'meal_type_id' => $this->mealType->id, 'menu_date' => now()->toDateString(), 'status' => 'Approved']);
        $booking = $this->booking('Served');
        $booking->update(['menu_id' => $menu->id]);
        $this->deleteJson('/api/meal-menus/'.$menu->id)->assertUnprocessable();
        $this->assertDatabaseHas('employee_meals', ['id' => $booking->id, 'status' => 'Served']);
        $this->assertDatabaseHas('meal_menus', ['id' => $menu->id]);
    }

    public function test_reports_use_real_item_name_and_exclude_cancelled_charges(): void
    {
        $menu = MealMenu::create(['code' => 'MM-REPORT', 'meal_type_id' => $this->mealType->id, 'menu_date' => now()->toDateString(), 'status' => 'Approved']);
        $item = MenuItem::create(['code' => 'MI-REPORT', 'menu_id' => $menu->id, 'name' => 'Rice', 'item_type' => 'Main']);
        $booking = $this->booking('Served');
        $booking->update(['menu_id' => $menu->id]);
        EmployeeMealItem::create(['code' => 'EMI-REPORT', 'employee_meal_id' => $booking->id, 'menu_item_id' => $item->id]);
        $this->getJson('/api/meal-reports/item-consumption')->assertOk()->assertJsonPath('data.0.item_name', 'Rice');
        $booking->update(['status' => 'Cancelled']);
        $response = $this->getJson('/api/meal-reports/daily')->assertOk();
        $this->assertEquals(0, $response->json('summary.total_amount'));
        $this->getJson('/api/meal-reports/item-consumption')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cancelling_someone_elses_booking_is_denied(): void
    {
        $other = Employee::create(['code' => 'E-OTHER', 'name' => 'Other Employee', 'status' => 'Active']);
        $booking = $this->booking('Selected');
        $booking->update(['employee_id' => $other->id]);
        $this->putJson('/api/meal-bookings/'.$booking->id.'/cancel')->assertNotFound();
        $this->assertDatabaseHas('employee_meals', ['id' => $booking->id, 'status' => 'Selected']);
    }

    public function test_rate_change_preserves_earlier_and_future_effective_schedules(): void
    {
        $earlier = today()->subDays(10)->toDateString();
        $future = today()->addDays(10)->toDateString();
        MealRate::create(['code' => 'MR-EARLIER', 'meal_type_id' => $this->mealType->id, 'rate' => 50, 'effective_date' => $earlier, 'status' => 'Active']);
        MealRate::create(['code' => 'MR-FUTURE', 'meal_type_id' => $this->mealType->id, 'rate' => 90, 'effective_date' => $future, 'status' => 'Active']);

        $this->putJson('/api/meal-types/'.$this->mealType->id, ['meal_rate' => 75])->assertOk();
        $type = $this->mealType->fresh();
        $rates = app(MealRateService::class);
        $this->assertSame(50.0, $rates->forDate($type, $earlier));
        $this->assertSame(75.0, $rates->forDate($type, today()->toDateString()));
        $this->assertSame(90.0, $rates->forDate($type, $future));
    }

    private function payload(): array
    {
        return ['employee_id' => $this->employee->id, 'meal_type_id' => $this->mealType->id, 'booking_date' => now()->toDateString(), 'items' => []];
    }

    private function booking(string $status): EmployeeMeal
    {
        return EmployeeMeal::create(['code' => 'EM-TEST', 'employee_id' => $this->employee->id, 'meal_type_id' => $this->mealType->id,
            'meal_date' => now()->toDateString(), 'meal_rate' => 50, 'quantity' => 1, 'total_amount' => 50, 'status' => $status]);
    }
}
