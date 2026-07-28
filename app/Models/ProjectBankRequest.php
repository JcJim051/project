<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBankRequest extends Model
{
    protected $fillable = [
        'project_id',
        'document_template_id',
        'created_by_user_id',
        'variant',
        'version_number',
        'generation_type',
        'status',
        'form_data',
        'update_reason',
        'output_filename',
        'drive_folder_id',
        'drive_file_id',
        'generated_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'form_data' => 'array',
        'generated_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id')->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
