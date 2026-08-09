<?php

namespace App\Jobs;

use App\Services\DepositCallbackService;
// use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDepositCallback implements ShouldQueue
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
        app(DepositCallbackService::class)->handle($this->payload);
    }
}
