<?php

// Style du code PHP : règles @Symfony uniquement (aucune règle risky).
// Cache par défaut (.php-cs-fixer.cache), ignoré par Git.
$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/migrations',
    ])
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
