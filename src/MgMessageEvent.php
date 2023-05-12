<?php namespace MgTools;

use Illuminate\Database\Eloquent\Model;

class MgMessageEvent extends Model
{
    public $timestamps = false;

    protected $appends = ['event_name'];

    const MG_EVENT_DELIVERED='delivered';
    const MG_EVENT_DROPPED='dropped';
    const MG_EVENT_BOUNCED='bounced';
    const MG_EVENT_SPAM='complained';
    const MG_EVENT_CLICKED='clicked';
    const MG_EVENT_OPENED='opened';
    const MG_EVENT_UNSUBSCRIBED='unsubscribed';

    const EVENT_DELIVERED=1;
    const EVENT_DROPPED=2;
    const EVENT_BOUNCED=3;
    const EVENT_SPAM=4;
    const EVENT_CLICKED=5;
    const EVENT_OPENED=6;
    const EVENT_UNSUBSCRIBED=7;

    const EXCEPTION_EMPTY_WEBHOOK_DATA = 0;
    const EXCEPTION_MISSING_WEBHOOK_SIGNATURE = 1;
    const EXCEPTION_MISSING_WEBHOOK_SIGNATURE_DATA = 2;
    const EXCEPTION_INVALID_WEBHOOK_SIGNATURE = 3;
    const EXCEPTION_MISSING_EVENT_DATA = 4;

    public function getDetailsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details']=json_encode($value);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function getEventName()
    {
        switch($this->event) {
            case self::EVENT_DELIVERED:
                return 'Message delivered';
                break;

            case self::EVENT_DROPPED:
                return 'Message was dropped';
                break;

            case self::EVENT_BOUNCED:
                return 'Message bounced';
                break;

            case self::EVENT_SPAM:
                return 'Message was identified as spam';
                break;

            case self::EVENT_CLICKED:
                return 'Message was clicked';
                break;

            case self::EVENT_OPENED:
                return 'Message was opened';
                break;

            case self::EVENT_UNSUBSCRIBED:
                return 'User unsubscribed';
                break;

            default:
                break;
        }
    }

    public static function getTimeIntervalQuery($timeInterval = 3600)
    {
        $query = MgMessageEvent::query();
        $query->select(\DB::raw('FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP (`timestamp`)/'.$timeInterval.')*'.$timeInterval.') as `interval`'), \DB::raw('count(*) as count'), 'event');
        $query->groupBy('interval');
        return $query;
    }

    public function getEventNameAttribute()
    {
        return $this->getEventName();
    }

    public static function checkWebhookData(array $webhookData)
    {
        if(empty($webhookData)) {
            throw new \Exception('Empty webhook data', self::EXCEPTION_EMPTY_WEBHOOK_DATA);
        }
        if(!isset($webhookData['signature'])) {
            throw new \Exception('Missing webhook signature', self::EXCEPTION_MISSING_WEBHOOK_SIGNATURE);
        }

        $signature = $webhookData['signature'];
        if(!isset($signature['timestamp']) or !isset($signature['token']) or !isset($signature['signature'])) {
            throw new \Exception('Missing webhook signature data', self::EXCEPTION_MISSING_WEBHOOK_SIGNATURE_DATA);
        }

        $calculatedSignature = hash_hmac(
            'sha256',
            sprintf('%s%s', $signature['timestamp'], $signature['token']),
            config('mailgun.api_key')
        );
        if(!(config('mailgun.ignore_webhook_signature') or $signature['signature'] == $calculatedSignature)) {
            throw new \Exception('Invalid webhook signature', self::EXCEPTION_INVALID_WEBHOOK_SIGNATURE);
        }

        if(!isset($webhookData['event-data'])) {
            throw new \Exception('Missing event data', self::EXCEPTION_MISSING_EVENT_DATA);
        }


        return self::checkEventData($webhookData['event-data']);
    }

    public static function checkEventData($eventData)
    {
        if(is_string($eventData)) {
            $eventData = json_decode(json_encode($eventData), true);
        }

        if(isset($eventData['message']['headers']['message-id'])) {
            $eventData['message-id'] = $eventData['message']['headers']['message-id'];
            unset($eventData['message']);
        }

        foreach([
                    'id',
                    'domain',
                    'tags',
                    'tag',
                    'user-variables',
                    'campaigns',
                    'envelope',
                    'log-level',
                    'message',
                    'recipient-domain',
                ] as $param) {
            if(isset($eventData[$param])) {
                unset($eventData[$param]);
            }
        }

        if(isset($eventData['event-timestamp'])) {
            $datetime = date('Y-m-d H:i:s', ceil($eventData['event-timestamp']));
            unset($eventData['event-timestamp']);
            unset($eventData['timestamp']);
        } else if(isset($eventData['timestamp'])) {
            $datetime = date('Y-m-d H:i:s', ceil($eventData['timestamp']));
            unset($eventData['timestamp']);
        } else {
            $datetime = date('Y-m-d H:i:s');
        }
        $eventData['timestamp'] = $datetime;

        ksort($eventData);

        return $eventData;
    }

