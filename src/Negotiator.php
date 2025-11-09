<?php

/**
 * @file
 * PSR-15 Middleware for content negotiation based on the Accept header.
 *
 * This middleware uses the `willdurand/negotiation` library to
 * match the media type preferred by the client with the types supported
 * by the server. If no matching type is found, either a
 * default value can be used or an HTTP 406 (Not Acceptable) error can be returned.
 *
 * Optionally, the negotiated media type can also be added as a `Content-Type` header
 * to the response.
 *
 * @author Neubauer Clemens
 * @license MIT
 */

declare(strict_types=1);

namespace CNTools\NegotiationMiddleware;

use Negotiation\Accept;
use Negotiation\Negotiator as NegotiationLib;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Nyholm\Psr7\Response;

class Negotiator implements MiddlewareInterface
{
    /** The object that performs the negotiation.
     * @var NegotiationLib
     */
    protected $negotiator;

    /** The object that represents a negotiated media type.
     * @var Accept|null
     */
    protected $mediaType;

    /** An array of acceptable media types.
     * @var string[]
     */
    protected $priorities;

    /** indicating whether or not to supply a default media type if negotiation cannot determine a match.
     * @var bool
     */
    protected $supplyDefault;

    /** @var bool */
    protected $addContentTypeHeader;

    /**
     * Konstruktor für die Negotiator-Middleware.
     *
     * @param string[] $priorities An array of acceptable media types. (ex. ['text/html', 'application/json']).
     * @param bool $supplyDefault Whether a default value should be used if no match is found.
     * @param bool $addContentTypeHeader Whether the negotiated type should be set as the Content-Type header.
     */
    public function __construct(array $priorities = [], bool $supplyDefault = false, bool $addContentTypeHeader = false)
    {
        $this->negotiator = new NegotiationLib();
        $this->mediaType = null;
        $this->priorities = $priorities;
        $this->supplyDefault = $supplyDefault;
        $this->addContentTypeHeader = $addContentTypeHeader;
    }

    /**
     * Performs content negotiation for the request.
     *
     * Content negotiation uses willdurand/negotiation to determine if a request
     * specifies an acceptable media type and, if not, responds immediately with
     * a 406 error (Not Acceptable).
     *
     * If the negotiator middleware has been instructed to supply a default
     * media type and the accept header is empty, it will negotiate a match
     * against the first given priority.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->negotiateMediaType($request);

        if (empty($this->mediaType)) {
            return (new Response())->withStatus(406);
        }

        $request = $request->withAttribute('mediaType', $this->mediaType);

        $response = $handler->handle($request);

        if ($this->addContentTypeHeader) {
            $response = $response->withHeader('Content-Type', $this->mediaType->getValue());
        }

        return $response;
    }

    /**
     * Negotiates a media type for the request and stores it in a property on
     * the middleware object.
     *
     * @param ServerRequestInterface $request
     */
    private function negotiateMediaType(ServerRequestInterface $request): void
    {
        $acceptHeader = $request->getHeaderLine('accept');

        if (empty($acceptHeader)) {
            if ($this->supplyDefault && !empty($this->priorities)) {
                $this->mediaType = new Accept(reset($this->priorities));
            }
        } else {
            $this->mediaType = $this->negotiator->getBest($acceptHeader, $this->priorities);
        }
    }
}
