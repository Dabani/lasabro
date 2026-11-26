<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use App\Support\HasGeneratedCode;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasGeneratedCode;

    protected static string $codeEntity = 'client';

    use BelongsToOrganization, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected $casts = ['portal_enabled' => 'boolean'];

    public function status()
    {
        return $this->belongsTo(LookupValue::class, 'status_id');
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }
}
