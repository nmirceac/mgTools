<?php namespace MgTools;

use Illuminate\Database\Eloquent\Model;

class MgMessageEvent extends Model
{
    public $timestamps = false;

    protected $appends = ['event_name'];

    const EVENT_DELIVERED=1;
    const EVENT_DROPPED=2;
    const EVENT_BOUNCED=3;
    const EVENT_SPAM=4;
    const EVENT_CLICKED=5;
    const EVENT_OPENED=6;
    const EVENT_UNSUBSCRIBED=7;

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
        $query = MessageEvent::query();
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
            return false;
        }

        if(!isset($webhookData['signature']) or !isset($webhookData['timestamp']) or !isset($webhookData['token'])) {
            return false;
        }

        $calculatedSignature = hash_hmac(
            'sha256',
            sprintf('%s%s', $webhookData['timestamp'], $webhookData['token']),
            config('services.mailgun.secret')
        );
        if($webhookData['signature'] != $calculatedSignature) {
            return false;
        }
        unset($webhookData['signature']);
        unset($webhookData['timestamp']);
        unset($webhookData['token']);

        if(isset($webhookData['event'])) {
            unset($webhookData['event']);
        }
        if(isset($webhookData['domain'])) {
            unset($webhookData['domain']);
        }
        if(isset($webhookData['tags'])) {
            unset($webhookData['tags']);
        }
        if(isset($webhookData['tag'])) {
            unset($webhookData['tag']);
        }
        if(isset($webhookData['event-timestamp'])) {
            $datetime = date('Y-m-d H:i:s', ceil($webhookData['event-timestamp']));
            unset($webhookData['event-timestamp']);
            unset($webhookData['timestamp']);
        } else if(isset($webhookData['timestamp'])) {
            $datetime = date('Y-m-d H:i:s', ceil($webhookData['timestamp']));
            unset($webhookData['timestamp']);
        } else {
            $datetime = date('Y-m-d H:i:s');
        }
        $webhookData['timestamp'] = $datetime;

        ksort($webhookData);

        return $webhookData;
    }

    public static function checkEventData($eventData)
    {
        $eventData = json_decode(json_encode($eventData), true);
        if(isset($eventData['tags'])) {
            unset($eventData['tags']);
        }
        if(isset($eventData['campaigns'])) {
            unset($eventData['campaigns']);
        }
        if(isset($eventData['user-variables'])) {
            unset($eventData['user-variables']);
        }
        if(isset($eventData['recipient-domain'])) {
            unset($eventData['recipient-domain']);
        }
        if(isset($eventData['log-level'])) {
            unset($eventData['log-level']);
        }
        if(isset($eventData['id'])) {
            unset($eventData['id']);
        }
        if(isset($eventData['message']['headers']['message-id'])) {
            $eventData['message-id'] = $eventData['message']['headers']['message-id'];
            unset($eventData['message']);
        }



        foreach($eventData as $eventParam=>$eventValue) {
            if(is_array($eventValue)) {
                $eventData = array_merge($eventData, $eventValue);
            }
        }

        foreach($eventData as $eventParam=>$eventValue) {
            if(is_array($eventValue)) {
                unset($eventData[$eventParam]);
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

    public static function checkForDuplicates(array $webhookData, $method)
    {
        $message = MgMessage::findFromWebookData($webhookData);
        if(!is_null($message)) {
            $eventType = null;
            switch ($method) {
                case 'delivered':
                    $eventType = MessageEvent::EVENT_DELIVERED;
                    break;

                case 'failed':
                    $eventType = MessageEvent::EVENT_DROPPED;
                    break;

                case 'rejected':
                    $eventType = MessageEvent::EVENT_BOUNCED;
                    break;

                case 'complained':
                    $eventType = MessageEvent::EVENT_SPAM;
                    break;

                case 'clicked':
                    $eventType = MessageEvent::EVENT_CLICKED;
                    break;

                case 'opened':
                    $eventType = MessageEvent::EVENT_OPENED;
                    break;

                default:
                    break;
            }

            $matches = MessageEvent::where('message_id', $message->id)
                ->where('event', $eventType)
                ->where('timestamp', '>=', date('Y-m-d H:i:s', strtotime($webhookData['timestamp']) - 1))
                ->where('timestamp', '<=', date('Y-m-d H:i:s', strtotime($webhookData['timestamp']) + 1))
                ->count();

            return $matches;
        } else {
            return 0;
        }
    }

    public static function add($eventType, array $webhookData)
    {
        $timestamp = $webhookData['timestamp'];
        unset($webhookData['timestamp']);

        $message = MgMessage::findFromWebookData($webhookData);

        if($message) {
            $messageRecipient = $message->email;
            $messageId = $message->id;

            switch ($eventType) {
                case MgMessageEvent::EVENT_DELIVERED :
                    $message->delivered = true;
                    break;

                case MgMessageEvent::EVENT_DROPPED :
                    $message->dropped = true;
                    break;

                case MgMessageEvent::EVENT_BOUNCED :
                    $message->bounced = true;
                    break;

                case MgMessageEvent::EVENT_SPAM :
                    $message->spam = true;
                    break;

                case MgMessageEvent::EVENT_CLICKED :
                    $message->clicked = true;
                    break;

                case MgMessageEvent::EVENT_OPENED :
                    $message->opened = true;
                    break;

                default:
                    break;
            }

            $message->save();
        } else {
            $messageRecipient = 0;
            $messageId = 0;
        }

        $existing = MgMessageEvent::where('event', $eventType)
            ->where('message_id', $messageId)
            ->where('recipient', $messageRecipient)
            ->where('timestamp', $timestamp)
            ->first();

        if($existing and md5(json_encode($existing->details))==md5(json_encode($webhookData))) {
            return $existing;
        }

        $messageEvent = new MgMessageEvent();
        $messageEvent->recipient = $messageRecipient;
        $messageEvent->message_id = $messageId;
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
        $message = MgMessage::where('campaign_id', $campaign->id)
            ->where('recipient', $subscriber->email)
            ->first();

        if($message) {
            $messageEvent->recipient = $message->email;
            $messageEvent->message_id = $message->id;
            $message->unsubscribed = true;
            $message->save();
        }

        $messageEvent->details = [];
        $messageEvent->timestamp = date('Y-m-d H:i:s');
        $messageEvent->save();
    }
}
