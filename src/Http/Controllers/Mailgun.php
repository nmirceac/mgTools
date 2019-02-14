<?php namespace MgTools\Http\Controllers;

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
            $webhookData = MgMessageEvent::checkWebhookData($data);
            if(!$webhookData) {
                throw new \Exception('Invalid webhook request');
            }

            if(MgMessageEvent::checkForDuplicates($webhookData)==0) {
                return MgMessageEvent::add($webhookData);
            } else {
                return response()->json(['message'=>'Duplicate']);
            }
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()], 417);
        }
    }
}
