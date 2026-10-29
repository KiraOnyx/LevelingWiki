<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class Auth
{
    private array $config;
    public bool $expired=false;
    public function __construct(private PDO $pdo) { $this->config = require dirname(__DIR__, 2) . '/config/security.php'; }
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.gc_maxlifetime','86400');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.upload_progress.enabled', '0');
        session_name('lw_session');
        session_set_cookie_params(['lifetime'=>86400, 'path'=>'/', 'secure'=>$this->config['secure_cookie'], 'httponly'=>true, 'samesite'=>'Lax']);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public function csrf(): string { $this->start(); return $_SESSION['csrf']; }
    public function validCsrf(mixed $token): bool { return is_string($token) && hash_equals($this->csrf(), $token); }
    // Paramètre conservé pour les appelants existants : toutes les routes utilisent le même cache.
    public function user(bool $sensitive = false): ?array
    {
        $this->start();
        if (!isset($_SESSION['user_id'])) return null;
        $now = time();
        if ($now - ($_SESSION['last_seen'] ?? 0) >= $this->config['idle_seconds']
            || $now - ($_SESSION['signed_in'] ?? 0) >= $this->config['absolute_seconds']) {
            $this->expired=true;$this->logout(); return null;
        }
        $statement = $this->pdo->prepare('SELECT id, username, email, role, auth_provider, is_active, session_version, must_change_password, created_at FROM users WHERE id = ?');
        $statement->execute([$_SESSION['user_id']]);
        $user = $statement->fetch();
        if (!$user || !$user['is_active'] || !in_array($user['role'], ['admin','editor','member','super_admin'], true)
            || (int) $user['session_version'] !== ($_SESSION['session_version'] ?? 0)) {
            $this->logout(); return null;
        }
        if ($user['auth_provider']==='discord') {
            $user['role']=(new DiscordAuthService($this->pdo))->authorize($user);
            if(in_array($_SESSION['discord_failure']??null,['oauth','session'],true)){$this->expired=true;$this->logout();return null;}
            $profile=$this->pdo->prepare('SELECT display_name,discord_user_id,avatar_hash FROM discord_identities WHERE user_id=?');$profile->execute([$user['id']]);
            $identity=$profile->fetch();$user['display_name']=$identity['display_name']??$user['username'];$user['discord_user_id']=$identity['discord_user_id']??null;$user['avatar_hash']=$identity['avatar_hash']??null;
        }
        elseif ($user['role']!=='super_admin') $user['role']='legacy';
        $_SESSION['last_seen'] = $now;
        return $user;
    }
    public function login(string $identifier, string $password, string $ip): bool
    {
        $this->start();
        $identifier = strtolower(trim($identifier));
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$identifier]);
        $user = $statement->fetch();
        $buckets = [hash('sha256', 'ip:' . $ip) => $this->config['login_ip_limit'],
            hash('sha256', 'account:' . ($user ? $user['id'] : $identifier)) => $this->config['login_account_limit']];
        ksort($buckets);
        $allowed = true;
        $this->pdo->beginTransaction();
        try {
            $now = time(); $cutoff = $now - $this->config['login_window'];
            foreach ($buckets as $bucket => $limit) {
                $statement = $this->pdo->prepare('INSERT INTO login_limits (bucket, attempts, window_started) VALUES (?, 1, ?)
                    ON DUPLICATE KEY UPDATE attempts = IF(window_started <= ?, 1, LEAST(attempts + 1, 1000000)),
                    window_started = IF(window_started <= ?, ?, window_started)');
                $statement->execute([$bucket, $now, $cutoff, $cutoff, $now]);
                $query = $this->pdo->prepare('SELECT attempts FROM login_limits WHERE bucket = ?');
                $query->execute([$bucket]);
                if ((int) $query->fetchColumn() > $limit) $allowed = false;
            }
            $this->pdo->prepare('DELETE FROM login_limits WHERE window_started < ?')->execute([$now - 86400]);
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
        if (!$allowed) { Audit::record($this->pdo,null,'owner.login_limited','authentication',null);http_response_code(429); return false; }
        // Hash factice uniquement pour uniformiser le travail sur un identifiant inconnu ; aucun compte associé.
        $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $user['password_hash'] ?? $dummy);
        if (!$valid || !$user || !$user['is_active'] || $user['auth_provider']!=='local' || $user['role']!=='super_admin') {Audit::record($this->pdo,null,'owner.login_failed','authentication',null);return false;}
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT, ['cost'=>12])) {
            $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?')->execute([password_hash($password, PASSWORD_DEFAULT, ['cost'=>12]), $user['id'], $user['password_hash']]);
        }
        $this->pdo->prepare('DELETE FROM login_limits WHERE bucket = ?')->execute([hash('sha256', 'account:' . $user['id'])]);
        session_regenerate_id(true);
        $_SESSION = ['user_id'=>(int) $user['id'], 'session_version'=>(int) $user['session_version'],
            'signed_in'=>time(), 'last_seen'=>time(), 'csrf'=>bin2hex(random_bytes(32))];
        Audit::record($this->pdo, (int) $user['id'], 'login', 'user', (int) $user['id']);
        return true;
    }
    public function confirmPassword(array $user, mixed $password): bool
    {
        if (!is_string($password) || strlen($password) > 72 || str_contains($password, "\0")) return false;
        $query = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $query->execute([$user['id']]);
        return password_verify($password, (string) $query->fetchColumn());
    }
    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires'=>time()-3600, 'path'=>'/', 'secure'=>$params['secure'], 'httponly'=>true, 'samesite'=>'Lax']);
        session_destroy();
    }
}
