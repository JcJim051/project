<?php

namespace App\Services;

class UserBulkImportResult
{
    public int $created = 0;
    public int $skipped = 0;
    public int $warnings = 0;
    public int $emailsSent = 0;
    public int $emailsFailed = 0;

    /** @var array<int, array{line:int,type:string,message:string}> */
    public array $messages = [];

    public function addMessage(int $line, string $type, string $message): void
    {
        $this->messages[] = [
            'line' => $line,
            'type' => $type,
            'message' => $message,
        ];
    }
}
