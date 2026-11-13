<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
// Индекс по family бандл просит объявить на своей сущности: по нему ищется цепочка токенов
#[ORM\Index(name: 'IDX_REFRESH_TOKENS_FAMILY', fields: ['family'])]
class RefreshToken extends BaseRefreshToken
{
}
