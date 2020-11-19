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

    # these are in .cow.json
    'symbiote/silverstripe-queuedjobs',
    "dnadesign/silverstripe-elemental-subsites",
    'dnadesign/silverstripe-elemental-userforms',
    "undefinedoffset/sortablegridfield",

    // upgrade only for core as well if included
    'silverstripe/lumberjack',
    'symbiote/silverstripe-gridfieldextensions',
    'symbiote/silverstripe-multivaluefield',
    'tractorcow/classproxy',
    'tractorcow/silverstripe-proxy-db',
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
