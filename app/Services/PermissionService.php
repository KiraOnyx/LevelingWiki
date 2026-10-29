<?php
declare(strict_types=1);
namespace App\Services;
final class PermissionService
{
    public static function manageDiscordLevel(array $user,string $level): bool {
        $ranks=['member'=>1,'editor'=>2,'admin'=>3];
        if (!isset($ranks[$level]) || !self::member($user)) return false;
        return self::owner($user) || ($ranks[$user['role']]??0)>$ranks[$level];
    }
    public static function discordSettings(array $user): bool { return self::manageDiscordLevel($user,'member'); }
    public static function member(array $user): bool { return in_array($user['role'],['member','editor','admin','super_admin'],true) && !($user['must_change_password'] ?? false); }
    public static function edit(array $user): bool { return in_array($user['role'],['editor','admin','super_admin'],true); }
    public static function admin(array $user): bool { return in_array($user['role'],['admin','super_admin'],true); }
    public static function owner(array $user): bool { return $user['role']==='super_admin' && $user['auth_provider']==='local'; }
}
