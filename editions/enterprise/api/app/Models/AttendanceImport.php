<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AttendanceImport extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'hr_attendance_imports';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];
}
