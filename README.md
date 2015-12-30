# nette-api

### work in progress - in early development mode

**Nette simple api library**

[![Build Status](https://travis-ci.org/tomaj/nette-api.svg)](https://travis-ci.org/tomaj/nette-api)
[![Dependency Status](https://www.versioneye.com/user/projects/567d3b10a7c90e002c0003a7/badge.svg?style=flat)](https://www.versioneye.com/user/projects/567d3b10a7c90e002c0003a7)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/tomaj/nette-api.svg)](https://packagist.org/packages/tomaj/nette-api)

## Why Nette-api

This library provide out-of-the box API solution for Nette framework. You can register APi endpoints and connect it to specified handlers. You need only implement you custom busines logic. Library provide autorization, validation and formating services for you api.

## Installation

This library requires PHP 5.4 or later. It works also on HHVM and PHP 7.0.

Recommended installation method is via Composer:

```bash
$ composer require tomaj/nette-api
```

Library is compliant with [PSR-1][], [PSR-2][], [PSR-3][] and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-3]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## How Nette-API works

First you have register library presenter for routing. In *config.neon* just add this line:

```
application:
    mapping:
        Api: Tomaj\NetteApi\Presenters\*Presenter
```

And add route to you RouterFactory:

```
$router[] = new Route('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default', $flags);
```

After that you need only register your api handlers to *apiDecider* and register ApiLink and IpDetector. This can be done also with *config.neon*:

```
- Tomaj\NetteApi\Link\ApiLink
- Tomaj\NetteApi\Misc\IpDetector
apiDecider:
		class: Tomaj\NetteApi\ApiDecider
		setup:
			- addApiHandler(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), \Tomaj\NetteApi\Authorization\NoAuthorization())
			- addApiHandler(\Tomaj\NetteApi\EndpointIdentifier('POST', 1, 'users', 'send-email'), \App\MyApi\v1\Handlers\SendEmailHandler(), \Tomaj\NetteApi\Authorization\BearerTokenAuthorization())
```

As you can see in example, you can register as many endpoints as you want with different configuration. Nette-api support api version from the beggining.

Core of the nette api is handlers. For this example you need implement 2 classes:

1. App\MyApi\v1\Handlers\UsersListingHandler
2. Tomaj\NetteApi\EndpointIdentifier

This handlers implements interface *ApiHandlerInterface* but for easier usage you can extens your handler from BaseHandler. 

```

namespace App\MyApi\v1\Handlers

class UsersListingHandler extends Basehandler
{
	private $userRepository;

	public function __construct(UsersRepository $userRepository)
	{
		parent::__construct();
		$this->userRepository = $userRepository;
	}

	public function handle($params)
	{
		$users = [] 
		foreach ($this->useRepository->all() as $user) {
			$users[] = $user->toArray();
		}
		return new ApiResponse(200, ['status' => 'ok', 'users' => $users]);
	}
}

```

This simple handler is usign UsersRepository that was created by Nette Container (so you have to register App\MyApi\v1\Handlers\UsersListingHandler in config.neon).

## Advanced use

TODO - fractal..

## Security

TODO - bearer

## Logging

TODO - enable logging

## WEB console - API tester

Nette-api contains 2 UI controls that can be used to validate you api.
It will generate listing with all API calls and also auto generate form with all api params.

All components generate bootstrap html and can be style:

You have to create components in your controller:

```
use Nette\Application\UI\Presenter;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Component\ApiConsoleControl;
use Tomaj\NetteApi\Component\ApiListingControl;

class MyPresenter extends Presenter
{
    private $apiDecider;
    
    public function __construct(ApiDecider $apiDecider)
    {
        parent::__construct();
        $this->apiDecider = $apiDecider;
    }

    public function renderShow($method, $version, $package, $apiAction)
    {
    }
    
    protected function createComponentApiListing()
    {
        $apiListing = new ApiListingControl($this, 'apiListingControl', $this->apiDecider);
        $apiListing->onClick(function($method, $version, $package, $apiAction) {
            $this->redirect('show', $method, $version, $package, $apiAction);
        });
        return $apiListing;
    }

    protected function createComponentApiConsole()
    {
        $api = $this->apiDecider->getApiHandler($this->params['method'], $this->params['version'], $this->params['package'], isset($this->params['apiAction']) ? $this->params['apiAction'] : null);
        $apiConsole = new ApiConsoleControl($this->getHttpRequest(), $api['endpoint'], $api['handler'], $api['authorization']);
        return $apiConsole;
    }
{
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email tomasmajer@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information
