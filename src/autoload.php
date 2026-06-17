<?php
/**
 * Autoloader PSR-4 minimal (sans Composer).
 * Mappe le namespace racine "App\" vers le dossier src/.
 *
 * Principe SOLID respecté : Single Responsibility — ce fichier ne fait
 * QUE charger les classes à la demande.
 */

spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;

    // La classe demandée n'appartient pas à notre namespace -> on ignore.
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
