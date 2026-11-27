<?php

namespace Lasabro\Projects\Models;

use App\Models\LookupValue;
use App\Models\User;
use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(LookupValue::class, 'project_role_id');
    }
}
