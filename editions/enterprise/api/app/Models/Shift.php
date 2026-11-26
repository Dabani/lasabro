<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use App\Support\HasGeneratedCode;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasGeneratedCode;

    protected static string $codeEntity = 'shift';

    use BelongsToOrganization, HasUlids;

    protected $table = 'hr_shifts';

    protected $guarded = [];

    protected $casts = ['break_minutes' => 'integer', 'auto_deduct_break' => 'boolean', 'grace_minutes' => 'integer', 'is_active' => 'boolean'];
}
