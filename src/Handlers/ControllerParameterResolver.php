<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2018 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Api\Handlers;

use Opulence\Api\ControllerContext;
use Opulence\Net\Formatting\UriParser;
use Opulence\Net\Http\ContentNegotiation\ContentNegotiationResult;
use Opulence\Net\Http\IHttpRequestMessage;
use Opulence\Serialization\SerializationException;
use ReflectionParameter;

/**
 * Defines the default controller parameter resolver
 */
class ControllerParameterResolver implements IControllerParameterResolver
{
    /** @var UriParser The URI parser to use */
    private $uriParser;

    /**
     * @param UriParser $uriParser The URI parser to use
     */
    public function __construct(UriParser $uriParser = null)
    {
        $this->uriParser = $uriParser ?? new UriParser();
    }

    /**
     * @inheritdoc
     */
    public function resolveParameter(ReflectionParameter $reflectionParameter, ControllerContext $controllerContext)
    {
        $request = $controllerContext->getRequest();
        $requestContentNegotiationResult = $controllerContext->getRequestContentNegotiationResult();
        $routeVars = $controllerContext->getMatchedRoute()->getRouteVars();
        $queryStringVars = $this->uriParser->parseQueryString($request->getUri());

        if ($reflectionParameter->getClass() !== null) {
            return $this->resolveObjectParameter(
                $reflectionParameter,
                $request,
                $requestContentNegotiationResult
            );
        }

        if (isset($routeVars[$reflectionParameter->getName()])) {
            return $routeVars[$reflectionParameter->getName()];
        }

        if (isset($queryStringVars[$reflectionParameter->getName()])) {
            return $queryStringVars[$reflectionParameter->getName()];
        }

        if ($reflectionParameter->isDefaultValueAvailable()) {
            return $reflectionParameter->getDefaultValue();
        }

        if ($reflectionParameter->allowsNull()) {
            return null;
        }

        throw new MissingControllerParameterValueException(
            "No valid value for parameter {$reflectionParameter->getName()}"
        );
    }

    /**
     * Resolves an object parameter
     *
     * @param ReflectionParameter $reflectionParameter The parameter to resolve
     * @param IHttpRequestMessage $request The current request
     * @param ContentNegotiationResult|null $requestContentNegotiationResult The request content negotiation result
     * @return \object|null The resolved parameter
     * @throws FailedRequestContentNegotiationException Thrown if the request content negotiation failed
     * @throws MissingControllerParameterValueException Thrown if there was no valid value for the parameter
     * @throws RequestBodyDeserializationException Thrown if the request body could not be deserialized
     */
    private function resolveObjectParameter(
        ReflectionParameter $reflectionParameter,
        IHttpRequestMessage $request,
        ?ContentNegotiationResult $requestContentNegotiationResult
    ): ?object {
        if ($request->getBody() === null) {
            if (!$reflectionParameter->allowsNull()) {
                throw new MissingControllerParameterValueException('Missing request body');
            }

            return null;
        }

        if ($requestContentNegotiationResult === null) {
            if (!$reflectionParameter->allowsNull()) {
                throw new FailedRequestContentNegotiationException('Failed to negotiation content when invoking');
            }

            return null;
        }

        try {
            return $requestContentNegotiationResult->getFormatter()
                ->readFromStream($request->getBody()->readAsStream(), $reflectionParameter->getType());
        } catch (SerializationException $ex) {
            if (!$reflectionParameter->allowsNull()) {
                throw new RequestBodyDeserializationException(
                    "Failed to deserialize request body when resolving parameter {$reflectionParameter->getName()}",
                    0,
                    $ex
                );
            }

            return null;
        }
    }
}