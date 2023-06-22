<?php

namespace Bundles\Foundation\Ksb\Console;

use Bundles\Foundation\Framework\Console\Command;
use Models\FeedbackQueue;
use Enjoin;

class CleanupFeedbackQueue extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ksb:cleanup-feedback-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup feedback queue...';

    /**
     * Handle...
     */
    public function handle()
    {
        Enjoin::get('FeedbackQueue')->destroy([
            'where' => [
                ['or' => [
                    'status' => FeedbackQueue::COMMITTED_STATUS,
                    ['and' => [
                        'status' => FeedbackQueue::SENT_STATUS,
                        'tries_counter' => ['gte' => count(config('feedback.mailing_timeouts'))]
                    ]]
                ]]
            ]
        ]);
    }

}
