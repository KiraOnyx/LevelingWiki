<?php
declare(strict_types=1);
namespace App\Services;
final class UploadService
{
    private array $config;
    public function __construct(private bool $guide = false) { $this->config = require dirname(__DIR__, 2) . '/config/security.php'; if ($guide) { $this->config['upload_directory'] .= '/guides'; $this->config['upload_max_bytes'] = max(1,(int)(getenv('GUIDE_IMAGE_MAX_BYTES') ?: 5242880)); } }
    public function store(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new ValidationException('Téléversement invalide ou trop volumineux.');
        $size = filesize($file['tmp_name']);
        if (!$size || $size > $this->config['upload_max_bytes']) throw new ValidationException('La capture dépasse la taille maximale autorisée.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $types = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        $extension = strtolower(pathinfo(is_string($file['name'] ?? null) ? $file['name'] : '', PATHINFO_EXTENSION));
        $image = @getimagesize($file['tmp_name']);
        if (!isset($types[$mime]) || !in_array($extension, $mime === 'image/jpeg' ? ['jpg','jpeg'] : [$types[$mime]], true)
            || !$image || ($image['mime'] ?? '') !== $mime || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > ($this->guide ? max(1,(int)(getenv('GUIDE_IMAGE_MAX_PIXELS') ?: 20000000)) : 20000000)) {
            throw new ValidationException('La capture doit être une image JPEG, PNG ou WebP valide (20 mégapixels maximum).');
        }
        $directory = $this->config['upload_directory'];
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Stockage indisponible.');
        $name = bin2hex(random_bytes(32)) . '.' . $types[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) throw new \RuntimeException('Stockage indisponible.');
        chmod($directory . '/' . $name, 0640);
        return ($this->guide ? '/guide-media/' : '/media/') . $name;
    }
    public function resolve(string $url): ?string
    {
        if (!preg_match('~^/'.($this->guide ? 'guide-media' : 'media').'/([a-f0-9]{64}\.(?:jpg|png|webp))$~D', $url, $match)) return null;
        $directory = realpath($this->config['upload_directory']);
        $candidate = $directory ? $directory . '/' . $match[1] : '';
        if (!$directory || is_link($candidate) || !is_file($candidate) || dirname((string) realpath($candidate)) !== $directory) return null;
        return $candidate;
    }
    public function remove(?string $url): void
    {
        if ($url && ($file = $this->resolve($url)) && !unlink($file)) error_log('LevelingWiki : nettoyage différé d’une capture.');
    }
}
