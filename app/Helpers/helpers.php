<?php

use App\Models\Setting;

if (!function_exists('setting')) {
    /**
     * Get setting value
     */
    function setting(string $key, $default = null)
    {
        try {
            return Setting::get($key, $default);
        } catch (\Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('media_url')) {
    /**
     * Resolve a stored media path to a usable URL.
     *
     * Uploaded files are kept as relative paths under the public disk, but
     * seeded and imported records may hold an absolute URL instead. Wrapping
     * one of those in asset('storage/...') produced a broken link, so the
     * two cases are distinguished in exactly one place here.
     */
    function media_url(?string $path, ?string $default = null): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return $default;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format currency
     */
    function format_currency($amount): string
    {
        return '$' . number_format($amount, 2);
    }
}

if (!function_exists('contract_status_badge')) {
    /**
     * Get contract status badge HTML
     */
    function contract_status_badge(string $status): string
    {
        $badges = [
            'not_assigned' => '<span class="badge bg-warning">Not Assigned</span>',
            'active' => '<span class="badge bg-success">Active</span>',
            'closing_soon' => '<span class="badge bg-orange">Closing Soon</span>',
            'expired' => '<span class="badge bg-danger">Expired</span>',
            'closed' => '<span class="badge bg-secondary">Closed</span>',
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}

if (!function_exists('contract_status_color')) {
    /**
     * Get contract status row color class
     */
    function contract_status_color(string $status): string
    {
        $colors = [
            'not_assigned' => 'table-warning',
            'active' => 'table-success',
            'closing_soon' => 'table-orange',
            'expired' => 'table-danger',
            'closed' => 'table-secondary',
        ];

        return $colors[$status] ?? '';
    }
}

if (!function_exists('employee_status_badge')) {
    /**
     * Get employee status badge HTML
     */
    function employee_status_badge(string $status): string
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'active' => '<span class="badge bg-success">Active</span>',
            'inactive' => '<span class="badge bg-danger">Inactive</span>',
            'suspended' => '<span class="badge bg-dark">Suspended</span>',
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}

if (!function_exists('payment_status_badge')) {
    /**
     * Get payment status badge HTML
     */
    function payment_status_badge(string $status): string
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'completed' => '<span class="badge bg-success">Completed</span>',
            'failed' => '<span class="badge bg-danger">Failed</span>',
            'refunded' => '<span class="badge bg-info">Refunded</span>',
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}

