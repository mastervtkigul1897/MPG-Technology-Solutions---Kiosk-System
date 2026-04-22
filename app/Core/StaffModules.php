<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Per-cashier module keys (store owner assigns via Staff screen).
 * tenant_admin always has full access.
 */
final class StaffModules
{
    /** @var array<string,string> */
    public const LABELS = [
        'pos' => 'Loads Status',
        'transactions' => 'Customer Profile',
        'activity_logs' => 'Activity log',
        'ingredients' => 'Inventory Management',
        'expenses' => 'Expenses',
        'damaged_items' => 'Damaged items',
    ];

    /** Always granted to every cashier; store owner adds optional modules on top. */
    public const REQUIRED_BASELINE = ['pos', 'transactions', 'activity_logs'];

    /** Legacy / NULL column: same as baseline (no optional modules). */
    public const DEFAULT_CASHIER = self::REQUIRED_BASELINE;

    public static function isValidKey(string $key): bool
    {
        return array_key_exists($key, self::LABELS);
    }

    /**
     * NULL/empty column = legacy cashiers: default set. Explicit JSON [] = no modules.
     *
     * @return list<string>
     */
    public static function normalizeCashierModules(?string $json): array
    {
        if ($json === null || $json === '') {
            return self::DEFAULT_CASHIER;
        }
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return self::DEFAULT_CASHIER;
        }

        $valid = array_keys(self::LABELS);

        return array_values(array_unique(array_intersect($decoded, $valid)));
    }

    /**
     * @param list<string> $requested
     *
     * @return list<string>
     */
    public static function sanitizeRequested(array $requested): array
    {
        $valid = array_keys(self::LABELS);

        return array_values(array_unique(array_intersect($requested, $valid)));
    }

    /** Modules the owner may toggle (not the mandatory baseline). */
    public static function optionalModuleKeys(): array
    {
        return array_values(array_diff(array_keys(self::LABELS), self::REQUIRED_BASELINE));
    }

    /**
     * Ensures POS, Transactions, and Activity log are always present.
     *
     * @param list<string> $ownerPicked Optional modules from the form (may be empty).
     *
     * @return list<string>
     */
    public static function mergeRequiredBaseline(array $ownerPicked): array
    {
        $merged = array_values(array_unique(array_merge(self::REQUIRED_BASELINE, self::sanitizeRequested($ownerPicked))));

        return array_values(array_intersect($merged, array_keys(self::LABELS)));
    }
}
