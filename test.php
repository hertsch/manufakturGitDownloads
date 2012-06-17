<?php

require_once '../../config.php';

require_once WB_PATH.'/modules/manufaktur_git_downloads/library.php';

$test = array(
    'download_path' => 'bllaalalslldfldsdls',
    'download_url' => 'irgenwads',
    'history' => array(
        'MemInfo' => '23333',
        'baseFoo' => '23'
    )
);

//writeConfiguration($test);

//$test = readConfiguration();
//print_r($test);
//$cmd = sprintf("cd %s; ln -s %s %s", WB_PATH, WB_PATH.'/modules/manufaktur_git_downloads/dl.php', 'download.php');


$git = new githubDownloads();
if ($git->isError()) exit($git->getError());
if (!$git->getRepositories()) {
  echo $git->getError();
}
