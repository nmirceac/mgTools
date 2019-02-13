<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportText implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries=1;
    public $timeout=900;

    protected $list = null;
    protected $importText = null;
    protected $user = null;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($listId, $importText)
    {
        $this->list = \App\Lst::find($listId);
        $this->importText = $importText;
        $this->user = \Auth::user();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $list = $this->list;
        $user = $this->user;

        $records = $list->importText($this->importText);

        \Mail::send('notifications.importSubscribers', ['records'=>$records, 'list'=>$list, 'user'=>$user], function ($m) use ($records, $list, $user) {
            $m->from('do-not-reply@ev.contactmedia.co.za', 'EV Contact Media');
            $m->to($user->email, $user->name);
            $m->subject('Subscribers import finalized for '.$list->name);
        });

        $this->delete();
    }
}
