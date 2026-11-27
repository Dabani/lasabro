<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
