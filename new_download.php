<?php

/**
 * manufakturGitDownloads
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link http://phpmanufaktur.de
 * @copyright 2012
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

$root = '';
$level = 1;
while (($level < 10) && (!file_exists($root.'config.php'))) {
  $root .= '../';
  $level += 1;
}
if (file_exists($root.'config.php')) {
  require_once $root.'config.php';
}
else {
  trigger_error(sprintf("[ <b>%s</b> ] Can't find config.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
}

global $database;

if (!isset($_GET['file']))
  exit("Please use the parameter 'file'.");

$SQL = "SELECT * FROM `".TABLE_PREFIX."mod_manufaktur_downloads` WHERE `addon`='{$_GET['file']}' AND `status`='ACTIVE' ORDER BY `file_datetime` DESC LIMIT 1";
if (null == ($query = $database->query($SQL)))
  exit($database->get_error());
if ($query->numRows() < 1)
  exit("The file {$_GET['file']} is not registered for download!");
// fetch the addon data
$download = $query->fetchRow(MYSQL_ASSOC);

// check if file exists
if (!file_exists($download['file_name']))
  exit("The file {$_GET['file']} does not exist for download!");

// update the download counter
$SQL = "UPDATE `".TABLE_PREFIX."mod_manufaktur_downloads` SET `downloads`='".($download['downloads']+1)."' WHERE `id`='{$download['id']}'";
if (null == $database->query($SQL))
  exit($database->get_error());

// send header informations for the download
header('Content-type: application/force-download');
header('Content-Transfer-Encoding: Binary');
if ($download['file_size'] > 0)
  header('Content-length: '.$download['file_size']);
header('Content-disposition: attachment;filename="'.basename($download['file_name']).'"');
readfile($download['file_name']);
exit();
