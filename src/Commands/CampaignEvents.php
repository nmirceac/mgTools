<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Campaign;
use App\MessageEvent;

class CampaignEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:events';

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
        $campaignIds = Campaign::whereDate('created_at', '>=', date('Y-m-d', strtotime('-7 days')))->where('status','>',1)->pluck('id');
        foreach($campaignIds as $campaignId) {
            $campaign = Campaign::find($campaignId);
            $this->comment('Getting events for '.$campaign->name);
            $mailgunMessagesIds = $campaign->getMailGunMessagesIds();
            $campaignStats = [
                'delivered' => 0,
                'opened' => 0,
                'clicked' => 0,
            ];
            foreach($mailgunMessagesIds as $mailgunMessageId) {
                $this->info('Getting events for message id '.$mailgunMessageId);
                $events = Campaign::getEventsForMessageId($mailgunMessageId);
                $this->comment('Processing '.count($events).' events');
                $stats = [
                    'delivered' => 0,
                    'opened' => 0,
                    'clicked' => 0,
                ];
                foreach($events as $event) {
                    $stats[$event['event']]++;
                    $campaignStats[$event['event']]++;
                    $eventInfo = Campaign::processEvent($event);
                    if(!is_null($eventInfo)) {
                        $this->comment('Adding new event');
                        MessageEvent::add($eventInfo['type'], $event);
                    }
                }
                $this->info($stats['delivered'].' delivered - '.$stats['opened'].' opened - '.$stats['clicked'].' clicked');
            }
            $this->comment('Campaign totals: '.$campaignStats['delivered'].' delivered - '.$campaignStats['opened'].' opened - '.$campaignStats['clicked'].' clicked');
            $this->info('Campaign DB totals: '.$campaign->deliveries()->count().' delivered - '.
                $campaign->opens()->count().' opened - '.
                $campaign->clicks()->count().' clicked - '.
                $campaign->interactions()->count().' interactions'
            );
        }

        $campaignIds = Campaign::whereDate('created_at', '>=', date('Y-m-d', strtotime('-30 days')))->where('status','>',1)->pluck('id');
        foreach($campaignIds as $campaignId) {
            $campaign = Campaign::find($campaignId);
            $this->comment('Getting events for '.$campaign->name);

            $events = [];
            foreach($campaign->events as $event) {
                if(!isset($events[$event->message_id.'-'.$event->event])) {
                    $events[$event->message_id.'-'.$event->event] = $event;
                } else {
                    $timestampDifference = abs(strtotime($event->timestamp) - strtotime($events[$event->message_id.'-'.$event->event]->timestamp));
                    if($timestampDifference<60) {
                        if($event->details != $events[$event->message_id.'-'.$event->event]->details) {
                            $this->comment('Event #'.$event->id.' is a duplicate');
                            $event->delete();
                        }
                    }
                }
            }
        }
    }
}
