<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2018 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Api\Tests\Handlers;

use Opulence\Api\ControllerContext;
use Opulence\Api\Handlers\ControllerParameterResolver;
use Opulence\Api\Handlers\FailedRequestContentNegotiationException;
use Opulence\Api\Handlers\IControllerParameterResolver;
use Opulence\Api\Handlers\MissingControllerParameterValueException;
use Opulence\Api\Handlers\ReflectionRouteActionInvoker;
use Opulence\Api\Handlers\RequestBodyDeserializationException;
use Opulence\Api\Tests\Handlers\Mocks\ApiController;
use Opulence\Net\Http\ContentNegotiation\ContentNegotiationResult;
use Opulence\Net\Http\ContentNegotiation\IMediaTypeFormatter;
use Opulence\Net\Http\HttpException;
use Opulence\Net\Http\HttpStatusCodes;
use Opulence\Net\Http\Request;
use Opulence\Net\Uri;
use Opulence\Routing\Matchers\MatchedRoute;
use Opulence\Routing\RouteAction;
use RuntimeException;

/**
 * Tests the reflection route action invoker
 */
class ReflectionRouteActionInvokerTest extends \PHPUnit\Framework\TestCase
{
    /** @var ReflectionRouteActionInvoker The invoker to use in tests */
    private $invoker;
    /** @var ControllerParameterResolver|\PHPUnit_Framework_MockObject_MockObject The controller parameter resolver to use in tests */
    private $parameterResolver;
    /** @var ApiController The controller to use in tests */
    private $controller;

    public function setUp(): void
    {
        $this->parameterResolver = $this->createMock(IControllerParameterResolver::class);
        $this->invoker = new ReflectionRouteActionInvoker($this->parameterResolver);
        $this->controller = new ApiController();
    }

    public function testFailedRequestContentNegotiationExceptionIsRethrownAsHttpException(): void
    {
        $controllerContext = new ControllerContext(
            $this->controller,
            $this->createRequest('http://foo.com'),
            null,
            null,
            new MatchedRoute(new RouteAction(ApiController::class, 'stringParameter', null), [], [])
        );

        try {
            $this->parameterResolver->expects($this->once())
                ->method('resolveParameter')
                ->with($this->anything(), $this->anything())
                ->willThrowException(new FailedRequestContentNegotiationException);
            $this->invoker->invokeRouteAction($controllerContext);
            $this->fail('Failed to assert that a 415 was thrown');
        } catch (HttpException $ex) {
            $this->assertEquals(HttpStatusCodes::HTTP_UNSUPPORTED_MEDIA_TYPE, $ex->getStatusCode());
        }
    }

    public function testInvokingMethodThatThrowsExceptionThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->invoker->invokeRouteAction(
            new ControllerContext(
                $this->controller,
                $this->createRequest('http://foo.com'),
                null,
                null,
                new MatchedRoute(new RouteAction(ApiController::class, 'throwsException', null), [], [])
            )
        );
    }

    public function testInvokingMethodWithNoParametersIsSuccessful(): void
    {
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $response = $this->invoker->invokeRouteAction(
            new ControllerContext(
                $this->controller,
                $this->createRequest('http://foo.com'),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(ApiController::class, 'noParameters', null), [], [])
            )
        );
        $this->assertNotNull($response->getBody());
        $this->assertEquals('noParameters', $response->getBody()->readAsString());
    }

    public function testInvokingMethodWithVoidReturnTypeReturnsNoContentResponse(): void
    {
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $response = $this->invoker->invokeRouteAction(
            new ControllerContext(
                $this->controller,
                $this->createRequest('http://foo.com'),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(ApiController::class, 'voidReturnType', null), [], [])
            )
        );
        $this->assertNull($response->getBody());
        $this->assertEquals(HttpStatusCodes::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testInvokingNonExistentMethodThrowsException(): void
    {
        $this->expectException(HttpException::class);
        $this->invoker->invokeRouteAction(
            new ControllerContext(
                $this->controller,
                $this->createRequest('http://foo.com'),
                null,
                null,
                new MatchedRoute(new RouteAction(ApiController::class, 'doesNotExist', null), [], [])
            )
        );
    }

    public function testInvokingPrivateMethodThrowsException(): void
    {
        $this->expectException(HttpException::class);
        $this->invoker->invokeRouteAction(
            new ControllerContext(
                $this->controller,
                $this->createRequest('http://foo.com'),
                null,
                null,
                new MatchedRoute(new RouteAction(ApiController::class, 'privateMethod', null), [], [])
            )
        );
    }

    public function testInvokingProtectedMethodThrowsException(): void
    {
        $this->expectException(HttpException::class);
        $this->invoker->invokeRouteAction(
            new ControllerContext(
                $this->controller,
                $this->createRequest('http://foo.com'),
                null,
                null,
                new MatchedRoute(new RouteAction(ApiController::class, 'protectedMethod', null), [], [])
            )
        );
    }

    public function testMissingControllerParameterValueExceptionIsRethrownAsHttpException(): void
    {
        $controllerContext = new ControllerContext(
            $this->controller,
            $this->createRequest('http://foo.com'),
            null,
            null,
            new MatchedRoute(new RouteAction(ApiController::class, 'stringParameter', null), [], [])
        );

        try {
            $this->parameterResolver->expects($this->once())
                ->method('resolveParameter')
                ->with($this->anything(), $this->anything())
                ->willThrowException(new MissingControllerParameterValueException);
            $this->invoker->invokeRouteAction($controllerContext);
            $this->fail('Failed to assert that a 400 was thrown');
        } catch (HttpException $ex) {
            $this->assertEquals(HttpStatusCodes::HTTP_BAD_REQUEST, $ex->getStatusCode());
        }
    }

    public function testRequestBodyDeserializationExceptionIsRethrownAsHttpException(): void
    {
        $controllerContext = new ControllerContext(
            $this->controller,
            $this->createRequest('http://foo.com'),
            null,
            null,
            new MatchedRoute(new RouteAction(ApiController::class, 'stringParameter', null), [], [])
        );

        try {
            $this->parameterResolver->expects($this->once())
                ->method('resolveParameter')
                ->with($this->anything(), $this->anything())
                ->willThrowException(new RequestBodyDeserializationException);
            $this->invoker->invokeRouteAction($controllerContext);
            $this->fail('Failed to assert that a 522 was thrown');
        } catch (HttpException $ex) {
            $this->assertEquals(HttpStatusCodes::HTTP_UNPROCESSABLE_ENTITY, $ex->getStatusCode());
        }
    }

    /**
     * Creates a request with the input URI
     *
     * @param string $uri The URI to use
     * @return Request The request
     */
    private function createRequest(string $uri): Request
    {
        return new Request('GET', new Uri($uri));
    }
}