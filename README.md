## Summary

Pig is a program to supplement Cow.  It's used to work out which modules in a cwp-recipe-kitchen-sink installation need to have new modules released.

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

Run the script
`php run.php`

Pig will read composer.lock from the installed kitchen-sink and work out which modules need to have new versions released

Outputs to
csv/modules.csv

Eventually this functionality will probably be moved to Cow

### GitHub REST-API vs GraphQL-API

Using the more reliable github rest api instead of the much faster (especially for getting commit info) though non-always-reliable github graphql api (sometimes flakes out for not particular reason, particularly on brand new queries) because accuracy is far more important than speed for this use case
