<?php

namespace Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Admin\Models\User;
use Modules\Meeting\Services\MeetingAccess;

class Meeting extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['can_manage'];

    public function getCanManageAttribute(): bool
    {
        return auth()->user() instanceof User && MeetingAccess::canManage(auth()->user(), $this);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function chairperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chairperson_id');
    }


    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(MeetingAgenda::class)
            ->orderBy('sort_order');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(MeetingMinute::class);
    }
}
