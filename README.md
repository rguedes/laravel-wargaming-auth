# Wargaming authentication for laravel 5

This package is a Laravel 5 service provider which provides support for Wargaming OpenID and is very easy to integrate with any project that requires Wargaming authentication.

## Installation Via Composer
Add this to your `composer.json` file, in the require object:

```javascript
"rguedes/laravel-wargaming-auth": "dev-master"
```
and this:
```javascript
"repositories": [
        {
            "type":"vcs",
            "url": "https://github.com/rguedes/laravel-wargaming-auth.git"
        }
]
```

After that, run `composer install` to install the package.

Add the service provider to `app/config/app.php`, within the `providers` array.

```php
'providers' => [
	// ...
	Rguedes\LaravelWargamingAuth\WargamingServiceProvider::class,
]
```

Lastly, publish the config file.

```
php artisan vendor:publish
```
## Usage example
In `config/wargaming-auth.php`
```php
return [

    /*
     * Redirect URL after login
     */
    'redirect_url' => '/login',
    /*
     *  API Key (https://developers.wargaming.net/applications)
     */
    'api_key' => 'Your API Key',
    /*
     * Is using https?
     */
    'https' => false
];

```
In `routes/web.php`
```php
Route::get('login', 'AuthController@login')->name('login');
```
**Note:** if you want to keep using Laravel's default logout route, add the following as well:
```php
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
```
In `AuthController`
```php
namespace App\Http\Controllers;

use Rguedes\LaravelWargamingAuth\WargamingAuth;
use App\User;
use Auth;

class AuthController extends Controller
{
    /**
     * @var WargamingAuth
     */
    private $wargaming;

    public function __construct(WargamingAuth $wargaming)
    {
        $this->wargaming = $wargaming;
    }

    public function login()
    {
        if ($this->wargaming->validate()) {
            $info = $this->wargaming->getUserInfo();
            if (!is_null($info)) {
                $user = User::where('wargamingid', $info->account_id)->first();
                if (is_null($user)) {
                    $user = User::create([
                        'username' => $info->nickname,
                        'wargamingid'  => $info->account_id
                    ]);
                }
            	Auth::login($user, true);
            	return redirect('/'); // redirect to site
            }
        }
        return $this->wargaming->redirect(); // redirect to Wargaming login page
    }
}

```
