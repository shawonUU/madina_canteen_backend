<?php

namespace Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HRM\Models\Employee;

class MeetingParticipant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function employee(){
        return $this->belongsTo(Employee::class);
    }

    public function attendance() 
    {
        return $this->hasOne(MeetingAttendance::class);
    }
}
