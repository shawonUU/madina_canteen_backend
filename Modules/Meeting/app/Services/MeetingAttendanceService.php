<?php

namespace Modules\Meeting\Services;

use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingAttendance;

class MeetingAttendanceService
{
    public function save(Meeting $meeting, int $participantId, string $status, ?string $remarks, int $userId): MeetingAttendance
    {
        $attendance = MeetingAttendance::firstOrNew(['meeting_id' => $meeting->id, 'meeting_participant_id' => $participantId]);
        $attendance->fill([
            'status' => $status,
            'remarks' => $remarks,
            'checked_in_at' => in_array($status, ['present', 'late'], true) ? ($attendance->checked_in_at ?? now()) : null,
            'marked_by' => $userId,
        ]);
        $attendance->save();
        return $attendance;
    }
}
