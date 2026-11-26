<?php

use Modules\Approval\Models\ApprovalWorkflow;

if (! function_exists('moduleId')) {
    function moduleId(): ?int{
        $value = request()->attributes->get('navigation_context')['module_id'] ?? null;
        return $value !== null ? (int) $value : null;
    }
}

if (! function_exists('menuId')) {
    function menuId(): ?int{
        $value = request()->attributes->get('navigation_context')['menu_id'] ?? null;
        return $value !== null ? (int) $value : null;
    }
}

if (! function_exists('childMenuId')) {
    function childMenuId(): ?int{
        $value = request()->attributes->get('navigation_context')['child_menu_id'] ?? null;
        return $value !== null ? (int) $value : null;
    }
}


if (! function_exists('approvalWorkflow')) {
    function approvalWorkflow(): ?ApprovalWorkflow {
        $moduleId = moduleId();
        $menuId = menuId();
        $childMenuId = childMenuId();
        if (!$moduleId) {return null;}
        return ApprovalWorkflow::query()
            ->where('module_id', $moduleId)
            ->where('menu_id', $menuId)
            ->where('child_menu_id', $childMenuId)
            ->where('status', 'Active')
            ->first();
    }
}

if (! function_exists('getGenerateCode')) {
    function getGenerateCode($model, $column,  $prefix){
        return app(\App\Services\RecordCodeService::class)->next($model, $column, $prefix);
    }
}
