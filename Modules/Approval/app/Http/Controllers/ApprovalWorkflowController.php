<?php

namespace Modules\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Models\ApprovalWorkflow;
use Modules\Approval\Models\ApprovalWorkflowLevel;

class ApprovalWorkflowController extends Controller
{
    public function index()
    {
        $workflows = ApprovalWorkflow::with([
            'levels',
        ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Workflows Retrieved Successfully.',
            'data' => $workflows,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => [
                'required',
                'exists:modules,id',
            ],

            'menu_id' => [
                'nullable',
                'exists:menus,id',
            ],

            'child_menu_id' => [
                'nullable',
                'exists:child_menus,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'levels' => [
                'required',
                'array',
                'min:1',
            ],

            'levels.*.level_no' => [
                'required',
                'integer',
                'min:1',
            ],

            'levels.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'levels.*.approver_type' => [
                'required',
                'in:Role,User,Department Role',
            ],

            'levels.*.role_id' => [
                'nullable',
                'exists:roles,id',
            ],

            'levels.*.user_id' => [
                'nullable',
                'exists:users,id',
            ],

            'levels.*.department_id' => [
                'nullable',
                'exists:departments,id',
            ],

            'levels.*.min_approvers' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'levels.*.is_required' => [
                'nullable',
                'boolean',
            ],

            'levels.*.can_reject' => [
                'nullable',
                'boolean',
            ],

            'levels.*.can_return' => [
                'nullable',
                'boolean',
            ],
        ]);

        $workflow = DB::transaction(function () use (
            $validated
        ) {
            $workflow = ApprovalWorkflow::create([
                'code' => getGenerateCode(ApprovalWorkflow::class, 'code', 'AWF', 8),
                'module_id' => $validated['module_id'],
                'menu_id' => $validated['menu_id'] ?? null,
                'child_menu_id' => $validated['child_menu_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => 'Active',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['levels'] as $level) {
                ApprovalWorkflowLevel::create([
                    'workflow_id' => $workflow->id,
                    'code' => getGenerateCode(ApprovalWorkflowLevel::class, 'code', 'AWFL', 8),
                    'level_no' => $level['level_no'],
                    'name' => $level['name'],
                    'approver_type' => $level['approver_type'],
                    'role_id' => $level['role_id'] ?? null,
                    'user_id' => $level['user_id'] ?? null,
                    'department_id' => $level['department_id'] ?? null,
                    'min_approvers' => $level['min_approvers'] ?? 1,
                    'is_required' => $level['is_required'] ?? true,
                    'can_reject' => $level['can_reject'] ?? true,
                    'can_return' => $level['can_return'] ?? true,
                    'status' => 'Active',
                ]);
            }

            return $workflow->load('levels');
        });

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Workflow Created Successfully.',
            'data' => $workflow,
        ], 201);
    }

    public function show(ApprovalWorkflow $approvalWorkflow)
    {
        $approvalWorkflow->load('levels');

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Workflow Retrieved Successfully.',
            'data' => $approvalWorkflow,
        ]);
    }

    public function update(
        Request $request,
        ApprovalWorkflow $approvalWorkflow
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'required',
                'in:Active,Inactive',
            ],
        ]);

        $approvalWorkflow->update($validated);

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Workflow Updated Successfully.',
            'data' => $approvalWorkflow,
        ]);
    }
}
