#!/usr/bin/php -q
<?php

// Always start by backing up the email content to disk.
$content = file_get_contents("php://stdin");
file_put_contents(__DIR__ . "/email_output.log", $content, FILE_APPEND | LOCK_EX);
ob_start();
var_dump($_SERVER);
$result = ob_get_clean();
file_put_contents(__DIR__ . "/email_env.log", $result, FILE_APPEND | LOCK_EX);

define("DEBUG", false);
define("DS", DIRECTORY_SEPARATOR);
require(__DIR__ . DS . "vendor" . DS . "autoload.php");
require(__DIR__ . DS . "Remailer.php");

$parserFactory = function($emailContent) {
    return new PlancakeEmailParser($emailContent);
};

$smtpFactory = function($destHost, $port, $me) {
    return new Net_SMTP2($destHost, $port, $me);
};

$whitelist = require(__DIR__ . "/whitelist.php");

try {
    $remailer = new Remailer($content, $parserFactory, $smtpFactory, $whitelist);
    $remailer->process();
} catch (Exception $e) {
    echo $e->getMessage();
}
