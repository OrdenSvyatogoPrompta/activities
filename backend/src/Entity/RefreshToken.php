<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
// Колонки family/family_valid (gesdinet 3.x) приходят из mapped superclass; индекс по family
// бандл рекомендует объявлять на своей сущности — по нему идут все поиски цепочки токенов.
#[ORM\Index(name: 'IDX_REFRESH_TOKENS_FAMILY', fields: ['family'])]
class RefreshToken extends BaseRefreshToken
{
}
