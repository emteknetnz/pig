<?php
/*
Pig is designed to work out what modules need a release as part of a quarterly CWP release

Cow is only able to do a single tag per module, so you EITHER:
- tag a new patch version
- tag a new minor version

You CAN NOT tag BOTH a new patch version AND a new minor version

Whether you use the new patch tag or the new minor version depends on whether you are doing a patch or minor release
*/

// Read first argument to see if doing patch|minor release
//    
$releaseType = $argv[1];
if ($releaseType != 'patch' && $releaseType != 'minor') {
    echo "Release must be either 'patch' or 'minor'\n";
    echo "Usage:\n";
    echo "php run.php patch\n";
    echo "php run.php minor\n";
    die;
}


include 'modules.php';
include 'functions.php';

/**
 * 
 */
function deriveEndpointUrl($name, $extra)
{
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

/**
 * 
 */
function isDevFile($path)
{
    // possiblly should treat .travis.yml and .scrutinizer as 'tooling'
    return in_array($path, ['.travis.yml', '.scrutinizer.yml', 'composer.lock', 'package.json', 'yarn.lock']);
}

/**
 * Get the latest available:
 * - tag released
 * - sha of the latest tag released
 */
function deriveLatestTagItems($gitTagsJson, $currentTag, $releaseType)
{
    $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)(-beta1|-beta2|-beta3|-rc1|-rc2|-rc3|)$#';
    if (!preg_match($rx, $currentTag, $m)) {
        return 'unknown_current_tag';
    }
    array_shift($m);
    list($currentMajor, $currentMinor, $currentPatch, $suffix) = $m;
    // released tags are listed DESC
    foreach ($gitTagsJson as $tag) {
        if (!preg_match($rx, $tag->name, $m)) {
            continue;
        }
        array_shift($m);
        list($latestMajor, $latestMinor, $latestPatch, $suffix) = $m;

        $use = false;
        if ($releaseType == 'patch' && $currentMajor == $latestMajor && $currentMinor == $latestMinor) {
            $use = true;
        } elseif ($releaseType == 'minor' && $currentMajor == $latestMajor) {
            $use = true;
        }
        if ($use) {
            return [
                "${latestMajor}.${latestMinor}.${latestPatch}${suffix}",
                $tag->commit->sha
            ];
        }
    }
    return ['unknown_latest_tag', 'unknown_latest_tag_sha'];
}

/**
 * Derive what a new tag would be for a module depending if doing a path or minor release
 * Returns an array than includes:
 * - $hasUnreleasedChanges
 * - $newTag
 * - $latestSha
 * - $devOnlyCommitsSinceLastTag
 */
function deriveNewTag($gitBranchCommitsJson, $latestTag, $latestTagSha, $moduleName)
{
    global $useLocalData, $alwaysReleaseModulesWithRC, $releaseType;
    
    if (!is_array($gitBranchCommitsJson)) {
        return [false, 'unknown_new_tag', 'unknown_latest_sha', 'unknown_dev_only_comments_since_last_tag'];
    }

    $latestSha = $gitBranchCommitsJson[0]->sha;
        
    // derive account and repo
    preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $moduleName, $m);
    array_shift($m);
    list($account, $repo) = $m;

    // get files changes in compare
    $path = "json/rest-$account-$repo-compare-$latestTagSha-$latestSha.json";
    if ($useLocalData && file_exists($path)) {
        echo "Using local data from $path\n";
        $compareJson = json_decode(file_get_contents($path));
    } else {
        $url = deriveEndpointUrl($moduleName, "compare/$latestTagSha...$latestSha");
        $compareJson = fetchRest($url, $account, $repo, "compare-$latestTagSha-$latestSha");
    }
    $devOnlyCommitsSinceLastTag = true;
    foreach ($compareJson->files ?? [] as $file) {
        echo $file->filename . "\n";
        if (!isDevFile($file->filename)) {
            $devOnlyCommitsSinceLastTag = false;
            break;
        }
    }

    $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)(\-beta1|\-beta2|\-rc1|\-rc2|)$#';
    if (!preg_match($rx, $latestTag, $m)) {
        return [true, 'unknown_new_tag', $latestSha, $devOnlyCommitsSinceLastTag];
    }
    array_shift($m);
    list($latestMajor, $latestMinor, $latestPatch) = $m;
    $newMajor = $latestMajor;
    $newMinor = $latestMinor;
    $newPatch = $latestPatch;
    if ($releaseType == 'patch') {
        $newPatch = $latestPatch += 1;
        if (in_array($moduleName, $alwaysReleaseModulesWithRC)) {
            $newPatch .= '-rc1';
        }
    } elseif ($releaseType == 'minor') {
        $newMinor = $latestMinor += 1;
        $newPatch = 0;
        if (in_array($moduleName, $alwaysReleaseModulesWithRC)) {
            $newPatch .= '-rc1';
        }
    }
    return [true, "$newMajor.$newMinor.$newPatch", $latestSha, $devOnlyCommitsSinceLastTag];
}

