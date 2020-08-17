.credentials:
```
user=mygithubuser
token=abcdef123456abcdef123456abcdef123456abcd
```

Generate the latest composer.lock
`composer create-project cwp/cwp-recipe-kitchen-sink:2.6.0`

Run the script
`php run.php`

Outputs to
csv/modules.csv

### GitHub REST-API vs GraphQL-API

Using the completely reliable github rest api instead of the much faster (especially for getting commit info) though non-always-reliable github graphql api (sometimes flakes out for not particular reason, particularly on brand new queries) because accuracy is far more important than speed for this use case
