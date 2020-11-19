## Summary

Pig is a program to supplement Cow.  It's used to help plan which modules in a cwp-recipe-kitchen-sink installation need
to have new modules released.  It also shows the prior versions used.

## Usage

```
git clone git@github.com:emteknetnz/pig.git
cd pig
touch .credentials && edit .credentials
```

.credentials:
```
user=mygithubuser
token=abcdef123456abcdef123456abcdef123456abcd
```

Generate the composer.lock from the LATEST TAG on kitchen sink
`composer create-project cwp/cwp-recipe-kitchen-sink:2.6.1`

If doing a patch release:
`php run.php patch`

If doing a minor release:
`php run.php minor`

Pig will read composer.lock from the installed kitchen-sink and work out which modules need to have new versions
released

Outputs to
csv/modules.csv

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
