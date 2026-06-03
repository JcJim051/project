<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class AttachmentPdfRuntime
{
    public function pythonBin(): string
    {
        return (string) config('services.attachments_pdf.python_bin', 'python3');
    }

    public function scriptPath(): string
    {
        $configured = (string) config('services.attachments_pdf.script_path', '');
        $fallback = base_path('scripts/generate_attachment_pdfs.py');

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        return $fallback;
    }

    public function health(): array
    {
        $pythonBin = $this->pythonBin();
        $scriptPath = $this->scriptPath();

        $pythonOk = false;
        $pythonVersion = null;
        $pythonError = null;

        try {
            $process = new Process([$pythonBin, '--version']);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                $pythonOk = true;
                $pythonVersion = trim($process->getOutput() ?: $process->getErrorOutput());
            } else {
                $pythonError = trim($process->getErrorOutput() ?: $process->getOutput());
            }
        } catch (\Throwable $e) {
            $pythonError = $e->getMessage();
        }

        return [
            'python_bin' => $pythonBin,
            'script_path' => $scriptPath,
            'script_exists' => is_file($scriptPath),
            'python_ok' => $pythonOk,
            'python_version' => $pythonVersion,
            'python_error' => $pythonError,
        ];
    }
}
