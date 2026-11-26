<?php

namespace Tests\Feature\Reception;

use App\Http\Middleware\EnforceApiAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Models\User;
use Modules\Reception\Models\GatePass;
use Modules\Reception\Models\GatePassPurpose;
use Tests\TestCase;

class GatePassTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private GatePassPurpose $purpose;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnforceApiAccess::class);
        $this->user = User::create(['code' => 'U-RECEPTION', 'name' => 'Requester', 'email' => 'reception@example.test', 'password' => bcrypt('Test-password-123'), 'status' => 'Active']);
        Sanctum::actingAs($this->user);
        $this->purpose = GatePassPurpose::create(['code' => 'PURPOSE-TEST', 'name' => 'Official visit', 'is_active' => true, 'approval_status' => 'Approved']);
    }

    public function test_purpose_filters_and_pagination_are_applied_by_the_server(): void
    {
        GatePassPurpose::create(['code' => 'PURPOSE-INACTIVE', 'name' => 'Inactive purpose', 'is_active' => false, 'approval_status' => 'Approved']);
        GatePassPurpose::create(['code' => 'PURPOSE-PENDING', 'name' => 'Pending purpose', 'is_active' => true, 'approval_status' => 'Pending']);
        $this->getJson('/api/reception/gate-pass-purposes?is_active=1&approval_status=Approved&per_page=1')
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.name', 'Official visit');
    }

    public function test_material_gate_pass_returns_code_and_filter_meta_and_deletes_children_atomically(): void
    {
        $response = $this->postJson('/api/reception/gate-passes', $this->payload())->assertCreated();
        $id = $response->json('data.id');
        $code = $response->json('data.code');
        $this->getJson('/api/reception/gate-passes?per_page=1&filter[code]='.urlencode($code))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.code', $code);
        $this->deleteJson('/api/reception/gate-passes/'.$id)->assertOk();
        $this->assertDatabaseMissing('gate_pass_items', ['gate_pass_id' => $id]);
        $this->assertDatabaseMissing('gate_passes', ['id' => $id]);
    }

    public function test_edit_preserves_original_requester(): void
    {
        $response = $this->postJson('/api/reception/gate-passes', $this->payload())->assertCreated();
        $editor = User::create(['code' => 'U-EDITOR', 'name' => 'Editor', 'email' => 'editor@example.test', 'password' => bcrypt('Test-password-123'), 'status' => 'Active']);
        Sanctum::actingAs($editor);
        $payload = $this->payload();
        $payload['remarks'] = 'Updated destination';
        $this->putJson('/api/reception/gate-passes/'.$response->json('data.id'), $payload)->assertOk();
        $this->assertDatabaseHas('gate_passes', ['id' => $response->json('data.id'), 'requested_by' => $this->user->id, 'remarks' => 'Updated destination']);
    }

    public function test_unapproved_purpose_cannot_be_used(): void
    {
        $this->purpose->update(['approval_status' => 'Pending']);
        $this->postJson('/api/reception/gate-passes', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('gate_passes', 0);
    }

    private function payload(): array
    {
        return ['gate_pass_type' => 'Person With Material', 'department_name' => 'Administration', 'designation_name' => 'Officer',
            'purpose_id' => $this->purpose->id, 'expected_exit_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'expected_return_at' => now()->addHours(2)->format('Y-m-d H:i:s'), 'remarks' => 'Office visit',
            'items' => [['product_name' => 'Laptop', 'quantity' => 1, 'unit' => 'Pcs', 'serial_no' => 'TEST-123']]];
    }
}
