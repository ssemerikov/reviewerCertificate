<?php
/** Stage the locked production graph without development-only PHP constraints. */
$destination = $argv[1] ?? '';
if (!is_dir($destination)) {
    throw new RuntimeException('An existing staging directory is required');
}
$root = dirname(__DIR__);
$composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$lock = json_decode(file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
unset($composer['require-dev'], $composer['autoload-dev'], $composer['scripts']);
$lock['packages-dev'] = [];
$lock['platform-dev'] = [];
foreach (['composer.json' => $composer, 'composer.lock' => $lock] as $name => $data) {
    if (file_put_contents($destination . '/' . $name, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
        throw new RuntimeException('Unable to stage ' . $name);
    }
}
