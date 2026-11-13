<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\Gender;
use App\Repository\ChildRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChildRepository::class)]
#[ORM\Table(name: 'child')]
class Child
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $parent;

    #[ORM\Column(length: 50)]
    private string $firstName;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lastName;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $age;

    #[ORM\Column(length: 8, enumType: Gender::class)]
    private Gender $gender;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $parent, string $firstName, ?string $lastName, int $age, Gender $gender)
    {
        $this->id = Uuid::v7();
        $this->parent = $parent;
        $this->firstName = $firstName;
        $this->lastName = self::emptyToNull($lastName);
        $this->age = $age;
        $this->gender = $gender;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function belongsTo(User $user): bool
    {
        return $this->parent->equals($user);
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = self::emptyToNull($lastName);

        return $this;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getGender(): Gender
    {
        return $this->gender;
    }

    public function setGender(Gender $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function emptyToNull(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
