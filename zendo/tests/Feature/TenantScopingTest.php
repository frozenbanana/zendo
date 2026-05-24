<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use App\Modules\Tenancy\Models\Concerns\HasTenantScopeThrough;

class TenantScopingTest extends TestCase
{
    protected array $globalModels = [
        \App\Models\User::class,
        \App\Modules\People\Models\GuestProfile::class,
        \App\Modules\Events\Models\Teacher::class,
        \App\Modules\Events\Models\Category::class,
    ];

    public function test_all_models_with_tenant_id_have_scoping(): void
    {
        $modelsUsingScope = $this->getModelsUsingTrait(HasTenantScope::class);
        $modelsUsingScopeThrough = $this->getModelsUsingTrait(HasTenantScopeThrough::class);

        $modelFiles = (new Filesystem())->allFiles(app_path('Modules'));
        $violations = [];

        foreach ($modelFiles as $file) {
            if (str_contains($file->getPathname(), 'Concerns')) {
                continue;
            }

            if (str_contains($file->getPathname(), 'Middleware')) {
                continue;
            }

            $class = $this->getClassFromFile($file);
            if (!$class || !class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            // Skip global models
            if (in_array($class, $this->globalModels)) {
                continue;
            }

            $model = new $class;
            $tableName = $model->getTable();

            // Skip if the table doesn't exist yet (migrations haven't run)
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // If the table has a tenant_id column, the model MUST use a scope trait
            if (Schema::hasColumn($tableName, 'tenant_id')) {
                if (!in_array($class, $modelsUsingScope) && !in_array($class, $modelsUsingScopeThrough)) {
                    $violations[] = $class;
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "The following models have a tenant_id column but are missing a tenant scope trait:\n" .
            collect($violations)->map(fn($v) => "  - {$v}")->join("\n") .
            "\n\nAdd HasTenantScope or HasTenantScopeThrough to each model."
        );
    }

    public function test_global_models_do_not_have_tenant_scoping(): void
    {
        foreach ($this->globalModels as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $traits = class_uses_recursive($modelClass);

            $this->assertArrayNotHasKey(
                HasTenantScope::class,
                $traits,
                "{$modelClass} is a global model but uses HasTenantScope."
            );
        }
    }

    protected function getModelsUsingTrait(string $trait): array
    {
        $models = [];
        $modelFiles = (new Filesystem())->allFiles(app_path('Modules'));

        foreach ($modelFiles as $file) {
            $class = $this->getClassFromFile($file);
            if ($class && class_exists($class) && is_subclass_of($class, Model::class) && in_array($trait, class_uses_recursive($class))) {
                $models[] = $class;
            }
        }

        return $models;
    }

    protected function getClassFromFile($file): ?string
    {
        $contents = $file->getContents();

        if (!preg_match('/namespace\s+([^;]+);/', $contents, $namespace)) {
            return null;
        }

        if (!preg_match('/class\s+(\w+)/', $contents, $className)) {
            return null;
        }

        return $namespace[1] . '\\' . $className[1];
    }
}
