<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class UserService
{
    public function __construct(private PDO $pdo) {}
    private function identity(array $data): array
    {
        $name = Input::text($data,'username',50,true); $email = $this->email($data);
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D',$name)) throw new ValidationException('Identifiant : 3 à 50 lettres ASCII, chiffres, points, tirets ou underscores.');
        return ['username'=>$name,'email'=>$email];
    }
    private function email(array $data): ?string
    {
        $email = strtolower(Input::text($data, 'email', 254));
        if ($email === '') return null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationException('Email invalide.');
        return $email;
    }
    public function bootstrap(array $data): int
    {
        if (PHP_SAPI !== 'cli') throw new ValidationException('Commande CLI requise.');
        if ((int) $this->pdo->query("SELECT GET_LOCK('levelingwiki_first_admin', 10)")->fetchColumn() !== 1) throw new ValidationException('Commande déjà en cours.');
        try {
            if ((int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin') AND is_active = 1")->fetchColumn() > 0) throw new ValidationException('Un administrateur actif existe déjà. Utilisez la gestion des utilisateurs.');
            return $this->create([...$data,'role'=>'admin'],null,false);
        } finally { $this->pdo->query("SELECT RELEASE_LOCK('levelingwiki_first_admin')"); }
    }
    public function create(array $data, ?int $actor, bool $changeRequired = true): int
    {
        $values = $this->identity($data); $role = Input::choice($data,'role',['admin','editor']);
        $password = Input::password($data['password'] ?? null);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO users (username,email,role,password_hash,is_active,must_change_password) VALUES (?,?,?,?,1,?)')
                ->execute([$values['username'],$values['email'],$role,password_hash($password,PASSWORD_DEFAULT,['cost'=>12]),(int)$changeRequired]);
            $id = (int) $this->pdo->lastInsertId();
            Audit::record($this->pdo,$actor,'user.create','user',$id,null,[...$values,'role'=>$role,'is_active'=>1]);
            $this->pdo->commit();return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? null) === 1062) throw new ValidationException('Identifiant ou email déjà utilisé.');
            throw $e;
        }
    }
    public function update(array $data, int $actor): void
    {
        $id = Input::id($data['id'] ?? null); $action = Input::choice($data,'action',['update','reset']);
        $password = $action === 'reset' ? Input::password($data['password'] ?? null) : null;
        $role = $action === 'update' ? Input::choice($data,'role',['admin','editor','member','super_admin']) : null;
        $active = isset($data['is_active']) ? 1 : 0;
        $emailProvided = array_key_exists('email', $data);
        $email = $action === 'update' && $emailProvided ? $this->email($data) : null;
        $this->pdo->beginTransaction();
        try {
            // Sérialise toutes les modifications d’accès pour protéger le dernier admin, même en concurrence.
            $admins = $this->pdo->query("SELECT id FROM users WHERE role IN ('admin','super_admin') AND is_active = 1 ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            $query = $this->pdo->prepare('SELECT id, username, email, role, auth_provider, is_active FROM users WHERE id = ? FOR UPDATE');$query->execute([$id]);$before=$query->fetch();
            if (!$before) throw new ValidationException('Utilisateur introuvable.');
            if ($before['role']==='super_admin' && ($action==='update' && ($role!=='super_admin' || !$active))) throw new ValidationException('Le propriétaire unique ne peut être désactivé ni rétrogradé.');
            if ($role==='super_admin' && $before['role']!=='super_admin') throw new ValidationException('Désignation du propriétaire réservée à la CLI.');
            if ($before['auth_provider']==='discord' && $action==='reset') throw new ValidationException('Un compte Discord ne possède pas de mot de passe local.');
            if ($before['auth_provider']==='discord') $role=$before['role'];
            if ($action === 'update') {
                if ($before['role'] === 'admin' && $before['is_active'] && count($admins) <= 1 && ($role !== 'admin' || !$active)) throw new ValidationException('Le dernier administrateur actif ne peut pas être désactivé ou rétrogradé.');
                $email = $emailProvided ? $email : $before['email'];
                $this->pdo->prepare('UPDATE users SET role = ?, is_active = ?, email = ?, session_version = session_version + 1 WHERE id = ?')->execute([$role,$active,$email,$id]);
                Audit::record($this->pdo,$actor,'user.access','user',$id,$before,['role'=>$role,'is_active'=>$active,'email'=>$email]);
            } else {
                $this->pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, session_version = session_version + 1 WHERE id = ?')->execute([password_hash($password,PASSWORD_DEFAULT,['cost'=>12]),$id]);
                Audit::record($this->pdo,$actor,'user.password_reset','user',$id);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? null) === 1062) throw new ValidationException('Identifiant ou email déjà utilisé.');
            throw $e;
        }
    }
    public function changePassword(int $id, string $password): void
    {
        $password = Input::password($password);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?')->execute([password_hash($password,PASSWORD_DEFAULT,['cost'=>12]),$id]);
            Audit::record($this->pdo,$id,'user.password_change','user',$id);
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack();throw $e; }
    }
}
