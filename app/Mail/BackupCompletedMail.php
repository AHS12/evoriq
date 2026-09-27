<?php

namespace App\Mail;

use App\Models\BackupRun;
use App\Services\Backup\BackupScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The finished backup archive, sent to backup managers when the email setting
 * is enabled. Archives above the configured size limit are not attached (mail
 * servers reject oversized mail) — the body then points to the run history.
 */
class BackupCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public BackupRun $backupRun,
        public int $attachmentLimitMb,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Backup archive from :app', ['app' => (string) config('app.name', 'Laravel')]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $run = $this->backupRun;
        $sizeMb = (int) round(($run->file_size ?? 0) / 1024 / 1024, 1);

        $body = __('A new backup of all files and the database completed successfully.')."\n\n"
            .__('Created at').': '.$run->completed_at?->format('Y-m-d H:i')."\n"
            .__('File').': '.$run->file_name."\n"
            .__('Size').': '.$sizeMb.' MB';

        if ($this->attachesArchive()) {
            $body .= "\n\n".__('The backup archive is attached to this email.');
        } else {
            $body .= "\n\n".__(
                'The archive is larger than :limit MB and was not attached. Download it from the run history in Settings -> Backups.',
                ['limit' => $this->attachmentLimitMb],
            );
        }

        return new Content(htmlString: nl2br(e($body)));
    }

    /**
     * Build the message and attach the archive when it is within the limit.
     */
    public function build(): self
    {
        if ($this->attachesArchive()) {
            $this->attachFromStorageDisk(
                BackupScheduleService::LOCAL_DISK,
                (string) $this->backupRun->file_name,
                basename((string) $this->backupRun->file_name),
            );
        }

        return $this;
    }

    /**
     * Whether the archive is small enough to attach.
     */
    protected function attachesArchive(): bool
    {
        $sizeMb = ($this->backupRun->file_size ?? 0) / 1024 / 1024;

        return $sizeMb <= $this->attachmentLimitMb;
    }
}
