<?php namespace MgTools\Jobs;

use App\MgCampaign;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendCampaignConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries=1;
    public $timeout=15;

    protected $campaignId = null;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle()
    {
        $campaign = MgCampaign::find($this->campaignId);
        if(!isset($campaign->details['user_id'])) {
            return false;
        }
        $user = User::find($campaign->details['user_id']);

        \Mail::send('emails.confirmation', ['campaign'=>$campaign,'user'=>$user], function ($m) use ($user, $campaign) {
            $m->to($user->email, $user->name);
            $m->subject('Campaign "'.$campaign->name.'" sent');
        });
    }
}
