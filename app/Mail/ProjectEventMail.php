<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProjectEventMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $title,
        public readonly string $projectName,
        public readonly string $eventLabel,
        public readonly ?string $detail,
        public readonly ?string $actionUrl,
        public readonly ?string $actionLabel
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->view('emails.project-event');
    }
}

