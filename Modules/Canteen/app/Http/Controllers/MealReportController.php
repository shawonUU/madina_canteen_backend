<?php

namespace Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MealReportController extends Controller
{
    public function daily(Request $request)
    {
        $filters = $this->filters($request);
        $base = $this->bookings($filters);
        $rows = (clone $base)->select('em.meal_date', 'em.meal_type_id', 'mt.name as meal_type_name')
            ->selectRaw('COUNT(em.id) as total_bookings')
            ->selectRaw("SUM(CASE WHEN em.status = 'Selected' THEN 1 ELSE 0 END) as selected_count")
            ->selectRaw("SUM(CASE WHEN em.status = 'Served' THEN 1 ELSE 0 END) as served_count")
            ->selectRaw("SUM(CASE WHEN em.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN em.status <> 'Cancelled' THEN em.quantity ELSE 0 END), 0) as total_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN em.status <> 'Cancelled' THEN em.total_amount ELSE 0 END), 0) as total_amount")
            ->groupBy('em.meal_date', 'em.meal_type_id', 'mt.name')
            ->orderByDesc('em.meal_date')->orderBy('mt.name')->get();
        $summary = [];
        foreach (['total_bookings', 'selected_count', 'served_count', 'cancelled_count', 'total_quantity', 'total_amount'] as $field) {
            $summary[$field] = $rows->sum($field);
        }
        return response()->json(['success' => true, 'filters' => $filters, 'summary' => $summary, 'data' => $rows]);
    }

    public function employeeBookings(Request $request)
    {
        $filters = $this->filters($request);
        $rows = $this->bookingRows($filters);
        return response()->json(['success' => true, 'filters' => $filters, 'summary' => $this->summary($rows), 'data' => $rows]);
    }

    public function cost(Request $request)
    {
        $filters = $this->filters($request);
        $rows = $this->bookingRows($filters);
        $summary = $this->summary($rows);
        $summary['served_amount'] = $rows->where('status', 'Served')->sum('total_amount');
        $summary['selected_amount'] = $rows->where('status', 'Selected')->sum('total_amount');
        $summary['cancelled_amount'] = $rows->where('status', 'Cancelled')->sum('total_amount');
        return response()->json(['success' => true, 'filters' => $filters, 'summary' => $summary, 'data' => $rows]);
    }

    public function itemConsumption(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->bookings($filters)
            ->join('employee_meal_items as emi', 'emi.employee_meal_id', '=', 'em.id')
            ->join('menu_items as mi', 'mi.id', '=', 'emi.menu_item_id');
        if (empty($filters['status'])) {
            $query->where('em.status', '<>', 'Cancelled');
        }
        $rows = $query->select('emi.menu_item_id', 'mi.name as item_name', 'mi.item_type', 'mi.alternative_of', 'em.meal_type_id', 'mt.name as meal_type_name')
            ->selectRaw('SUM(em.quantity) as selected_count')
            ->groupBy('emi.menu_item_id', 'mi.name', 'mi.item_type', 'mi.alternative_of', 'em.meal_type_id', 'mt.name')
            ->orderByDesc('selected_count')->get();
        return response()->json(['success' => true, 'filters' => $filters, 'data' => $rows]);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'meal_type_id' => ['nullable', 'integer', 'exists:meal_types,id'],
            'menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'status' => ['nullable', 'in:Selected,Served,Cancelled'],
        ]);
        $data['date_from'] = $data['date_from'] ?? now()->toDateString();
        $data['date_to'] = $data['date_to'] ?? $data['date_from'];
        return $data;
    }

    private function bookings(array $filters)
    {
        $query = DB::table('employee_meals as em')
            ->join('meal_types as mt', 'mt.id', '=', 'em.meal_type_id')
            ->whereBetween('em.meal_date', [$filters['date_from'], $filters['date_to']]);
        foreach (['employee_id', 'meal_type_id', 'status'] as $key) {
            if (!empty($filters[$key])) {
                $query->where('em.' . $key, $filters[$key]);
            }
        }
        if (!empty($filters['menu_item_id'])) {
            $query->whereExists(fn ($items) => $items->selectRaw('1')->from('employee_meal_items as filter_items')
                ->whereColumn('filter_items.employee_meal_id', 'em.id')->where('filter_items.menu_item_id', $filters['menu_item_id']));
        }
        return $query;
    }

    private function bookingRows(array $filters)
    {
        return $this->bookings($filters)->leftJoin('employees as e', 'e.id', '=', 'em.employee_id')
            ->select('em.id', 'em.code', 'em.employee_id', 'e.name as employee_name', 'e.code as employee_code',
                'em.meal_date', 'em.meal_type_id', 'mt.name as meal_type_name', 'em.menu_id', 'em.meal_rate',
                'em.quantity', 'em.total_amount', 'em.status', 'em.remarks', 'em.created_by', 'em.created_at', 'em.updated_at')
            ->orderByDesc('em.meal_date')->orderByDesc('em.id')->get();
    }

    private function summary($rows): array
    {
        $active = $rows->where('status', '<>', 'Cancelled');
        return [
            'total_bookings' => $rows->count(),
            'selected_count' => $rows->where('status', 'Selected')->count(),
            'served_count' => $rows->where('status', 'Served')->count(),
            'cancelled_count' => $rows->where('status', 'Cancelled')->count(),
            'total_quantity' => $active->sum('quantity'),
            'total_amount' => $active->sum('total_amount'),
        ];
    }
}
