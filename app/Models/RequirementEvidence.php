<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequirementEvidence extends Model
{
    use HasFactory;

    protected $table = 'requirement_evidences';

    protected $fillable = [
        'project_id',
        'requirement_id',
        'drive_file_id',
        'drive_file_name',
        'drive_mime_type',
        'drive_modified_time',
        'drive_folder_name',
        'source',
        'in_drive',
    ];

    protected $casts = [
        'drive_modified_time' => 'datetime',
        'in_drive' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }
}
