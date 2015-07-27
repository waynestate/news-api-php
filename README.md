news-api-php
================

Connector to interact with the News API

### Example Usage

```php
<?php
use Waynestate\Api\News;

// News API
$news = new News(NEWS_API_KEY);

// List of releases
$releases = $news->request('releases', array('perPage' => '2'));
var_dump($releases);
```
