<?php

declare(strict_types=1);

namespace App\Dto\Children;

use App\Entity\Enum\Gender;

/**
 * Допустимые значения `gender` для валидации запросов.
 *
 * Пол принимается строкой и проверяется `Assert\Choice`, а не денормализуется сразу в enum:
 * так неверное значение даёт русское сообщение, а не «Значение должно иметь тип …».
 * При добавлении значения в {@see Gender} его нужно добавить и сюда.
 */
final class ChildGender
{
    /** @var list<string> */
    public const array VALUES = [Gender::Male->value, Gender::Female->value];

    public const string MESSAGE = 'Пол должен быть male или female';
}
