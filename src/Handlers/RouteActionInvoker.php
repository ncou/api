<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2018 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Api\Handlers;

use Closure;
use Opulence\Api\RequestContext;
use Opulence\Api\ResponseFactories\IResponseFactory;
use Opulence\Api\ResponseFactories\OkResponseFactory;
use Opulence\Net\Http\HttpException;
use Opulence\Net\Http\HttpStatusCodes;
use Opulence\Net\Http\IHttpResponseMessage;
use Opulence\Net\Http\Response;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Defines the route action invoker
 */
class RouteActionInvoker implements IRouteActionInvoker
{
    /** @var IControllerParameterResolver The controller parameter resolver to use */
    private $controllerParameterResolver;

    /**
     * @param IControllerParameterResolver|null $controllerParameterResolver The controller parameter resolver to use
     */
    public function __construct(IControllerParameterResolver $controllerParameterResolver = null)
    {
        $this->controllerParameterResolver = $controllerParameterResolver ?? new ControllerParameterResolver();
    }

    /**
     * @inheritdoc
     */
    public function invokeRouteAction(callable $routeAction, RequestContext $requestContext): IHttpResponseMessage
    {
        try {
            if (\is_array($routeAction)) {
                $reflectionFunction = new ReflectionMethod($routeAction[0], $routeAction[1]);

                if (!$reflectionFunction->isPublic()) {
                    throw new HttpException(
                        HttpStatusCodes::HTTP_INTERNAL_SERVER_ERROR,
                        sprintf(
                            'Controller method %s must be public',
                            $this->getRouteActionDisplayName($routeAction)
                        )
                    );
                }
            } else {
                $reflectionFunction = new ReflectionFunction($routeAction);
            }
        } catch (ReflectionException $ex) {
            throw new HttpException(
                HttpStatusCodes::HTTP_INTERNAL_SERVER_ERROR,
                sprintf(
                    'Reflection failed for %s',
                    $this->getRouteActionDisplayName($routeAction)
                ),
                0,
                $ex
            );
        }

        $resolvedParameters = [];

        try {
            foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
                $resolvedParameters[] = $this->controllerParameterResolver->resolveParameter(
                    $reflectionParameter,
                    $requestContext
                );
            }
        } catch (MissingControllerParameterValueException $ex) {
            throw new HttpException(
                HttpStatusCodes::HTTP_BAD_REQUEST,
                "Failed to invoke {$this->getRouteActionDisplayName($routeAction)}",
                0,
                $ex
            );
        } catch (FailedRequestContentNegotiationException $ex) {
            throw new HttpException(
                HttpStatusCodes::HTTP_UNSUPPORTED_MEDIA_TYPE,
                "Failed to invoke {$this->getRouteActionDisplayName($routeAction)}",
                0,
                $ex
            );
        } catch (RequestBodyDeserializationException $ex) {
            throw new HttpException(
                HttpStatusCodes::HTTP_UNPROCESSABLE_ENTITY,
                "Failed to invoke {$this->getRouteActionDisplayName($routeAction)}",
                0,
                $ex
            );
        }

        $actionResult = $routeAction(...$resolvedParameters);

        if ($actionResult instanceof IHttpResponseMessage) {
            return $actionResult;
        }

        // Handle void return types
        if ($actionResult === null) {
            return new Response(HttpStatusCodes::HTTP_NO_CONTENT);
        }

        // Create a response from the factory
        if ($actionResult instanceof IResponseFactory) {
            return $actionResult->createResponse($requestContext);
        }

        // Attempt to create an OK response from the return value
        return (new OkResponseFactory(null, $actionResult))->createResponse($requestContext);
    }

    /**
     * Gets the display name for a route action for use in exception messages
     *
     * @param callable $routeAction The route action whose display name we want
     * @return string The route action display name
     */
    private function getRouteActionDisplayName(callable $routeAction): string
    {
        if (\is_array($routeAction)) {
            if (\is_string($routeAction[0])) {
                return $routeAction[0];
            }

            return \get_class($routeAction[0]) . '::' . $routeAction[1];
        }

        return Closure::class;
    }
}