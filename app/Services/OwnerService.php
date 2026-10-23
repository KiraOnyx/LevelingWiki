<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class OwnerService {
    public function __construct(private PDO $pdo) {}
    public function designate(string $username,string $confirmation,string $password): void {
        if (PHP_SAPI!=='cli' || $username!==$confirmation || $username==='') throw new ValidationException('Confirmation explicite de l’identifiant requise.');
        if ((int)$this->pdo->query("SELECT GET_LOCK('levelingwiki_owner',10)")->fetchColumn()!==1) throw new ValidationException('Désignation en cours.');
        try {
            $this->pdo->beginTransaction();
            if ($this->pdo->query("SELECT id FROM users WHERE role='super_admin' FOR UPDATE")->fetchColumn()) throw new ValidationException('Un propriétaire existe déjà.');
            $q=$this->pdo->prepare("SELECT * FROM users WHERE username=? AND auth_provider='local' AND role='admin' AND is_active=1 FOR UPDATE");$q->execute([$username]);$user=$q->fetch();
            if (!$user || strlen($password)>72 || !password_verify($password,$user['password_hash'])) throw new ValidationException('Compte ADMIN local actif ou mot de passe invalide.');
            $this->pdo->prepare("UPDATE users SET role='super_admin',session_version=session_version+1 WHERE id=?")->execute([$user['id']]);
            Audit::record($this->pdo,(int)$user['id'],'owner.designate','user',(int)$user['id'],['role'=>'admin'],['role'=>'super_admin']);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e; }
        finally { $this->pdo->query("SELECT RELEASE_LOCK('levelingwiki_owner')"); }
    }
}
