<?php

return [
    'template_path' => env('MEETING_ATTENDANCE_TEMPLATE_PATH'),
    'fallback_template_path' => resource_path('templates/attendance/102_sig_fr_007_registro_de_asistencia.xlsx'),
    'template_version' => env('MEETING_ATTENDANCE_TEMPLATE_VERSION', '102-SIG-FR-007-V04'),
    'soffice_path' => env(
        'MEETING_ATTENDANCE_SOFFICE_PATH',
        'libreoffice'
    ),
    'python_path' => env(
        'MEETING_ATTENDANCE_PYTHON_PATH',
        'python3'
    ),
];
