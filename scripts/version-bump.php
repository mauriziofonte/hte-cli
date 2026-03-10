#!/usr/bin/env php
<?php
/**
 * Version bump script for hte-cli
 * Usage: php scripts/version-bump.php [patch|minor|major]
 */

$versionFile = __DIR__ . '/../VERSION';

if (!file_exists($versionFile)) {
    file_put_contents($versionFile, "1.0.0\n");
}

$version = trim(file_get_contents($versionFile));
$parts = explode('.', $version);

if (count($parts) !== 3) {
    echo "Invalid version format in VERSION file\n";
    exit(1);
}

$type = $argv[1] ?? 'patch';

switch ($type) {
    case 'major':
        $parts[0]++;
        $parts[1] = 0;
        $parts[2] = 0;
        break;
    case 'minor':
        $parts[1]++;
        $parts[2] = 0;
        break;
    case 'patch':
    default:
        $parts[2]++;
        break;
}

$newVersion = implode('.', $parts);
file_put_contents($versionFile, $newVersion . "\n");

echo $newVersion . "\n";
