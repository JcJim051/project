<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriveUploadSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'requirement_id',
        'user_id',
        'status',
        'original_name',
        'target_name',
        'mime_type',
        'size_bytes',
        'uploaded_bytes',
        'drive_folder_id',
        'drive_file_id',
        'resumable_url',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'uploaded_bytes' => 'integer',
        'resumable_url' => 'encrypted',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
