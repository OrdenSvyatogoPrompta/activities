<?php

declare(strict_types=1);

namespace App\Dto\Activity;

use App\Entity\Enum\Category;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Тело `POST /api/me/activities`.
 *
 * Все поля допускают null на уровне типа, чтобы отсутствующее поле давало понятное
 * сообщение валидатора на русском, а не ошибку денормализации.
 */
final readonly class CreateActivityRequest
{
    /**
     * @param list<string>|null              $tags
     * @param list<ActivityBranchInput>|null $branches
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название', normalizer: 'trim')]
        #[Assert\Length(min: 2, max: 100, minMessage: 'Название — не короче {{ limit }} символов', maxMessage: 'Название — не длиннее {{ limit }} символов', normalizer: 'trim')]
        public ?string $name = null,

        #[Assert\NotBlank(message: 'Выберите категорию')]
        #[Assert\Choice(callback: 'categories', message: 'Допустимые категории: Спорт, Творчество, Наука, Музыка, Танцы')]
        public ?string $category = null,

        /** По умолчанию 🎯. */
        #[Assert\Length(max: 16, maxMessage: 'Эмодзи — не длиннее {{ limit }} символов')]
        public ?string $emoji = null,

        #[Assert\NotBlank(message: 'Добавьте описание', normalizer: 'trim')]
        #[Assert\Length(max: 5000, maxMessage: 'Описание — не длиннее {{ limit }} символов')]
        public ?string $description = null,

        #[Assert\NotNull(message: 'Укажите минимальный возраст')]
        #[Assert\Range(min: 0, max: 99, notInRangeMessage: 'Возраст — от {{ min }} до {{ max }} лет')]
        public ?int $ageMin = null,

        #[Assert\NotNull(message: 'Укажите максимальный возраст')]
        #[Assert\Range(min: 0, max: 99, notInRangeMessage: 'Возраст — от {{ min }} до {{ max }} лет')]
        public ?int $ageMax = null,

        #[Assert\NotBlank(message: 'Выберите формат посещения')]
        #[Assert\Choice(callback: 'visitTypes', message: 'Допустимые форматы: recurring, oneTime, both')]
        public ?string $visitType = null,

        #[Assert\NotBlank(message: 'Выберите тип цены')]
        #[Assert\Choice(callback: 'priceTypes', message: 'Допустимые типы цены: free, pushkin, paid')]
        public ?string $priceType = null,

        /** ₽/мес; для free игнорируется и сохраняется 0. */
        #[Assert\Range(min: 0, max: 1_000_000, notInRangeMessage: 'Стоимость — от {{ min }} до {{ max }} ₽')]
        public ?int $priceAmount = null,

        /** @var list<string>|null */
        #[Assert\Count(max: 20, maxMessage: 'Не больше {{ limit }} тегов')]
        #[Assert\All([
            new Assert\Type(type: 'string', message: 'Тег должен быть строкой'),
            new Assert\NotBlank(message: 'Тег не может быть пустым', normalizer: 'trim'),
            new Assert\Length(max: 50, maxMessage: 'Тег — не длиннее {{ limit }} символов'),
        ])]
        public ?array $tags = null,

        /** @var list<ActivityBranchInput>|null */
        #[Assert\NotNull(message: 'Добавьте хотя бы один филиал')]
        #[Assert\Count(min: 1, max: 10, minMessage: 'Добавьте хотя бы один филиал', maxMessage: 'Не больше {{ limit }} филиалов')]
        #[Assert\Valid]
        public ?array $branches = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_map(static fn (Category $case): string => $case->value, Category::cases());
    }

    /**
     * @return list<string>
     */
    public static function visitTypes(): array
    {
        return array_map(static fn (VisitType $case): string => $case->value, VisitType::cases());
    }

    /**
     * @return list<string>
     */
    public static function priceTypes(): array
    {
        return array_map(static fn (PriceType $case): string => $case->value, PriceType::cases());
    }

    #[Assert\Callback]
    public function validateRanges(ExecutionContextInterface $context): void
    {
        if (null !== $this->ageMin && null !== $this->ageMax && $this->ageMin > $this->ageMax) {
            $context->buildViolation('Минимальный возраст не может быть больше максимального')
                ->atPath('ageMax')
                ->addViolation();
        }

        // Для бесплатных цена не нужна (сохраняется 0), для остальных — обязательна
        if (null !== $this->priceType && PriceType::Free->value !== $this->priceType && null === $this->priceAmount) {
            $context->buildViolation('Укажите стоимость')
                ->atPath('priceAmount')
                ->addViolation();
        }
    }
}
