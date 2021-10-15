# copernicus_publication

Drupal module to manage publications

# Installation

In `composer.json`:

1. Into `"require": {}` add:

```"drupal/copernicus_publication": "dev-main"```

2. Into `"repositories":[]` add:
```
{
    "type": "vcs",
    "url": "git@gitlab.edw.ro:ecmwf/copernicus_publication.git"
}
```

3. A SSH Key in GitLab is required.

4. Run: ```composer require drupal/copernicus_publication```

# Configuration

Add credentials in `settings.local.php`:

```
// Repository ID contains {MEMBER_ID.REPOSITORY_ID}
$settings['copernicus_publication']['datacite_repository_id'] = '';
// Test
$settings['copernicus_publication']['datacite_api'] = 'https://api.test.datacite.org/';
$settings['copernicus_publication']['datacite_fabrica'] = 'https://doi.test.datacite.org/';
// Prod
$settings['copernicus_publication']['datacite_api'] = 'https://api.datacite.org/';
$settings['copernicus_publication']['datacite_fabrica'] = 'https://doi.datacite.org/';
```

## Route: 

`/admin/content/upload-publication`