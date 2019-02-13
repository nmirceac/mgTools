<?php namespace MgTools;

use Illuminate\Database\Eloquent\Model;

class MgSubscriber extends Model
{
    const SUBSCRIBER_STATE_BOUNCED=-1;
    const SUBSCRIBER_STATE_NEW=0;
    const SUBSCRIBER_STATE_CONFIRMED=1;

    protected $appends = [
        'name'
    ];

    public static function findOrCreate($record)
    {
        if(is_string($record)) {
            $record = ['email'=>$record];
        }

        $record['email'] = trim(strtolower($record['email']));

        $subscriber = self::findByEmail($record['email']);
        if($subscriber) {
            $details = $subscriber->details;
        } else {
            $subscriber = new self();
            $subscriber -> email = $record['email'];
            $details = [];
        }

        if(!empty($record['firstname']) and $record['firstname']!=$subscriber->firstname) {
            $subscriber->firstname = ucfirst(strtolower($record['firstname']));
        }

        if(!empty($record['lastname']) and $record['lastname']!=$subscriber->lastname) {
            $subscriber->lastname = ucfirst(strtolower($record['lastname']));
        }

        $newDetails = array_diff_key($record, [
            'email'=>'',
            'firstname'=>'',
            'lastname'=>''
        ]);

        foreach($newDetails as $param=>$value) {
            $details[$param] = trim($value);
        }

        ksort($details);

        $subscriber->details = $details;
        $subscriber->save();

        return $subscriber;
    }

    public static function updateRecord(array $record)
    {
        $record['email'] = trim(strtolower($record['email']));

        $subscriber = self::findByEmail($record['email']);
        $details = $subscriber->details;
        $beforeUpdateHash = md5(json_encode($details));

        $updated = false;

        if(!empty($record['firstname']) and $record['firstname']!=$subscriber->firstname) {
            $subscriber->firstname = ucfirst(strtolower($record['firstname']));
            $updated = true;
        }

        if(!empty($record['lastname']) and $record['lastname']!=$subscriber->lastname) {
            $subscriber->lastname = ucfirst(strtolower($record['lastname']));
            $updated = true;
        }

        $newDetails = array_diff_key($record, [
            'email'=>'',
            'firstname'=>'',
            'lastname'=>''
        ]);

        foreach($newDetails as $param=>$value) {
            $details[$param] = trim($value);
        }

        ksort($details);
        if($beforeUpdateHash != md5(json_encode($details))) {
            $updated = true;
        }

        if($updated) {
            $subscriber->details = $details;
            $subscriber->save();
            return true;
        } else {
            return false;
        }
    }

    public static function findByEmail($email)
    {
        return self::where('email', $email)->first();
    }

    public function getDetailsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details']=json_encode($value);
    }

    public function getNameAttribute()
    {
        return trim(trim($this->firstname).' '.trim($this->lastname));
    }

    public function setNameAttribute()
    {
        return;
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
        return $this->id.md5($this->email.'-'.$this->created_at);
    }

    public static function getByIdentifier(string $identifier)
    {
        if(strlen($identifier) < 33) {
            throw new \Exception('Invalid identifier');
        }

        $id = substr($identifier, 0, -32);
        $subscriber = self::find($id);

        if(!$subscriber) {
            throw new \Exception('Wrong identifier');
        }

        if($subscriber->identifier==$identifier) {
            return $subscriber;
        } else {
            throw new \Exception('Fake identifier');
        }
    }

    public function getCampaignIdentifier(MgCampaign $campaign)
    {
        return $campaign->id.'cc'.$this->id.md5($campaign->id.'-'.$campaign->newlsetter_id.'-'.$campaign->created_at.'-'.$this->email.'-'.$this->created_at);
    }

    public static function resolveCampaignIdentifier(string $campaignIdentifier)
    {
        if(strlen($campaignIdentifier) < 36) {
            throw new \Exception('Invalid campaign identifier');
        }

        list($campaignId, $subscriberId) = explode('cc', substr($campaignIdentifier, 0, -32));
        $campaign = MgCampaign::find($campaignId);
        $subscriber = MgSubscriber::find($subscriberId);

        if(!$campaign or !$subscriber) {
            throw new \Exception('Wrong campaign identifier');
        }

        if($subscriber->getCampaignIdentifier($campaign)==$campaignIdentifier) {
            return [
                'campaign' => $campaign,
                'subscriber' => $subscriber
            ];
        } else {
            throw new \Exception('Fake campaign identifier');
        }
    }

    public function lists()
    {
        return $this->belongsToMany(MgList::class, 'mg_list_subscriber')
            ->withPivot([
                'added',
                'status',
                'counter',
                ]
            );
    }

    public function active_lists()
    {
        return $this->lists()
            ->wherePivot('status', '>=', 0);
    }

    public function messages()
    {
        return $this->hasMany(MgMessage::class);
    }

    public function lastMessage()
    {
        return $this->messages()->orderBy('id', 'DESC')->first();
    }

    public function secondToLastMessage()
    {
        return $this->messages()->orderBy('id', 'DESC')->offset(1)->first();
    }

    public function events()
    {
        return $this->hasMany(MgMessageEvent::class);
    }

    public function markAsBadSubscriber()
    {
        $this->lists()->sync([]);
        $this->state = self::SUBSCRIBER_STATE_BOUNCED;
        $this->save();
    }

    public function markAsConfirmed()
    {
        $this->state = self::SUBSCRIBER_STATE_CONFIRMED;
        $this->save();
    }

    public static function testEmailAddress($email)
    {
        $response = '';
        while(empty($response)) {
            try {
                $response = @file_get_contents('http://web1.weanswer.it/mail/check.php?email='.$email);
            } catch (\Exception $e) {
//                dd($e->getMessage());
            }
        }

        return json_decode($response);
    }
}
