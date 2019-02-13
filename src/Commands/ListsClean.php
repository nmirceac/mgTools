<?php

namespace App\Console\Commands;

use App\Lst;
use App\Subscriber;
use Illuminate\Console\Command;

class ListsClean extends Command
{

    protected $signature = 'lists:clean';


    protected $description = 'Cleaning lists of rotten subscribers';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $excluded = storage_path('exclude.csv');
        $excludedEmails = [];
        if(file_exists($excluded)) {
            $handle = fopen($excluded, 'r');
            while($row = fgetcsv($handle)) {
                $email = strtolower(trim(end($row)));
                if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $excludedEmails[] = $email;
                }
            }
        }

        foreach(\App\Lst::all() as $list) {
            if(isset($list->settings['enabled']) and $list->settings['enabled']==false) {
                continue;
            }


            $this->info('Checking list #'.$list->id.' - '.$list->name);
            $report = [];
            $subscribersCount = $list->subscribers->count();
            foreach($list->subscribers as $key=>$subscriber) {
                $number = $key+1;
                $percentage = round($number / $subscribersCount * 100, 1);
                $messageCount = $subscriber->messages()->count();
                $lastMessage = $subscriber->lastMessage();

                if ($subscriber->state == Subscriber::SUBSCRIBER_STATE_NEW and !is_null($lastMessage) and $lastMessage->delivered) {
                    $subscriber->markAsConfirmed();
                    continue;
                }

                $this->comment('Checking list #'.$list->id.' subscriber #'.$subscriber->id.' ('.$messageCount.' messages) - '.$subscriber->email.' - '.$number.'/'.$subscribersCount.' ('.$percentage.'%)');

                if(in_array($subscriber->email, $excludedEmails)) {
                    $subscriber->markAsBadSubscriber();
                    $message = 'Previously suppressed - removing '.$subscriber->email;
                    $this->error($message);
                    $report[] = $message;
                    continue;
                }

                if(is_null($lastMessage)) {
                    $verify = Subscriber::testEmailAddress($subscriber->email);
                    if($verify->status=='invalid') {
                        $subscriber->markAsBadSubscriber();
                        $message = 'New but dangerous - removing '.$subscriber->email;
                        $this->error($message);
                        $report[] = $message;
                    }
                    continue;
                }

                if ($lastMessage->bounced) {
                    if($messageCount == 1) {
                        $subscriber->markAsBadSubscriber();
                        $message = 'First delivery bounce - removing '.$subscriber->email;
                        $this->error($message);
                        $report[] = $message;
                    } else {
                        $secondToLastMessage = $subscriber->secondToLastMessage();
                        if(!$secondToLastMessage->delivered) {
                            $message = 'Bounced twice - removing '.$subscriber->email;
                            $this->error($message);
                            $report[] = $message;
                            $subscriber->markAsBadSubscriber();
                        } else {
                            $message = 'Bounced once after successful delivery - keeping for now '.$subscriber->email;
                            $this->comment($message);
                        }
                    }
                    continue;
                }

                if ($lastMessage->dropped) {
                    if($messageCount == 1) {
                        $subscriber->markAsBadSubscriber();
                        $verify = Subscriber::testEmailAddress($subscriber->email);
                        if($verify->status=='valid') {
                            $message = 'Dropped but keeping for now '.$subscriber->email;
                            $this->comment($message);
                        } else {
                            $subscriber->markAsBadSubscriber();
                            $message = 'Dropped and dangerous - removing '.$subscriber->email;
                            $this->error($message);
                            $report[] = $message;
                        }
                    } else {
                        $secondToLastMessage = $subscriber->secondToLastMessage();
                        if(!$secondToLastMessage->delivered) {
                            $message = 'Dropped twice - removing '.$subscriber->email;
                            $this->error($message);
                            $report[] = $message;
                            $subscriber->markAsBadSubscriber();
                        } else {
                            $message = 'Dropped once after successful delivery - keeping for now '.$subscriber->email;
                            $this->comment($message);

                        }
                    }
                    continue;
                }

                $this->info('Keeping '.$subscriber->email);
            }

            if(!empty($report) and \App::environment()!='testing') {
                $this->info('Mailing report for list #'.$list->id.' - '.$list->name);
                \Mail::send('notifications.cleanedList', ['report'=>$report, 'list'=>$list], function ($m) use ($report, $list) {
                    $m->from('do-not-reply@nuz.contactmedia.co.za', 'Nuz Contact Media');
                    $m->to(config('app.email'));
                    $m->subject('Finished cleaning '.$list->name.' - '.count($report).' '.
                        str_plural('action', count($report)).' taken');
                });
            }
        }
    }
}
