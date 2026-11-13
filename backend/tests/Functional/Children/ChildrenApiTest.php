<?php

declare(strict_types=1);

namespace App\Tests\Functional\Children;

use App\DataFixtures\AppFixtures;
use App\Entity\Child;
use App\Entity\Enrollment;
use App\Entity\Enum\EnrollmentStatus;
use App\Repository\ChildRepository;
use App\Tests\Functional\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

/**
 * У Марии (фикстуры) двое детей: Артём (подтверждённая заявка) и Соня (заявка на рассмотрении).
 */
final class ChildrenApiTest extends ApiTestCase
{
    private const string URL = '/api/me/children';
    private const string OTHER_PARENT_EMAIL = 'anna.petrova@example.com';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function endpoints(): iterable
    {
        $id = '0190a8e2-7c3b-7d4e-8f5a-1b2c3d4e5f60';

        yield 'GET list' => ['GET', self::URL];
        yield 'POST create' => ['POST', self::URL];
        yield 'PATCH update' => ['PATCH', self::URL.'/'.$id];
        yield 'DELETE' => ['DELETE', self::URL.'/'.$id];
    }

    #[DataProvider('endpoints')]
    public function testRequiresAuthentication(string $method, string $url): void
    {
        $this->jsonRequest($method, $url, ['firstName' => 'Петя', 'age' => 5, 'gender' => 'male']);

        $problem = $this->assertProblem(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame('Требуется вход: access-токен не передан', $problem['detail']);
    }

    public function testListReturnsOwnChildrenInCreationOrder(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(200);
        $children = $this->json();
        self::assertCount(2, $children);

        [$artem, $sonya] = $children;
        self::assertSame(['id', 'firstName', 'lastName', 'age', 'gender'], array_keys($artem));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $artem['id']);
        self::assertSame(['Артём', 'Иванов', 8, 'male'], [$artem['firstName'], $artem['lastName'], $artem['age'], $artem['gender']]);
        self::assertSame(['Соня', 'Иванова', 5, 'female'], [$sonya['firstName'], $sonya['lastName'], $sonya['age'], $sonya['gender']]);
    }

    public function testListDoesNotIncludeOtherUsersChildren(): void
    {
        $this->loginAs(self::OTHER_PARENT_EMAIL);

        $this->client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Маша'], array_column($this->json(), 'firstName'));
    }

