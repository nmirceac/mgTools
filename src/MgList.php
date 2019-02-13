<?php namespace MgTools;

use Illuminate\Database\Eloquent\Model;

class MgList extends Model
{
    const SUBSCRIBER_UNSUBSCRIBED=-2;
    const SUBSCRIBER_PAUSED=-1;
    const SUBSCRIBER_NEW=0;
    const SUBSCRIBER_ACTIVE=1;
    const SUBSCRIBER_READER=2;
    const SUBSCRIBER_VISTOR=3;

    protected $appends = [
        'active_subscribers_count',
        'subscribers_count',
        'subscribe_url',
        'subscribe_js_url',
    ];

    public function getDetailsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details']=json_encode($value);
    }

    public function getSettingsAttribute($value)
    {
        $settings = json_decode($value, true);
        if(!isset($settings['enabled'])) {
            $settings['enabled'] = false;
        }

        if(!isset($settings['popupDebug'])) {
            $settings['popupDebug'] = false;
        }

        if(!isset($settings['popupEmailOnly'])) {
            $settings['popupEmailOnly'] = false;
        }

        if(!isset($settings['popupInvertedInputs'])) {
            $settings['popupInvertedInputs'] = false;
        }

        if(!isset($settings['popupBackgroundColor'])) {
            $settings['popupBackgroundColor'] = '#dddddd';
        }

        if(!isset($settings['popupResizeBackgroundImage'])) {
            $settings['popupResizeBackgroundImage'] = true;
        }

        if(!isset($settings['popupBackgroundImage'])) {
            $settings['popupBackgroundImage'] = '';
        }

        if(!isset($settings['popupLogo'])) {
            $settings['popupLogo'] = '';
        }

        if(!isset($settings['popupCloseColor'])) {
            $settings['popupCloseColor'] = '#dddddd';
        }

        if(!isset($settings['popupCloseColorHover'])) {
            $settings['popupCloseColorHover'] = '#ffffff';
        }

        if(!isset($settings['popupHeadingColor'])) {
            $settings['popupHeadingColor'] = '#333333';
        }

        if(!isset($settings['popupTextColor'])) {
            $settings['popupTextColor'] = '#222222';
        }

        if(!isset($settings['popupSubmitButtonBackgroundColor'])) {
            $settings['popupSubmitButtonBackgroundColor'] = '#336699';
        }

        if(!isset($settings['popupSubmitButtonBackgroundColorHover'])) {
            $settings['popupSubmitButtonBackgroundColorHover'] = '#4477aa';
        }

        if(!isset($settings['popupSubmitButtonTextColor'])) {
            $settings['popupSubmitButtonTextColor'] = '#dddddd';
        }

        if(!isset($settings['popupTriggerDelay'])) {
            $settings['popupTriggerDelay'] = 5;
        }

        if(!isset($settings['popupTriggerOnScroll'])) {
            $settings['popupTriggerOnScroll'] = false;
        }

        if(!isset($settings['popupFooterText'])) {
            $settings['popupFooterText'] = '';
        }

        return $settings;
    }

    public function setSettingsAttribute($value)
    {
        $this->attributes['settings']=json_encode($value);
    }

    public function subscribers()
    {
        return $this->belongsToMany(MgSubscriber::class, 'mg_list_subscriber')->withPivot([
                'added',
                'status',
                'counter',
        ]);
    }

    public function getActiveSubscribers()
    {
        return $this->subscribers()->where('status', '>=', 0);
    }

    public static function getSubscribersForListIds(array $listIds)
    {
        return MgSubscriber::query()
            ->with('lists')
            ->whereHas('lists', function($q) use ($listIds) {
                $q->whereIn('id', $listIds);
            });
    }

    public static function getActiveSubscribersForListIds(array $listIds)
    {
        return MgSubscriber::query()
            ->with('active_lists')
            ->whereHas('active_lists', function($q) use ($listIds) {
                $q->whereIn('id', $listIds);
            });
    }

    public function getSubscribersCountAttribute($value)
    {
        return $this->subscribers()->count();
    }

    public function setSubscribersCountAttribute()
    {
        return;
    }

    public function getActiveSubscribersCountAttribute($value)
    {
        return $this->getActiveSubscribers()->count();
    }

    public function setActiveSubscribersCountAttribute()
    {
        return;
    }

    public function getEmailAddresses()
    {
        return $this->subscribers()->pluck('email')->toArray();
    }


    public function addSubscribers($subscribers=[])
    {
        foreach($subscribers as $subscriber) {
            $subscriber = MgSubscriber::findOrCreate($subscriber);
            if(!$this->subscribers->contains($subscriber->id)) {
                $this->subscribers()->attach($subscriber->id, [
                        'added'=>date('Y-m-d H:i:s'),
                        'status'=>0,
                        'counter'=>0,
                    ]
                );
            }
        }
    }

    public static function domainHasMxRecords($domain)
    {
        $cacheKey = __FUNCTION__.'-'.md5(json_encode(func_get_args()));
        $data = \Cache::get($cacheKey);
        if(is_null($data)) {
            $mx=[];
            getmxrr($domain, $mx);
            if(empty($mx)) {
                $data=false;
            } else {
                $data=true;
            }
            \Cache::put($cacheKey, $data, \Carbon\Carbon::now()->addHours(12));
        }

        return $data;
    }

    public static function emailHasMxRecords($emailAddress)
    {
        $domain = substr($emailAddress, 1+strrpos($emailAddress, '@'));
        return self::domainHasMxRecords($domain);
    }

    public static function parseImportText($importText, $existingEmailAddresses=[])
    {
        $records = explode(PHP_EOL, $importText);
        $added = [];
        $invalid = [];
        $existing = [];
        foreach($records as $record) {
            $record = trim($record);
            if(empty($record)) {
                continue;
            }
            $record = str_getcsv($record);
            $email = strtolower(trim(array_shift($record)));

            $name = '';
            if(!empty($record)) {
                $name = trim(preg_replace( '/\s+/', ' ', implode(' ', $record)));
            }

            if(in_array($email, $existingEmailAddresses)) {
                $existing[$email] = ['email' => $email, 'name' => $name];
            } else {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    if(self::emailHasMxRecords($email)) {
                        $added[$email] = ['email' => $email, 'name' => $name];
                    } else {
                        $invalid[$email] = ['email' => $email, 'name' => $name, 'problem'=>'Domain has no MX records'];
                    }
                } else {
                    $invalid[$email] = ['email' => $email, 'name' => $name, 'problem'=>'Invalid email address format'];
                }
            }
        }

        return ['added'=>$added, 'invalid'=>$invalid, 'existing'=>$existing];
    }

    public static function checkRecords($records, $existingEmailAddresses=[])
    {
        $added = [];
        $invalid = [];
        $existing = [];
        foreach($records as $record) {
            $email = strtolower(trim($record['email']));
            $record['email'] = $email;

            if(in_array($email, $existingEmailAddresses)) {
                $existing[$email] = $record;
            } else {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    if(self::emailHasMxRecords($email)) {
                        $added[$email] = $record;
                    } else {
                        $invalid[$email] = array_merge($record, ['problem'=>'Domain has no MX records']);
                    }
                } else {
                    $invalid[$email] = array_merge($record, ['problem'=>'Invalid email address format']);
                }
            }
        }

        return ['added'=>$added, 'invalid'=>$invalid, 'existing'=>$existing];
    }

    public function importText($importText)
    {
        $records = self::parseImportCsv($importText, $this->getEmailAddresses());
//        $records = self::validateRecords($records);

        $records['skipped'] = [];
        $records['updated'] = [];

//        dd($records);

        $this->addSubscribers($records['added']);

        foreach($records['existing'] as $record) {
            $subscriber = Subscriber::findByEmail($record['email']);
            $details = $subscriber->details;
            if(!empty($record['name']) and $record['name']!=$details['name']) {
                $records['updated'][] = $record;
                $details['name'] = $record['name'];
                $subscriber->details = $details;
                $subscriber->save();
            } else {
                $records['skipped'][] = $record;
            }
        }

        return $records;
    }

    public static function parseImportCsv($csvText, $existingEmailAddresses=[], $skipDomainCheck=false)
    {
        $csvText = trim($csvText);
        $rows = explode(PHP_EOL, $csvText);

        $records = [];

        $header = ['email', 'firstname', 'lastname'];
        if(strpos($rows[0], '@')===false) {
            $header = str_getcsv(strtolower(trim($rows[0])));
            $rows = array_slice($rows, 1);

            $emailFound = false;
            $firstnameFound = false;
            $lastnameFound = false;
            $phoneFound = false;

            foreach($header as $part=>$value) {
                if(stripos($value, 'email')!==false and !$emailFound) {
                    $header[$part] = 'email';
                    $emailFound = true;
                }
                if(stripos($value, 'first')!==false and !$firstnameFound) {
                    $header[$part] = 'firstname';
                    $firstnameFound = true;
                }
                if((stripos($value, 'last')!==false or stripos($value, 'sur')!==false) and !$lastnameFound) {
                    $header[$part] = 'lastname';
                    $lastnameFound = true;
                }
                if((stripos($value, 'phone')!==false or stripos($value, 'number')!==false) and !$phoneFound) {
                    $header[$part] = 'phone';
                    $phoneFound = true;
                }
            }
        }

        foreach($rows as $row) {
            $row = trim($row);
            if(empty($row)) {
                continue;
            }
            $row = str_getcsv(trim($row));
            if(empty($row)) {
                continue;
            }

            foreach($row as $param=>$value) {
                if($param == 'email') {
                    $value = strtolower($value);
                }
                $row[$param] = trim($value);
            }



            foreach($header as $key=>$param) {
                if(!isset($row[$key])) {
                    $row[$key] = '';
                }
            }

            $records[] = array_combine($header, $row);
        }

        $added = [];
        $invalid = [];
        $existing = [];
        foreach($records as $record) {
            if(in_array($record['email'], $existingEmailAddresses)) {
                $existing[$record['email']] = $record;
            } else {
                if (filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                    if($skipDomainCheck or self::emailHasMxRecords($record['email'])) {
                        $added[$record['email']] = $record;
                    } else {
                        $invalid[$record['email']] = array_merge($record, ['problem'=>'Domain has no MX records']);
                    }
                } else {
                    $invalid[$record['email']] = array_merge($record, ['problem'=>'Invalid email address format']);
                }
            }
        }

        return ['added'=>array_values($added), 'invalid'=>array_values($invalid), 'existing'=>array_values($existing)];
    }

    public static function validateRecords($records)
    {
        if(!isset($records['invalid'])) {
            $records['invalid'] = [];
        }

        foreach($records['added'] as $key=>$record) {
            if (filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                if(self::emailHasMxRecords($record['email'])) {
                    continue;
                } else {
                    unset($records['added'][$key]);
                    $records['invalid'][$record['email']] = array_merge($record, ['problem'=>'Domain has no MX records']);
                }
            } else {
                $records['invalid'][$record['email']] = array_merge($record, ['problem'=>'Domain has no MX records']);
            }
        }

        $records['added'] = array_values($records['added']);
        $records['invalid'] = array_values($records['invalid']);

        return $records;
    }

    public function importCsv($csvText)
    {
        $records = self::parseImportCsv($csvText, $this->getEmailAddresses());

        $records['skipped'] = [];
        $records['updated'] = [];

        $this->addSubscribers($records['added']);

        foreach($records['existing'] as $record) {
            if(Subscriber::updateRecord($record)) {
                $records['updated'][] = $record;
            } else {
                $records['skipped'][] = $record;
            }
        }

        return $records;
    }

    public function importRecords($records)
    {
        $records['skipped'] = [];
        $records['updated'] = [];

        $this->addSubscribers($records['added'], true);

        foreach($records['existing'] as $record) {
            if(Subscriber::updateRecord($record)) {
                $records['updated'][] = $record;
            } else {
                $records['skipped'][] = $record;
            }
        }

        return $records;
    }

    public function getIdentifierAttribute()
    {
        return $this->getIdentifier();
    }

    public function setIdentifierAttribute()
    {
        return;
    }

    public function getSubscribeUrlAttribute()
    {
        return route(config('mailgun.router.namedPrefix').'.subscribeList', $this->identifier);
    }

    public function getSubscribeJsUrlAttribute()
    {
        return route(config('mailgun.router.namedPrefix').'.api.subscribeJsGet').'?list='.$this->identifier;
    }

    public function getIdentifier()
    {
        return $this->id.md5($this->id.'-'.$this->created_at);
    }

    public static function getByIdentifier(string $identifier)
    {
        if(strlen($identifier) < 33) {
            throw new \Exception('Invalid identifier');
        }

        $id = substr($identifier, 0, -32);
        $list = self::find($id);

        if(!$list) {
            throw new \Exception('Wrong identifier');
        }

        if($list->identifier==$identifier) {
            return $list;
        } else {
            throw new \Exception('Fake identifier');
        }
    }

    public static function arrayToCsv($array)
    {
        if(!empty($array)) {
            $fp = fopen('php://temp', 'r+');
            fputcsv($fp, array_keys($array[0]));
            foreach($array as $record) {
                fputcsv($fp, $record);
            }
            rewind($fp);
            $csv = fread($fp, 1048576);
            fclose($fp);
            return $csv;
        }
    }

}
