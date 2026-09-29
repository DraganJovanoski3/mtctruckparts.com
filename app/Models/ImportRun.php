<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    protected $fillable = [
        'user_id', 'type', 'status', 'original_filename', 'stored_path', 'mode', 'dry_run',
        'total_rows', 'processed_rows', 'updated_rows', 'created_rows', 'skipped_rows', 'failed_rows',
        'report_path', 'error_message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercent(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) floor(($this->processed_rows / $this->total_rows) * 100);
    }
}
