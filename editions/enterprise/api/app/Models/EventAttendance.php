<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class EventAttendance extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'event_attendance';

    protected $guarded = [];

    protected $casts = ['checked_in_at' => UtcDateTime::class];

    public function registration()
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    public function method()
    {
        return $this->belongsTo(LookupValue::class, 'method_id');
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
