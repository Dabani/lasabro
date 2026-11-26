<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use App\Support\HasGeneratedCode;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class HrPosition extends Model
{
    use HasGeneratedCode;

    protected static string $codeEntity = 'hr_position';

    use BelongsToOrganization, HasUlids;

    protected $table = 'hr_positions';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function grade()
    {
        return $this->belongsTo(HrGrade::class, 'grade_id');
    }
}
