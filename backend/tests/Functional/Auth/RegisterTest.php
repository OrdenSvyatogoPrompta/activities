<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Seed\DemoData;
use PHPUnit\Framework\Attributes\DataProvider;

final class RegisterTest extends AuthApiTestCase
{
    public function testRegisterReturnsTokenUserAndRefreshCookie(): void
    {
        $email = self::uniqueEmail('new');

        $body = $this->register($email, 'Новый Пользователь');

        self::assertSame(['accessToken', 'user'], array_keys($body));
        self::assertIsString($body['accessToken']);
        self::assertNotSame('', $body['accessToken']);
        self::assertArrayNotHasKey('refresh_token', $body);
        self::assertArrayNotHasKey('token', $body);

        self::assertUserShape($body['user']);
        self::assertSame('Новый Пользователь', $body['user']['name']);
        self::assertSame($email, $body['user']['email']);
        self::assertSame(['ROLE_USER'], $body['user']['roles']);
        self::assertSame(['enrollments' => 0, 'activities' => 0, 'children' => 0], $body['user']['stats']);

        $this->assertRefreshCookieIssued();

        // Пароль сохранён хешем
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);
        self::assertNotSame(self::PASSWORD, $user->getPassword());
    }

    public function testEmailIsNormalizedAndNameTrimmed(): void
    {
        $local = 'MiXeD-'.bin2hex(random_bytes(4));

        $body = $this->register('  '.$local.'@Example.COM ', '  Иван  ');

        self::assertSame(strtolower($local).'@example.com', $body['user']['email']);
        self::assertSame('Иван', $body['user']['name']);

        // Вход с другим регистром
        $this->jsonRequest('POST', '/api/auth/login', ['email' => strtoupper($local).'@EXAMPLE.com', 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    public function testDuplicateEmailIsRejectedCaseInsensitively(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'name' => 'Двойник',
            'email' => strtoupper(DemoData::DEMO_EMAIL),
            'password' => self::PASSWORD,
        ]);

        $violations = $this->assertViolations('email');
        self::assertSame('Email уже занят', $violations[0]['message']);
        self::assertNull($this->responseRefreshCookie());
    }

    public function testDuplicateOfFreshlyRegisteredEmail(): void
    {
        $email = self::uniqueEmail('dup');
        $this->register($email);

        $this->jsonRequest('POST', '/api/auth/register', ['name' => 'Ещё раз', 'email' => $email, 'password' => self::PASSWORD]);

        $this->assertViolations('email');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'пустое тело' => [[], ['email', 'name', 'password']];
        yield 'некорректный email' => [['name' => 'Имя', 'email' => 'not-an-email', 'password' => self::PASSWORD], ['email']];
        yield 'короткий пароль' => [['name' => 'Имя', 'email' => 'short@example.com', 'password' => '1234567'], ['password']];
        yield 'пустое имя' => [['name' => '', 'email' => 'empty-name@example.com', 'password' => self::PASSWORD], ['name']];
        yield 'имя из пробелов' => [['name' => '   ', 'email' => 'blank-name@example.com', 'password' => self::PASSWORD], ['name']];
        yield 'длинное имя' => [['name' => str_repeat('я', 101), 'email' => 'long-name@example.com', 'password' => self::PASSWORD], ['name']];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $paths
     */
    #[DataProvider('invalidPayloads')]
    public function testValidation(array $payload, array $paths): void
    {
        $this->jsonRequest('POST', '/api/auth/register', $payload);

        $this->assertViolations(...$paths);
    }

    public function testNameOfExactly100CharsIsAccepted(): void
    {
        $body = $this->register(name: str_repeat('я', 100));

        self::assertSame(100, mb_strlen($body['user']['name']));
    }

    public function testMalformedJsonReturns400(): void
    {
        $this->client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: '{');

        $this->assertProblem(400);
    }
}
