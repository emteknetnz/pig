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
$upgradeOnlyModules = [
    'silverstripe/recipe-core',
    'silverstripe/recipe-cms',
    'symbiote/silverstripe-queuedjobs',
];

// not relevant for doing a release
$skipRepos = [
    'vendor-plugin',
    'recipe-plugin',
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
    if (preg_match('@/[0-9]+$@', $remotePath) || preg_match('@/[0-9]+/files$@', $remotePath) || preg_match('@/branches/[0-9\.]+$@', $remotePath)) {
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
    if (!is_array($json) && !is_object($json)) {
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

function filterModules($composerLockJson) {
    global $supportedAccounts, $skipRepos;
    $modules = [];
    foreach ($composerLockJson->packages as $package) {
        $b = preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $package->name, $m);
        array_shift($m);
        list($account, $repo) = $m;
        if (!in_array($account, $supportedAccounts)) {
            continue;
        }
        if (in_array($repo, $skipRepos)) {
            continue;
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

function deriveLatestPatch($gitTagsJson, $currentTag) {
    $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)$#';
    if (!preg_match($rx, $currentTag, $m)) {
        return 'unknown_current';
    }
    array_shift($m);
    list($currentMajor, $currentMinor, $currentPatch) = $m;
    // releases are listed DESC
    foreach ($gitTagsJson as $tag) {
        if (!preg_match($rx, $tag->name, $m)) {
            continue;
        }
        array_shift($m);
        list($latestMajor, $latestMinor, $latestPatch) = $m;
        if ($currentMajor == $latestMajor && $currentMinor == $latestMinor) {
            return [
                "$latestMajor.$latestMinor.$latestPatch",
                $tag->commit->sha
            ];
        }
    }
    return 'unknown_latest_patch';
}

function deriveEndpointUrl($name, $extra) {
    preg_match('#^([a-zA-Z0-9\-_]+?)/([a-zA-Z0-9\-_]+)$#', $name, $m);
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
    if ($account == 'tractorcow' && $repo == 'silverstripe-fluent') {
        $account = 'tractorcow-farm';
    }
    $url = "/repos/$account/$repo/$extra";
    return $url;
}

function deriveNewPatch($gitBranchJson, $latestPatchTag, $latestPatchSha) {
    $latestSha = $gitBranchJson->commit->sha;
    // echo $latestPatchSha;
    // echo "\n";
    // echo $latestSha;
    if ($latestSha == $latestPatchSha) {
        return [false, ''];
    }
    $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)$#';
    if (!preg_match($rx, $latestPatchTag, $m)) {
        return [true, 'unknown_new_patch'];
    }
    array_shift($m);
    list($latestMajor, $latestMinor, $latestPatch) = $m;
    $newPatch = $latestPatch += 1;
    return [true, "$latestMajor.$latestMinor.$newPatch"];
}

function deriveData() {
    global $upgradeOnlyModules, $useLocalData;
    $composerLockJson = getComposerLockJson();
    $modules = filterModules($composerLockJson);
    $data = [];
    foreach ($modules as $module) {

        // sboyd
        // if ($module->name != 'symbiote/silverstripe-multivaluefield') continue;

        // derive account and repo
        preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $module->name, $m);
        array_shift($m);
        list($account, $repo) = $m;
        
        // get latest released version
        $path = "json/rest-$account-$repo-tags.json";
        if ($useLocalData && file_exists($path)) {
            echo "Using local data from $path\n";
            $gitTagsJson = json_decode(file_get_contents($path));
        } else {
            $url = deriveEndpointUrl($module->name, 'tags');
            $gitTagsJson = fetchRest($url, $account, $repo, 'tags');
        }
        list($latestPatchTag, $latestPatchSha) = deriveLatestPatch($gitTagsJson, $module->version);
                
        // see if unreleased changes
        // silverstripe/admin
        // current = 1.6.0
        // latest = 1.6.1
        // unreleased = probably
        $upgradeOnly = in_array($module->name, $upgradeOnlyModules);
        $hasUnreleasedChanges = '';
        $newPatchTag = '';
        if (!$upgradeOnly) {
            $hasUnreleasedChanges = 'unknown_has_unreleased_changes';
            $newPatchTag = 'unknown_new_patch_tag';
            if (preg_match('#([0-9]+\.[0-9]+)\.[0-9]+#', $latestPatchTag, $m)) {
                $branch = $m[1];
                if (preg_match('#^0\.#', $branch)) {
                    $branch = 'master';
                }
                $path = "json/rest-$account-$repo-branches-$branch.json";
                if ($useLocalData && file_exists($path)) {
                    echo "Using local data from $path\n";
                    $gitBranchJson = json_decode(file_get_contents($path));
                } else {
                    $url = deriveEndpointUrl($module->name, "branches/$branch");
                    $gitBranchJson = fetchRest($url, $account, $repo, "branches-$branch");
                }
                list($hasUnreleasedChanges, $newPatchTag) = deriveNewPatch($gitBranchJson, $latestPatchTag, $latestPatchSha);
            }
        }

        // data row
        $data[] = [
            'name' => $module->name,
            'current_tag' => $module->version,
            'latest_patch_tag' => $latestPatchTag,
            'upgrade_only' => $upgradeOnly,
            'has_unreleased_changes' => $hasUnreleasedChanges,
            'new_patch_tag' => $newPatchTag,
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
    createCsv('csv/modules.csv', $data, array_keys($data[0]));
}

// accountCounts();
$useLocalData = true;
buildModulesCsv();
