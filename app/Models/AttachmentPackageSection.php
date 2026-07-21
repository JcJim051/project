<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttachmentPackageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'orden',
        'active',
        'source_group_code',
        'source_folder',
        'recursive_root_folder',
        'match_type',
        'code_prefixes',
        'allowed_extensions',
        'include_all_folder_files',
        'recursive_source_folders',
    ];

    protected $casts = [
        'orden' => 'integer',
        'active' => 'boolean',
        'code_prefixes' => 'array',
        'allowed_extensions' => 'array',
        'include_all_folder_files' => 'boolean',
        'recursive_source_folders' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden');
    }
}
