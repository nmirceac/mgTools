<?php namespace MgTools;

use Illuminate\Database\Eloquent\Model;

class MgMessage extends Model
{
    protected $appends = ['campaign_name'];

    protected $casts = [
        'delivered' => 'boolean',
        'opened' => 'boolean',
        'clicked' => 'boolean',
        'spam' => 'boolean',
        'bounced' => 'boolean',
        'dropped' => 'boolean',
    ];

    public function getDetailsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details']=json_encode($value);
    }

    public function events()
    {
        return $this->hasMany(\App\MessageEvent::class);
    }

    public function subscriber()
    {
        return $this->belongsTo(\App\Subscriber::class);
    }

    public function campaign()
    {
        return $this->belongsTo(\App\Campaign::class);
    }

    public static function add($campaignId, $recipient, $mailgunId, array $details)
    {
        $message = new self();
        $message->campaign_id = $campaignId;
        $message->recipient = $recipient;
        $message->mailgun_id = trim($mailgunId, "<> \r\n\t");
        $message->details = $details;
        $message->save();
        return $message;
    }

    public static function getByMailgunId($mailgunId)
    {
        $mailgunId = trim($mailgunId, "<> \r\n\t");
        return self::where('mailgun_id', $mailgunId)->first();
    }

    public static function findFromWebookData($webhookData)
    {
        return self::findByRecipientAndMailgunId($webhookData['recipient'], $webhookData['message-id']);
    }

    public static function findByRecipientAndMailgunId($recipient, $mailgunId)
    {
        $mailgunId = trim($mailgunId, "<> \r\n\t");
        return self::where('mailgun_id', $mailgunId)
            ->where('recipient', $recipient)
            ->first();
    }

    public function getCampaignName()
    {
        return $this->campaign()->first()->name;
    }

    public function getCampaignNameAttribute()
    {
        return $this->getCampaignName();
    }

}
