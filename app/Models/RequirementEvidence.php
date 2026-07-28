<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequirementEvidence extends Model
{
    use HasFactory;

    public const LICENSE_PERMIT_APPLICATION = 'application';

    public const LICENSE_PERMIT_ISSUED = 'issued';

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
        'linked_by_user_id',
        'linked_at',
        'link_note',
        'license_permit_status',
        'classified_by_user_id',
        'classified_at',
        'in_drive',
    ];

    protected $casts = [
        'drive_modified_time' => 'datetime',
        'linked_at' => 'datetime',
        'classified_at' => 'datetime',
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

    public function linkedBy()
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }

    public function classifiedBy()
    {
        return $this->belongsTo(User::class, 'classified_by_user_id');
    }

    public static function licensePermitStatusOptions(): array
    {
        return [
            self::LICENSE_PERMIT_APPLICATION => 'Solicitud o radicado',
            self::LICENSE_PERMIT_ISSUED => 'Licencia o permiso expedido',
        ];
    }

    public static function isValidLicensePermitStatus(?string $status): bool
    {
        return array_key_exists((string) $status, self::licensePermitStatusOptions());
    }

    public function licensePermitStatusLabel(): string
    {
        return self::licensePermitStatusOptions()[$this->license_permit_status] ?? 'Por clasificar';
    }

    public function canPreviewInPortal(): bool
    {
        $mime = strtolower((string) ($this->drive_mime_type ?? ''));
        $name = strtolower((string) ($this->drive_file_name ?? ''));

        if ($mime === 'application/pdf' || str_ends_with($name, '.pdf')) {
            return true;
        }

        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        if (in_array($mime, ['text/plain', 'text/csv'], true)) {
            return true;
        }

        return preg_match('/\.(txt|csv|jpe?g|png|gif|webp|bmp|svg)$/', $name) === 1;
    }
}
