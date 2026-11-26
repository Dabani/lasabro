<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use App\Support\HasGeneratedCode;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class HrGrade extends Model
{
    use HasGeneratedCode;

    protected static string $codeEntity = 'hr_grade';

    use BelongsToOrganization, HasUlids;

    protected $table = 'hr_grades';

    protected $guarded = [];

    protected $casts = ['level' => 'integer', 'is_active' => 'boolean', 'salary_min' => 'decimal:2', 'salary_max' => 'decimal:2'];
}
