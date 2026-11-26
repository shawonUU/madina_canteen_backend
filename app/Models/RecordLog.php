<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Models\User;

class RecordLog extends Model
{
    protected $guarded = [];
    public const UPDATED_AT = null;
    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'changed_fields' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->select('id', 'name');
    }
}
