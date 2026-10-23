<?php
declare(strict_types=1);
namespace App\Services;

final class CatalogService
{
    public const REWARDS = ['reward_xp' => 'XP', 'reward_gold' => 'Pièces', 'reward_gems' => 'Gemmes', 'reward_keys' => 'Clés'];

    public static function rewardsKnown(array $item): bool
    {
        foreach (self::REWARDS as $field => $label) {
            if ($item[$field] === null) return false;
        }
        return (bool) $item['title_rewards_known'];
    }

    public static function stats(array $items): array
    {
        return [count($items), count(array_filter($items, fn($a) => $a['secret_type'] === 'secret')),
            count(array_filter($items, self::rewardsKnown(...))),
            count(array_filter($items, fn($a) => $a['verification_status'] === 'verified'))];
    }

    public static function image(?string $path): ?string
    {
        // Aucun lien distant, SVG importé, protocole ou traversée de répertoire.
        if ($path && preg_match('~^/media/[a-f0-9]{64}\.(?:jpg|png|webp)$~D', $path)) return $path;
        return $path && preg_match('~^/assets/images/[a-zA-Z0-9_-]+\.(png|jpe?g|webp|avif)$~D', $path)
            ? $path : null;
    }
}
