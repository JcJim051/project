<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStudySpecialistAssignment extends Model
{
    protected $fillable = [
        'project_id',
        'study_folder',
        'specialist_id',
        'user_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
