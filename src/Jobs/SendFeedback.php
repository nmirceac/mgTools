<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendFeedback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries=1;
    public $timeout=15;

    protected $campaignIdentifier = null;
    protected $feedback = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($campaignIdentifier, $feedback)
    {
        $this->campaignIdentifier = $campaignIdentifier;
        $this->feedback = $feedback;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $context = \App\Subscriber::resolveCampaignIdentifier($this->campaignIdentifier);

        \Mail::send('emails.feedback', [
            'campaign'=>$context['campaign'],
            'subscriber'=>$context['subscriber'],
            'feedback'=>$this->feedback
            ], function ($m) use ($context) {
            $m->from($context['subscriber']->email, $context['subscriber']->name);
            $m->to($context['campaign']->settings['from']['email']);
            $m->cc(config('app.email'));
            $m->subject('User feedback for "'.$context['campaign']->name.'" received');
        });
    }
}
