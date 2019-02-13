<?php namespace MgTools;

use Illuminate\Database\Eloquent\Model;

class MgCampaign extends Model
{
    const STATUS_CANCELLED = -1;
    const STATUS_NEW = 0;
    const STATUS_SCHEDULED = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_FINISHED = 3;

    private $performanceData = null;

    protected $appends = [
        'list_ids',
        'first_event_timestamp',
        'identifier'
    ];

    public static function createFromHtml($subject, $fromArray, $html, $text=null, $baseUrl=null)
    {
        $campaign = new self();
        $campaign->name = $subject;
        $campaign->status = self::STATUS_NEW;
        $campaign->sent = 0;
        $settings = [];

        $settings['html'] = $html;
        if(is_null($text)) {
            $settings['text'] = strip_tags($html);
        } else {
            $settings['text'] = $text;
        }

        $settings['subject'] = $subject;
        if(is_array($fromArray)) {
            $settings['from'] = $fromArray;
        } elseif(is_string(($fromArray))) {
            $settings['from']['email'] = $fromArray;
        }

        $campaign->settings = $settings;

        if(\Auth::user()) {
            $campaign->details = [
                'user_id' => \Auth::user()->id
            ];
        } else {
            $campaign->details = [];
        }

        $campaign->save();

        if($baseUrl) {
            $campaign->setHtmlTrackingCodes($baseUrl);
        }

        return $campaign;
    }

    public function setHtmlTrackingCodes($baseUrl)
    {
        $settings = $this->settings;
        $html = $settings['html'];

        preg_match_all("/href=\"(.*)\"/U", $html, $links);
        $links = array_unique($links[1]);

        $fromLinks = [];
        $toLinks = [];

        foreach($links as $link) {
            if(strpos($link, $baseUrl)!==0) {
                continue;
            }

            if(strpos($link, '&utm_')) {
                continue;
            }

            $newLink = $link;

            if($newLink==$baseUrl) {
                $newLink.='/';
            }

            $appendString = 'utm_source=newsletter&utm_medium=email&utm_campaign=campaign-'.$this->id.'&nuzId=%recipient.identifier%';

            if(strpos($newLink, '?')) {
                $newLink.='&'.$appendString;
            } else {
                $newLink.='?'.$appendString;
            }

            $fromLinks[] = '"'.$link.'"';
            $toLinks[] = '"'.$newLink.'"';
        }

        $html = str_replace($fromLinks, $toLinks, $html);
        $settings['html'] = $html;
        $this->settings = $settings;
        $this->save();
    }

    public function getFirstEventTimestampAttribute()
    {
        return $this->getFirstEventTimestamp();
    }

    public function setFirstEventTimestampAttribute()
    {
        return;
    }

    public function messages()
    {
        return $this->hasMany(MgMessage::class);
    }

    public function events()
    {
        return $this->hasManyThrough(MgMessageEvent::class, MgMessage::class);
    }

    public function deliveries()
    {
        return $this->events()->where('event', MgMessageEvent::EVENT_DELIVERED);
    }

    public function drops()
    {
        return $this->events()->where('event', MgMessageEvent::EVENT_DROPPED);
    }

    public function bounces()
    {
        return $this->events()->where('event', MgMessageEvent::EVENT_BOUNCED);
    }

    public function spam()
    {
        return $this->events()->where('event', MgMessageEvent::EVENT_SPAM);
    }

    public function clicks()
    {
        return $this->events()->where('event', MgMessageEvent::EVENT_CLICKED);
    }

    public function opens()
    {
        return $this->events()->where('event', MgMessageEvent::EVENT_OPENED);
    }

    public function interactions()
    {
        return $this->events()->whereIn('event', [MgMessageEvent::EVENT_OPENED, MgMessageEvent::EVENT_CLICKED]);
    }

    public function unsubscribes()
    {
        return $this->events()->where('event', MgMessageEvent::EVENT_UNSUBSCRIBED);
    }

    public function lists()
    {
        return $this->belongsToMany(MgList::class, 'mg_campaign_list');
    }

    public function getListIdsAttribute()
    {
        return $this->lists()->pluck('id')->toArray();
    }

    public function setListIdsAttribute(array $listIds)
    {
        $this->lists()->sync($listIds);
    }

    public function subscribers()
    {
        return MgList::getActiveSubscribersForListIds($this->list_ids);
    }

    public function getMessagesCountAttribute()
    {
        return $this->messages()->count();
    }

    public function setMessagesCountAttribute()
    {
        return;
    }

    public function getEventsCountAttribute()
    {
        return $this->events()->count();
    }

    public function setEventsCountAttribute()
    {
        return;
    }

