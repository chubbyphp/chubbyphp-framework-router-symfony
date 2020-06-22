# chubbyphp-framework-router-symfony

[![Build Status](https://api.travis-ci.org/chubbyphp/chubbyphp-framework-router-symfony.png?branch=master)](https://travis-ci.org/chubbyphp/chubbyphp-framework-router-symfony)
[![Coverage Status](https://coveralls.io/repos/github/chubbyphp/chubbyphp-framework-router-symfony/badge.svg?branch=master)](https://coveralls.io/github/chubbyphp/chubbyphp-framework-router-symfony?branch=master)
[![Total Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-symfony/downloads.png)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony)
[![Monthly Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-symfony/d/monthly)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony)
[![Latest Stable Version](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-symfony/v/stable.png)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony)
[![Latest Unstable Version](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-symfony/v/unstable)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony)

## Description

Symfony Router implementation for [chubbyphp-framework][1].

## Requirements

 * php: ^7.2
 * [chubbyphp/chubbyphp-framework][1]: ^3.0
 * [symfony/expression-language][2]: ^4.3|^5.0
 * [symfony/routing][3]: ^4.3|^5.0

## Installation

Through [Composer](http://getcomposer.org) as [chubbyphp/chubbyphp-framework-router-symfony][10].

```bash
composer require chubbyphp/chubbyphp-framework-router-symfony "^1.0"
```

## Usage

```php
<?php

declare(strict_types=1);

namespace App;

use Chubbyphp\Framework\Application;
use Chubbyphp\Framework\ErrorHandler;
use Chubbyphp\Framework\Middleware\ExceptionMiddleware;
use Chubbyphp\Framework\Middleware\RouterMiddleware;
use Chubbyphp\Framework\RequestHandler\CallbackRequestHandler;
use Chubbyphp\Framework\Router\Symfony\Router;
use Chubbyphp\Framework\Router\Route;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

$loader = require __DIR__.'/vendor/autoload.php';

set_error_handler([new ErrorHandler(), 'errorToException']);

$responseFactory = new ResponseFactory();

$app = new Application([
    new ExceptionMiddleware($responseFactory, true),
    new RouterMiddleware(new SymfonyRouter([
        Route::get('/hello/{name}', 'hello', new CallbackRequestHandler(
            function (ServerRequestInterface $request) use ($responseFactory) {
                $name = $request->getAttribute('name');
                $response = $responseFactory->createResponse();
                $response->getBody()->write(sprintf('Hello, %s', $name));

                return $response;
            }
        ))->pathOptions([SymfonyRouter::PATH_REQUIREMENTS => ['name' => '[a-z]+']])
    ]), $responseFactory),
]);

$app->emit($app->handle((new ServerRequestFactory())->createFromGlobals()));
```

## Copyright

Dominik Zogg 2020

[1]: https://packagist.org/packages/chubbyphp/chubbyphp-framework
[2]: https://packagist.org/packages/symfony/expression-language
[3]: https://packagist.org/packages/symfony/routing
[10]: https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony
