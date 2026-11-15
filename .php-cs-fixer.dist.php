<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRules(['@PSR12' => true, 'declare_strict_types' => true, 'array_syntax' => ['syntax' => 'short']])
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in(['src', 'tests', 'bin', 'public'])->name('*.php')->append(['phinx.php']));
