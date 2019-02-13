<?php

namespace App\Console\Commands;

use App\Campaign;
use App\Jobs\SendCampaign;
use Illuminate\Console\Command;

class CampaignsSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends scheduled campaigns';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now = time();
        $scheduledCampaigns = Campaign::where('status', Campaign::STATUS_SCHEDULED)->get();
        foreach($scheduledCampaigns as $campaign) {
            $scheduledTime = \App\Log::checkDateTime($campaign->settings['schedule_time']);
            if($now<strtotime($scheduledTime)) {
                $this->comment('Skipping campaign '.$campaign->name.' - scheduled for '.$scheduledTime);
            } else {
                $this->info('Sending campaign '.$campaign->name);
                dispatch(new SendCampaign($campaign->id));
            }
        }
    }
}
