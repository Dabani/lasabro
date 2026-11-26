<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class HrProgressionCriterion extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'hr_progression_criteria';

    protected $guarded = [];

    protected $casts = ['min_months_in_grade' => 'integer', 'min_rating_level' => 'integer'];

    public function fromGrade()
    {
        return $this->belongsTo(HrGrade::class, 'from_grade_id');
    }

    public function toGrade()
    {
        return $this->belongsTo(HrGrade::class, 'to_grade_id');
    }
}
