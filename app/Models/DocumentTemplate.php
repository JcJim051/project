<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DocumentTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'nombre',
        'template_type',
        'file_kind',
        'version',
        'is_active',
        'effective_at',
        'sheet_config',
        'ruta_archivo',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_at' => 'date',
        'sheet_config' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (DocumentTemplate $template): void {
            if ($template->isForceDeleting()
                && $template->ruta_archivo
                && Storage::disk('local')->exists($template->ruta_archivo)) {
                Storage::disk('local')->delete($template->ruta_archivo);
            }
        });

        static::saving(function (DocumentTemplate $template): void {
            if (! $template->is_active || blank($template->template_type)) {
                return;
            }

            static::query()
                ->where('template_type', $template->template_type)
                ->when($template->exists, fn ($query) => $query->whereKeyNot($template->getKey()))
                ->update(['is_active' => false]);
        });
    }

    public function bankRequests()
    {
        return $this->hasMany(ProjectBankRequest::class, 'document_template_id');
    }
}
