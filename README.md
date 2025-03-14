# chubbyphp-framework-router-symfony


[![CI](https://github.com/chubbyphp/chubbyphp-framework-router-symfony/actions/workflows/ci.yml/badge.svg)](https://github.com/chubbyphp/chubbyphp-framework-router-symfony/actions/workflows/ci.yml)
[![Coverage Status](https://coveralls.io/repos/github/chubbyphp/chubbyphp-framework-router-symfony/badge.svg?branch=master)](https://coveralls.io/github/chubbyphp/chubbyphp-framework-router-symfony?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fchubbyphp%2Fchubbyphp-framework-router-symfony%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/chubbyphp/chubbyphp-framework-router-symfony/master)
[![Latest Stable Version](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-symfony/v)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony)
[![Total Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-symfony/downloads)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony)
[![Monthly Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-symfony/d/monthly)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony)

[![bugs](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=bugs)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![code_smells](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=code_smells)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![coverage](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=coverage)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![duplicated_lines_density](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=duplicated_lines_density)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![ncloc](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=ncloc)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![sqale_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![alert_status](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=alert_status)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![reliability_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![security_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=security_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![sqale_index](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=sqale_index)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)
[![vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-symfony&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-symfony)

## Description

Symfony Router implementation for [chubbyphp-framework][1].

## Requirements

 * php: ^8.2
 * [chubbyphp/chubbyphp-framework][1]: ^5.2
 * [chubbyphp/chubbyphp-http-exception][2]: ^1.2
 * [psr/http-message][3]: ^1.1|^2.0
 * [symfony/expression-language][4]: ^5.4.45|^6.4.13|^7.2
 * [symfony/routing][5]: ^5.4.48|^6.4.18|^7.2

## Installation

Through [Composer](http://getcomposer.org) as [chubbyphp/chubbyphp-framework-router-symfony][10].

```bash
composer require chubbyphp/chubbyphp-framework-router-symfony "^2.2"
```

## Usage

```php
<?php

declare(strict_types=1);

namespace App;

use Chubbyphp\Framework\Application;
use Chubbyphp\Framework\Middleware\ExceptionMiddleware;
use Chubbyphp\Framework\Middleware\RouterMiddleware;
use Chubbyphp\Framework\RequestHandler\CallbackRequestHandler;
use Chubbyphp\Framework\Router\Symfony\Router;
use Chubbyphp\Framework\Router\Route;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

$loader = require __DIR__.'/vendor/autoload.php';

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
        ), [], [SymfonyRouter::PATH_REQUIREMENTS => ['name' => '[a-z]+']])
    ])),
]);

$app->emit($app->handle((new ServerRequestFactory())->createFromGlobals()));
```

## Copyright

2025 Dominik Zogg

[1]: https://packagist.org/packages/chubbyphp/chubbyphp-framework
[2]: https://packagist.org/packages/chubbyphp/chubbyphp-http-exception
[3]: https://packagist.org/packages/psr/http-message
[4]: https://packagist.org/packages/symfony/expression-language
[5]: https://packagist.org/packages/symfony/routing
[10]: https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-symfony
