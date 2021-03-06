<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

abstract class AbstractSapiEmitter implements EmitterInterface
{
    /**
     * Assert either that no headers been sent or the output buffer contains no content.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function assertNoPreviousOutput(): void
    {
        $file = $line = null;

        if (headers_sent($file, $line)) {
            throw new RuntimeException(\sprintf(
                'Unable to emit response: Headers already sent in file %s on line %s. ' .
                'This happens if echo, print, printf, print_r, var_dump, var_export or similar statement that writes to the output buffer are used.',
                $file,
                $line
            ));
        }

        if (\ob_get_level() > 0 && \ob_get_length() > 0) {
            throw new RuntimeException('Output has been emitted previously; cannot emit response.');
        }
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is availble, it, too, is emitted.
     *
     * It's important to mention that, in order to prevent PHP from changing
     * the status code of the emitted response, this method should be called
     * after `sendBody()`
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    protected function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        header(
            \vsprintf(
                'HTTP/%s %d%s',
                [
                    $response->getProtocolVersion(),
                    $statusCode,
                    \rtrim(' ' . $response->getReasonPhrase()),
                ]
            ),
            true,
            $statusCode
        );
    }

    /**
     * Emit response headers.
     *
     * Loops through each header, emitting each; if the header value
     * is an array with multiple values, ensures that each is sent
     * in such a way as to create aggregate headers (instead of replace
     * the previous).
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    protected function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values) {
            $name  = $this->toWordCase($header);
            $first = $name !== 'Set-Cookie';

            foreach ($values as $value) {
                header(
                    \sprintf(
                    '%s: %s',
                        $name,
                        $value
                    ),
                    $first,
                    $statusCode
                );

                $first = false;
            }
        }
    }

    /**
     * Converts header names to wordcase.
     *
     * @param string $header
     *
     * @return string
     */
    protected function toWordCase(string $header): string
    {
        $filtered = \str_replace('-', ' ', $header);
        $filtered = \ucwords($filtered);

        return \str_replace(' ', '-', $filtered);
    }
}
