<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Cache-Control: public` и ETag по содержимому; совпавший If-None-Match даёт 304.
 */
final class PublicCache
{
    private const int MAX_AGE = 60;

    public function apply(Response $response, Request $request): Response
    {
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $response->isNotModified($request);

        return $response;
    }
}
