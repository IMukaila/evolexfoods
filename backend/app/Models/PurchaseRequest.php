<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'request_number', 'requested_by', 'requested_at', 'item_name', 'category',
        'quantity', 'unit', 'estimated_cost', 'priority', 'status', 'justification',
        'approval_tier', 'approval_stage', 'required_approver_role',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'date',
            'quantity' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'approval_tier' => 'integer',
        ];
    }

    /** Accessor-style helpers so API consumers always see the routed tier. */
    public function getApprovalTierAttribute($value): int
    {
        if ($value) {
            return (int) $value;
        }

        return ((float) ($this->attributes['estimated_cost'] ?? 0))
            > (float) config('evolex.procurement.executive_threshold_ghs', 1000) ? 2 : 1;
    }

    public function getApprovalStageAttribute($value): string
    {
        if ($value) {
            return (string) $value;
        }

        return match ($this->attributes['status'] ?? 'pending') {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'ordered', 'converted' => 'ordered',
            'received' => 'received',
            'closed' => 'closed',
            default => $this->approval_tier === 2 ? 'pending_tier_2' : 'pending_tier_1',
        };
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseRequestApproval::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
