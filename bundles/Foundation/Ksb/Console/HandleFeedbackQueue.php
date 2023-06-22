<?php

namespace Bundles\Foundation\Ksb\Console;

use Bundles\Foundation\Framework\Console\Command;
use Carbon\Carbon;
use Models\FeedbackQueue;
use Bundles\Foundation\Ksb\Lib\Feedback as FeedbackLib;
use Enjoin, DB;

class HandleFeedbackQueue extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ksb:handle-feedback-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle feedback queue...';

    /**
     * Handle...
     */
    public function handle()
    {
        DB::transaction(function () {
            $jobs = Enjoin::get('FeedbackQueue')->findAll([
                'where' => [
                    ['or' => [
                        ['and' => [
                            'status' => FeedbackQueue::AWAIT_STATUS,
                            'end_date' => ['lte' => Carbon::now()->subDays(config('feedback.mailing_timeouts.0'))]
                        ]],
                        ['and' => [
                            'status' => FeedbackQueue::SENT_STATUS,
                            'tries_counter' => ['lt' => count(config('feedback.mailing_timeouts'))]
                        ]]
                    ]]
                ],
                'include' => [
                    [
                        'model' => Enjoin::get('Pages'),
                        'required' => true,
                        'attributes' => ['name', 'type'],
                    ]
                ],
                'order' => ['end_date']
            ]);
            foreach ($jobs as $job) {
                if (Carbon::parse($job->end_date)->lte($this->calcNextMailingBorderDate($job))) {
                    $res = (new FeedbackLib)->uploadToMindbox($job);
                    if (! empty($res)) {
                        $job->update([
                            'tries_counter' => $job->tries_counter + 1,
                            'status' => FeedbackQueue::SENT_STATUS,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @param \Enjoin\Record\Record $job
     * @return Carbon
     */
    protected function calcNextMailingBorderDate(Enjoin\Record\Record $job): Carbon
    {
        return Carbon::now()->subDays(config('feedback.mailing_timeouts')[$job->tries_counter]);
    }

}
