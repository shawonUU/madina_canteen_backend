<?php

namespace Modules\Reception\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Approval\Services\ApprovalService;
use Modules\Reception\Models\GatePass;
use Modules\Reception\Models\GatePassItem;
use Modules\Reception\Models\GatePassPurpose;

class GatePassController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => [
                'nullable',
                'string',
                'in:code,gate_pass_type,requester,department,purpose,expected_exit_at,status,created_at',
            ],
            'sort_dir' => ['nullable', 'in:asc,desc'],
            'filter' => ['nullable', 'array'],
            'filter.code' => ['nullable', 'string', 'max:100'],
            'filter.gate_pass_type' => [
                'nullable',
                'in:Person,Person With Material',
            ],
            'filter.requester' => ['nullable', 'string', 'max:100'],
            'filter.department' => ['nullable', 'string', 'max:100'],
            'filter.purpose' => ['nullable', 'string', 'max:500'],
            'filter.status' => [
                'nullable',
                'in:Pending,Approved,Rejected,Cancelled,Completed',
            ],
            'filter.exit_from' => ['nullable', 'date'],
            'filter.exit_to' => [
                'nullable',
                'date',
                'after_or_equal:filter.exit_from',
            ],
        ]);

        $query = GatePass::query()->with([
            'requester:id,name',
            'items',
            'approvalRequest.levels' => function ($query) {
                $query->where('status', 'Pending');
            },
            'approvalRequest.levels.approver:id,name',
        ]);

        $search = trim($validated['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('gate_pass_type', 'like', "%{$search}%")
                    ->orWhere('department_name', 'like', "%{$search}%")
                    ->orWhere('designation_name', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('requester', function ($requesterQuery) use ($search) {
                        $requesterQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('approvalRequest.levels', function ($levelQuery) use ($search) {
                        $levelQuery->where('status', 'Pending')
                            ->where(function ($q) use ($search) {
                                $q->where('level_no', 'like', "%{$search}%")
                                    ->orWhereHas('approver', function ($approverQuery) use ($search) {
                                        $approverQuery->where('name', 'like', "%{$search}%");
                                    });
                            });
                    });
            });
        }

        $filters = $validated['filter'] ?? [];

        if (!empty($filters['code'])) {
            $query->where(
                'code',
                'like',
                '%' . $filters['code'] . '%'
            );
        }

        if (!empty($filters['gate_pass_type'])) {
            $query->where(
                'gate_pass_type',
                $filters['gate_pass_type']
            );
        }

        if (!empty($filters['requester'])) {
            $query->whereHas('requester', function ($q) use ($filters) {
                $q->where(
                    'name',
                    'like',
                    '%' . $filters['requester'] . '%'
                );
            });
        }

        if (!empty($filters['department'])) {
            $query->where(function ($q) use ($filters) {
                $value = '%' . $filters['department'] . '%';

                $q->where('department_name', 'like', $value)
                    ->orWhere('designation_name', 'like', $value);
            });
        }

        if (!empty($filters['purpose'])) {
            $query->where(
                'purpose',
                'like',
                '%' . $filters['purpose'] . '%'
            );
        }

        if (!empty($filters['status'])) {
            $query->where(
                'status',
                $filters['status']
            );
        }

        if (!empty($filters['exit_from'])) {
            $query->whereDate(
                'expected_exit_at',
                '>=',
                $filters['exit_from']
            );
        }

        if (!empty($filters['exit_to'])) {
            $query->whereDate(
                'expected_exit_at',
                '<=',
                $filters['exit_to']
            );
        }

        $sortColumns = [
            'code' => 'code',
            'gate_pass_type' => 'gate_pass_type',
            'department' => 'department_name',
            'purpose' => 'purpose',
            'expected_exit_at' => 'expected_exit_at',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_dir'] ?? 'desc';

        if ($sortBy === 'requester') {
            $query->orderBy(
                DB::table('users')
                    ->select('name')
                    ->whereColumn('users.id', 'gate_passes.requested_by')
                    ->limit(1),
                $sortDirection
            );
        } else {
            $query->orderBy(
                $sortColumns[$sortBy] ?? 'created_at',
                $sortDirection
            );
        }

        $paginator = $query
            ->orderBy('id', $sortDirection)
            ->paginate($validated['per_page'] ?? 25);

        $paginator->getCollection()->transform(function ($gatePass) {
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
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
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

            'purpose_id' => [
                'required',
                'integer',
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
            $validated['gate_pass_type'] === 'Person With Material' &&
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

            $purpose = GatePassPurpose::findOrFail(
                $validated['purpose_id']
            );

            $gatePass = GatePass::create([
                'code' => getGenerateCode(GatePass::class, 'code', 'GP'),

                'gate_pass_type' => $validated['gate_pass_type'],

                'requested_by' => auth()->id(),

                'department_name' => $validated['department_name'],

                'designation_name' => $validated['designation_name'],

                'purpose_id' => $purpose->id,

                'purpose' => $purpose->name,

                'expected_exit_at' => $validated['expected_exit_at'],

                'expected_return_at' => $validated['expected_return_at'] ?? null,

                'remarks' => $validated['remarks'] ?? null,

                'status' => 'Pending',
            ]);

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $gatePass->items()->create([
                        'code' => getGenerateCode(
                            GatePassItem::class,
                            'code',
                            'GPI'
                        ),

                        'product_id' => $item['product_id'] ?? null,

                        'product_name' => $item['product_name'],

                        'quantity' => $item['quantity'],

                        'unit' => $item['unit'] ?? null,

                        'asset_no' => $item['asset_no'] ?? null,

                        'serial_no' => $item['serial_no'] ?? null,

                        'remarks' => $item['remarks'] ?? null,
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

            'purpose_id' => [
                'required',
                'integer',
                Rule::exists('gate_pass_purposes', 'id')
                    ->where('is_active', true)
                    ->where('approval_status', 'Approved'),
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
            $validated['gate_pass_type'] === 'Person With Material' &&
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

            $purpose = GatePassPurpose::findOrFail(
                $validated['purpose_id']
            );

            $gatePass->update([
                'gate_pass_type' => $validated['gate_pass_type'],

                'requested_by' => auth()->id(),

                'department_name' => $validated['department_name'],

                'designation_name' => $validated['designation_name'],

                'purpose_id' => $purpose->id,

                'purpose' => $purpose->name,

                'expected_exit_at' => $validated['expected_exit_at'],

                'expected_return_at' => $validated['expected_return_at'] ?? null,

                'remarks' => $validated['remarks'] ?? null,
            ]);

            $gatePass->items()->delete();

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $gatePass->items()->create([
                        'code' => getGenerateCode(
                            GatePassItem::class,
                            'code',
                            'GPI',
                            8
                        ),

                        'product_id' => $item['product_id'] ?? null,

                        'product_name' => $item['product_name'],

                        'quantity' => $item['quantity'],

                        'unit' => $item['unit'] ?? null,

                        'asset_no' => $item['asset_no'] ?? null,

                        'serial_no' => $item['serial_no'] ?? null,

                        'remarks' => $item['remarks'] ?? null,
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
            'approvalRequest.levels' => function ($query) {
                $query->where('status', 'Approved');
            },
            'approvalRequest.levels.approver:id,name',
        ]);

        $pdf = Pdf::loadView(
            'reception::gatepass.gate-pass-pdf',
            [
                'gatePass' => $gatePass,
            ]
        );

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download(
            $gatePass->code . '.pdf'
        );
    }
}
