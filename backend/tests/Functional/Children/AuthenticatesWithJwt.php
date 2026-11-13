<?php

declare(strict_types=1);

namespace App\Tests\Functional\Children;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Вход в функциональных тестах без эндпоинта логина: access-токен выпускается напрямую
 * через JWTTokenManagerInterface для пользователя из фикстур.
 *
 * Клиент нужно создать до вызова (createClient() после загрузки ядра запрещён).
 *
 * @phpstan-require-extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
 */
trait AuthenticatesWithJwt
{
    protected static function findUser(string $email): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user, \sprintf('Пользователь %s не найден — загружены ли фикстуры в тестовую базу?', $email));

        return $user;
    }

    protected static function tokenFor(string $email): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create(self::findUser($email));
    }

    /**
     * Все последующие запросы клиента идут с заголовком `Authorization: Bearer <access>`.
     */
    protected static function loginAs(KernelBrowser $client, string $email): void
    {
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.self::tokenFor($email));
    }
}
