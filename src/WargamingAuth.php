<?php namespace Rguedes\LaravelWargamingAuth;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Fluent;
use Illuminate\Support\Facades\Cookie;

class WargamingAuth implements WargamingAuthInterface
{

    /**
     * @var integer|null
     */
    public $wargamingId = null;

    /**
     * @var integer|null
     */
    public $wargamingToken = null;

    /**
     * @var WargamingInfo
     */
    public $wargamingInfo = null;
    
    /**
     * @var WargamingLogin
     */
    public $wargamingLogin = null;

    /**
     * @var string
     */
    public $authUrl;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var GuzzleClient
     */
    private $guzzleClient;

    /**
     * @var string
     */
    const OPENID_URL = 'https://api.worldoftanks.eu/wot/auth/login/';

    /**
     * @var string
     */
    const WARGAMING_INFO_URL = 'https://api.worldoftanks.eu/wgn/account/info/?application_id=%s&account_id=%s&access_token=%s';

    /**
     * Create a new WargamingAuth instance
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->loadWargamingID();
        $this->authUrl = $this->buildUrl(url(Config::get('wargaming-auth.redirect_url'), [], Config::get('wargaming-auth.https')));
        $this->guzzleClient  = new GuzzleClient;
    }

    /**
     * Validates if the request object has required stream attributes.
     *
     * @return bool
     */
    private function requestIsValid()
    {
        return $this->request->has('application_id');
    }

    /**
     * Checks the wargaming login
     *
     * @param bool $parseInfo whether to parse info
     * @return bool
     */
    public function validate($parseInfo = true)
    {
        $this->loadWargamingID();
        if (is_null($this->wargamingId) || is_null($this->wargamingToken)) {
            return false;
        }

        $response = $this->guzzleClient->get(sprintf(self::WARGAMING_INFO_URL, Config::get('wargaming-auth.api_key'), $this->wargamingId, $this->wargamingToken));

        $results = $this->parseResults($response->getBody());
        return is_array($results) && !is_null($results['private']);
    }

    public function loadWargamingInfo(){
        if($this->validate()){
            return $this->getUserInfo();
        }
    }

    /**
     * Get param list for openId validation
     *
     * @return array
     */
    public function getParams()
    {
        $params = [
            'application_id' => Config::get('wargaming-auth.api_key'),
            'access_token' => '',
            'account_id' => $this->getWargamingId()
        ];
        return $params;
    }

    /**
     * Parse openID reponse to fluent object
     *
     * @param  string $results wg reponse body
     * @return Fluent
     */
    public function parseResults($results)
    {
        $results = json_decode($results, true);
        $this->wargamingInfo = $results['data'][$this->wargamingId];
        return $results['data'][$this->wargamingId];


        $parsed = [];
        $lines = explode("\n", $results);

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $line = explode(':', $line, 2);
            $parsed[$line[0]] = $line[1];
        }

        return new Fluent($parsed);
    }

    /**
     * Validates a given URL, ensuring it contains the http or https URI Scheme
     *
     * @param string $url
     *
     * @return bool
     */
    private function validateUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    /**
     * Build the Wargaming login URL
     *
     * @param string $return A custom return to URL
     *
     * @return string
     */
    private function buildUrl($return = null)
    {
        if (is_null($return)) {
            $return = url('/', [], Config::get('wargaming-auth.https'));
        }
        if (!is_null($return) && !$this->validateUrl($return)) {
            throw new Exception('The return URL must be a valid URL with a URI Scheme or http or https.');
        }

        $params = array(
            'application_id' => Config::get('wargaming-auth.api_key'),
            'redirect_uri'     => $return
        );

        return self::OPENID_URL . '?' . http_build_query($params, '', '&');
    }

    /**
     * Returns the redirect response to login
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function redirect()
    {
        return redirect($this->getAuthUrl());
    }

    /**
     * Parse the wargamingID from the OpenID response
     *
     * @return void
     */
    public function loadWargamingID()
    {
        $this->wargamingId = session('wargamingId', null);
        $this->wargamingToken = session('wargamingToken', null);
    }

    /**
     * Get user data from wargaming api
     *
     * @return void
     */
    public function parseInfo($info)
    {
        if (is_null($info)) return;

        if($info['status']=="ok"){
            session(['wargamingId'=>$info['account_id']]);
            session(['wargamingToken'=>$info['access_token']]);
            $this->wargamingId = $info['account_id'];
            $this->wargamingToken = $info['access_token'];
            $this->wargamingLogin = $info;
        }
    }

    /**
     * Returns the login url
     *
     * @return string
     */
    public function getAuthUrl()
    {
        return $this->authUrl;
    }

    /**
     * Returns the WargamingUser info
     *
     * @return WargamingInfo
     */
    public function getUserInfo()
    {
        return $this->wargamingInfo;
    }

    /**
     * Returns the wargaming id
     *
     * @return bool|string
     */
    public function getWargamingId()
    {
        return $this->wargamingId;
    }

    /**
     * Returns the wargaming token
     *
     * @return bool|string
     */
    public function getWargamingToken()
    {
        return $this->wargamingToken;
    }

}
