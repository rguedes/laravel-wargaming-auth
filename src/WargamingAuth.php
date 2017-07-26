<?php namespace Rguedes\LaravelWargamingAuth;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client as GuzzleClient;

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
    const WARGAMING_INFO_URL = 'https://api.worldoftanks.eu/wot/account/info/?application_id=%s&account_id=%s&access_token=%s';

    /**
     * @var string
     */
    const WARGAMING_CLAN_INFO_URL = 'https://api.worldoftanks.eu/wgn/clans/membersinfo/?application_id=%s&account_id=%s';

    /**
     * @var string
     */
    const WARGAMING_CLAN_MEMBERS_URL = 'https://api.worldoftanks.eu/wgn/clans/info/?application_id=%s&clan_id=%s&fields=members';

    /**
     * @var string
     */
    const WARGAMING_LOGOUT_URL = 'https://api.worldoftanks.eu/wot/auth/logout/';

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
        $date = $this->getExpirateDate();
        $now = time();
        if(is_null($date) || $date < $now)
            return false;

        $results = $this->getWargamingUserInfo();

        return is_array($results) && !is_null($results['private']);
    }

    public function getWargamingUserInfo(){
        $this->loadWargamingID();

        if (is_null($this->getWargamingId()) || is_null($this->getWargamingToken())) {
            return false;
        }
        $response = $this->guzzleClient->get(sprintf(self::WARGAMING_INFO_URL, Config::get('wargaming-auth.api_key'), $this->getWargamingId(), $this->getWargamingToken()));
        return $this->parseResults($response->getBody());
    }

    public function getWargamingUserClanInfo()
    {

        $this->loadWargamingID();

        if (is_null($this->wargamingId)) {
            return false;
        }

        $response = $this->guzzleClient->get(sprintf(self::WARGAMING_CLAN_INFO_URL, Config::get('wargaming-auth.api_key'), $this->wargamingId));
        return $this->parseResults($response->getBody());
    }

    public function getWargamingClanMembers($clanId = null)
    {
        $this->loadWargamingID();

        if (is_null($clanId)) {
            return false;
        }

        $response = $this->guzzleClient->get(sprintf(self::WARGAMING_CLAN_MEMBERS_URL, Config::get('wargaming-auth.api_key'), $clanId));
        return current(current(json_decode($response->getBody(), true)['data']));
    }

    public function getLogout()
    {
        $this->loadWargamingID();

        if (is_null($this->getWargamingToken())) {
            return false;
        }
        $response = $this->guzzleClient->post(self::WARGAMING_LOGOUT_URL,
            ['form_params' => ['application_id' => Config::get('wargaming-auth.api_key'), 'access_token'=>$this->getWargamingToken()]]);
        $result = json_decode($response->getBody(), true);

        if($result['status'] == "ok"){
            app('session')->forget('wargamingId');
            app('session')->forget('wargamingToken');
            return true;
        }
        return false;
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
     * @return array | Redirector
     */
    public function parseResults($results)
    {
        $results = json_decode($results, true);
        if($results['status'] == "error" && $results['error']['message'] == "INVALID_ACCESS_TOKEN"){
            return $this->redirect();
        }
        $this->wargamingInfo = $results['data'][$this->wargamingId];
        return $results['data'][$this->wargamingId];
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
     * @return WargamingAuth
     */
    public function loadWargamingID()
    {
        $this->wargamingId = session('wargamingId', null);
        $this->wargamingToken = session('wargamingToken', null);
        return $this;
    }

    /**
     * Get user data from wargaming api
     *
     * @return void | Redirector
     */
    public function parseInfo($info)
    {
        if (is_null($info)) return;

        if($info['status']=="ok"){
            $this->setWargamingId($info['account_id']);
            $this->setWargamingToken($info['access_token']);
            $this->setExpirateDate($info['expires_at']);
            $this->wargamingLogin = $info;
        }else{
            return $this->redirect();
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
     * Sets the wargaming id
     *
     * @return WargamingAuth
     */
    public function setWargamingId($id)
    {
        session(['wargamingId'=>$id]);
        $this->wargamingId = $id;
        return $this;
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

    /**
     * Set the wargaming token
     *
     * @return WargamingAuth
     */
    public function setWargamingToken($token)
    {
        $this->wargamingToken= $token;
        session(['wargamingToken'=>$token]);
        return $this;
    }


    /**
     * Returns the wargaming token
     *
     * @return bool|string
     */
    public function getExpirateDate()
    {
        return session('expirateAt', null);
    }

    /**
     * Set the wargaming token
     *
     * @return WargamingAuth
     */
    public function setExpirateDate($date)
    {
        session(['expirateAt'=>$date]);
        return $this;
    }

}
