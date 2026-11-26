<?php

namespace App\Models;

use App\Support\HasGeneratedCode;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LegalReference extends Model
{
    use HasGeneratedCode;

    protected static string $codeEntity = 'legal_reference';

    use HasUlids;

    protected $table = 'legal_references';

    protected $guarded = [];

    protected $casts = ['verified_at' => 'datetime'];

    public function status()
    {
        return $this->belongsTo(LookupValue::class, 'status_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
