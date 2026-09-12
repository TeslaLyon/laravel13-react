<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): float
    {
        return round(((int) ($value ?? 0)) / 100, 2);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return (int) bcmul((string) ($value ?? 0), '100', 0);
    }
}
