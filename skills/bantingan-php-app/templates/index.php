<?php
require_once 'vendor/autoload.php';
// require_once 'config/language/en.php';

use Bantingan\Bantingan;
use Bantingan\Settings;
use Modules\Common\Session\MongoSession;

error_reporting(E_ALL);

$basepath = __DIR__;
// load settings
Settings::LoadFromPath($basepath, '/config/web.config.yml');
// session settings
if (isset(APPLICATION_SETTINGS["Session_DB"]) && APPLICATION_SETTINGS["Session_DB"])
{
    $session = new MongoSession(); // session stored in mysql     
} else {
    session_start();
}
// start application
new Bantingan();
