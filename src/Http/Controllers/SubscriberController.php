<?php

namespace MgTools\Http\Controllers;

class SubscriberController extends \App\Http\Controllers\Controller
{
    public function unsubscribe($campaignIdentifier)
    {
        $context = \App\Subscriber::resolveCampaignIdentifier($campaignIdentifier);
        $context['campaignIdentifier'] = $campaignIdentifier;

        if(request()->getMethod()=='POST') {
            foreach($context['campaign']->lists as $list) {
                $subscriber = $list->subscribers()->where('id', $context['subscriber']->id)->first();
                if($subscriber) {
                    $subscriber->pivot->status = \App\Lst::SUBSCRIBER_UNSUBSCRIBED;
                    $subscriber->pivot->save();
                }
            }

            \App\MessageEvent::addUnsubscribe($context['campaign'], $context['subscriber']);

            $context['confirm']=true;
            return view('public.unsubscribed', $context);
        } else {
            $context['confirm']=false;
            return view('public.unsubscribe', $context);
        }
    }

    public function feedback()
    {
        $campaignIdentifier = request('campaignIdentifier');
        if(empty($campaignIdentifier)) {
            abort(404);
        }

        $context = \App\Subscriber::resolveCampaignIdentifier($campaignIdentifier);
        $context['feedback'] = trim(request('feedback'));
        $context['confirm']=true;

        if(!empty($context['feedback'])) {
            dispatch(new \App\Jobs\SendFeedback($campaignIdentifier, $context['feedback']));
        }

        return view('public.feedback', $context);
    }



    public function read($campaignIdentifier)
    {
        $context = \App\Subscriber::resolveCampaignIdentifier($campaignIdentifier);

        return $context['campaign']->renderForSubscriber($context['subscriber']);
    }

    public function subscribeList($listIdentifier)
    {
        $list = \App\Lst::getByIdentifier($listIdentifier);
        $context['list'] = $list;

        if(request()->getMethod()=='POST') {

            $recaptchaSecret = config('waitools.recaptcha.secret');
            if($recaptchaSecret) {
                $response = request()->get('g-recaptcha-response');
                $verify = \WAI\Form::verifyCaptchaReponse($response);
            }
            if($verify) {
                $subscriber = request()->except(['g-recaptcha-response', '_token']);

                $records = \App\Lst::checkRecords([$subscriber], $list->getEmailAddresses());

                $list->addSubscribers($records['added']);
                $records['skipped'] = [];
                $records['updated'] = [];
                foreach ($records['existing'] as $record) {
                    if (\App\Subscriber::updateRecord($record)) {
                        $records['updated'][$record['email']] = $record;
                    } else {
                        $records['skipped'][$record['email']] = $record;
                    }
                }

                $context['records'] = $records;
                return view('public.subscribed', $context);
            }
        } else {
            return view('public.subscribe', $context);
        }
    }

    public function subscribeListApplication($listIdentifier, $applicationIdentifier)
    {
        $list = \App\Lst::getByIdentifier($listIdentifier);
        $application = \App\Application::getByIdentifier($applicationIdentifier);
        $campaign = $application->latestCampaignSent();


        $context['list'] = $list;
        $context['application'] = $application;
        $context['campaign'] = $campaign;

        if(request()->getMethod()=='POST') {

            $recaptchaSecret = config('waitools.recaptcha.secret');
            if($recaptchaSecret) {
                $response = request()->get('g-recaptcha-response');
                $verify = \WAI\Form::verifyCaptchaReponse($response);
            }
            if($verify) {
                $subscriber = request()->except(['g-recaptcha-response', '_token']);

                $records = \App\Lst::checkRecords([$subscriber], $list->getEmailAddresses());

                $list->addSubscribers($records['added']);
                $records['skipped'] = [];
                $records['updated'] = [];
                foreach ($records['existing'] as $record) {
                    if (\App\Subscriber::updateRecord($record)) {
                        $records['updated'][$record['email']] = $record;
                    } else {
                        $records['skipped'][$record['email']] = $record;
                    }
                }

                $context['records'] = $records;
                return view('public.subscribed', $context);
            }
        } else {
            return view('public.subscribe', $context);
        }
    }
}
