<?php

namespace App\Contracts;

interface MacroContextProvider
{
    public function generate(array $technicalContext): array;

    public function name(): string;
}
