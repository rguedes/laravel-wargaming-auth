<?php namespace Rguedes\LaravelWargamingAuth;

use Illuminate\Support\Fluent;

class WargamingInfo extends Fluent
{

    public function __construct($data)
    {
        $wargamingidID = isset($data['account_id']) ? $data['account_id'] : null;
        $wargamingidToken = isset($data['wargamingtoken']) ? $data['wargamingtoken'] : null;
        unset($data['wargamingid']);
        unset($data['wargamingtoken']);

        parent::__construct($data);

        $this->attributes['wargamingid'] = $wargamingidID;
        $this->attributes['wargamingtoken'] = $wargamingidToken;
    }
}
