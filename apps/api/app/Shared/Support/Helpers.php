<?php

namespace App\Shared\Support;

/**
 * Collection of helper utilities used across the application.
 */
class Helpers
{
    /**
     * Generate a URL-friendly slug from a string.
     */
    public static function generateSlug(string $value): string
    {
        return \Illuminate\Support\Str::slug($value);
    }

    /**
     * Generate a unique slug ensuring no conflict with existing records.
     */
    public static function generateUniqueSlug(string $value, string $model, string $column = 'slug', ?int $ignoreId = null): string
    {
        $slug = static::generateSlug($value);
        $original = $slug;
        $counter = 1;

        $query = $model::where($column, $slug);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        while ($query->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
            $query = $model::where($column, $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
        }

        return $slug;
    }

    /**
     * Format bytes to human-readable string.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
