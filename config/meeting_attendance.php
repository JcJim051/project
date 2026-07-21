<?php

return [
    'template_path' => env(
        'MEETING_ATTENDANCE_TEMPLATE_PATH',
        '/Users/jonathanjimenez/Downloads/102-SIG-FR-007 REGISTRO DE ASISTENCIA (10).xlsx'
    ),
    'template_version' => env('MEETING_ATTENDANCE_TEMPLATE_VERSION', '102-SIG-FR-007-V04'),
    'soffice_path' => env(
        'MEETING_ATTENDANCE_SOFFICE_PATH',
        '/Users/jonathanjimenez/.cache/codex-runtimes/codex-primary-runtime/dependencies/bin/override/soffice'
    ),
    'python_path' => env(
        'MEETING_ATTENDANCE_PYTHON_PATH',
        '/Users/jonathanjimenez/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3'
    ),
];
