<?php

namespace App\Jobs;

use App\Services\WithdrawalCallbackService;
// use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWithdrawalCallback implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private array $payload)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(WithdrawalCallbackService::class)->handle($this->payload);
    }
}
