<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyFee extends Model
{
    public $table = 'property_fees';
    public $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'property_id', 'name', 'rate', 'billing_type',
        'taxable', 'applies_discount', 'is_mandatory', 'sort_order', 'status',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'taxable' => 'boolean',
        'applies_discount' => 'boolean',
        'is_mandatory' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function scopeMandatory($q) { return $q->where('is_mandatory', true); }
    public function scopeOptional($q) { return $q->where('is_mandatory', false); }
}
