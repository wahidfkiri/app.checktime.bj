<?php

namespace App\Support;

/**
 * Mots de passe initiaux communiqués à l'utilisateur.
 *
 * Les caractères ambigus (0/O, 1/l/I) sont écartés : ces mots de passe sont
 * transmis à l'oral ou recopiés à la main.
 *
 * Str::password() n'existe pas dans Laravel 9, d'où ce générateur.
 */
class PasswordGenerator
{
    private const LETTERS_UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LETTERS_LOWER = 'abcdefghijkmnpqrstuvwxyz';
    private const DIGITS = '23456789';
    private const SYMBOLS = '!@#$%&*';

    public static function make(int $length = 12): string
    {
        $length = max(8, $length);
        $alphabet = self::LETTERS_UPPER . self::LETTERS_LOWER . self::DIGITS . self::SYMBOLS;

        // Au moins un caractère de chaque famille, pour satisfaire les
        // politiques de mot de passe usuelles.
        $password = [
            self::pick(self::LETTERS_UPPER),
            self::pick(self::LETTERS_LOWER),
            self::pick(self::DIGITS),
            self::pick(self::SYMBOLS),
        ];

        for ($i = count($password); $i < $length; $i++) {
            $password[] = self::pick($alphabet);
        }

        shuffle($password);

        return implode('', $password);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
