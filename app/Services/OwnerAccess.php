<?php
declare(strict_types=1);
namespace App\Services;
final class OwnerAccess {
    public static function path(): ?string {
        $path=getenv('OWNER_LOGIN_PATH');
        // Canonical path only; at least 128 random bits in the generated suffix.
        return is_string($path) && preg_match('~^/(?:[a-z0-9-]+/)*[a-z0-9-]*[a-f0-9]{32,64}$~D',$path) && strlen($path)<=240 ? $path : null;
    }
    public static function matches(string $path): bool { $configured=self::path();return $configured!==null && hash_equals($configured,$path); }
    public static function generate(): string { return '/sanctuaire/'.bin2hex(random_bytes(32)); }
}
