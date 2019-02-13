<?php

namespace MgTools\Http\Controllers;

use App\MgList;
use App\MgStats;
use App\MgSubscriber;

class Api extends \App\Http\Controllers\Controller
{
    public function verifyRequest()
    {
        if(request('key')!=config('app.api_key')) {
            return response()->json(['message'=>'Wrong key'], 403);
        } else {
            return false;
        }
    }

    public function stats()
    {
        $apiKey = request()->get('api_key');
        if($apiKey==config('app.api_key')) {
            return (new StatsController())->getStats();
        } else {
//            return abort(403, 'Not authorized');
        }
    }

    public function subscribe()
    {
        $verifyRequest = $this->verifyRequest();
        if($verifyRequest) {
            return $verifyRequest;
        }

        $payload = request()->except('key');

        $validator = \Validator::make($payload, [
            'listId' => 'integer|required',
            'subscribers' => 'array|required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Had a few validation problems, please check the data.',
                'validation_problems' => $validator->messages()->toArray()
            ], 406);
        }

        $list = Lst::find($payload['listId']);
        if(is_null($list)) {
            return response()->json([
                'message' => 'List not found'
            ], 404);
        }

        $added = [];

        foreach($payload['subscribers'] as $email=>$emailData) {
            if(is_string($emailData)) {
                $added[] = ['email'=>$emailData];
            } else if(is_array($emailData)) {
                if(!isset($emailData['email'])) {
                    $emailData['email'] = $email;
                }
                $added[] = $emailData;
            }
        }

        $records = Lst::checkRecords($added, $list->getEmailAddresses());

        $list->addSubscribers($records['added']);
        $records['skipped'] = [];
        $records['updated'] = [];
        foreach($records['existing'] as $record) {
            if(Subscriber::updateRecord($record)) {
                $records['updated'][$record['email']] = $record;
            } else {
                $records['skipped'][$record['email']] = $record;
            }
        }

        foreach($records as $param=>$value) {
            $records[$param] = array_keys($value);
        }

        return response()->json($records);
    }

    public function subscribeJsGet()
    {
        $list = \App\Lst::getByIdentifier(request('list'));

        $context = [
            'list'=>$list
        ];

        if(empty($list->settings['enabled'])) {
            $subscribeJs = view('subscribeJsBase', $context)->render();
            $subscribeJs = trim(str_replace(['<script>', '</script>'], '', $subscribeJs));
            return response($subscribeJs)->header('Content-Type', 'application/javascript');
        }

        $subscribeHtml = trim(view('subscribeJsHtml', $context)->render());
        $subscribeHtml = '\''.str_replace(PHP_EOL, '\'+'.PHP_EOL.'\'', $subscribeHtml).'\'';

        $context['subscribeHtml'] = $subscribeHtml;

        $subscribeJs = view('subscribeJs', $context)->render();
        $subscribeJs = trim(str_replace(['<script>', '</script>'], '', $subscribeJs));

        return response($subscribeJs)->header('Content-Type', 'application/javascript');
    }

    public function subscribeJsPost()
    {
//        return response()->json(request()->all())->header('Access-Control-Allow-Origin', '*');

        $list = Lst::getByIdentifier(request('list'));
        $added = [request()->except('list')];

        $records = Lst::checkRecords($added, $list->getEmailAddresses());

        if(!empty($records['invalid'])) {
            $problem = reset($records['invalid']);
            return response()
                ->json([
                    'message'=>'There\'s a problem with your email address: '.$problem['problem'],
                    'subscribed'=>false,
                    'existing'=>false
                ])
                ->header('Access-Control-Allow-Origin', '*');
        }

        if(!empty($records['existing'])) {
            $subscriber = Subscriber::findByEmail($added[0]['email']);
            return response()
                ->json([
                    'message'=>'You are already subscribed to this list',
                    'subscribed'=>true,
                    'existing'=>true,
                    'identifier'=>$subscriber->identifier,
                    'email'=>$subscriber->email
                ])
                ->header('Access-Control-Allow-Origin', '*');
        }

        $list->addSubscribers($records['added']);
        $records['skipped'] = [];
        $records['updated'] = [];
        foreach($records['existing'] as $record) {
            if(Subscriber::updateRecord($record)) {
                $records['updated'][$record['email']] = $record;
            } else {
                $records['skipped'][$record['email']] = $record;
            }
        }

        $subscriber = Subscriber::findByEmail($added[0]['email']);
        return response()
            ->json([
                'message'=>'You are now subscribed to this list',
                'subscribed'=>true,
                'existing'=>false,
                'identifier'=>$subscriber->identifier,
                'email'=>$subscriber->email
            ])
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function campaignDelivery($identifier)
    {
        $campaign = \App\Campaign::getByIdentifier($identifier);
        $performance = [];
        $performance['messages'] = $campaign->messages()->count();
        $performance['events'] = $campaign->events()->count();
        $performance['deliveries'] = $campaign->deliveries()->count();
        $performance['drops'] = $campaign->drops()->count();
        $performance['bounces'] = $campaign->bounces()->count();
        $performance['spam'] = $campaign->spam()->count();
        $performance['clicks'] = $campaign->clicks()->count();
        $performance['unique_clicks'] = $campaign->clicks()->distinct('message_events.subscriber_id')->count('message_events.subscriber_id');
        $performance['opens'] = $campaign->opens()->count();
        $performance['unique_opens'] = $campaign->opens()->distinct('message_events.subscriber_id')->count('message_events.subscriber_id');
        $performance['unsubscribes'] = $campaign->unsubscribes()->count();
        $performance['unsubscribes_list'] = $campaign->unsubscribes()->with('subscriber')->get();

        $delivery = [
            [
                ['Delivery', 'Campaign'],
                ['Sent', $campaign->messages()->count()],
                ['Delivered', $campaign->deliveries()->count()],
                ['Drops', $campaign->drops()->count()],
                ['Opens', $campaign->opens()->distinct('message_events.subscriber_id')->count('message_events.subscriber_id')],
                ['Clicks', $campaign->opens()->distinct('message_events.subscriber_id')->count('message_events.subscriber_id')],
            ]
        ];

        return response()->json($delivery);
    }
}
