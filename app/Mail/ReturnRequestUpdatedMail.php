<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReturnRequestUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(public ReturnRequest $returnRequest)
    {
        $this->afterCommit();
    }

    public function build(): self
    {
        return $this->subject('Cập nhật yêu cầu đổi trả #'.$this->returnRequest->id)
            ->view('emails.returns.updated');
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('mail')->error('Return update email exhausted retries', ['return_id' => $this->returnRequest->id, 'exception' => $exception::class]);
    }
}
