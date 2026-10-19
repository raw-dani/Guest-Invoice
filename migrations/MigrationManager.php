<?php

namespace GuestInvoice\Migrations;

use WHMCS\Database\Capsule;

class MigrationManager
{
    /**
     * Run all pending migrations.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function run(): array
    {
        try {
            self::createTokensTable();
            self::createLogsTable();
            self::createSettingsTable();
            self::createRateLimitsTable();
            self::seedDefaultSettings();

            return [
                'success' => true,
                'message' => 'Guest Invoice database tables created/verified successfully.'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Database migration error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create mod_guestinvoice_tokens table
     */
    public static function createTokensTable(): void
    {
        if (!Capsule::schema()->hasTable('mod_guestinvoice_tokens')) {
            Capsule::schema()->create('mod_guestinvoice_tokens', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('invoice_id')->index();
                $table->string('token_hash', 64)->unique();
                $table->string('raw_token', 128)->nullable();
                $table->enum('status', ['Active', 'Revoked', 'Expired'])->default('Active')->index();
                $table->dateTime('created_at');
                $table->dateTime('expires_at')->index();
                $table->dateTime('revoked_at')->nullable();
                $table->unsignedInteger('created_by_admin_id')->nullable();
                $table->dateTime('last_access_at')->nullable();
                $table->unsignedInteger('access_count')->default(0);
                $table->dateTime('updated_at');
            });
        } else {
            // Add raw_token column if migrating from previous version
            if (!Capsule::schema()->hasColumn('mod_guestinvoice_tokens', 'raw_token')) {
                Capsule::schema()->table('mod_guestinvoice_tokens', function ($table) {
                    $table->string('raw_token', 128)->nullable()->after('token_hash');
                });
            }
        }
    }

    /**
     * Create mod_guestinvoice_logs table
     */
    public static function createLogsTable(): void
    {
        if (!Capsule::schema()->hasTable('mod_guestinvoice_logs')) {
            Capsule::schema()->create('mod_guestinvoice_logs', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('token_id')->nullable()->index();
                $table->unsignedInteger('invoice_id')->nullable()->index();
                $table->string('event_type', 50)->index();
                $table->string('result', 30)->default('success');
                $table->enum('actor_type', ['admin', 'guest', 'system'])->default('guest');
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('ip_hash', 64)->nullable()->index();
                $table->string('user_agent_hash', 64)->nullable();
                $table->text('details')->nullable();
                $table->dateTime('created_at')->index();
            });
        }
    }

    /**
     * Create mod_guestinvoice_settings table
     */
    public static function createSettingsTable(): void
    {
        if (!Capsule::schema()->hasTable('mod_guestinvoice_settings')) {
            Capsule::schema()->create('mod_guestinvoice_settings', function ($table) {
                $table->increments('id');
                $table->string('setting_key', 64)->unique();
                $table->text('setting_value')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }
    }

    /**
     * Create mod_guestinvoice_rate_limits table
     */
    public static function createRateLimitsTable(): void
    {
        if (!Capsule::schema()->hasTable('mod_guestinvoice_rate_limits')) {
            Capsule::schema()->create('mod_guestinvoice_rate_limits', function ($table) {
                $table->increments('id');
                $table->string('ip_hash', 64)->index();
                $table->string('action_key', 32)->index();
                $table->unsignedInteger('attempt_count')->default(1);
                $table->dateTime('first_attempt_at');
                $table->dateTime('last_attempt_at');
                $table->dateTime('locked_until')->nullable()->index();
            });
        }
    }

    /**
     * Seed initial configuration defaults
     */
    public static function seedDefaultSettings(): void
    {
        $defaults = [
            'default_expiry_days' => '30',
            'allowed_statuses' => json_encode(['Unpaid', 'Overdue']),
            'allow_pdf_download' => '0',
            'auto_generate_on_invoice_created' => '1',
            'auto_email_on_invoice_created' => '0',
            'rate_limit_per_minute' => '15',
            'rate_limit_lockout_minutes' => '15',
            'log_retention_days' => '90',
            'custom_branding_title' => 'Secure Guest Invoice & Payment Portal',
            'enable_guest_payment' => '1',
            'supported_gateways' => json_encode(['banktransfer', 'midtrans', 'xendit', 'tripay', 'doku']),
            'email_template_name' => 'Guest Invoice Link Delivery',
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($defaults as $key => $val) {
            $exists = Capsule::table('mod_guestinvoice_settings')
                ->where('setting_key', $key)
                ->first();

            if (!$exists) {
                Capsule::table('mod_guestinvoice_settings')->insert([
                    'setting_key' => $key,
                    'setting_value' => $val,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Drop all module tables upon explicit uninstall (never on simple deactivation)
     */
    public static function uninstall(): void
    {
        Capsule::schema()->dropIfExists('mod_guestinvoice_rate_limits');
        Capsule::schema()->dropIfExists('mod_guestinvoice_settings');
        Capsule::schema()->dropIfExists('mod_guestinvoice_logs');
        Capsule::schema()->dropIfExists('mod_guestinvoice_tokens');
    }
}
