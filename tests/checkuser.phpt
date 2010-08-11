--TEST--
Mail_CheckUser: Check email address
--FILE--
<?php
require_once('./config.php');
require_once("../CheckUser.php");

// using $_SERVER
$chk = new Mail_CheckUser();
$chk->timeout = 10;
var_dump($chk->checkEmail(EMAIL_VALID));
var_dump($chk->checkEmail(EMAIL_BAD_SYNTAX));
var_dump($chk->checkEmail(EMAIL_USER_UNKNOWN));
var_dump($chk->checkEmail(EMAIL_SERVER_NOT_FOUND));

// not using $_SERVER
$chk = new Mail_CheckUser(FQDN, SENDER);
$chk->setTimeout(1);
var_dump($chk->checkEmail(EMAIL_VALID));
var_dump($chk->checkEmail(EMAIL_BAD_SYNTAX));
var_dump($chk->checkEmail(EMAIL_USER_UNKNOWN));
var_dump($chk->checkEmail(EMAIL_SERVER_NOT_FOUND));
var_dump($chk->getResponse());
$chk->setOKCodes(1001);
var_dump($chk->checkEmail(EMAIL_SERVER_NOT_FOUND));
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(false)
bool(true)
bool(false)
bool(false)
bool(false)
array(2) {
  [0]=>
  int(1001)
  [1]=>
  string(46) "Failed to connect socket: Connection timed out"
}
bool(true)

