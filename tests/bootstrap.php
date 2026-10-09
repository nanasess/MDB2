<?php

error_reporting(E_ALL);

require_once dirname(__DIR__).'/vendor/autoload.php';
require_once 'MDB2.php';

// MDB2 refers to these globals without initialising them (PHP 7.2+)
if (!array_key_exists('_MDB2_dsninfo_default', $GLOBALS)) {
    $GLOBALS['_MDB2_dsninfo_default'] = array();
}
if (!array_key_exists('_MDB2_databases', $GLOBALS)) {
    $GLOBALS['_MDB2_databases'] = array();
}

require_once __DIR__.'/TestCase.php';
