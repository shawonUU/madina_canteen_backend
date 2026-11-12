<?php

use Illuminate\Support\Facades\Route;
use Modules\Meeting\Http\Controllers\MeetingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('meetings', MeetingController::class)->names('meeting');
});
