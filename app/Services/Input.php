<?php
declare(strict_types=1);
namespace App\Services;
final class Input
{
    public static function text(array $data, string $key, int $max, bool $required = false): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || !preg_match('//u', $value) || str_contains($value, "\0")) throw new ValidationException('Champ invalide : ' . $key);
        $value = trim($value);
        if (($required && $value === '') || preg_match_all('/./us', $value) > $max) throw new ValidationException('Champ manquant ou trop long : ' . $key);
        return $value;
    }
    public static function choice(array $data, string $key, array $allowed): string
    {
        $value = self::text($data, $key, 50, true);
        if (!in_array($value, $allowed, true)) throw new ValidationException('Valeur invalide : ' . $key);
        return $value;
    }
    public static function id(mixed $value): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $value)) throw new ValidationException('Identifiant invalide.');
        return (int) $value;
    }
    public static function password(mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 12 || strlen($value) > 72 || str_contains($value, "\0")) {
            throw new ValidationException('Le mot de passe doit contenir entre 12 et 72 octets. Privilégiez une longue phrase unique.');
        }
        return $value;
    }
    public static function slug(string $value): string
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value) || strlen($value) > 191) throw new ValidationException('Le slug doit contenir uniquement des lettres minuscules, chiffres et tirets.');
        return $value;
    }
    public static function amount(array $data, string $key): ?string
    {
        $value = self::text($data, $key, 20);
        if ($value === '') return null;
        if (!ctype_digit($value)) throw new ValidationException('Les récompenses doivent être des entiers positifs ou nuls, ou rester vides.');
        $value = ltrim($value, '0') ?: '0';
        if (strlen($value) > 20 || (strlen($value) === 20 && strcmp($value, '18446744073709551615') > 0)) throw new ValidationException('Récompense trop élevée.');
        return $value;
    }
}
