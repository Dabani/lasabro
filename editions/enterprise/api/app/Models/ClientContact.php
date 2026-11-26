<?php

namespace App\Models;

use App\Support\BelongsToClient;
use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    use BelongsToClient, BelongsToOrganization, HasUlids;

    protected $guarded = [];

    protected $casts = ['is_primary' => 'boolean'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
