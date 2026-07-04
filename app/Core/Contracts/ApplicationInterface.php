<?php

declare(strict_types=1);

namespace App\Core\Contracts;

interface ApplicationInterface
{
    public function bootstrap(): self;
    public function run(): void;
}