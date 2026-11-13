<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP-кеширование публичных ответов каталога: `Cache-Control: public, max-age=60` и ETag
 * по содержимому ответа. Если клиент прислал совпадающий If-None-Match, ответ
 * превращается в `304 Not Modified` без тела.
 */
final class PublicCache
{
    public const int MAX_AGE = 60;

    public function apply(Response $response, Request $request): Response
    {
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $response->isNotModified($request);

        return $response;
    }
}
