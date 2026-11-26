<?php

namespace Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Canteen\Models\MealRate;
use Modules\Canteen\Models\MealType;
use Modules\Canteen\Services\MealRateService;

class MealRateController extends Controller
{
    public function index(Request $request)
    {
        $query = MealRate::with('mealType:id,name');
        foreach (['status', 'meal_type_id', 'rate'] as $field) {
            if ($request->filled($field)) $query->where($field, $request->input($field));
        }
        return response()->json(['success' => true, 'data' => $query->orderByDesc('effective_date')->orderByDesc('id')
            ->paginate(min(500, max(1, (int) $request->input('per_page', 25))))]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $rate = DB::transaction(function () use ($data) {
            $type = MealType::whereKey($data['meal_type_id'])->lockForUpdate()->firstOrFail();
            $rate = MealRate::create($data + ['code' => getGenerateCode(MealRate::class, 'code', 'MRT'), 'status' => 'Active']);
            $this->syncRate($type);
            return $rate->load('mealType:id,name');
        });
        return response()->json(['success' => true, 'message' => 'Meal rate created successfully.', 'data' => $rate], 201);
    }

    public function show($id)
    {
        return response()->json(['success' => true, 'data' => MealRate::with('mealType:id,name')->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->validated($request);
        $rate = DB::transaction(function () use ($data, $id) {
            $rate = MealRate::whereKey($id)->lockForUpdate()->firstOrFail();
            $oldType = $rate->meal_type_id;
            $rate->update($data);
            foreach (array_unique([$oldType, $rate->meal_type_id]) as $typeId) {
                $this->syncRate(MealType::whereKey($typeId)->lockForUpdate()->firstOrFail());
            }
            return $rate->fresh()->load('mealType:id,name');
        });
        return response()->json(['success' => true, 'message' => 'Meal rate updated successfully.', 'data' => $rate]);
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $rate = MealRate::whereKey($id)->lockForUpdate()->firstOrFail();
            $type = MealType::whereKey($rate->meal_type_id)->lockForUpdate()->firstOrFail();
            $rate->delete();
            $this->syncRate($type);
        });
        return response()->json(['success' => true, 'message' => 'Meal rate deleted successfully. Existing booking amounts were preserved.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'meal_type_id' => ['required', 'integer', 'exists:meal_types,id'],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'required', 'in:Active,Inactive'],
        ]);
    }

    private function syncRate(MealType $type): void
    {
        $type->update(['meal_rate' => app(MealRateService::class)->forDate($type, now()->toDateString())]);
    }
}
