<?php

if (!function_exists('azure_getconfig')) {
    echo 'Please install php_azure.dll before executing this script.'.PHP_EOL;

    exit(1);
}

$list = array();

$cache = __DIR__.'/config.cache';

$path = __DIR__.'/../../ServiceDefinition.csdef';
$xml = simplexml_load_file($path);

foreach ($xml->WebRole->ConfigurationSettings->Setting as $setting) {
    $name = (string)$setting['name'];
    $list[$name] = azure_getconfig($name);
}

$serialized = serialize($list);

if (!is_file($cache)) {
    file_put_contents($cache, $serialized);

    exit(1);
}

if (file_get_contents($cache) !== $serialized) {
    file_put_contents($cache, $serialized);

    exit(1);
}

exit(0);