/**
 * 
 */
function deriveData($releaseType)
{
    global $upgradeOnlyModules, $useLocalData, $alwaysReleaseModulesWithRC, $releaseType;
    $composerLockJson = getComposerLockJson();
    $modules = filterModules($composerLockJson);
    $data = [];
    foreach ($modules as $module) {
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
        list($latestTag, $latestTagSha) = deriveLatestTagItems($gitTagsJson, $module->version, $releaseType);
        $upgradeOnly = in_array($module->name, $upgradeOnlyModules);

        $hasUnreleasedChanges = '';
        $newTag = '';
        $latestSha = '';
        $branch = '';
        $devOnlyCommitsSinceLastTag = false;
        if (!$upgradeOnly) {
            $hasUnreleasedChanges = 'unknown_has_unreleased_changes';
            $newTag = 'unknown_new_tag';

            if ($releaseType == 'patch') {
                $rx = '#([0-9]+\.[0-9]+)\.[0-9]+(\-beta1|\-beta2|\-rc1|\-rc2|)#';
            } else { // minor
                $rx = '#([0-9])+\.[0-9]+\.[0-9]+(\-beta1|\-beta2|\-rc1|\-rc2|)#';
            }

            if (preg_match($rx, $latestTag, $m)) {
                $branch = $m[1];
                if ($releaseType == 'patch') {
                    $rx = '#^0\.#';
                } else { // minor
                    $rx = '#^0$#';
                }
                if (preg_match($rx, $branch)) {
                    $branch = 'master';
                }
                $path = "json/rest-$account-$repo-commits-$branch.json";
                if ($useLocalData && file_exists($path)) {
                    echo "Using local data from $path\n";
                    $gitBranchCommitsJson = json_decode(file_get_contents($path));
                } else {
                    $url = deriveEndpointUrl($module->name, "commits?sha=$branch");
                    $gitBranchCommitsJson = fetchRest($url, $account, $repo, "commits-$branch");
                }
                $arr = deriveNewTag($gitBranchCommitsJson, $latestTag, $latestTagSha, $module->name);
                list($hasUnreleasedChanges, $newTag, $latestSha, $devOnlyCommitsSinceLastTag) = $arr;
            }
        }

        // compare url
        $compareUrl = '';
        if ($branch && $newTag) {
            $compareUrl = str_replace('.git', '', $module->source->url) . "/compare/$latestTagSha...$latestSha";
        }

        $useTag = $latestTag;
        if ($hasUnreleasedChanges && !$upgradeOnly && !$devOnlyCommitsSinceLastTag && $newTag) {
            $useTag = $newTag;
        }
        if (in_array($module->name, $alwaysReleaseModulesWithRC)) {
            $useTag = $newTag;
        }

        $data[] = [
            'name' => $module->name,
            'current_tag' => $module->version,
            'latest_tag' => $latestTag,
            'upgrade_only' => $upgradeOnly,
            'has_unreleased_changes' => $hasUnreleasedChanges,
            'dev_only_commits_since_last_tag' => $devOnlyCommitsSinceLastTag,
            'new_tag' => ($upgradeOnly || $devOnlyCommitsSinceLastTag) ? '' : $newTag,
            'use_tag' => $useTag,
            'compare_url' => $compareUrl,
            'tags_url' => str_replace('.git', '', $module->source->url) . '/tags',
        ];
    }
    return $data;
}

/**
 * init
 */
function buildModulesCsv()
{
    global $releaseType;
    foreach (['csv', 'json'] as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir);
        }
    }
    $data = deriveData($releaseType);
    createCsv('csv/modules.csv', $data, array_keys($data[0]));
}

// accountCounts();
$useLocalData = true;
buildModulesCsv();
