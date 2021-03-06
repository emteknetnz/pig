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
// This list is for a CWP release
$upgradeOnlyModules = [

    // core release is done seperately before a cwp release
    'silverstripe/recipe-cms',
    'silverstripe/recipe-core',
    'silverstripe/assets',
    'silverstripe/config',
    'silverstripe/framework',
    'silverstripe/mimevalidator',
    'silverstripe/recipe-cms',
    'silverstripe/admin',
    'silverstripe/asset-admin',
    'silverstripe/campaign-admin',
    'silverstripe/versioned-admin',
    'silverstripe/cms',
    'silverstripe/errorpage',
    'silverstripe/graphql',
    'silverstripe/reports',
    'silverstripe/siteconfig',
    'silverstripe/versioned',

    # These are in .cow.json
    # https://github.com/silverstripe/cwp-recipe-kitchen-sink/blob/2/.cow.json
    "dnadesign/silverstripe-elemental-userforms",
    "silverstripe/subsites",
    "tractorcow/silverstripe-fluent",

    // manual list of loose dependencies not to release new tags for
    'silverstripe/lumberjack',
    'symbiote/silverstripe-gridfieldextensions',
    'symbiote/silverstripe-multivaluefield',
    "dnadesign/silverstripe-elemental-subsites",
    "undefinedoffset/sortablegridfield",
    "tractorcow/classproxy",
    "tractorcow/silverstripe-proxy-db",
];

// applicable to cwp patch release
// this stuff will be in a .cow.json or something, cos cow knows what to do
$alwaysReleaseModulesWithRC = [
    // these get "-rc1"
    'cwp/cwp',
    'cwp/cwp-core',
    'cwp/cwp-recipe-cms',
    'cwp/cwp-recipe-core',
    'cwp/cwp-installer',
    'cwp/cwp-recipe-kitchen-sink',
    'cwp/cwp-recipe-search',
    'silverstripe/recipe-authoring-tools',
    'silverstripe/recipe-blog',
    'silverstripe/recipe-collaboration',
    'silverstripe/recipe-content-blocks',
    'silverstripe/recipe-form-building',
    'silverstripe/recipe-reporting-tools',
    'silverstripe/recipe-services',
];

// not relevant for doing a release
$skipRepos = [
    'vendor-plugin',
    'recipe-plugin',
];
