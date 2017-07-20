<?php namespace Rguedes\LaravelWargamingAuth;

use Illuminate\Support\Fluent;

class WargamingInfo extends Fluent
{

    public function __construct($data)
    {
        $wargamingidID = isset($data['wargamingid']) ? $data['wargamingid'] : null;
        unset($data['wargamingid']);

        parent::__construct($data);

        $this->attributes['wargamingid'] = $wargamingidID;
    }
}
