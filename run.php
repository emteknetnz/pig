<?php

/*
Generate the latest composer.lock
composer create-project cwp/cwp-recipe-kitchen-sink:2.6.0

Create csv used to get latest versions
php run.php

Outputs to
csvs/modules.csv
*/

function accountCounts() {
    $json = readJson();
    $accounts = [];
    foreach ($json->packages as $package) {
        $b = preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $package->name, $m);
        array_shift($m);
        list($account, $repo) = $m;
        $accounts[$account] ?? 0;
        $accounts[$account]++;
    }
    asort($accounts);
    $accounts = array_reverse($accounts);
    print_r($accounts);
    /*
    'silverstripe',
    'cwp',
    'symbiote',
    'tractorcow',
    'bringyourownideas',
    'dnadesign',
    */
}

function readJson() {
    $s = file_get_contents('cwp-recipe-kitchen-sink/composer.lock');
    $json = json_decode($s);
    return $json;
}

function filterSupportedModules($json) {
    $supportedAccounts = [
        'silverstripe',
        'cwp',
        'symbiote',
        'tractorcow',
        'bringyourownideas',
        'dnadesign',
    ];
    $modules = [];
    foreach ($json->packages as $package) {
        $b = preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $package->name, $m);
        array_shift($m);
        list($account, $repo) = $m;
        if (!in_array($account, $supportedAccounts)) {
            continue;;
        }
        $modules[] = $package;
    }
    return $modules;
}

function deriveData($json) {
    $modules = filterSupportedModules($json);
    $data = [];
    foreach ($modules as $module) {
        $data[] = [
            'name' => $module->name,
            'current_version' => $module->version,
            'release_url' => str_replace('.git', '', $module->source->url) . '/releases'
        ];
    }
    return $data;
}

function createCsv($filename, $data, $fields, $maxN = 9999) {
    $lines = [];
    $n = 0;
    foreach ($data as $row) {
        $line = [];
        foreach ($fields as $field) {
            $line[] = str_replace(',', '', $row[$field]);
        }
        $lines[] = implode(',', $line);
        if (++$n >= $maxN) {
            break;
        }
    }
    array_unshift($lines, implode(',', $fields));
    $output = implode("\n", $lines);
    file_put_contents($filename, $output);
    echo "\nWrote to $filename\n\n";
}

function run() {
    if (!file_exists('csvs')) {
        mkdir('csvs');
    }
    $json = readJson();
    $data = deriveData($json);
    createCsv('csvs/modules.csv', $data, [
        'name',
        'current_version',
        'release_url'
    ]);
}

accountCounts();
//run();
