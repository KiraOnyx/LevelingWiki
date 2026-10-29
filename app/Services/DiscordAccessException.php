<?php
declare(strict_types=1);
namespace App\Services;
/** Only fixed public messages: never include provider bodies, tokens or callback parameters. */
final class DiscordAccessException extends \RuntimeException {
    public function __construct(public readonly string $reason) {
        parent::__construct(self::messageFor($reason));
    }
    public static function messageFor(string $reason): string {
        return match($reason) {
            'configuration'=>'La configuration de connexion Discord est incorrecte. Contactez un administrateur.',
            'communication'=>'Discord est momentanément injoignable. Réessayez dans quelques instants.',
            'oauth'=>'L’autorisation Discord a échoué ou a expiré. Recommencez la connexion.',
            'membership'=>'Votre compte ne fait pas partie du serveur Discord requis.',
            'roles'=>'Accès refusé : au moins un rôle MEMBER, EDITOR ou ADMIN autorisé est nécessaire.',
            'session'=>'Connexion expirée ou state invalide. Recommencez depuis la page de connexion.',
            default=>'Une erreur technique empêche la vérification Discord. Réessayez plus tard.',
        };
    }
    public function status(): int { return in_array($this->reason,['communication','configuration','internal'],true)?503:403; }
}
