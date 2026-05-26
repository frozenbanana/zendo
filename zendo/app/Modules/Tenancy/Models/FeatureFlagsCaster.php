<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class FeatureFlagsCaster implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): FeatureFlags
    {
        $decoded = json_decode($value ?? '{}', true);

        return new FeatureFlags($decoded ?? []);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof FeatureFlags) {
            return json_encode($value->toArray());
        }

        if (is_array($value)) {
            return json_encode((new FeatureFlags($value))->toArray());
        }

        return '{}';
    }
}
