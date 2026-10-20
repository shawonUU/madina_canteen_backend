<?php

namespace Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAgenda extends Model
{
    protected $guarded = [];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
