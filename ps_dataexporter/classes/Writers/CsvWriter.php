<?php
/**
 * CsvWriter - Écriture CSV sécurisée avec protection injection
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PdeCsvWriter
{
    /** @var resource */
    private $handle;

    /** @var string */
    private $filepath;

    /** @var string */
    private $separator;

    /** @var string */
    private $enclosure;

    /** @var int */
    private $rowCount = 0;

    /** @var bool */
    private $headerWritten = false;

    /** @var array */
    private $columns;

    /** @var bool */
    private $anonymize;

    /** @var array Colonnes à anonymiser */
    private static $piiColumns = array(
        'email', 'passwd', 'password', 'secure_key', 'reset_password_token',
        'firstname', 'lastname', 'phone', 'phone_mobile',
        'address1', 'address2', 'other', 'dni', 'vat_number'
    );

    /**
     * Constructeur
     */
    public function __construct($filepath, $separator = ';', $enclosure = '"', $anonymize = false)
    {
        $this->filepath = $filepath;
        $this->separator = $separator;
        $this->enclosure = $enclosure;
        $this->anonymize = $anonymize;
    }

    /**
     * Ouvre le fichier en écriture
     */
    public function open()
    {
        // Créer le répertoire si nécessaire
        $dir = dirname($this->filepath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception('Impossible de créer le répertoire: ' . $dir);
            }
        }

        // Ouvrir en mode streaming
        $this->handle = fopen($this->filepath, 'w');
        if (!$this->handle) {
            throw new Exception('Impossible d\'ouvrir le fichier: ' . $this->filepath);
        }

        // BOM UTF-8 pour Excel
        fwrite($this->handle, "\xEF\xBB\xBF");

        return true;
    }

    /**
     * Définit les colonnes (header)
     */
    public function setColumns(array $columns)
    {
        $this->columns = $columns;
    }

    /**
     * Écrit le header
     */
    public function writeHeader(array $columns = null)
    {
        if ($columns !== null) {
            $this->columns = $columns;
        }

        if (empty($this->columns)) {
            throw new Exception('Aucune colonne définie');
        }

        // Nettoyer les noms de colonnes
        $header = array_map(array($this, 'sanitizeHeader'), $this->columns);

        fputcsv($this->handle, $header, $this->separator, $this->enclosure);
        $this->headerWritten = true;

        return true;
    }

    /**
     * Écrit une ligne de données
     */
    public function writeRow(array $row)
    {
        if (!$this->headerWritten && !empty($this->columns)) {
            $this->writeHeader();
        }

        // Si le header n'est pas écrit et pas de colonnes définies, utiliser les clés
        if (!$this->headerWritten) {
            $this->columns = array_keys($row);
            $this->writeHeader();
        }

        // Construire la ligne dans l'ordre des colonnes
        $line = array();
        foreach ($this->columns as $column) {
            $value = isset($row[$column]) ? $row[$column] : '';

            // Anonymisation si activée
            if ($this->anonymize && $this->isPiiColumn($column)) {
                $value = $this->anonymizeValue($value, $column);
            }

            // Protection CSV injection
            $value = $this->sanitizeValue($value);

            $line[] = $value;
        }

        fputcsv($this->handle, $line, $this->separator, $this->enclosure);
        $this->rowCount++;

        // Flush périodique pour gros volumes
        if ($this->rowCount % 1000 === 0) {
            fflush($this->handle);
        }

        return true;
    }

    /**
     * Écrit plusieurs lignes
     */
    public function writeRows(array $rows)
    {
        foreach ($rows as $row) {
            $this->writeRow($row);
        }
        return count($rows);
    }

    /**
     * Ferme le fichier
     */
    public function close()
    {
        if ($this->handle) {
            fflush($this->handle);
            fclose($this->handle);
            $this->handle = null;
        }

        // Définir les permissions strictes
        if (file_exists($this->filepath)) {
            chmod($this->filepath, 0644);
        }

        return true;
    }

    /**
     * Récupère le nombre de lignes écrites
     */
    public function getRowCount()
    {
        return $this->rowCount;
    }

    /**
     * Récupère la taille du fichier
     */
    public function getFilesize()
    {
        if (file_exists($this->filepath)) {
            return filesize($this->filepath);
        }
        return 0;
    }

    /**
     * Calcule le checksum SHA256
     */
    public function getChecksum()
    {
        if (file_exists($this->filepath)) {
            return hash_file('sha256', $this->filepath);
        }
        return null;
    }

    /**
     * Protection contre CSV injection
     * Neutralise les caractères dangereux en début de cellule
     */
    private function sanitizeValue($value)
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        // Caractères dangereux en début de cellule
        $dangerousChars = array('=', '+', '-', '@', "\t", "\r", "\n");

        // Si la valeur commence par un caractère dangereux, préfixer avec apostrophe
        if ($value !== '' && in_array($value[0], $dangerousChars)) {
            $value = "'" . $value;
        }

        // Supprimer les caractères de contrôle (sauf tab/newline qui sont gérés)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        return $value;
    }

    /**
     * Nettoie le nom de colonne pour le header
     */
    private function sanitizeHeader($column)
    {
        // Supprimer les caractères spéciaux
        $column = preg_replace('/[^a-zA-Z0-9_]/', '_', $column);

        // Protection injection aussi pour les headers
        $dangerousChars = array('=', '+', '-', '@');
        if ($column !== '' && in_array($column[0], $dangerousChars)) {
            $column = '_' . $column;
        }

        return $column;
    }

    /**
     * Vérifie si une colonne contient des PII
     */
    private function isPiiColumn($column)
    {
        $columnLower = strtolower($column);
        foreach (self::$piiColumns as $pii) {
            if (strpos($columnLower, $pii) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Anonymise une valeur PII
     */
    private function anonymizeValue($value, $column)
    {
        if (empty($value)) {
            return $value;
        }

        $columnLower = strtolower($column);

        // Email : hash partiel
        if (strpos($columnLower, 'email') !== false) {
            return $this->hashEmail($value);
        }

        // Téléphone : masquer partiellement
        if (strpos($columnLower, 'phone') !== false) {
            return $this->maskPhone($value);
        }

        // Mots de passe : toujours masquer complètement
        if (strpos($columnLower, 'passwd') !== false || strpos($columnLower, 'password') !== false) {
            return '***MASKED***';
        }

        // Clés sécurisées : masquer complètement
        if (strpos($columnLower, 'secure_key') !== false || strpos($columnLower, 'token') !== false) {
            return '***MASKED***';
        }

        // Autres PII : hash SHA256 tronqué
        return substr(hash('sha256', $value), 0, 16);
    }

    /**
     * Hash partiel d'un email
     */
    private function hashEmail($email)
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return substr(hash('sha256', $email), 0, 16) . '@anonymized.local';
        }

        $localPart = $parts[0];
        $domain = $parts[1];

        // Garder les 2 premiers caractères + hash
        $prefix = substr($localPart, 0, 2);
        $hash = substr(hash('sha256', $localPart), 0, 8);

        return $prefix . '***' . $hash . '@' . $domain;
    }

    /**
     * Masque partiel d'un téléphone
     */
    private function maskPhone($phone)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        $length = strlen($phone);

        if ($length <= 4) {
            return '****';
        }

        // Garder les 2 premiers et 2 derniers chiffres
        return substr($phone, 0, 2) . str_repeat('*', $length - 4) . substr($phone, -2);
    }

    /**
     * Récupère le chemin du fichier
     */
    public function getFilepath()
    {
        return $this->filepath;
    }

    /**
     * Crée un fichier ZIP du CSV
     */
    public function createZip()
    {
        if (!file_exists($this->filepath)) {
            return null;
        }

        $zipPath = preg_replace('/\.csv$/i', '.zip', $this->filepath);
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $zip->addFile($this->filepath, basename($this->filepath));
        $zip->close();

        return $zipPath;
    }

    /**
     * Destructeur - s'assure que le fichier est fermé
     */
    public function __destruct()
    {
        $this->close();
    }
}
