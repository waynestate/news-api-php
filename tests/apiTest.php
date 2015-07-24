<?php

use Waynestate\News\API;

/**
 * Class ParserTest
 */
class apiTest extends PHPUnit_Framework_TestCase {

    /**
     * @var Waynestate\News\API
     */
    protected $api;

    /**
     * Setup
     */
    public function setUp()
    {
        define('NEWS_API_CACHE', __DIR__ . '/../storage/');
        define('NEWS_API_ENDPOINT', 'http://news.app/api/v1/');
        $this->api = new API('123');
    }
}
