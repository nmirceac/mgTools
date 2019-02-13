<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Campaign;

class CampaignFixStuck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaing:fixstuck {campaignId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $campaignId = $this->argument('campaignId');

        $campaign = Campaign::find($campaignId);

        if(in_array($campaign->status, [Campaign::STATUS_NEW, Campaign::STATUS_FINISHED, Campaign::STATUS_CANCELLED])) {
            $this->error('The campaign is not stuck');
            return false;
        }

        $context = [
            'html'=>$campaign->settings['html'],
            'text'=>$campaign->settings['text'],
        ];

        $alreadySent = [];
        $messages = $campaign->messages()->get();
        foreach($messages as $message) {
            $alreadySent[] = $message->subscriber_id;
        }

        $subscribers = $campaign->subscribers()->get();
        foreach($subscribers as $id=>$subscriber) {
            if(in_array($subscriber->id, $alreadySent)) {
                unset($subscribers[$id]);
            }
        }

        $chunks = ceil($subscribers->count() / 500);

        $subscribersChunks = $subscribers->split($chunks);

        $campaign->sent = count($alreadySent);

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
                \App\Message::add($campaign->id, $subscriber->id, $mailgun->id, [])->id;
            }

            $campaign->sent += count($subscribers);
            $campaign->save();
        }

        $campaign->status = Campaign::STATUS_FINISHED;
        $campaign->save();
    }
}
