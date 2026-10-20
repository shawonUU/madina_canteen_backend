
<?php

use Illuminate\Support\Facades\Route;
use Modules\Meeting\Http\Controllers\MeetingController;
use Modules\Meeting\Http\Controllers\MeetingParticipantController;
use Modules\Meeting\Http\Controllers\MeetingAgendaController;
use Modules\Meeting\Http\Controllers\MeetingAttendanceController;
use Modules\Meeting\Http\Controllers\MeetingMinuteController;

Route::middleware('auth:sanctum')
    ->prefix('meeting-management')
    ->group(function () {

        Route::apiResource('meetings', MeetingController::class);

        Route::apiResource(
            'meetings.participants',
            MeetingParticipantController::class
        );

        Route::patch(
            'meetings/{meeting}/participants/{participant}/response',
            [MeetingParticipantController::class, 'respond']
        );

        Route::apiResource(
            'meetings.agendas',
            MeetingAgendaController::class
        );

        Route::get(
            'meetings/{meeting}/attendance',
            [MeetingAttendanceController::class, 'index']
        );

        Route::post(
            'meetings/{meeting}/attendance',
            [MeetingAttendanceController::class, 'store']
        );

        Route::put(
            'meetings/{meeting}/attendance',
            [MeetingAttendanceController::class, 'bulkUpdate']
        );

        Route::get(
            'meetings/{meeting}/minutes',
            [MeetingMinuteController::class, 'show']
        );

        Route::put(
            'meetings/{meeting}/minutes',
            [MeetingMinuteController::class, 'upsert']
        );

        Route::patch(
            'meetings/{meeting}/minutes/approve',
            [MeetingMinuteController::class, 'approve']
        );

        Route::get(
            '/my-invitations',
            [MeetingParticipantController::class, 'myInvitations']
        );

        Route::patch(
            '/meetings/{meetingId}/participants/{participantId}',
            [MeetingParticipantController::class, 'updateInvitationStatus']
        );
    });

