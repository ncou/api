<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2018 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Api\Handlers;

use Opulence\Net\Http\IHttpRequestMessage;
use Opulence\Routing\Matchers\MatchedRoute;
use ReflectionParameter;

/**
 * Defines the interface for controller parameter resolvers to implement
 */
interface IControllerParameterResolver
{
    /**
     * Resolved a controller parameter
     *
     * @param ReflectionParameter $reflectionParameter The reflected parameter
     * @param IHttpRequestMessage $request The current request
     * @param MatchedRoute $matchedRoute The matched route
     * @return mixed The resolved parameter value
     * @throws FailedRequestContentNegotiationException Thrown if the request content negotiation failed
     * @throws MissingControllerParameterValueException Thrown if there was no valid value for the parameter
     * @throws RequestBodyDeserializationException Thrown if the request body could not be deserialized
     */
    public function resolveParameter(
        ReflectionParameter $reflectionParameter,
        IHttpRequestMessage $request,
        MatchedRoute $matchedRoute
    );
}
