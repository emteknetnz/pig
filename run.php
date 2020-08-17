<?php

$supportedAccounts = [
    'silverstripe',
    'cwp',
    'symbiote',
    'tractorcow',
    'bringyourownideas',
    'dnadesign',
    // 'colymba',
];

// We can adopt new tags for these but don't create new tags for them:
$updateOnlyModules = [
    'silverstripe/recipe-core',
    'silverstripe/recipe-cms',
    'symbiote/silverstripe-queuedjobs',
];

/**
 * username:token
 */
function getCredentials($userOnly = false) {
    // https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token
    //
    // .credentials
    // user=my_github_username
    // token=abcdef123456
    //
    // https://github.com/settings/tokens/new
    // [x] Access commit status 
    // [x] Access public repositories 
    //
    $data = [];
    $s = file_get_contents('.credentials');
    $lines = preg_split('/[\r\n]/', $s);
    foreach ($lines as $line) {
        $kv = preg_split('/=/', $line);
        if (count($kv) != 2) break;
        $key = $kv[0];
        $value = $kv[1];
        $data[$key] = $value;
    }
    if ($userOnly) {
        return $data['user'];
    }
    return $data['user'] . ':' . $data['token'];
}

function fetchRest($remotePath, $account, $repo, $extra) {
    $remoteBase = 'https://api.github.com';
    $remotePath = str_replace($remoteBase, '', $remotePath);
    $remotePath = ltrim($remotePath, '/');
    if (preg_match('@/[0-9]+$@', $remotePath) || preg_match('@/[0-9]+/files$@', $remotePath)) {
        // requesting details
        $url = "$remoteBase/${remotePath}";
    } else {
        // requesting a list
        $op = strpos($remotePath, '?') ? '&' : '?';
        $url = "$remoteBase/${remotePath}${op}per_page=100";
    }
    $label = str_replace($remoteBase, '', $url);
    echo "Fetching from ${label}\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, getCredentials());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    waitUntilCanFetch();
    $s = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($s);
    if (!is_array($json)) {
        echo "Error fetching data\n";
        return null;
    } else {
        $str = json_encode($json, JSON_PRETTY_PRINT);
        file_put_contents("json/rest-$account-$repo-$extra.json", $str);
    }
    return $json;
}

function getComposerLockJson() {
    $s = file_get_contents('cwp-recipe-kitchen-sink/composer.lock');
    $json = json_decode($s);
    return $json;
}

function filterSupportedModules($json) {
    global $supportedAccounts;
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

function deriveData($json) {
    global $updateOnlyModules;
    $modules = filterSupportedModules($json);
    $data = [];
    foreach ($modules as $module) {
        $data[] = [
            'name' => $module->name,
            'current_version' => $module->version,
            'upgrade_only' => in_array($module->name, $updateOnlyModules),
            'release_url' => str_replace('.git', '', $module->source->url) . '/releases'
        ];
    }
    return $data;
}

function accountCounts() {
    $json = getComposerLockJson();
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
}

function buildModulesCsv() {
    if (!file_exists('csv')) {
        mkdir('csv');
    }
    $json = getComposerLockJson();
    $data = deriveData($json);
    createCsv('csvs/modules.csv', $data, [
        'name',
        'current_version',
        'release_url'
    ]);
}

function getLatestVersionFromGithub() {
    if (!file_exists('json')) {
        mkdir('json');
    }
    $json = getComposerLockJson();
    $data = deriveData($json);
    foreach ($data as $row) {        
        $name = $row['name'];
        preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $name, $m);
        array_shift($m);
        list($account, $repo) = $m;
        $upgradeOnly = $row['upgrade_only'];
        $currentVersion = $row['current_version'];
        $releaseUrl = $row['release_url'];

        $url = "/repos/$account/$repo/releases";
        $data = fetchRest($url, $account, $repo, 'issues-open');

        //$s = file_get_contents($releaseUrl);
        sleep(1);
    }
}

// accountCounts();
//buildModulesCsv();
