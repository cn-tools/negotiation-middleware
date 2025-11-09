<?php
/**
* @file
* PHPUnit test for the CNTools\NegotiationMiddleware\Negotiator middleware.
*
* This test checks media type negotiation based on the Accept header
* according to the PSR-15 middleware specification. It tests whether the middleware
* selects the correct Content-Type or responds correctly with a 406 status.
*
* Components used:
* - Slim 4 middleware compatibility
* - Nyholm PSR-7 implementation
* - PHPUnit for unit tests
* - PSR-15 RequestHandlerInterface mocking
*
* @author Clemens Neubauer
*/

require 'vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use CNTools\NegotiationMiddleware\Negotiator;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NegotiatorTest extends TestCase
{
    /**
     * Executes the middleware with an optional Accept header and returns the response.
     *
     * @param string|null $acceptHeader
     * @param array $accepted
     * @param bool $defaultToFirst
     * @return ResponseInterface
     */
    private function invokeMiddlewareWithAccept(?string $acceptHeader = null, array $accepted = ['text/html', 'application/json'], bool $defaultToFirst = true): ResponseInterface
    {
        $request = new ServerRequest('GET', '/hello', $acceptHeader ? ['Accept' => $acceptHeader] : []);

        /** @var RequestHandlerInterface&\PHPUnit\Framework\MockObject\MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function ($request) {
            $media = $request->getAttribute('mediaType');
            $value = $media ? $media->getValue() : '';
            $response = new Response();
            $response->getBody()->write($value);
            return $response;
        });

        $middleware = new Negotiator($accepted, $defaultToFirst, true);

        return $middleware->process($request, $handler);
    }

    /**
     * Führt die Middleware aus und gibt den Body-Inhalt der Response als String zurück.
     *
     * @param string|null $acceptHeader
     * @param array $accepted
     * @param bool $defaultToFirst
     * @return string
     */
    private function invokeMiddlewareByBody(?string $acceptHeader = null, array $accepted = ['text/html', 'application/json'], bool $defaultToFirst = true): string
    {
        $result = $this->invokeMiddlewareWithAccept($acceptHeader, $accepted, $defaultToFirst);
        return (string) $result->getBody();
    }

    public function testSelectsTextHtmlWhenBrowserAcceptsHtml()
    {
        $body = $this->invokeMiddlewareByBody('text/html;q=0.9,application/json;q=0.1');
        $this->assertEquals('text/html', $body);
    }

    public function testSelectsApplicationJsonWhenRequested()
    {
        $body = $this->invokeMiddlewareByBody('application/json');
        $this->assertEquals('application/json', $body);
    }

    public function testDefaultsToFirstWhenNoAcceptHeaderAndDefaultEnabled()
    {
        $body = $this->invokeMiddlewareByBody(null, ['text/html','application/json'], true);
        $this->assertEquals('text/html', $body);
    }

    public function testReturnsEmptyWhenNoAcceptMatchesAndNoDefault()
    {
        $body = $this->invokeMiddlewareByBody('image/png', ['text/html','application/json'], false);
        $this->assertEquals('', $body);
    }

    public function testReturns406WhenNoAcceptMatchesAndNoDefault()
    {
        $result = $this->invokeMiddlewareWithAccept('image/png', ['text/html','application/json'], false);
        $this->assertEquals(406, $result->getStatusCode());
    }

    public function testAddsContentTypeHeaderWhenEnabled()
    {
        $result = $this->invokeMiddlewareWithAccept('application/json', ['text/html','application/json'], true);
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));
    }

    public function testDoesNotAddContentTypeHeaderWhenDisabled()
    {
        $request = new ServerRequest('GET', '/hello', ['Accept' => 'application/json']);

        /** @var RequestHandlerInterface&\PHPUnit\Framework\MockObject\MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function ($request) {
            $media = $request->getAttribute('mediaType');
            $value = $media ? $media->getValue() : '';
            $response = new Response();
            $response->getBody()->write($value);
            return $response;
        });

        $middleware = new Negotiator(['text/html','application/json'], true, false);

        $result = $middleware->process($request, $handler);
        $this->assertFalse($result->hasHeader('Content-Type'));
    }
}
