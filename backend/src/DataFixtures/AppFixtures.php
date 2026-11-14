<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Service\Seed\DemoData;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly DemoData $demoData)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->demoData->load($manager);
    }
}
