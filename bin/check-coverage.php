<?php

declare(strict_types=1);

$file = $argv[1] ?? __DIR__ . '/../build/coverage.xml';
$threshold = (float) ($argv[2] ?? 95);
$xml = simplexml_load_file($file);
if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(STDERR, "Coverage report is missing or invalid.\n");
    exit(1);
}
$metrics = $xml->project->metrics;
$total = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percentage = $total === 0 ? 0 : 100 * $covered / $total;
printf("Application line coverage: %.2f%% (required: %.2f%%)\n", $percentage, $threshold);
exit($total > 0 && $percentage >= $threshold ? 0 : 1);
