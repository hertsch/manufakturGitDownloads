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

require_once WB_PATH.'/modules/manufaktur_git_downloads/library.php';


if (isset($_GET['file'])) {
  $git = new githubDownloads();
  if (false === ($data = $git->getRepositoryData($git::$config['owner'], $_GET['file']))) {
    if ($git->isError()) exit($git->getError());
    exit($_GET['file'].' does not exists!');
  }
  header('Location: '.$data['download_file_url']);
}
else {
  exit("Please use the parameter 'file'.");
}