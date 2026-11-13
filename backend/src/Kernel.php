<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const string DEFAULT_TIMEZONE = 'Asia/Vladivostok';

    public function boot(): void
    {
        // Таймзона приложения (раздел 2 docs/backend-requirements.md) — одна для HTTP, консоли
        // и тестов, не зависит от date.timezone в php.ini образа
        date_default_timezone_set($_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? self::DEFAULT_TIMEZONE);

        parent::boot();
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
