<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$specDir = $root.DIRECTORY_SEPARATOR.'specs';

if (! is_dir($specDir)) {
    fwrite(STDERR, "Spec directory not found: specs\n");
    exit(1);
}

$requiredSections = [
    'Status',
    'Contexto',
    'Objetivos',
    'Fora de Escopo',
    'Contrato de API',
    'Criterios de Aceite',
    'Plano de Testes',
    'Notas de Implementacao',
];

$ignoredFiles = [
    'README.md',
    'TEMPLATE.md',
];

$files = array_values(array_filter(
    glob($specDir.DIRECTORY_SEPARATOR.'*.md') ?: [],
    fn (string $file): bool => ! in_array(basename($file), $ignoredFiles, true)
));

if ($files === []) {
    echo "No feature specs found. Add specs using specs/TEMPLATE.md when changing product behavior.\n";
    exit(0);
}

$errors = [];

foreach ($files as $file) {
    $relative = 'specs/'.basename($file);
    $contents = file_get_contents($file);

    if ($contents === false) {
        $errors[] = "{$relative}: could not be read.";

        continue;
    }

    foreach ($requiredSections as $section) {
        if (! preg_match('/^##\s+'.preg_quote($section, '/').'\s*$/mi', $contents)) {
            $errors[] = "{$relative}: missing section \"{$section}\".";
        }
    }

    if (! preg_match('/^##\s+Criterios de Aceite\s*$([\s\S]*?)(?:^##\s+|\z)/mi', $contents, $matches)) {
        continue;
    }

    if (! preg_match('/^\s*-\s+\[[ xX]\]\s+\S+/m', $matches[1])) {
        $errors[] = "{$relative}: section \"Criterios de Aceite\" must include at least one checklist item.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Spec check failed:\n");

    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }

    exit(1);
}

echo 'Spec check passed for '.count($files).' spec(s).'.PHP_EOL;