    public static function checkForDuplicates(array $webhookData)
    {
        $message = MgMessage::findFromWebookData($webhookData);
        if(!is_null($message)) {
            $eventType = self::getEventType($webhookData);
//            switch ($method) {
//                case 'delivered':
//                    $eventType = MessageEvent::EVENT_DELIVERED;
//                    break;
//
//                case 'failed':
//                    $eventType = MessageEvent::EVENT_DROPPED;
//                    break;
//
//                case 'rejected':
//                    $eventType = MessageEvent::EVENT_BOUNCED;
//                    break;
//
//                case 'complained':
//                    $eventType = MessageEvent::EVENT_SPAM;
//                    break;
//
//                case 'clicked':
//                    $eventType = MessageEvent::EVENT_CLICKED;
//                    break;
//
//                case 'opened':
//                    $eventType = MessageEvent::EVENT_OPENED;
//                    break;
//
//                default:
//                    break;
//            }

            $matches = MgMessageEvent::where('mg_message_id', $message->id)
                ->where('event', $eventType)
                ->where('timestamp', '>=', date('Y-m-d H:i:s', strtotime($webhookData['timestamp']) - 1))
                ->where('timestamp', '<=', date('Y-m-d H:i:s', strtotime($webhookData['timestamp']) + 1))
                ->count();

            return $matches;
        } else {
            return 0;
        }
    }

    public static function getEventType(array &$webhookData) {
        $mailgunEventType = $webhookData['event'];
        unset($webhookData['event']);
        if($mailgunEventType=='failed') {
            if(isset($webhookData['severity'])) {
                if($webhookData['severity']=='temporary') {
                    $mailgunEventType='bounced';
                } else {
                    $mailgunEventType='dropped';
                }
                unset($webhookData['severity']);
            } else {
                $mailgunEventType='dropped';
            }
        }

        switch ($mailgunEventType) {
            case self::MG_EVENT_DELIVERED :
                $eventType = self::EVENT_DELIVERED;
                break;

            case self::MG_EVENT_DROPPED :
                $eventType = self::EVENT_DROPPED;
                break;

            case self::MG_EVENT_BOUNCED :
                $eventType = self::EVENT_BOUNCED;
                break;

            case self::MG_EVENT_SPAM :
                $eventType = self::EVENT_SPAM;
                break;

            case self::MG_EVENT_CLICKED :
                $eventType = self::EVENT_CLICKED;
                break;

            case self::MG_EVENT_OPENED :
                $eventType = self::EVENT_OPENED;
                break;

            default:
                break;
        }

        return $eventType;
    }

    public static function add(array $webhookData)
    {
        $eventType = self::getEventType($webhookData);

        $timestamp = $webhookData['timestamp'];
        unset($webhookData['timestamp']);

        $message = MgMessage::findFromWebookData($webhookData);

        if($message) {
            $messageRecipient = $message->recipient;
            $messageId = $message->id;

            switch ($eventType) {
                case self::EVENT_DELIVERED :
                    $message->delivered = true;
                    break;

                case self::EVENT_DROPPED :
                    $message->dropped = true;
                    break;

                case self::EVENT_BOUNCED :
                    $message->bounced = true;
                    break;

                case self::EVENT_SPAM :
                    $message->spam = true;
                    break;

                case self::EVENT_CLICKED :
                    $message->clicked = true;
                    break;

                case self::EVENT_OPENED :
                    $message->opened = true;
                    break;

                default:
                    break;
            }

            $message->save();
        } else {
            $messageRecipient = $webhookData['recipient'];
            $messageId = null;
        }

        $existing = MgMessageEvent::where('event', $eventType)
            ->where('mg_message_id', $messageId)
            ->where('recipient', $messageRecipient)
            ->where('timestamp', $timestamp)
            ->first();

        if($existing and md5(json_encode($existing->details))==md5(json_encode($webhookData))) {
            return $existing;
        }

        $messageEvent = new MgMessageEvent();
        $messageEvent->recipient = $messageRecipient;
        $messageEvent->mg_message_id = $messageId;
        $messageEvent->event = $eventType;
        $messageEvent->details = $webhookData;
        $messageEvent->timestamp = $timestamp;
        $messageEvent->save();

        return $messageEvent;
    }

    public static function addUnsubscribe(\App\Campaign $campaign, \App\Subscriber $subscriber)
    {
        $messageEvent = new MgMessageEvent();
        $messageEvent->event = MgMessageEvent::EVENT_UNSUBSCRIBED;
        $message = MgMessage::where('mg_campaign_id', $campaign->id)
            ->where('recipient', $subscriber->email)
            ->first();

        if($message) {
            $messageEvent->recipient = $message->email;
            $messageEvent->mg_message_id = $message->id;
            $message->unsubscribed = true;
            $message->save();
        }

        $messageEvent->details = [];
        $messageEvent->timestamp = date('Y-m-d H:i:s');
        $messageEvent->save();
    }
}
