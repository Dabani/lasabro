<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayTaxBracket extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'pay_tax_brackets';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['lower' => 'decimal:2', 'upper' => 'decimal:2', 'rate' => 'decimal:6'];
}
