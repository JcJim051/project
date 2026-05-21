<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'template_type',
        'file_kind',
        'ruta_archivo',
    ];

    protected static function booted(): void
    {
        static::deleting(function (DocumentTemplate $template): void {
            if ($template->ruta_archivo && Storage::disk('local')->exists($template->ruta_archivo)) {
                Storage::disk('local')->delete($template->ruta_archivo);
            }
        });
    }
}
