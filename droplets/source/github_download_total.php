<?php

/**
 * manufakturGitDownloads
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link http://phpmanufaktur.de
 * @copyright 2012
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

if (!file_exists(WB_PATH.'/modules/manufaktur_git_downloads/library.php'))
  return 'manufakturGitDownloads is not installed!';

require_once WB_PATH.'/modules/manufaktur_git_downloads/library.php';

$add_historic_count = (isset($add_history) && (strtolower($add_history) == 'false')) ? false : true;

$github = new githubDownloads();
if (false === ($downloads = $github->getRepositoriesDownloadTotal($add_historic_count))) {
  if ($github->isError()) return $github->getError();
  return "No Repositories found!";
}
return number_format($downloads,0,',','.');