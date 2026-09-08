<?php

namespace App\Contracts;

interface MacroContextProvider
{
    public function generate(array $technicalContext, array $evidence): array;

    public function name(): string;

    public function model(): string;

    public function configured(): bool;
}
