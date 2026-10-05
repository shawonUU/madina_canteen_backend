<?php
namespace Modules\Reception\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Services\ApprovalService;
use Modules\Reception\Models\GatePass;
use Modules\Reception\Models\GatePassItem;

class GatePassController extends Controller
{
    public function index(Request $request)
    {
        $query = GatePass::with([
            'requester:id,name',
            'items',
            'approvalRequest.levels' => function ($query) {
                $query->where('status', 'Pending');
            },
            'approvalRequest.levels.approver:id,name',
        ])->latest();

        if ($request->filled('gate_pass_type')) {
            $query->where(
                'gate_pass_type',
                $request->gate_pass_type
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        $gatePasses = $query->get();

        $gatePasses->transform(function ($gatePass) {

            $pendingLevel = $gatePass->approvalRequest
                ?->levels
                ->firstWhere('status', 'Pending');

            $gatePass->pending_approval = $pendingLevel
                ? [
                    'level' => $pendingLevel->level_no,
                    'approver' => $pendingLevel->approver?->name,
                ]
                : null;

            return $gatePass;
        });

        return response()->json([
            'success' => true,
            'data' => $gatePasses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gate_pass_type' => [
                'required',
                'in:Person,Person With Material',
            ],

            'department_name' => [
                'required',
                'string',
                'max:255',
            ],

            'designation_name' => [
                'required',
                'string',
                'max:255',
            ],

            'purpose' => [
                'required',
                'string',
                'max:500',
            ],

            'expected_exit_at' => [
                'required',
                'date',
            ],

            'expected_return_at' => [
                'nullable',
                'date',
                'after_or_equal:expected_exit_at',
            ],

            'remarks' => [
                'nullable',
                'string',
            ],

            'items' => [
                'nullable',
                'array',
            ],

            'items.*.product_id' => [
                'nullable',
                'exists:products,id',
            ],

            'items.*.product_name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'items.*.unit' => [
                'nullable',
                'string',
                'max:50',
            ],

            'items.*.asset_no' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.serial_no' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.remarks' => [
                'nullable',
                'string',
            ],
        ]);

        if (
            in_array(
                $validated['gate_pass_type'],
                ['material', 'Person With Material']
            ) &&
            empty($validated['items'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'At least one material item is required.',
            ], 422);
        }

        if (
            $validated['gate_pass_type'] === 'Person' &&
            !empty($validated['items'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Person gate pass cannot contain material items.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $gatePass = GatePass::create([
                'code' => getGenerateCode(GatePass::class, 'code', 'GPT', 8),
                'pass_no' => $this->generatePassNo(),

                'gate_pass_type' =>
                    $validated['gate_pass_type'],

                'requested_by' => auth()->id(),

                'department_name' =>
                    $validated['department_name'],

                'designation_name' =>
                    $validated['designation_name'],

                'purpose' =>
                    $validated['purpose'],

                'expected_exit_at' =>
                    $validated['expected_exit_at'],

                'expected_return_at' =>
                    $validated['expected_return_at'] ?? null,

                'remarks' =>
                    $validated['remarks'] ?? null,

                'status' => 'pending',
            ]);

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $gatePass->items()->create([
                        'code' => getGenerateCode(GatePassItem::class, 'code', 'GPI', 8),
                        'product_id' =>
                            $item['product_id'] ?? null,

                        'product_name' =>
                            $item['product_name'],

                        'quantity' =>
                            $item['quantity'],

                        'unit' =>
                            $item['unit'] ?? null,

                        'asset_no' =>
                            $item['asset_no'] ?? null,

                        'serial_no' =>
                            $item['serial_no'] ?? null,

                        'remarks' =>
                            $item['remarks'] ?? null,
                    ]);
                }
            }

            app(ApprovalService::class)->submit($gatePass);
            DB::commit();

            $gatePass->load([
                'requester:id,name',
                'items',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gate pass created successfully.',
                'data' => $gatePass,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create gate pass.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(GatePass $gatePass)
    {
        $gatePass->load([
            'requester:id,name',
            'items',
        ]);

        return response()->json([
            'success' => true,
            'data' => $gatePass,
        ]);
    }

    public function update(Request $request, GatePass $gatePass)
    {
        $validated = $request->validate([
            'gate_pass_type' => [
                'required',
                'in:Person,Person With Material',
            ],

            'department_name' => [
                'required',
                'string',
                'max:255',
            ],

            'designation_name' => [
                'required',
                'string',
                'max:255',
            ],

            'purpose' => [
                'required',
                'string',
                'max:500',
            ],

            'expected_exit_at' => [
                'required',
                'date',
            ],

            'expected_return_at' => [
                'nullable',
                'date',
                'after_or_equal:expected_exit_at',
            ],

            'remarks' => [
                'nullable',
                'string',
            ],

            'items' => [
                'nullable',
                'array',
            ],

            'items.*.product_id' => [
                'nullable',
                'exists:products,id',
            ],

            'items.*.product_name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'items.*.unit' => [
                'nullable',
                'string',
                'max:50',
            ],

            'items.*.asset_no' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.serial_no' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.remarks' => [
                'nullable',
                'string',
            ],
        ]);

        if (
            in_array(
                $validated['gate_pass_type'],
                ['material', 'Person With Material']
            ) &&
            empty($validated['items'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'At least one material item is required.',
            ], 422);
        }

        if (
            $validated['gate_pass_type'] === 'Person' &&
            !empty($validated['items'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Person gate pass cannot contain material items.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $gatePass->update([
                'gate_pass_type' =>
                    $validated['gate_pass_type'],

                'requested_by' => auth()->id(),

                'department_name' =>
                    $validated['department_name'],

                'designation_name' =>
                    $validated['designation_name'],

                'purpose' =>
                    $validated['purpose'],

                'expected_exit_at' =>
                    $validated['expected_exit_at'],

                'expected_return_at' =>
                    $validated['expected_return_at'] ?? null,

                'remarks' =>
                    $validated['remarks'] ?? null,
            ]);

            $gatePass->items()->delete();

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $gatePass->items()->create([
                        'code' => getGenerateCode(GatePassItem::class, 'code', 'GPI', 8),
                        'product_id' =>
                            $item['product_id'] ?? null,

                        'product_name' =>
                            $item['product_name'],

                        'quantity' =>
                            $item['quantity'],

                        'unit' =>
                            $item['unit'] ?? null,

                        'asset_no' =>
                            $item['asset_no'] ?? null,

                        'serial_no' =>
                            $item['serial_no'] ?? null,

                        'remarks' =>
                            $item['remarks'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $gatePass->load([
                'requester:id,name',
                'items',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gate pass updated successfully.',
                'data' => $gatePass,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update gate pass.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(GatePass $gatePass)
    {
        try {
            $gatePass->delete();

            return response()->json([
                'success' => true,
                'message' => 'Gate pass deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete gate pass.',
            ], 500);
        }
    }

    public function pdf(GatePass $gatePass)
    {
        $gatePass->load([
            'requester:id,name',
            'items',
        ]);

        $pdf = Pdf::loadView(
            'reception::gatepass.gate-pass-pdf',
            [
                'gatePass' => $gatePass,
            ]
        );

        $pdf->setPaper(
            'A4',
            'portrait'
        );

        return $pdf->download(
            $gatePass->pass_no . '.pdf'
        );
    }

    private function generatePassNo(): string
    {
        $lastId = GatePass::max('id') ?? 0;

        return 'GP-' . date('Y') . '-' .
            str_pad(
                $lastId + 1,
                5,
                '0',
                STR_PAD_LEFT
            );
    }
}