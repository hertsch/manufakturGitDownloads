<?php

require_once '../../config.php';

require_once WB_PATH.'/modules/manufaktur_git_downloads/library.php';

/*
$time_array = array(
    'dbBotTrap_0.12.zip' => '2012-06-14',
    'dbConnect_0.37.zip' => '2012-06-14',
    'dbConnect_LE_0.70.zip' => '2012-06-15',
    'dbGlossary_0.34.zip' => '2012-06-14',
    'dbMultipleChoice_0.21.zip' => '2012-09-03',
    'dbWatchSite_0.12.zip' => '2012-06-17',
    'DirList_0.23.zip' => '2012-06-17',
    'DropletsExtension_0.24.zip' => '2012-11-16',
    'Dwoo_0.17.zip' => '2012-11-16',
    'extendedWYSIWYG_10.16.zip' => '2012-11-26',
    'fbPageTool_FIW_0.12.zip' => '2012-06-14',
    'FeedbackModule_0.34.zip' => '2012-06-18',
    'flexTable_0.24.zip' => '2012-08-25',
    'imageTweak_0.52.zip' => '2012-06-15',
    'KeepInTouch_0.64.zip' => '2012-12-08',
    'kitDirList_0.29.zip' => '2012-06-18',
    'kitEvent_0.36.zip' => '2012-11-28',
    'kitForm_0.40.zip' => '2012-12-08',
    'kitIdea_0.28.zip' => '2012-10-05',
    'kitMarketPlace_0.13.zip' => '2012-06-19',
    'kitPoll_0.12.zip' => '2012-06-12',
    'kitRegistry_0.12.zip' => '2012-09-24',
    'kitTools_0.18.zip' => '2012-06-12',
    'kitUploader_0.11.zip' => '2012-06-19',
    'languageMenu_0.11.zip' => '2012-06-12',
    'libBitly_0.10.zip' => '2012-06-23',
    'libExcelRead_0.13.zip' => '2012-08-21',
    'libGitHubAPI_0.13.zip' => '2012-06-15',
    'libMarkdown_0.12.zip' => '2012-06-15',
    'libSimplePie_0.12.zip' => '2012-06-15',
    'libWebThumbnail_0.11.zip' => '2012-06-15',
    'manufakturConfig_0.16.zip' => '2012-09-27',
    'manufakturGallery_0.17.zip' => '2012-08-26',
    'multipleEducated_0.17.zip' => '2012-09-04',
    'newsletterSnippet_0.18.zip' => '2012-06-14',
    'pChart_2.1.3.zip' => '2012-06-20',
    'permaLink_0.15.zip' => '2012-06-14',
    'rhTools_0.52.zip' => '2012-06-12',
    'SampleAdminTool_0.11.zip' => '2012-06-20',
    'shortLink_0.20.zip' => '2012-06-21',
    'syncData_0.50.zip' => '2012-06-14',
    'TOPICS_0.71.4.zip' => '2012-12-10',
    'twoStepGallery_0.11.zip' => '2012-06-21'
    );

foreach ($time_array as $file => $time) {
  $fn = WB_PATH.'/media/downloads/'.$file;
  $ts = strtotime($time);
  $ft = mktime(0, 0, 0, date('n', $ts), date('j', $ts), date('Y', $ts));
  touch($fn, $ft, $ft);
}
*/

$git = new githubDownloads();
if ($git->isError()) exit($git->getError());

//$git->scanDownloads();

global $database;

// get the total downloads
$SQL = "SELECT `addon`, SUM(`downloads`) AS 'total' FROM `".TABLE_PREFIX."mod_manufaktur_downloads` WHERE `status`='ACTIVE' GROUP BY `addon`";
$query = $database->query($SQL);
if ($database->is_error()) {
  echo $database->get_error();
  exit();
}
$downloads = array();
while (false !== ($add = $query->fetchRow(MYSQL_ASSOC))) {
  $downloads[$add['addon']] = $add['total'];
}

$SQL = "SELECT *, MAX(`file_datetime`) AS 'f_date' FROM `".TABLE_PREFIX."mod_manufaktur_downloads` GROUP BY `addon` ASC";
$query = $database->query($SQL);
if ($database->is_error()) {
  echo $database->get_error();
  exit();
}
while (false !== ($addon = $query->fetchRow(MYSQL_ASSOC))) {
  echo "<pre>";
  print_r($addon);
  echo "</pre>";
}