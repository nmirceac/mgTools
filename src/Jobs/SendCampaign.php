<?php namespace MgTools\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\MgCampaign;

class SendCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries=1;
    public $timeout=900;
    public $maxSubscribersPerChunk = 500;

    protected $campaignId = null;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle()
    {
        $campaign = MgCampaign::find($this->campaignId);

        if(!in_array($campaign->status, [MgCampaign::STATUS_NEW, MgCampaign::STATUS_SCHEDULED])) {
//            return false;
        }

        $context = [
            'html'=>$campaign->settings['html'],
            'text'=>$campaign->settings['text'],
        ];

        $subscribers = $campaign->subscribers()->get();
        $chunks = ceil($subscribers->count() / $this->maxSubscribersPerChunk);

        $subscribersChunks = $subscribers->split($chunks);

        $campaign->status = MgCampaign::STATUS_IN_PROGRESS;
        $campaign->sent = 0;
        $campaign->save();

        foreach($subscribersChunks as $subscribers) {
            $mailgun = \Mailgun::send([
                'html'=>'emails.html',
                'text'=>'emails.text',
            ], $context,
                function ($message) use ($subscribers, $campaign) {
                    foreach($subscribers as $subscriber) {
                        $message->to($subscriber->email,
                            $subscriber->name,
                            [
                                'id'=>$subscriber->id,
                                'firstname'=>$subscriber->firstname,
                                'lastname'=>$subscriber->lastname,
                                'name'=>$subscriber->name,
                                'identifier'=>$subscriber->identifier,
                                'campaign_identifier'=>$subscriber->getCampaignIdentifier($campaign),
                            ]);
                    }

                    $message->from($campaign->settings['from']['email'], $campaign->settings['from']['name']);
                    //                $message->replyTo($campaign->settings['replyToEmail'], $campaign->settings['replyToName']);
                    $message->subject($campaign->settings['subject']);
                    $message->trackClicks(true);
                    $message->trackOpens(true);
                    $message->campaign($campaign->id);
                    $message->tag([
                        'campaign-'.$campaign->id,
                        'newsletter-'.$campaign->newsletter->id,
                        'application-'.$campaign->newsletter->application->id
                    ]);

                    $message->header('List-Unsubscribe', route('unsubscribe', $subscriber->getCampaignIdentifier($campaign)));
                    $message->header('Content-Location', route('read', $subscriber->getCampaignIdentifier($campaign)));

                    //                $message->header('Return-Receipt-To', '"'.$campaign->settings['replyToName'].'" <'.$campaign->settings['replyToEmail'].'>');
                    //                $message->header('Disposition-Notification-To', '"'.$campaign->settings['replyToName'].'" <'.$campaign->settings['replyToEmail'].'>');
            });

            foreach($subscribers as $subscriber) {
                \App\MgMessage::add($campaign->id, $subscriber->email, $mailgun->id, [])->id;
            }

            $campaign->sent += count($subscribers);
            $campaign->save();
        }

        $campaign->status = MgCampaign::STATUS_FINISHED;
        $campaign->save();

        dispatch(new SendCampaignConfirmation($campaign->id));
    }
}
