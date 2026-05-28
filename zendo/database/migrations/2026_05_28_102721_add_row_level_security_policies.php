<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tenantTables = [
        'events',
        'buildings',
        'rooms',
        'meal_plans',
        'membership_plans',
        'registrations',
        'invoices',
        'invoice_line_items',
        'payments',
        'refunds',
        'discount_codes',
        'outbox_entries',
        'user_tenant_roles',
    ];

    public function up(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tenants ENABLE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY tenant_all ON tenants
                FOR ALL
                TO authenticated
                USING (id = current_setting('app.current_tenant_id', true)::uuid)
        ");

        DB::statement("
            CREATE POLICY super_admin_all ON tenants
                FOR ALL
                TO authenticated
                USING (
                    EXISTS (
                        SELECT 1 FROM user_tenant_roles
                        WHERE user_tenant_roles.user_id = auth.uid()
                        AND user_tenant_roles.role = 'SUPER_ADMIN'
                    )
                )
        ");

        foreach ($this->tenantTables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                    FOR ALL
                    TO authenticated
                    USING (tenant_id::text = current_setting('app.current_tenant_id', true))
            ");

            DB::statement("
                CREATE POLICY super_admin_all ON {$table}
                    FOR ALL
                    TO authenticated
                    USING (
                        EXISTS (
                            SELECT 1 FROM user_tenant_roles
                            JOIN tenants ON tenants.id = user_tenant_roles.tenant_id
                            WHERE user_tenant_roles.user_id = auth.uid()
                            AND user_tenant_roles.role = 'SUPER_ADMIN'
                        )
                    )
            ");
        }
    }

    public function down(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        foreach ($this->tenantTables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("DROP POLICY IF EXISTS super_admin_all ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        DB::statement('DROP POLICY IF EXISTS tenant_all ON tenants');
        DB::statement('DROP POLICY IF EXISTS super_admin_all ON tenants');
        DB::statement('ALTER TABLE tenants DISABLE ROW LEVEL SECURITY');
    }
};