    public function getDetailsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details'] = json_encode($value);
    }

    public function getSettingsAttribute($value)
    {
        $settings = json_decode($value, true);
        if (!isset($settings['enabled'])) {
            $settings['enabled'] = false;
        }

        if (!isset($settings['trackClicks'])) {
            $settings['trackClicks'] = true;
        }

        if (!isset($settings['trackOpens'])) {
            $settings['trackOpens'] = true;
        }

        if (!isset($settings['readReceipt'])) {
            $settings['readReceipt'] = false;
        }

        if (!isset($settings['testEmails'])) {
            $settings['testEmails'] = [];
        }

        if (!isset($settings['tags'])) {
            $settings['tags'] = [];
        }

        return $settings;
    }

    public function setSettingsAttribute($value)
    {
        $this->attributes['settings'] = json_encode($value);
    }

    public function renderForSubscriber(MgSubscriber $subscriber)
    {
        $html = $this->settings['html'];

        $html = str_replace(
            [
                '%recipient%',
                '%email%',
                '%name%',
                '%recipient.id%',
                '%recipient.firstname%',
                '%recipient.lastname%',
                '%recipient.name%',
                '%recipient.identifier%',
                '%recipient.campaign_identifier%',
            ],
            [
                $subscriber->name,
                $subscriber->email,
                $subscriber->name,
                $subscriber->id,
                $subscriber->firstname,
                $subscriber->lastname,
                $subscriber->name,
                $subscriber->identifier,
                $subscriber->getCampaignIdentifier($this)
            ],
            $html
        );

        return $html;
    }

    public function getMailGunMessagesIds()
    {
        return $this->messages()->distinct('mailgun_id')->pluck('mailgun_id')->toArray();
    }

    public static function getEventsForMessageId(string $mailgunMessageId) {
        $events = [];

        $response = \Mailgun::api()->get(config('mailgun.domain').'/events', [
            'limit'=>300,
            'event'=>'clicked OR delivered OR opened',
            'message-id'=>$mailgunMessageId,
        ]);

        foreach($response->http_response_body->items as $event) {
            $events[] = MgMessageEvent::checkEventData($event);
        }

        while(count($response->http_response_body->items)==300) {
            $nextPageSuffix = substr($response->http_response_body->paging->next, strpos($response->http_response_body->paging->next, '/events/')+8);
            $response = \Mailgun::api()->get(config('mailgun.domain').'/events/'.$nextPageSuffix);
            foreach($response->http_response_body->items as $event) {
                $events[] = MgMessageEvent::checkEventData($event);
            }
        }

        return $events;
    }

    public static function processEvent(array $event)
    {
        $eventType = null;
        switch($event['event']) {
            case 'delivered':
                $eventType = MgMessageEvent::EVENT_DELIVERED;
                break;

            case 'failed':
                $eventType = MgMessageEvent::EVENT_DROPPED;
                break;

            case 'rejected':
                $eventType = MgMessageEvent::EVENT_BOUNCED;
                break;

            case 'complained':
                $eventType = MgMessageEvent::EVENT_SPAM;
                break;

            case 'clicked':
                $eventType = MgMessageEvent::EVENT_CLICKED;
                break;

            case 'opened':
                $eventType = MgMessageEvent::EVENT_OPENED;
                break;

            default:
                break;
        }

        if(is_null($eventType)) {
            return;
        }
        unset($event['event']);

        $message = MgMessage::findFromWebookData($event);

        if(is_null($message)) {
            return;
        }


        $matches = MgMessageEvent::where('message_id', $message->id)
            ->where('event', $eventType)
            ->where('timestamp', '>=', date('Y-m-d H:i:s', strtotime($event['timestamp'])-1))
            ->where('timestamp', '<=', date('Y-m-d H:i:s', strtotime($event['timestamp'])+1))
            ->count();

        if($matches == 0) {
            $events = MgMessageEvent::where('message_id', $message->id)
            ->where('event', $eventType)
            ->get()->toArray();

            $context = [
                'type' => $eventType,
                'message'=>$message->toArray(),
                'event'=>$event,
                'events'=>$events,
            ];

            return $context;
        } else {
            return;
        }
    }

    public function getIdentifierAttribute()
    {
        return $this->getIdentifier();
    }

    public function setIdentifierAttribute()
    {
        return;
    }

    public function getIdentifier()
    {
        return $this->id.md5($this->id.'-'.$this->newlsetter_id.'-'.$this->created_at);
    }

    public static function getByIdentifier(string $identifier)
    {
        if(strlen($identifier) < 33) {
            throw new \Exception('Invalid identifier');
        }

        $id = substr($identifier, 0, -32);
        $campaign = self::find($id);

        if(!$campaign) {
            throw new \Exception('Wrong identifier');
        }

        if($campaign->identifier==$identifier) {
            return $campaign;
        } else {
            throw new \Exception('Fake identifier');
        }
    }

    public static function getRegionLabel(MgMessageEvent $event)
    {
        $regionLabel = false;
        if($event->details['country']=='ZA') {
            switch ($event->details['region']) {
                case 'GT':
                    $regionLabel = 'Gauteng';
                    break;

                case 'NL':
                    $regionLabel = 'KwaZulu-Natal';
                    break;

                case 'WC':
                    $regionLabel = 'Western Cape';
                    break;

                case 'EC':
                    $regionLabel = 'Eastern Cape';
                    break;

                case 'NW':
                    $regionLabel = 'North West';
                    break;

                case 'LP':
                    $regionLabel = 'Limpopo';
                    break;

                case 'MP':
                    $regionLabel = 'Mpumalanga';
                    break;

                case 'FS':
                    $regionLabel = 'Orange Free State';
                    break;

                case 'NC':
                    $regionLabel = 'Northern Cape';
                    break;

                default:
                    break;
            }
        }

        return $regionLabel;
    }

    public function getFirstEvent()
    {
        return $this->events()->orderBy('timestamp', 'ASC')->first();
    }

    public function getFirstEventTimestamp()
    {
        return optional($this->getFirstEvent())->timestamp;
    }

    public function getPerformance()
    {
        if(is_null($this->performanceData)) {
            $firstEvent = $this->getFirstEventTimestamp();

            $rangeIntervalOneDay = 1800;
            $rangeIntervalOneDayDateFormat = 'G:i';
            $rangeIntervalsOneDay = \App\Analytics::getDateRangeIntervals($firstEvent,
                strtotime($firstEvent) + (3600 * 23.5),
                $rangeIntervalOneDay,
                0,
                $rangeIntervalOneDayDateFormat);

            $rangeIntervalOneWeek = 3600 * 24;
            $rangeIntervalOneWeekDateFormat = 'Y-m-d';
            $rangeIntervalsOneWeek = \App\Analytics::getDateRangeIntervals($firstEvent,
                strtotime($firstEvent) + (3600 * 24 * 6),
                $rangeIntervalOneWeek,
                0,
                $rangeIntervalOneWeekDateFormat);

            $performance = [];
            $performance['messages'] = $this->messages()->count();
            $performance['events'] = $this->events()->count();
            $performance['deliveries'] = $this->deliveries()->count();
            $performance['drops'] = $this->drops()->count();
            $performance['bounces'] = $this->bounces()->count();
            $performance['spam'] = $this->spam()->count();
            $performance['clicks'] = $this->clicks()->count();
            $performance['unique_clicks'] = $this->clicks()->distinct('message_events.subscriber_id')->count('message_events.subscriber_id');
            $performance['opens'] = $this->opens()->count();
            $performance['unique_opens'] = $this->opens()->distinct('message_events.subscriber_id')->count('message_events.subscriber_id');


            $unsubscribes = $this->unsubscribes()->with('subscriber')->get();
            $performance['unsubscribes'] = $unsubscribes->count();
            $performance['unsubscribes_list'] = [];
            foreach ($unsubscribes as $unsubscribe) {
                $performance['unsubscribes_list'][] = $unsubscribe->subscriber->email;
            }

            $performance['opensDetails']['provinces'] = [];
            $performance['opensDetails']['countries'] = [];
            $performance['opensDetails']['devices'] = [];
            $performance['opensDetails']['oneDay'] = $rangeIntervalsOneDay;
            $performance['opensDetails']['oneWeek'] = $rangeIntervalsOneWeek;

            $performance['clicksDetails']['provinces'] = [];
            $performance['clicksDetails']['countries'] = [];
            $performance['clicksDetails']['devices'] = [];
            $performance['clicksDetails']['oneDay'] = $rangeIntervalsOneDay;
            $performance['clicksDetails']['oneWeek'] = $rangeIntervalsOneWeek;
            $performance['clicksDetails']['urls'] = [];
            $performance['clicksDetails']['visitors'] = [];

            foreach ($this->opens()->get() as $event) {
                if (!in_array($event->details['country'], ['Unknown', 'RE'])) {
                    $countryLabel = \App\Country::getCountryByIso($event->details['country']);
                    if (!isset($performance['opensDetails']['countries'][$event->details['country']])) {
                        $performance['opensDetails']['countries'][$event->details['country']] = ['label' => $countryLabel, 'count' => 0];
                    }
                    $performance['opensDetails']['countries'][$event->details['country']]['count']++;
                }

                $regionLabel = self::getRegionLabel($event);
                if ($regionLabel) {
                    if (!isset($performance['opensDetails']['provinces'][$event->details['region']])) {
                        $performance['opensDetails']['provinces'][$event->details['region']] = ['label' => $regionLabel, 'count' => 0];
                    }
                    $performance['opensDetails']['provinces'][$event->details['region']]['count']++;
                }

                if (isset($event->details['device-type']) and $event->details['device-type'] != 'unknown') {
                    if (!isset($performance['opensDetails']['devices'][$event->details['device-type']])) {
                        $performance['opensDetails']['devices'][$event->details['device-type']] = 0;
                    }
                    $performance['opensDetails']['devices'][$event->details['device-type']]++;
                }

                $intervalDay = \App\Analytics::sectionDataByInterval($event->timestamp, $rangeIntervalOneDay, $rangeIntervalOneDayDateFormat);
                $intervalWeek = \App\Analytics::sectionDataByInterval($event->timestamp, $rangeIntervalOneWeek, $rangeIntervalOneWeekDateFormat);
                if (isset($performance['opensDetails']['oneDay'][$intervalDay])) {
                    $performance['opensDetails']['oneDay'][$intervalDay]++;
                }
                if (isset($performance['opensDetails']['oneWeek'][$intervalWeek])) {
                    $performance['opensDetails']['oneWeek'][$intervalWeek]++;
                }
            }

            foreach ($this->clicks()->get() as $event) {
                if (!in_array($event->details['country'], ['Unknown', 'RE'])) {
                    $countryLabel = \App\Country::getCountryByIso($event->details['country']);
                    if (!isset($performance['clicksDetails']['countries'][$event->details['country']])) {
                        $performance['clicksDetails']['countries'][$event->details['country']] = ['label' => $countryLabel, 'count' => 0];
                    }
                    $performance['clicksDetails']['countries'][$event->details['country']]['count']++;
                }

                $regionLabel = self::getRegionLabel($event);
                if ($regionLabel) {
                    if (!isset($performance['clicksDetails']['provinces'][$event->details['region']])) {
                        $performance['clicksDetails']['provinces'][$event->details['region']] = ['label' => $regionLabel, 'count' => 0];
                    }
                    $performance['clicksDetails']['provinces'][$event->details['region']]['count']++;
                }

                if (isset($event->details['device-type']) and $event->details['device-type'] != 'unknown') {
                    if (!isset($performance['clicksDetails']['devices'][$event->details['device-type']])) {
                        $performance['clicksDetails']['devices'][$event->details['device-type']] = 0;
                    }
                    $performance['clicksDetails']['devices'][$event->details['device-type']]++;
                }

                if (!strpos($event->details['url'], '/unsubscribe/')) {
                    if(strpos($event->details['url'], '?')) {
                        $url = trim(substr($event->details['url'], 0, strpos($event->details['url'], '?')));
                    } else {
                        $url = trim($event->details['url']);
                    }

                    if(!empty($url)) {
                        if (!isset($performance['clicksDetails']['urls'][$url])) {
                            $performance['clicksDetails']['urls'][$url] = 0;
                        }
                        $performance['clicksDetails']['urls'][$url]++;
                    }
                }

                if (!isset($performance['clicksDetails']['visitors'][$event->details['recipient']])) {
                    $performance['clicksDetails']['visitors'][$event->details['recipient']] = 0;
                }
                $performance['clicksDetails']['visitors'][$event->details['recipient']]++;

                $intervalDay = \App\Analytics::sectionDataByInterval($event->timestamp, $rangeIntervalOneDay, $rangeIntervalOneDayDateFormat);
                $intervalWeek = \App\Analytics::sectionDataByInterval($event->timestamp, $rangeIntervalOneWeek, $rangeIntervalOneWeekDateFormat);
                if (isset($performance['clicksDetails']['oneDay'][$intervalDay])) {
                    $performance['clicksDetails']['oneDay'][$intervalDay]++;
                }
                if (isset($performance['clicksDetails']['oneWeek'][$intervalWeek])) {
                    $performance['clicksDetails']['oneWeek'][$intervalWeek]++;
                }
            }


            $countries = [];
            foreach ($performance['clicksDetails']['countries'] as $country) {
                $countries[] = $country['count'];
            }
            array_multisort($performance['clicksDetails']['countries'], SORT_NUMERIC, $countries, SORT_DESC);

            $countries = [];
            foreach ($performance['opensDetails']['countries'] as $country) {
                $countries[] = $country['count'];
            }
            array_multisort($performance['opensDetails']['countries'], SORT_NUMERIC, $countries, SORT_DESC);

            arsort($performance['clicksDetails']['urls']);
            arsort($performance['clicksDetails']['visitors']);

            $this->performanceData = $performance;
        }

        return $this->performanceData;
    }

    public function send()
    {
        return dispatch(new \App\Jobs\SendCampaign($this->id));
    }
}
