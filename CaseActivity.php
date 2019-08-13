<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseActivity extends Model
{
    use SoftDeletes;

    /**
     * {@inheritdoc}
     */
    protected $table = 'case_activities';

    /**
     * {@inheritdoc}
     */
    protected $visible = [
        'id',
        'case_id',
        'history_value',
        'created_at',
        'updated_at',
    ];

    /**
     * Get with case.
     *
     * @return BelongsTo
     */
    public function cases(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get with user.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get with user.
     *
     * @return BelongsTo
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CaseActivityTypes::class, 'case_activity_type_id', 'type');
    }
}
