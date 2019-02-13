<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendTest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries=1;
    public $timeout=30;

    protected $newsletterId = null;
    protected $recipients = [];
    protected $userId = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $newsletterId, array $recipients = [], int $userId)
    {
        $this->newsletterId = $newsletterId;
        $this->recipients = $recipients;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = \App\User::find($this->userId);
        $newsletter = \App\Newsletter::find($this->newsletterId);

        $recipients = $this->recipients;
        if(empty($recipients)) {
            $recipients = $newsletter->application->options['testRecipients'];
        }

        #old debugging info
//        file_put_contents(base_path('debug.txt'), json_encode([
//            'user'=>$user->email,
//            'recipients'=>$recipients,
//            'app_recipients'=>$newsletter->application->options['testRecipients']
//        ]).PHP_EOL, FILE_APPEND);

        foreach ($recipients as $recipient) {
//            file_put_contents(base_path('debug.txt'), 'Sending email to '.$recipient.'...', FILE_APPEND);
            \Mail::send([
                'html'=>'emails.html',
                'text'=>'emails.text',
            ], [
                'html' => $newsletter->getHtmlSource(),
                'text' => $newsletter->getText(),
            ], function ($m) use ($newsletter, $recipient, $user) {
                $m->from($newsletter->application->options['email'], $newsletter->application->name);
                $m->to($recipient);
                $m->replyTo($user->email, $user->name);
                $m->subject('[Test] ' . $newsletter->options['title']);
//                file_put_contents(base_path('debug.txt'), ' Sending in progress...', FILE_APPEND);
            });
//            file_put_contents(base_path('debug.txt'), ' Done!'.PHP_EOL, FILE_APPEND);
        }
//        file_put_contents(base_path('debug.txt'), 'All done'.PHP_EOL, FILE_APPEND);
    }
}
