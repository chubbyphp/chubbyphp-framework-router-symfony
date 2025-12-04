<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\Symfony\Unit;

use Chubbyphp\Framework\Router\Exceptions\MissingRouteByNameException;
use Chubbyphp\Framework\Router\Exceptions\RouteGenerationException;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\Symfony\Router;
use Chubbyphp\HttpException\HttpException;
use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockMethod\WithReturnSelf;
use Chubbyphp\Mock\MockObjectBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @covers \Chubbyphp\Framework\Router\Symfony\Router
 *
 * @internal
 */
final class RouterTest extends TestCase
{
    public const string UUID_PATTERN = '[0-9a-f]{8}\b-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-\b[0-9a-f]{12}';

    public function testMatchFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route1 */
        $route1 = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_create'),
            new WithReturn('getPathOptions', [], []),
            new WithReturn('getName', [], 'pet_create'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'POST'),
        ]);

        /** @var RouteInterface $route2 */
        $route2 = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], []),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturnSelf('withAttributes', [[]]),
        ]);

        $cacheFile = sys_get_temp_dir().'/symfony-'.uniqid().uniqid().'.php';

        self::assertFileDoesNotExist($cacheFile);

        $router = new Router([$route1, $route2], $cacheFile);

        self::assertFileExists($cacheFile);

        self::assertSame($route2, $router->match($request));

        unlink($cacheFile);
    }

    public function testMatchNotFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], ''),
            new WithReturn('getPath', [], '/'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getRequestTarget', [], '/'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], []),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        $router = new Router([$route]);

        try {
            $router->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Not Found', $e->getTitle());
            self::assertSame(404, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.5',
                'status' => 404,
                'title' => 'Not Found',
                'detail' => 'The page "/" you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchMethodNotAllowed(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], '?offset=1&limit=20'),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'POST'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'POST'),
            new WithReturn('getRequestTarget', [], '/api/pets?offset=1&limit=20'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], []),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        $router = new Router([$route]);

        try {
            $router->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Method Not Allowed', $e->getTitle());
            self::assertSame(405, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.6',
                'status' => 405,
                'title' => 'Method Not Allowed',
                'detail' => 'Method "POST" at path "/api/pets?offset=1&limit=20" is not allowed. Must be one of: "GET"',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchWithTokensMatch(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets/8b72750c-5306-416c-bba7-5b41f1c44791'),
            new WithReturn('getQuery', [], ''),
            new WithReturn('getPath', [], '/api/pets/8b72750c-5306-416c-bba7-5b41f1c44791'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_read'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => self::UUID_PATTERN],
            ]),
            new WithReturn('getName', [], 'pet_read'),
            new WithReturn('getPath', [], '/api/pets/{id}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturnSelf('withAttributes', [['id' => '8b72750c-5306-416c-bba7-5b41f1c44791']]),
        ]);

        $router = new Router([$route]);

        self::assertSame($route, $router->match($request));
    }

    public function testMatchWithTokensNotMatch(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets/1'),
            new WithReturn('getQuery', [], ''),
            new WithReturn('getPath', [], '/api/pets/1'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getRequestTarget', [], '/api/pets/1'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_read'),
            new WithReturn('getPathOptions', [], [Router::PATH_REQUIREMENTS => ['id' => self::UUID_PATTERN]]),
            new WithReturn('getName', [], 'pet_read'),
            new WithReturn('getPath', [], '/api/pets/{id}'),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        $router = new Router([$route]);

        try {
            $router->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Not Found', $e->getTitle());
            self::assertSame(404, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.5',
                'status' => 404,
                'title' => 'Not Found',
                'detail' => 'The page "/api/pets/1" you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testHostMatchFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], ''),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], [Router::PATH_HOST => 'localhost']),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturnSelf('withAttributes', [[]]),
        ]);

        $router = new Router([$route]);

        self::assertSame($route, $router->match($request));
    }

    public function testHostMatchNotFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], ''),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getRequestTarget', [], '/api/pets'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], [Router::PATH_HOST => 'anotherhost']),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        $router = new Router([$route]);

        try {
            $router->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Not Found', $e->getTitle());
            self::assertSame(404, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.5',
                'status' => 404,
                'title' => 'Not Found',
                'detail' => 'The page "/api/pets" you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testSchemeMatchFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], [Router::PATH_SCHEMES => ['https']]),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturnSelf('withAttributes', [[]]),
        ]);

        $router = new Router([$route]);

        self::assertSame($route, $router->match($request));
    }

    public function testSchemeMatchNotFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getRequestTarget', [], '/api/pets?key=value'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], [Router::PATH_SCHEMES => ['http']]),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        $router = new Router([$route]);

        try {
            $router->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Not Found', $e->getTitle());
            self::assertSame(404, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.5',
                'status' => 404,
                'title' => 'Not Found',
                'detail' => 'The page "/api/pets?key=value" you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testConditionMatchFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_CONDITION => "context.getQueryString() matches '/key=/'",
            ]),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturnSelf('withAttributes', [[]]),
        ]);

        $router = new Router([$route]);

        self::assertSame($route, $router->match($request));
    }

    public function testConditionMatchNotFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'localhost'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getRequestTarget', [], '/api/pets?key=value'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], [Router::PATH_CONDITION => "context.getQueryString() matches '/key1=/'"]),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        $router = new Router([$route]);

        try {
            $router->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Not Found', $e->getTitle());
            self::assertSame(404, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.5',
                'status' => 404,
                'title' => 'Not Found',
                'detail' => 'The page "/api/pets?key=value" you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testGenerateUri(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'http'),
            new WithReturn('getPort', [], 80),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'http'),
            new WithReturn('getPort', [], 10080),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'http'),
            new WithReturn('getPort', [], null),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 10443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], null),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => '\d+', 'name' => '[a-z]+'],
                Router::PATH_DEFAULTS => ['name' => null],
            ]),
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
        ]);

        $router = new Router([$route]);

        self::assertSame(
            'http://user:password@localhost/user/1',
            $router->generateUrl($request, 'user', ['id' => 1])
        );
        self::assertSame(
            'http://user:password@localhost:10080/user/1?key=value',
            $router->generateUrl($request, 'user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            'http://user:password@localhost/user/1?key=value',
            $router->generateUrl($request, 'user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            'https://user:password@localhost/user/1/sample',
            $router->generateUrl($request, 'user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            'https://user:password@localhost:10443/user/1/sample?key1=value1&key2=value2',
            $router->generateUrl(
                $request,
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
        self::assertSame(
            'https://user:password@localhost/user/1/sample?key1=value1&key2=value2',
            $router->generateUrl(
                $request,
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGenerateUriWithMissingAttribute(): void
    {
        $this->expectException(RouteGenerationException::class);
        $this->expectExceptionMessage(
            'Route generation for route "user" with path "/user/{id}/{name}" with attributes "{}" failed. Some mandatory parameters are missing ("id") to generate a URL for route "user".'
        );
        $this->expectExceptionCode(3);

        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => '\d+', 'name' => '[a-z]+'],
                Router::PATH_DEFAULTS => ['name' => null],
            ]),
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
        ]);

        $router = new Router([$route]);
        $router->generateUrl($request, 'user');
    }

    public function testGenerateUriWithNotMatchingAttribute(): void
    {
        $this->expectException(RouteGenerationException::class);
        $this->expectExceptionMessage(
            'Route generation for route "user" with path "/user/{id}/{name}" with attributes "{"id":"a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791"}" failed. Parameter "id" for route "user" must match "\d+" ("a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791" given) to generate a corresponding URL'
        );
        $this->expectExceptionCode(3);

        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => '\d+', 'name' => '[a-z]+'],
                Router::PATH_DEFAULTS => ['name' => null],
            ]),
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
        ]);

        $router = new Router([$route]);
        $router->generateUrl($request, 'user', ['id' => 'a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791']);
    }

    public function testGenerateUriWithBasePath(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getPort', [], 443),
            new WithReturn('getHost', [], 'user:password@localhost'),
            new WithReturn('getPath', [], '/'),
            new WithReturn('getQuery', [], '?key=value'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getMethod', [], 'GET'),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => '\d+', 'name' => '[a-z]+'],
                Router::PATH_DEFAULTS => ['name' => null],
            ]),
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
        ]);

        $router = new Router([$route], null, '/path/to/directory');

        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1',
            $router->generateUrl($request, 'user', ['id' => 1])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1?key=value',
            $router->generateUrl($request, 'user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1/sample',
            $router->generateUrl($request, 'user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1/sample?key1=value1&key2=value2',
            $router->generateUrl(
                $request,
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGeneratePathWithMissingRoute(): void
    {
        $this->expectException(MissingRouteByNameException::class);
        $this->expectExceptionMessage('Missing route: "user"');

        $router = new Router([]);
        $router->generatePath('user', ['id' => 1]);
    }

    public function testGeneratePath(): void
    {
        $builder = new MockObjectBuilder();

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => '\d+', 'name' => '[a-z]+'],
                Router::PATH_DEFAULTS => ['name' => null],
            ]),
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
        ]);

        $router = new Router([$route]);

        self::assertSame('/user/1', $router->generatePath('user', ['id' => 1]));
        self::assertSame('/user/1?key=value', $router->generatePath('user', ['id' => 1], ['key' => 'value']));
        self::assertSame('/user/1/sample', $router->generatePath('user', ['id' => 1, 'name' => 'sample']));
        self::assertSame(
            '/user/1/sample?key1=value1&key2=value2',
            $router->generatePath(
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGeneratePathWithMissingAttribute(): void
    {
        $this->expectException(RouteGenerationException::class);
        $this->expectExceptionMessage(
            'Route generation for route "user" with path "/user/{id}/{name}" with attributes "{}" failed. Some mandatory parameters are missing ("id") to generate a URL for route "user".'
        );

        $builder = new MockObjectBuilder();

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => '\d+', 'name' => '[a-z]+'],
                Router::PATH_DEFAULTS => ['name' => null],
            ]),
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
        ]);

        $router = new Router([$route]);
        $router->generatePath('user');
    }

    public function testGeneratePathWithBasePath(): void
    {
        $builder = new MockObjectBuilder();

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPathOptions', [], [
                Router::PATH_REQUIREMENTS => ['id' => '\d+', 'name' => '[a-z]+'],
                Router::PATH_DEFAULTS => ['name' => null],
            ]),
            new WithReturn('getName', [], 'user'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
            new WithReturn('getPath', [], '/user/{id}/{name}'),
        ]);

        $router = new Router([$route], null, '/path/to/directory');

        self::assertSame('/path/to/directory/user/1', $router->generatePath('user', ['id' => 1]));
        self::assertSame(
            '/path/to/directory/user/1?key=value',
            $router->generatePath('user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            '/path/to/directory/user/1/sample',
            $router->generatePath('user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            '/path/to/directory/user/1/sample?key1=value1&key2=value2',
            $router->generatePath(
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testUseCache(): void
    {
        $builder = new MockObjectBuilder();

        /** @var RouteInterface $route1 */
        $route1 = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_create'),
            new WithReturn('getPathOptions', [], []),
            new WithReturn('getName', [], 'pet_create'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'POST'),
            new WithReturn('getName', [], 'pet_create'),
        ]);

        /** @var RouteInterface $route2 */
        $route2 = $builder->create(RouteInterface::class, [
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPathOptions', [], []),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getName', [], 'pet_list'),
        ]);

        $cacheFile = sys_get_temp_dir().'/symfony-'.uniqid().uniqid().'.php';

        new Router([$route1, $route2], $cacheFile);

        self::assertFileExists($cacheFile);

        new Router([$route1, $route2], $cacheFile);

        unlink($cacheFile);
    }
}
