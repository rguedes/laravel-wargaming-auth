<?php namespace Rguedes\LaravelWargamingAuth;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Fluent;

class WargamingAuth implements WargamingAuthInterface
{

    /**
     * @var integer|null
     */
    public $wargamingId = null;

    /**
     * @var WargamingInfo
     */
    public $wargamingInfo = null;

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
    const WARGAMING_INFO_URL = 'https://api.worldoftanks.eu/wgn/account/info/?application_id=%s&account_id=%s';

    /**
     * Create a new WargamingAuth instance
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->authUrl = $this->buildUrl(url(Config::get('wargaming-auth.redirect_url'), [],
            Config::get('wargaming-auth.https')));
        $this->guzzleClient  = new GuzzleClient;
    }

    /**
     * Validates if the request object has required stream attributes.
     *
     * @return bool
     */
    private function requestIsValid()
    {
        return $this->request->has('openid_assoc_handle')
               && $this->request->has('openid_signed')
               && $this->request->has('openid_sig');
    }

    /**
     * Checks the wargaming login
     *
     * @param bool $parseInfo whether to parse info
     * @return bool
     */
    public function validate($parseInfo = true)
    {
        if (!$this->requestIsValid()) {
            return false;
        }

        $params = $this->getParams();

        $response = $this->guzzleClient->request('POST', self::OPENID_URL, [
            'form_params' => $params
        ]);

        $results = $this->parseResults($response->getBody()->getContents());

        $this->parseWargamingID();
        if ($parseInfo) $this->parseInfo();

        return $results->is_valid == "true";
    }

    /**
     * Get param list for openId validation
     *
     * @return array
     */
    public function getParams()
    {
        $params = [
            'openid.assoc_handle' => $this->request->get('openid_assoc_handle'),
            'openid.signed'       => $this->request->get('openid_signed'),
            'openid.sig'          => $this->request->get('openid_sig'),
            'openid.ns'           => 'http://specs.openid.net/auth/2.0',
            'openid.mode'         => 'check_authentication'
        ];

        $signedParams = explode(',', $this->request->get('openid_signed'));

        foreach ($signedParams as $item) {
            $value = $this->request->get('openid_' . str_replace('.', '_', $item));
            $params['openid.' . $item] = get_magic_quotes_gpc() ? stripslashes($value) : $value;
        }

        return $params;
    }

    /**
     * Parse openID reponse to fluent object
     *
     * @param  string $results openid reponse body
     * @return Fluent
     */
    public function parseResults($results)
    {
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
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => $return,
            'openid.realm'      => (Config::get('wargaming-auth.https') ? 'https' : 'http') . '://' . $this->request->server('HTTP_HOST'),
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
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
    public function parseWargamingID()
    {
        //https://api.worldoftanks.eu/wot/auth/login/?application_id=0b32e06aa1d2e132f0ca7ad6b5faa3d7
        //https://api.worldoftanks.eu/id/508431014-ptCode/confirm/?redirect_uri=https%3A%2F%2Fdevelopers.wargaming.net%2Freference%2Fall%2Fwot%2Fauth%2Flogin%2F&language=en
        preg_match("#^http://api.worldoftanks.eu/id/([0-9]{9})#", $this->request->get('openid_claimed_id'), $matches);
        $this->wargamingId = is_numeric($matches[1]) ? $matches[1] : 0;
    }

    /**
     * Get user data from wargaming api
     *
     * @return void
     */
    public function parseInfo()
    {
        if (is_null($this->wargamingId)) return;

        $reponse = $this->guzzleClient->request('GET', sprintf(self::WARGAMING_INFO_URL, Config::get('wargaming-auth.api_key'), $this->wargamingId));
        $json = json_decode($reponse->getBody(), true);

        $this->wargamingInfo = new WargamingInfo($json["data"][$this->wargamingId]);
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

}
