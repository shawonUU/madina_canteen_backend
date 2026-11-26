<?php

namespace Modules\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\Module;
use Modules\Approval\Models\ApprovalWorkflow;
use Modules\Approval\Models\ApprovalWorkflowLevel;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalWorkflow::with('levels')->latest();
        $search = trim((string) $request->input('filter.workflow', $request->input('search', '')));
        if ($search !== '') $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%'));
        if ($request->filled('filter.module')) {
            $query->whereIn('module_id', Module::where('name', 'like', '%'.trim((string) $request->input('filter.module')).'%')->select('id'));
        }
        if ($request->filled('filter.status')) {
            $request->validate(['filter.status' => 'in:Active,Inactive']);
            $query->where('status', $request->input('filter.status'));
        }
        if ($request->filled('filter.levels')) {
            $request->validate(['filter.levels' => 'integer|min:0']);
            $query->has('levels', '=', (int) $request->input('filter.levels'));
        }
        if ($request->boolean('all')) {
            $workflows = $query->get();
            $this->loadLocations($workflows);
        } else {
            $workflows = $query->paginate(min(max((int) $request->input('per_page', 20), 1), 200))->withQueryString();
            $this->loadLocations($workflows->getCollection());
        }
        return response()->json(['success' => true, 'status' => 'Success', 'message' => 'Approval workflows retrieved successfully.', 'data' => $workflows]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateWorkflow($request);
        $workflow = DB::transaction(function () use ($validated) {
            // Nullable scope columns cannot prevent duplicate locations with a SQL unique index alone.
            Module::whereKey($validated['module_id'])->lockForUpdate()->firstOrFail();
            $this->ensureUniqueLocation($validated);
            $workflow = ApprovalWorkflow::create([
                'code' => getGenerateCode(ApprovalWorkflow::class, 'code', 'AWF', 8),
                ...$this->workflowAttributes($validated),
                'created_by' => auth()->id(),
            ]);
            foreach ($validated['levels'] as $level) {
                $workflow->levels()->create([
                    'code' => getGenerateCode(ApprovalWorkflowLevel::class, 'code', 'AWFL', 8),
                    ...$this->levelAttributes($level),
                    'status' => 'Active',
                ]);
            }
            return $workflow->load('levels');
        });
        $this->loadLocations(collect([$workflow]));
        return response()->json(['success' => true, 'status' => 'Success', 'message' => 'Approval workflow created successfully.', 'data' => $workflow], 201);
    }

    public function show(ApprovalWorkflow $approvalWorkflow)
    {
        $approvalWorkflow->load('levels');
        $this->loadLocations(collect([$approvalWorkflow]));
        return response()->json(['success' => true, 'status' => 'Success', 'message' => 'Approval workflow retrieved successfully.', 'data' => $approvalWorkflow]);
    }

    public function update(Request $request, ApprovalWorkflow $approvalWorkflow)
    {
        $validated = $this->validateWorkflow($request, $approvalWorkflow);
        $workflow = DB::transaction(function () use ($validated, $approvalWorkflow) {
            Module::whereIn('id', array_unique([(int) $approvalWorkflow->module_id, (int) $validated['module_id']]))->orderBy('id')->lockForUpdate()->get();
            $workflow = ApprovalWorkflow::whereKey($approvalWorkflow->id)->lockForUpdate()->firstOrFail();
            $this->ensureUniqueLocation($validated, $workflow->id);
            $levels = $workflow->levels()->lockForUpdate()->get()->keyBy('id');
            $submitted = $validated['levels'];

            // Older clients omitted IDs; retain the matching level at each position in that case.
            if (!collect($submitted)->contains(fn ($level) => !empty($level['id']))) {
                foreach ($submitted as &$level) {
                    $existing = $levels->firstWhere('level_no', (int) $level['level_no']);
                    if ($existing) $level['id'] = $existing->id;
                }
                unset($level);
            }
            foreach ($submitted as $index => $level) {
                if (!empty($level['id']) && !$levels->has($level['id'])) {
                    throw ValidationException::withMessages(['levels.'.$index.'.id' => 'This level no longer belongs to the selected workflow.']);
                }
            }
            $workflow->update($this->workflowAttributes($validated));
            $submittedIds = collect($submitted)->pluck('id')->filter()->all();

            // Reserve positions without emitting audit events for temporary implementation values.
            $temporary = max((int) $levels->max('level_no'), count($submitted)) + 1;
            foreach ($levels as $level) {
                DB::table('approval_workflow_levels')->where('id', $level->id)->update(['level_no' => $temporary++]);
            }
            foreach ($levels as $level) {
                if (!in_array($level->id, $submittedIds)) $level->delete();
            }
            foreach ($submitted as $level) {
                if (!empty($level['id'])) {
                    DB::table('approval_workflow_levels')->where('id', $level['id'])->update(['level_no' => $level['level_no']]);
                }
            }
            foreach ($submitted as $level) {
                if (!empty($level['id'])) {
                    // These models still hold the original values, so audit records show the actual old -> new change.
                    $existing = $levels->get($level['id']);
                    $existing->update($this->levelAttributes($level, $existing));
                } else {
                    $workflow->levels()->create([
                        'code' => getGenerateCode(ApprovalWorkflowLevel::class, 'code', 'AWFL', 8),
                        ...$this->levelAttributes($level),
                        'status' => 'Active',
                    ]);
                }
            }
            return $workflow->load('levels');
        });
        $this->loadLocations(collect([$workflow]));
        return response()->json(['success' => true, 'status' => 'Success', 'message' => 'Approval workflow updated successfully.', 'data' => $workflow]);
    }

    private function validateWorkflow(Request $request, ?ApprovalWorkflow $workflow = null): array
    {
        if ($workflow) $request->mergeIfMissing([
            'module_id' => $workflow->module_id, 'menu_id' => $workflow->menu_id,
            'child_menu_id' => $workflow->child_menu_id, 'status' => $workflow->status,
        ]);
        $data = $request->validate([
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'menu_id' => ['nullable', 'integer', 'exists:menus,id'],
            'child_menu_id' => ['nullable', 'integer', 'exists:child_menus,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', 'in:Active,Inactive'],
            'levels' => ['required', 'array', 'min:1', 'max:100'],
            'levels.*.id' => $workflow ? ['nullable', 'integer', 'distinct', Rule::exists('approval_workflow_levels', 'id')->where('workflow_id', $workflow->id)] : ['prohibited'],
            'levels.*.level_no' => ['required', 'integer', 'min:1', 'max:100', 'distinct'],
            'levels.*.name' => ['required', 'string', 'max:255'],
            'levels.*.approver_type' => ['required', 'in:Role,User,Department Role'],
            'levels.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'levels.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'levels.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'levels.*.min_approvers' => ['nullable', 'integer', 'min:1'],
            'levels.*.is_required' => ['nullable', 'boolean'],
            'levels.*.can_reject' => ['nullable', 'boolean'],
            'levels.*.can_return' => ['nullable', 'boolean'],
        ]);
        if (!empty($data['menu_id']) && !Menu::whereKey($data['menu_id'])->where('module_id', $data['module_id'])->exists()) {
            throw ValidationException::withMessages(['menu_id' => 'Selected menu does not belong to the selected module.']);
        }
        if (!empty($data['child_menu_id']) && (empty($data['menu_id']) || !ChildMenu::whereKey($data['child_menu_id'])->where('menu_id', $data['menu_id'])->exists())) {
            throw ValidationException::withMessages(['child_menu_id' => 'Selected child menu does not belong to the selected menu.']);
        }
        if (collect($data['levels'])->pluck('level_no')->map(fn ($number) => (int) $number)->sort()->values()->all() !== range(1, count($data['levels']))) {
            throw ValidationException::withMessages(['levels' => 'Approval levels must be numbered consecutively from 1.']);
        }
        foreach ($data['levels'] as $index => $level) {
            foreach (match ($level['approver_type']) { 'User' => ['user_id'], 'Role' => ['role_id'], 'Department Role' => ['role_id', 'department_id'] } as $field) {
                if (empty($level[$field])) throw ValidationException::withMessages(['levels.'.$index.'.'.$field => 'Please select the required approver for this level.']);
            }
        }
        return $data;
    }

    private function ensureUniqueLocation(array $data, ?int $exceptId = null): void
    {
        $query = ApprovalWorkflow::where('module_id', $data['module_id'])->where('menu_id', $data['menu_id'] ?? null)->where('child_menu_id', $data['child_menu_id'] ?? null);
        if ($exceptId) $query->where('id', '!=', $exceptId);
        if ($query->exists()) throw ValidationException::withMessages(['module_id' => 'A workflow already exists for this module, menu and child menu. Edit the existing workflow.']);
    }

    private function workflowAttributes(array $data): array
    {
        return ['module_id' => $data['module_id'], 'menu_id' => $data['menu_id'] ?? null, 'child_menu_id' => $data['child_menu_id'] ?? null,
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'status' => $data['status'] ?? 'Active'];
    }

    private function levelAttributes(array $data, ?ApprovalWorkflowLevel $existing = null): array
    {
        return ['level_no' => $data['level_no'], 'name' => $data['name'], 'approver_type' => $data['approver_type'],
            'user_id' => $data['approver_type'] === 'User' ? $data['user_id'] : null,
            'role_id' => $data['approver_type'] !== 'User' ? $data['role_id'] : null,
            'department_id' => $data['approver_type'] === 'Department Role' ? $data['department_id'] : null,
            'min_approvers' => $data['min_approvers'] ?? $existing?->min_approvers ?? 1,
            'is_required' => $data['is_required'] ?? $existing?->is_required ?? true,
            'can_reject' => $data['can_reject'] ?? $existing?->can_reject ?? true,
            'can_return' => $data['can_return'] ?? $existing?->can_return ?? true];
    }

    private function loadLocations(Collection $workflows): void
    {
        $modules = Module::whereIn('id', $workflows->pluck('module_id'))->get(['id', 'name'])->keyBy('id');
        $menus = Menu::whereIn('id', $workflows->pluck('menu_id')->filter())->get(['id', 'name', 'module_id'])->keyBy('id');
        $children = ChildMenu::whereIn('id', $workflows->pluck('child_menu_id')->filter())->get(['id', 'name', 'menu_id'])->keyBy('id');
        foreach ($workflows as $workflow) {
            $workflow->setRelation('module', $modules->get($workflow->module_id));
            $workflow->setRelation('menu', $menus->get($workflow->menu_id));
            $workflow->setRelation('child_menu', $children->get($workflow->child_menu_id));
        }
    }
}
