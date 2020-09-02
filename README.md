.credentials:
```
user=mygithubuser
token=abcdef123456abcdef123456abcdef123456abcd
```

Generate the latest composer.lock
`composer create-project cwp/cwp-recipe-kitchen-sink:2.6.0`

Run the script
`php run.php`

Pig will read composer.lock from the installed kitchen-sink and work out which modules need to have new versions released

Outputs to
csv/modules.csv

Eventually this functionality will probably be moved to Cow

### GitHub REST-API vs GraphQL-API

Using the more reliable github rest api instead of the much faster (especially for getting commit info) though non-always-reliable github graphql api (sometimes flakes out for not particular reason, particularly on brand new queries) because accuracy is far more important than speed for this use case
