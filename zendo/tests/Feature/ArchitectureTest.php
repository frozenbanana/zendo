<?php

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

test('all models with tenant_id column use HasTenantScope trait')
    ->expect(function () {
        $violations = [];
        $modelFiles = (new Filesystem)->allFiles(app_path('Modules'));

        foreach ($modelFiles as $file) {
            if (str_contains($file->getPathname(), 'Concerns') || str_contains($file->getPathname(), 'Middleware')) {
                continue;
            }

            $contents = $file->getContents();
            if (! preg_match('/namespace\s+([^;]+);/', $contents, $namespace)) {
                continue;
            }
            if (! preg_match('/class\s+(\w+)/', $contents, $className)) {
                continue;
            }

            $class = $namespace[1].'\\'.$className[1];
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $model = new $class;
            $tableName = $model->getTable();

            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'tenant_id')) {
                $traits = class_uses_recursive($class);
                if (! isset($traits[HasTenantScope::class])) {
                    $violations[] = $class;
                }
            }
        }

        return $violations;
    })
    ->toBeEmpty('Models with tenant_id must use HasTenantScope trait');

test('models using HasTenantScope have tenant_id column in fillable')
    ->expect(function () {
        $modelFiles = (new Filesystem)->allFiles(app_path('Modules'));
        $violations = [];

        foreach ($modelFiles as $file) {
            if (str_contains($file->getPathname(), 'Concerns') || str_contains($file->getPathname(), 'Middleware')) {
                continue;
            }

            $contents = $file->getContents();
            if (! preg_match('/namespace\s+([^;]+);/', $contents, $namespace)) {
                continue;
            }
            if (! preg_match('/class\s+(\w+)/', $contents, $className)) {
                continue;
            }

            $class = $namespace[1].'\\'.$className[1];
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $traits = class_uses_recursive($class);
            if (! isset($traits[HasTenantScope::class])) {
                continue;
            }

            $model = new $class;
            if (! in_array('tenant_id', $model->getFillable())) {
                $violations[] = $class;
            }
        }

        return $violations;
    })
    ->toBeEmpty('Models using HasTenantScope must include tenant_id in fillable');
