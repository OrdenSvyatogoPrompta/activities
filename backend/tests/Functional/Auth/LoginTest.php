<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Service\Seed\DemoData;

final class LoginTest extends AuthApiTestCase
{
    public function testLoginReturnsTokenUserWithStatsAndRefreshCookie(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['email' => DemoData::DEMO_EMAIL, 'password' => DemoData::DEMO_PASSWORD]);

        self::assertResponseIsSuccessful();
        $body = $this->json();
        self::assertSame(['accessToken', 'user'], array_keys($body));
        self::assertIsString($body['accessToken']);

        $user = $body['user'];
        self::assertUserShape($user);
        self::assertSame(DemoData::DEMO_EMAIL, $user['email']);
        self::assertSame('Иванова Мария', $user['name']);
        self::assertSame(['ROLE_USER'], $user['roles']);
        // Фикстуры: Артём → плавание (confirmed), Соня → гимнастика (pending);
        // «Акварель» (одобрена) и «Настольные игры» (на модерации); двое детей
        self::assertSame(['enrollments' => 2, 'activities' => 2, 'children' => 2], $user['stats']);

        $this->assertRefreshCookieIssued();
    }

    public function testAdminHasAdminRole(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['email' => DemoData::ADMIN_EMAIL, 'password' => DemoData::ADMIN_PASSWORD]);

        self::assertResponseIsSuccessful();
        self::assertContains('ROLE_ADMIN', $this->json()['user']['roles']);
    }

    public function testWrongPasswordAndUnknownEmailGiveSameError(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['email' => DemoData::DEMO_EMAIL, 'password' => 'wrong-password']);
        $wrongPassword = $this->assertProblem(401, 'Неверный email или пароль');
        self::assertNull($this->responseRefreshCookie());

        $this->jsonRequest('POST', '/api/auth/login', ['email' => self::uniqueEmail('ghost'), 'password' => 'wrong-password']);
        $unknownEmail = $this->assertProblem(401, 'Неверный email или пароль');

        self::assertSame($wrongPassword, $unknownEmail);
    }

    public function testLoginIsThrottledAfterFiveFailedAttempts(): void
    {
        $email = self::uniqueEmail('throttle');
        $this->register($email);

        for ($i = 1; $i <= 5; ++$i) {
            $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => 'wrong-password']);
            $this->assertProblem(401, 'Неверный email или пароль');
        }

        // Шестая попытка в ту же минуту блокируется, даже с верным паролем
        $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        $this->assertProblem(429);
    }

    public function testRegisteredUserCanLogIn(): void
    {
        $email = self::uniqueEmail('login');
        $registered = $this->register($email, 'Вход После Регистрации');

        $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);

        self::assertResponseIsSuccessful();
        self::assertSame($registered['user'], $this->json()['user']);
    }
}