    public function testListIsEmptyForUserWithoutChildren(): void
    {
        $this->loginAs(AppFixtures::ADMIN_EMAIL);

        $this->client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->json());
    }

    public function testCreateReturns201AndChildAppearsInList(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->jsonRequest('POST', self::URL, ['firstName' => '  Лиза ', 'lastName' => 'Иванова', 'age' => 3, 'gender' => 'female']);

        self::assertResponseStatusCodeSame(201);
        $created = $this->json();
        self::assertSame('Лиза', $created['firstName']);
        self::assertSame('Иванова', $created['lastName']);
        self::assertSame(3, $created['age']);
        self::assertSame('female', $created['gender']);
        self::assertIsString($created['id']);

        $this->client->request('GET', self::URL);
        $list = $this->json();
        self::assertCount(3, $list);
        self::assertSame($created, $list[2]);
    }

    public function testCreateWithoutLastNameStoresNull(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->jsonRequest('POST', self::URL, ['firstName' => 'Петя', 'lastName' => '  ', 'age' => 18, 'gender' => 'male']);

        self::assertResponseStatusCodeSame(201);
        self::assertNull($this->json()['lastName']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidCreatePayloads(): iterable
    {
        $valid = ['firstName' => 'Петя', 'lastName' => 'Петров', 'age' => 7, 'gender' => 'male'];

        yield 'нет имени' => [array_diff_key($valid, ['firstName' => 0]), 'firstName', 'Укажите имя ребёнка'];
        yield 'пустое имя' => [['firstName' => '   '] + $valid, 'firstName', 'Укажите имя ребёнка'];
        yield 'длинное имя' => [['firstName' => str_repeat('я', 51)] + $valid, 'firstName', 'Имя не длиннее 50 символов'];
        yield 'длинная фамилия' => [['lastName' => str_repeat('я', 51)] + $valid, 'lastName', 'Фамилия не длиннее 50 символов'];
        yield 'нет возраста' => [array_diff_key($valid, ['age' => 0]), 'age', 'Укажите возраст ребёнка'];
        yield 'возраст 0' => [['age' => 0] + $valid, 'age', 'Возраст должен быть от 1 до 18 лет'];
        yield 'возраст 19' => [['age' => 19] + $valid, 'age', 'Возраст должен быть от 1 до 18 лет'];
        yield 'нет пола' => [array_diff_key($valid, ['gender' => 0]), 'gender', 'Укажите пол ребёнка'];
        yield 'неизвестный пол' => [['gender' => 'other'] + $valid, 'gender', 'Пол должен быть male или female'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidCreatePayloads')]
    public function testCreateValidation(array $payload, string $field, string $message): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->jsonRequest('POST', self::URL, $payload);

        $problem = $this->assertProblem(422);
        self::assertContains(['propertyPath' => $field, 'message' => $message], $problem['violations']);
        $this->assertChildrenCount(AppFixtures::DEMO_EMAIL, 2);
    }

    public function testCreateWithAgeOfWrongTypeReturns422(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->jsonRequest('POST', self::URL, ['firstName' => 'Петя', 'age' => 'восемь', 'gender' => 'male']);

        $problem = $this->assertProblem(422);
        self::assertContains('age', array_column($problem['violations'], 'propertyPath'));
    }

    public function testCreateWithMalformedJsonReturns400(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->client->request('POST', self::URL, server: ['CONTENT_TYPE' => 'application/json'], content: '{"firstName":');

        $this->assertProblem(400);
    }

    public function testPatchUpdatesOnlyPassedFields(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $artem = $this->child(AppFixtures::DEMO_EMAIL, 'Артём')->getId()->toRfc4122();

        $this->jsonRequest('PATCH', self::URL.'/'.$artem, ['age' => 9]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['id' => $artem, 'firstName' => 'Артём', 'lastName' => 'Иванов', 'age' => 9, 'gender' => 'male'],
            $this->json(),
        );
    }

    public function testPatchUpdatesAllFieldsAndPersists(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $sonya = $this->child(AppFixtures::DEMO_EMAIL, 'Соня')->getId()->toRfc4122();

        $this->jsonRequest('PATCH', self::URL.'/'.$sonya, ['firstName' => 'Софья', 'lastName' => 'Петрова', 'age' => 6, 'gender' => 'female']);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', self::URL);
        $updated = array_values(array_filter($this->json(), static fn (array $c): bool => $c['id'] === $sonya));
        self::assertSame(
            [['id' => $sonya, 'firstName' => 'Софья', 'lastName' => 'Петрова', 'age' => 6, 'gender' => 'female']],
            $updated,
        );
    }

    public function testPatchWithNullLastNameClearsIt(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $artem = $this->child(AppFixtures::DEMO_EMAIL, 'Артём')->getId()->toRfc4122();

        $this->jsonRequest('PATCH', self::URL.'/'.$artem, ['lastName' => null]);

        self::assertResponseStatusCodeSame(200);
        self::assertNull($this->json()['lastName']);
        self::assertSame('Артём', $this->json()['firstName']);
    }

    public function testPatchWithEmptyBodyChangesNothing(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $artem = $this->child(AppFixtures::DEMO_EMAIL, 'Артём')->getId()->toRfc4122();

        $this->jsonRequest('PATCH', self::URL.'/'.$artem, []);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Иванов', $this->json()['lastName']);
        self::assertSame(8, $this->json()['age']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidPatchPayloads(): iterable
    {
        yield 'пустое имя' => [['firstName' => ''], 'firstName', 'Имя ребёнка не может быть пустым'];
        yield 'длинное имя' => [['firstName' => str_repeat('я', 51)], 'firstName', 'Имя не длиннее 50 символов'];
        yield 'длинная фамилия' => [['lastName' => str_repeat('я', 51)], 'lastName', 'Фамилия не длиннее 50 символов'];
        yield 'возраст 0' => [['age' => 0], 'age', 'Возраст должен быть от 1 до 18 лет'];
        yield 'возраст 19' => [['age' => 19], 'age', 'Возраст должен быть от 1 до 18 лет'];
        yield 'неизвестный пол' => [['gender' => 'boy'], 'gender', 'Пол должен быть male или female'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPatchPayloads')]
    public function testPatchValidation(array $payload, string $field, string $message): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $artem = $this->child(AppFixtures::DEMO_EMAIL, 'Артём')->getId()->toRfc4122();

        $this->jsonRequest('PATCH', self::URL.'/'.$artem, $payload);

        $problem = $this->assertProblem(422);
        self::assertContains(['propertyPath' => $field, 'message' => $message], $problem['violations']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writeMethods(): iterable
    {
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('writeMethods')]
    public function testOtherUsersChildReturns404(string $method): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $foreign = $this->child(self::OTHER_PARENT_EMAIL, 'Маша')->getId()->toRfc4122();

        $this->jsonRequest($method, self::URL.'/'.$foreign, ['firstName' => 'Взлом']);

        $problem = $this->assertProblem(404);
        self::assertSame('Ребёнок не найден', $problem['detail']);

        // Чужой ребёнок не изменён и не удалён
        $this->assertChildrenCount(self::OTHER_PARENT_EMAIL, 1);
        self::assertSame('Маша', $this->childEntity($foreign)->getFirstName());
    }

    #[DataProvider('writeMethods')]
    public function testUnknownIdReturns404(string $method): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->jsonRequest($method, self::URL.'/0190a8e2-7c3b-7d4e-8f5a-1b2c3d4e5f60', ['age' => 5]);

        $problem = $this->assertProblem(404);
        self::assertSame('Ребёнок не найден', $problem['detail']);
    }

    #[DataProvider('writeMethods')]
    public function testMalformedIdReturns404(string $method): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);

        $this->jsonRequest($method, self::URL.'/not-a-uuid', ['age' => 5]);

        $problem = $this->assertProblem(404);
        self::assertSame('Ребёнок не найден', $problem['detail']);
    }

    public function testDeleteChildWithoutEnrollmentsReturns204(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $this->jsonRequest('POST', self::URL, ['firstName' => 'Лиза', 'age' => 3, 'gender' => 'female']);
        $id = $this->json()['id'];

        $this->client->request('DELETE', self::URL.'/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        $this->assertChildrenCount(AppFixtures::DEMO_EMAIL, 2);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function childrenWithActiveEnrollments(): iterable
    {
        yield 'подтверждённая заявка' => ['Артём'];
        yield 'заявка на рассмотрении' => ['Соня'];
    }

    #[DataProvider('childrenWithActiveEnrollments')]
    public function testDeleteChildWithActiveEnrollmentReturns409(string $firstName): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $id = $this->child(AppFixtures::DEMO_EMAIL, $firstName)->getId()->toRfc4122();

        $this->client->request('DELETE', self::URL.'/'.$id);

        $problem = $this->assertProblem(409);
        self::assertSame('Сначала отмените записи ребёнка', $problem['detail']);
        $this->assertChildrenCount(AppFixtures::DEMO_EMAIL, 2);
    }

    public function testDeleteChildWithOnlyFinishedEnrollmentsReturns204(): void
    {
        $this->loginAs(AppFixtures::DEMO_EMAIL);
        $this->jsonRequest('POST', self::URL, ['firstName' => 'Лиза', 'age' => 3, 'gender' => 'female']);
        $id = $this->json()['id'];

        // Отменённая заявка не мешает удалению (статус ставится напрямую, как в фикстурах)
        $em = $this->entityManager();
        $club = $this->club('gimnastika-voskhod');
        $enrollment = new Enrollment($this->user(AppFixtures::DEMO_EMAIL), $club, $club->getSingleBranch(), $this->childEntity($id));
        $enrollment->setMarking(EnrollmentStatus::Cancelled->value);
        $em->persist($enrollment);
        $em->flush();

        $this->client->request('DELETE', self::URL.'/'.$id);

        self::assertResponseStatusCodeSame(204);
        $this->assertChildrenCount(AppFixtures::DEMO_EMAIL, 2);
    }

    private function childEntity(string $id): Child
    {
        $child = self::getContainer()->get(ChildRepository::class)->find(Uuid::fromString($id));
        self::assertInstanceOf(Child::class, $child);

        return $child;
    }

    private function assertChildrenCount(string $email, int $expected): void
    {
        self::assertSame($expected, self::getContainer()->get(ChildRepository::class)->countByParent($this->user($email)));
    }
}
