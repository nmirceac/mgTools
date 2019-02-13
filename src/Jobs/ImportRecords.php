<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries=1;
    public $timeout=900;

    protected $list = null;
    protected $records = null;
    protected $user = null;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($listId, $records)
    {
        $this->list = \App\Lst::find($listId);
        $this->records = $records;
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
        $records = $list->importRecords(\App\Lst::validateRecords($this->records));


        if(\App::environment()!='testing') {
            \Mail::send('notifications.importSubscribers', ['records'=>$records, 'list'=>$list, 'user'=>$user], function ($m) use ($records, $list, $user) {
                $m->from('do-not-reply@nuz.contactmedia.co.za', 'Nuz Contact Media');
                if(!empty($records['invalid'])) {
                    $m->attachData(\App\Lst::arrayToCsv($records['invalid']), $list->name.' invalid entries.csv', [
                        'mime'=>'text/csv'
                    ]);
                }

                $m->to($user->email, $user->name);
                $m->subject('Subscribers import finalized for '.$list->name);
            });
        }
    }
}
