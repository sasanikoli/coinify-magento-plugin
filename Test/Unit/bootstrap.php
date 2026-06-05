<?php
/**
 * Test bootstrap — loaded by PHPUnit before any test runs.
 *
 * Registers the Composer autoloader (which picks up the plugin's own classes
 * via PSR-4) and loads the Magento stub definitions so that PHPUnit can create
 * mocks for Magento framework classes without a full Magento installation.
 */
$autoload = __DIR__ . '/../../vendor-test/autoload.php';
if (!file_exists($autoload)) {
    echo "Run 'COMPOSER=composer-dev.json composer install' before running tests.\n";
    exit(1);
}
require_once $autoload;

// Magento's translation helper — returns the first argument unchanged in test context.
if (!function_exists('__')) {
    function __($text, ...$args)
    {
        return $args ? vsprintf($text, $args) : $text;
    }
}
