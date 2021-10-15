# copernicus_publication

Drupal module to manage publications

#Installation
In `composer.json`:

1. Into `"require": {}` add:

``"drupal/copernicus_publication": "dev-main"``

2. Into `"repositories":[]` add:
```
{
    "type": "vcs",
    "url": "git@gitlab.edw.ro:ecmwf/copernicus_publication.git"
}
```
3. A SSH Key in GitLab is required.
4. Run: ```composer require drupal/copernicus_publication```