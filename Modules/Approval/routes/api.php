<?php

use Illuminate\Support\Facades\Route;
use Modules\Approval\Http\Controllers\ApprovalController;
use Modules\Approval\Http\Controllers\ApprovalDelegationController;
use Modules\Approval\Http\Controllers\ApprovalWorkflowController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('approvals', ApprovalController::class)->names('approval');
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/approvals',
        [ApprovalController::class, 'index']
    );

    Route::get(
        '/approvals/{approvalRequest}',
        [ApprovalController::class, 'show']
    );

    Route::post(
        '/approvals/{approvalRequest}/approve',
        [ApprovalController::class, 'approve']
    );

    Route::post(
        '/approvals/{approvalRequest}/reject',
        [ApprovalController::class, 'reject']
    );

    Route::post(
        '/approvals/{approvalRequest}/return',
        [ApprovalController::class, 'return']
    );


    Route::get(
        '/approval-workflows',
        [ApprovalWorkflowController::class, 'index']
    );

    Route::post(
        '/approval-workflows',
        [ApprovalWorkflowController::class, 'store']
    );

    Route::get(
        '/approval-workflows/{approvalWorkflow}',
        [ApprovalWorkflowController::class, 'show']
    );

    Route::put(
        '/approval-workflows/{approvalWorkflow}',
        [ApprovalWorkflowController::class, 'update']
    );


    Route::get(
        '/approval-delegations',
        [ApprovalDelegationController::class, 'index']
    );

    Route::post(
        '/approval-delegations',
        [ApprovalDelegationController::class, 'store']
    );

    Route::put(
        '/approval-delegations/{approvalDelegation}',
        [ApprovalDelegationController::class, 'update']
    );

    Route::delete(
        '/approval-delegations/{approvalDelegation}',
        [ApprovalDelegationController::class, 'destroy']
    );
});
