<?php

namespace MgTools\Http\Controllers;

use App\MgMessage;
use App\MgMessageEvent;

class Mailgun extends \App\Http\Controllers\Controller
{
    public function dispatcher($method)
    {
        $data = request()->all();

        foreach($data as $param=>$value) {
            unset($data[$param]);
            $data[strtolower($param)] = $value;
        }

        if(!isset($data['message-id'])) {
            $data['message-id'] = '';
        }

        try {
            $webhookData = MessageEvent::checkWebhookData($data);
            if(!$webhookData) {
                throw new \Exception('Invalid webhook request');
            }

            if(MessageEvent::checkForDuplicates($webhookData, $method)==0) {
                return $this->$method($webhookData);
            } else {
                return response()->json(['message'=>'Duplicate']);
            }
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()], 417);
        }
    }

    public function delivered($data)
    {
        MessageEvent::add(MessageEvent::EVENT_DELIVERED, $data);
        return response()->json(['response'=>'Thanks']);
    }

    public function dropped($data)
    {
        MessageEvent::add(MessageEvent::EVENT_DROPPED, $data);
        return response()->json(['response'=>'Thanks']);
    }

    public function bounced($data)
    {
        MessageEvent::add(MessageEvent::EVENT_BOUNCED, $data);
        return response()->json(['response'=>'Thanks']);
    }

    public function spam($data)
    {
        MessageEvent::add(MessageEvent::EVENT_SPAM, $data);
        return response()->json(['response'=>'Thanks']);
    }

    public function clicked($data)
    {
        MessageEvent::add(MessageEvent::EVENT_CLICKED, $data);
        return response()->json(['response'=>'Thanks']);
    }

    public function opened($data)
    {
        MessageEvent::add(MessageEvent::EVENT_OPENED, $data);
        return response()->json(['response'=>'Thanks']);
    }
}
