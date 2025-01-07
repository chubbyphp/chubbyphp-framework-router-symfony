<?php

declare(strict_types=1);

namespace Chubbyphp\Framework\Router\Symfony;

use Chubbyphp\Framework\Router\Exceptions\MissingRouteByNameException;
use Chubbyphp\Framework\Router\Exceptions\RouteGenerationException;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RouteMatcherInterface;
use Chubbyphp\Framework\Router\UrlGeneratorInterface;
use Chubbyphp\HttpException\HttpException;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException as SymfonyInvalidParameterException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException as SymfonyMethodNotAllowedException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException as SymfonyMissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException as SymfonyResourceNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface as SymfonyUrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class Router implements RouteMatcherInterface, UrlGeneratorInterface
{
    public const PATH_DEFAULTS = 'defaults';
    public const PATH_REQUIREMENTS = 'requirements';
    public const PATH_HOST = 'host';
    public const PATH_SCHEMES = 'schemes';
    public const PATH_CONDITION = 'condition';

    private const MATCHER = 'matcher';
    private const GENERATOR = 'generator';

    /**
     * @var array<string, RouteInterface>
     */
    private array $routesByName;

    private CompiledUrlMatcher $urlMatcher;

    private CompiledUrlGenerator $urlGenerator;

    /**
     * @param array<int, RouteInterface> $routes
     */
    public function __construct(array $routes, ?string $cacheFile = null, private string $basePath = '')
    {
        $this->routesByName = $this->getRoutesByName($routes);

        $compiledRoutes = $this->getCompiledRoutes($routes, $cacheFile);

        $this->urlMatcher = new CompiledUrlMatcher($compiledRoutes[self::MATCHER], $this->getRequestContext());
        $this->urlGenerator = new CompiledUrlGenerator($compiledRoutes[self::GENERATOR], $this->getRequestContext());
    }

    public function match(ServerRequestInterface $request): RouteInterface
    {
        $this->urlMatcher->setContext($this->getRequestContext($request));

        try {
            $parameters = $this->urlMatcher->match($request->getUri()->getPath());
        } catch (SymfonyResourceNotFoundException $exception) {
            throw HttpException::createNotFound([
                'detail' => \sprintf(
                    'The page "%s" you are looking for could not be found.'
                    .' Check the address bar to ensure your URL is spelled correctly.',
                    $request->getRequestTarget()
                ),
            ]);
        } catch (SymfonyMethodNotAllowedException $exception) {
            throw HttpException::createMethodNotAllowed([
                'detail' => \sprintf(
                    'Method "%s" at path "%s" is not allowed. Must be one of: "%s"',
                    $request->getMethod(),
                    $request->getRequestTarget(),
                    implode('", "', $exception->getAllowedMethods()),
                ),
            ]);
        }

        /** @var RouteInterface $route */
        $route = $this->routesByName[$parameters['_route']];

        unset($parameters['_route']);

        return $route->withAttributes($parameters);
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed>  $queryParams
     */
    public function generateUrl(
        ServerRequestInterface $request,
        string $name,
        array $attributes = [],
        array $queryParams = []
    ): string {
        $route = $this->getRoute($name);

        return $this->generate($request, $name, $route->getPath(), $attributes, $queryParams);
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed>  $queryParams
     */
    public function generatePath(string $name, array $attributes = [], array $queryParams = []): string
    {
        $route = $this->getRoute($name);

        return $this->generate(null, $name, $route->getPath(), $attributes, $queryParams);
    }

    private function getRoute(string $name): RouteInterface
    {
        if (!isset($this->routesByName[$name])) {
            throw MissingRouteByNameException::create($name);
        }

        return $this->routesByName[$name];
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed>  $queryParams
     */
    private function generate(
        ?ServerRequestInterface $request,
        string $name,
        string $path,
        array $attributes = [],
        array $queryParams = []
    ): string {
        $this->urlGenerator->setContext($this->getRequestContext($request));

        try {
            return $this->urlGenerator->generate(
                $name,
                array_merge($attributes, $queryParams),
                $request instanceof ServerRequestInterface ? SymfonyUrlGeneratorInterface::ABSOLUTE_URL : SymfonyUrlGeneratorInterface::ABSOLUTE_PATH
            );
        } catch (SymfonyInvalidParameterException|SymfonyMissingMandatoryParametersException $exception) {
            throw RouteGenerationException::create(
                $name,
                $path,
                $attributes,
                $exception
            );
        }
    }

    /**
     * @param array<int, RouteInterface> $routes
     *
     * @return array<mixed>
     */
    private function getCompiledRoutes(array $routes, ?string $cacheFile): array
    {
        if (null !== $cacheFile && file_exists($cacheFile)) {
            $compiledRoutes = require $cacheFile;
        } else {
            $routeCollection = $this->getRouteCollection($routes);

            $compiledRoutes = [
                self::MATCHER => (new CompiledUrlMatcherDumper($routeCollection))->getCompiledRoutes(),
                self::GENERATOR => (new CompiledUrlGeneratorDumper($routeCollection))->getCompiledRoutes(),
            ];

            if (null !== $cacheFile) {
                file_put_contents($cacheFile, '<?php return '.var_export($compiledRoutes, true).';');
            }
        }

        return $compiledRoutes;
    }

    /**
     * @param array<int, RouteInterface> $routes
     */
    private function getRouteCollection(array $routes): RouteCollection
    {
        $routeCollection = new RouteCollection();

        foreach ($routes as $route) {
            $pathOptions = $route->getPathOptions();
            $routeCollection->add($route->getName(), new Route(
                $route->getPath(),
                $pathOptions[self::PATH_DEFAULTS] ?? [],
                $pathOptions[self::PATH_REQUIREMENTS] ?? [],
                [],
                $pathOptions[self::PATH_HOST] ?? null,
                $pathOptions[self::PATH_SCHEMES] ?? [],
                [$route->getMethod()],
                $pathOptions[self::PATH_CONDITION] ?? null
            ));
        }

        return $routeCollection;
    }

    private function getRequestContext(?ServerRequestInterface $request = null): RequestContext
    {
        if (!$request instanceof ServerRequestInterface) {
            return new RequestContext($this->basePath);
        }

        $uri = $request->getUri();

        $scheme = $uri->getScheme();
        $port = $uri->getPort();

        return new RequestContext(
            $this->basePath,
            $request->getMethod(),
            $uri->getHost(),
            $scheme,
            'http' === $scheme && null !== $port ? $port : 80,
            'https' === $scheme && null !== $port ? $port : 443,
            $uri->getPath(),
            $uri->getQuery()
        );
    }

    /**
     * @param array<int, RouteInterface> $routes
     *
     * @return array<string, RouteInterface>
     */
    private function getRoutesByName(array $routes): array
    {
        $routesByName = [];
        foreach ($routes as $route) {
            $routesByName[$route->getName()] = $route;
        }

        return $routesByName;
    }
}
