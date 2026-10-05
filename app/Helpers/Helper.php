<?php

use Modules\Approval\Models\ApprovalWorkflow;

if (! function_exists('moduleId')) {
    function moduleId(): ?int{
        $value = request()->header('X-Module-Id');
        return $value !== null ? (int) $value : null;
    }
}

if (! function_exists('menuId')) {
    function menuId(): ?int{
        $value = request()->header('X-Menu-Id');
        return $value !== null ? (int) $value : null;
    }
}

if (! function_exists('childMenuId')) {
    function childMenuId(): ?int{
        $value = request()->header('X-Child-Menu-Id');
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
    function getGenerateCode($model, $column,  $prefix, $numbers = 8,$noTrash = null){
        $lastRecord = $model::orderBy('id', 'desc')->first();

        if (!$lastRecord) {return $prefix . '-1';}
        $lastCode = $lastRecord->{$column};
        $number = (int) str_replace($prefix . '-', '', $lastCode);
        return $prefix . '-' . ($number + 1);
    }
}
