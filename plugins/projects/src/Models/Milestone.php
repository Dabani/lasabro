<?php

namespace Lasabro\Projects\Models;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];
}
