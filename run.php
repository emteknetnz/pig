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

$lastRequestTS = 0;
function waitUntilCanFetch() {
    // https://developer.github.com/v3/#rate-limiting
    // - authentacted users can make 5,000 requests per hour
    // - wait 1 second between requests (max of 3,600 per hour)
    global $lastRequestTS;
    $ts = time();
    if ($ts == $lastRequestTS) {
        sleep(1);
    }
    $lastRequestTS = $ts;
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

function accountCounts() {
    // one-off function
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

function getComposerLockJson() {
    $s = file_get_contents('cwp-recipe-kitchen-sink/composer.lock');
    $json = json_decode($s);
    return $json;
}

function filterSupportedModules($composerLockJson) {
    global $supportedAccounts;
    $modules = [];
    foreach ($composerLockJson->packages as $package) {
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

function getLatestPatchTag($gitTagsJson, $currentTag) {
    $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)$#';
    if (!preg_match($rx, $currentTag, $m)) {
        return 'unknown_current';
    }
    array_shift($m);
    list($currentMajor, $currentMinor, $currentPatch) = $m;
    // releases are listed DESC
    foreach ($gitTagsJson as $tag) {
        $name = $tag->name;
        if (!preg_match($rx, $currentTag, $m)) {
            continue;
        }
        array_shift($m);
        list($latestMajor, $latestMinor, $latestPatch) = $m;
        if ($currentMajor == $latestMajor && $currentMinor == $latestMinor) {
            return "$latestMajor.$latestMinor.$latestPatch";
        }
    }
    return 'unknown_latest_patch';
}

function deriveEndpointUrl($name) {
    preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $name, $m);
    array_shift($m);
    list($account, $repo) = $m;
    if ($account == 'silverstripe') {
        if (strpos($repo, 'recipe') !== 0 && $repo != 'comment-notifications' && $repo != 'vendor-plugin') {
            $repo = 'silverstripe-' . $repo;
        }
    }
    if ($account == 'cwp') {
        $account = 'silverstripe';
        if (strpos($repo, 'cwp') !== 0) {
            $repo = 'cwp-' . $repo;
        }
        if ($repo == 'cwp-agency-extensions') {
            $repo = 'cwp-agencyextensions';
        }
    }
    $url = "/repos/$account/$repo/tags";
    return $url;
    // /repos/silverstripe/silverstripe-comment-notifications/tags?per_page=100
    // /repos/silverstripe/silverstripe-vendor-plugin/tags?per_page=100
    // /repos/symbiote/silverstripe-multivaluefield/tags?per_page=100
    // /repos/tractorcow/classproxy/tags?per_page=100
}

function deriveData() {
    global $updateOnlyModules;
    $composerLockJson = getComposerLockJson();
    $modules = filterSupportedModules($composerLockJson);
    $data = [];
    foreach ($modules as $module) {
        preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $module->name, $m);
        array_shift($m);
        list($account, $repo) = $m;
        if ($account == 'cwp') {
            $account = 'silverstripe';
        }
        $url = deriveEndpointUrl($module->name);
        $gitTagsJson = fetchRest($url, $account, $repo, 'tags');
        $data[] = [
            'name' => $module->name,
            'current_tag' => $module->version,
            'latest_patch_tag' => getLatestPatchTag($gitTagsJson, $module->version),
            'upgrade_only' => in_array($module->name, $updateOnlyModules),
            'tags_url' => str_replace('.git', '', $module->source->url) . '/tags'
        ];
    }
    return $data;
}

function buildModulesCsv() {
    foreach (['csv', 'json'] as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir);
        }
    }
    $data = deriveData();
    createCsv('csv/modules.csv', $data, [
        'name',
        'current_tag',
        'latest_patch_tag',
        'upgrade_only',
        'tags_url'
    ]);
}

// accountCounts();
buildModulesCsv();
