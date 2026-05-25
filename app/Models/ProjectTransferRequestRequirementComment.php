<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTransferRequestRequirementComment extends Model
{
    protected $fillable = [
        'project_transfer_request_id',
        'requirement_id',
        'author_user_id',
        'comment',
    ];

    public function transferRequest()
    {
        return $this->belongsTo(ProjectTransferRequest::class, 'project_transfer_request_id');
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
