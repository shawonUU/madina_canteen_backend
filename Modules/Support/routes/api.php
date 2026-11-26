<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\SupportAssignmentController;
use Modules\Support\Http\Controllers\SupportDashboardController;
use Modules\Support\Http\Controllers\SupportTicketController;
use Modules\Support\Http\Controllers\SupportTicketReplyController;
use Modules\Support\Http\Controllers\SupportReportController;

Route::middleware('auth:sanctum') ->prefix('support') ->group(function () {
        Route::get('/reports', [SupportReportController::class, 'index']);
        Route::get('/assignees', [SupportAssignmentController::class, 'assignees']);
        Route::get('/tickets', [SupportTicketController::class, 'index']);
        Route::post('/tickets', [SupportTicketController::class, 'store']);
        Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
        Route::put('/tickets/{id}', [SupportTicketController::class, 'update']);
        Route::patch('/tickets/{id}', [SupportTicketController::class, 'update']);

        Route::post( '/tickets/{ticketId}/replies', [SupportTicketReplyController::class, 'store'] );

        Route::get('/dashboard', [
            SupportDashboardController::class,
            'index',
        ]);

        Route::put('/tickets/{id}/assign', [
            SupportAssignmentController::class,
            'assign',
        ]);

        Route::patch('/tickets/{id}/status', [
            SupportAssignmentController::class,
            'updateStatus',
        ]);
    });