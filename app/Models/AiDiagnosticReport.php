<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiDiagnosticReport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'child_id', 'report_week_start', 'language_dominance', 'provider', 'phonetic_blindspots', 'potty_timing_analysis',
        'sensory_behavior_summary', 'actionable_parent_exercises', 'dietary_chaining_tips',
    ];

    protected $casts = [
        'report_week_start' => 'date:Y-m-d',
        'phonetic_blindspots' => 'array',
        'potty_timing_analysis' => 'array',
        'actionable_parent_exercises' => 'array',
        'dietary_chaining_tips' => 'array',
    ];
}
