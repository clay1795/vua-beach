<?php

namespace Tests\Unit;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Mail\ReturnRequestUpdatedMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Tests\TestCase;

class QueuedMailCommitSafetyTest extends TestCase
{
    public function test_every_business_mail_waits_for_database_commit(): void
    {
        $order = new Order;
        $returnRequest = new ReturnRequest;

        $this->assertTrue((new OrderPlacedMail($order))->afterCommit);
        $this->assertTrue((new OrderStatusUpdatedMail($order))->afterCommit);
        $this->assertTrue((new ReturnRequestUpdatedMail($returnRequest))->afterCommit);
        $this->assertTrue((new VerifyEmailNotification)->afterCommit);
        $this->assertTrue((new ResetPasswordNotification('test-token'))->afterCommit);
    }

    public function test_database_queue_defaults_to_after_commit_dispatch(): void
    {
        $this->assertTrue(config('queue.connections.database.after_commit'));
    }
}
