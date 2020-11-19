## Summary

Pig is a program to supplement Cow and does the following:
- helps work out which modules need to have a new patch tag, minor tag, or stay on the existing tag
- provides github compare urls which shows the diff for a patch tag and for a minor tag
- shows the prior tag modules are on - meaning the tag used for the last release

## Usage

```
git clone git@github.com:emteknetnz/pig.git
cd pig
touch .credentials && edit .credentials
```

You need to create a Github API [access token](https://github.com/settings/tokens) and add it to the .credentials file.
This token should have the following checkboxes ticked:
- repo:status
- public_repo

.credentials:
```
user=mygithubuser
token=abcdef123456abcdef123456abcdef123456abcd
```

Install kitchen sink in the same directory as pig using the LATEST TAG.  This is done to generate a composer.lock
file which is used to get information on the last tag used for all the modules.
`composer create-project cwp/cwp-recipe-kitchen-sink:2.6.1`

If doing a patch release:
`php run.php patch`

If doing a minor release:
`php run.php minor`

This will output a new csv in `csv/modules.csv`

## Importing csv into a google spreadsheet

- Open csv/modules.csv in a text editor
- Copy the contents of the csv
- Open a new google spreadsheet
- Select cell A1
- Paste
- Top menu > Data > Split text to columns
- (Optional) Top menu > Data > Create a filter
- Paste the following formula into each data cell for the column "cow_use_version"

```
=if(indirect("R"&row()&"C"&match("manual_tag_type",A$1:Z$1,0),false)="patch",indirect("R"&row()&"C"&match("patch_new_tag",A$1:Z$1,0),false),if(indirect("R"&row()&"C"&match("manual_tag_type",A$1:Z$1,0),false)="minor",indirect("R"&row()&"C"&match("minor_new_tag",A$1:Z$1,0),false),""))
```

## Manually selecting a tag type

### For a patch release:
In each data cell for the column "manual_tag_type", type in either `patch` or `none`.

### For a minor release:
In each data cell for the column "manual_tag_type", type in either `patch`, `minor` or `none`.

## Importing plan data from pig into cow
When modifying the cow plan in your terminal, manually enter the values from the data cells from the final three
columns `cow_module`, `cow_new_version` and `cow_prior_version`.
