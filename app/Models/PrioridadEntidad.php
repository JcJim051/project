<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioridadEntidad extends Model
{
    protected $table = 'prioridad_entidad_catalogo';

    protected $fillable = ['numero', 'nombre', 'color_hex', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function getResolvedColorHexAttribute(): string
    {
        $color = strtoupper((string) ($this->color_hex ?? ''));

        if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
            return $color;
        }

        return match ((int) $this->numero) {
            1 => '#DC2626',
            2 => '#F97316',
            3 => '#EAB308',
            4 => '#16A34A',
            default => '#9CA3AF',
        };
    }

    public function getContrastTextHexAttribute(): string
    {
        $hex = ltrim($this->resolved_color_hex, '#');

        if (strlen($hex) !== 6) {
            return '#111827';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $brightness >= 160 ? '#111827' : '#FFFFFF';
    }

    public function badgeStyle(): string
    {
        return sprintf(
            'display:inline-flex;align-items:center;gap:4px;padding:1px 6px;border-radius:9999px;background:%s;color:%s;border:1px solid %s;font-weight:600;font-size:10px;line-height:1.1;',
            $this->resolved_color_hex,
            $this->contrast_text_hex,
            $this->resolved_color_hex
        );
    }

    public function badgeLabel(): string
    {
        return trim("{$this->numero} {$this->nombre}");
    }

    public function summaryLabel(): string
    {
        return trim("P. entidad: {$this->badgeLabel()}");
    }
}
