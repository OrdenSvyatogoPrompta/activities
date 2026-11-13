<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

final class MeTest extends AuthApiTestCase
{
    public function testMeRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/me');
        $this->assertProblem(401);

        $this->jsonRequest('PATCH', '/api/me', ['name' => 'Взлом']);
        $this->assertProblem(401);
    }

    public function testMeReturnsCurrentUser(): void
    {
        $email = self::uniqueEmail('me');
        $registered = $this->register($email, 'Я Сам');

        $this->jsonRequest('GET', '/api/me', accessToken: $registered['accessToken']);

        self::assertResponseIsSuccessful();
        $user = $this->json();
        self::assertUserShape($user);
        self::assertSame($registered['user']['id'], $user['id']);
        self::assertSame($email, $user['email']);
        self::assertSame('Я Сам', $user['name']);
    }

    public function testPatchUpdatesName(): void
    {
        $registered = $this->register(name: 'Старое Имя');
        $token = $registered['accessToken'];

        $this->jsonRequest('PATCH', '/api/me', ['name' => '  Новое Имя  '], $token);

        self::assertResponseIsSuccessful();
        self::assertSame('Новое Имя', $this->json()['name']);

        $this->jsonRequest('GET', '/api/me', accessToken: $token);
        self::assertSame('Новое Имя', $this->json()['name']);
    }

    public function testPatchWithoutFieldsChangesNothing(): void
    {
        $registered = $this->register(name: 'Неизменный');

        $this->jsonRequest('PATCH', '/api/me', [], $registered['accessToken']);

        self::assertResponseIsSuccessful();
        self::assertSame($registered['user'], $this->json());
    }

    public function testPatchValidatesName(): void
    {
        $token = $this->register()['accessToken'];

        $this->jsonRequest('PATCH', '/api/me', ['name' => '   '], $token);
        $this->assertViolations('name');

        $this->jsonRequest('PATCH', '/api/me', ['name' => str_repeat('я', 101)], $token);
        $this->assertViolations('name');
    }

    public function testPatchIgnoresEmailAndRoles(): void
    {
        $email = self::uniqueEmail('immutable');
        $token = $this->register($email)['accessToken'];

        $this->jsonRequest('PATCH', '/api/me', ['name' => 'Хитрец', 'email' => 'other@example.com', 'roles' => ['ROLE_ADMIN']], $token);

        // Лишние поля — либо 422 (allow_extra_attributes: false), либо игнорируются; роль и email не меняются
        $this->jsonRequest('GET', '/api/me', accessToken: $token);
        $user = $this->json();
        self::assertSame($email, $user['email']);
        self::assertSame(['ROLE_USER'], $user['roles']);
    }
}
