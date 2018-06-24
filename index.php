<?php
$documentRoot = $documentRoot ?? (PHP_SAPI !== 'cli' ? \rtrim(\dirname($_SERVER['SCRIPT_FILENAME']), '\/') : \getcwd());
$webRoot = $webRoot ?? $documentRoot . '/web/passed';
$vendorRoot = $vendorRoot ?? $documentRoot . '/vendor';

require_once $vendorRoot . '/autoload.php';

$controller = $controller ?? new \DrdPlus\RulesSkeleton\RulesController(
        'UA-121206931-1',
        \DrdPlus\RulesSkeleton\HtmlHelper::createFromGlobals($documentRoot),
        $documentRoot,
        $webRoot,
        $vendorRoot
    );
$controller->setFreeAccess();
$controller->setContactsFixed();
$controller->hideHomeButton();

include $vendorRoot . '/drd-plus/rules-skeleton/index.php';