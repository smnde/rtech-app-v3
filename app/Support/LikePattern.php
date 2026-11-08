<?php

namespace App\Support;

final class LikePattern
{
    /**
     * Pola "mengandung" untuk query LIKE, dengan wildcard user di-escape
     * supaya karakter % dan _ diperlakukan sebagai teks biasa.
     */
    public static function contains(string $term): string
    {
        return '%'.self::escape($term).'%';
    }

    /**
     * Pola "diawali dengan" untuk query LIKE.
     */
    public static function startsWith(string $term): string
    {
        return self::escape($term).'%';
    }

    private static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
