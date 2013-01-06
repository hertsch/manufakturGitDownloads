<?php

global $database;

if (!function_exists('bytes2str')) {
  function bytes2Str($byte) {
    if ($byte < 1024)
      $result = round($byte, 2) . ' Byte';
    elseif ($byte >= 1024 and $byte < pow(1024, 2))
    $result = round($byte / 1024, 2) . ' KB';
    elseif ($byte >= pow(1024, 2) and $byte < pow(1024, 3))
    $result = round($byte / pow(1024, 2), 2) . ' MB';
    else
      $result = round($byte / pow(1024, 3), 2) . ' GB';
    return $result;
  } // bytes2Str()
}

if (!function_exists('directoryTree')) {
    function directoryTree($dir) {
      if (substr($dir,-1) == "/") $dir = substr($dir,0,-1);
      $path = array();
      $stack = array();
      $stack[] = $dir;
      while ($stack) {
        $thisdir = array_pop($stack);
        if (false !== ($dircont = scandir($thisdir))) {
          $i=0;
          while (isset($dircont[$i])) {
            if ($dircont[$i] !== '.' && $dircont[$i] !== '..') {
              $current_file = "{$thisdir}/{$dircont[$i]}";
              if (is_file($current_file)) {
                $path[] = "{$thisdir}/{$dircont[$i]}";
              }
              elseif (is_dir($current_file)) {
                $stack[] = $current_file;
              }
            }
            $i++;
          }
        }
      }
      return $path;
    } // directoryTree()
}

// scan the download directory
$dir = directoryTree(WB_PATH.'/media/downloads');

$ignore = array('.htaccess','.htpasswd');
foreach ($dir as $file) {
  if (!is_file($file)) continue;
  $basename = basename($file, '.zip');
  if (in_array($basename, $ignore)) continue;
  $addon = substr($basename, 0, strrpos($basename, '_'));
  $release = substr($basename, strrpos($basename, '_')+1 );
  $SQL = "SELECT `id` FROM `".TABLE_PREFIX."mod_manufaktur_downloads` WHERE `addon`='$addon' AND `release`='$release'";
  $id = $database->get_one($SQL, MYSQL_ASSOC);
  if ($database->is_error())
    return $database->get_error();
  if ($id < 1) {
    $file_datetime = date('Y-m-d H:i:s', filemtime($file));
    $file_size = filesize($file);
    $SQL = "INSERT INTO `".TABLE_PREFIX."mod_manufaktur_downloads` (`addon`,`release`,`file_name`,`file_datetime`,`file_size`) VALUES ".
            "('$addon','$release','$file','$file_datetime','$file_size')";
    $database->query($SQL);
    if ($database->is_error())
        return $database->get_error();
  }
} // foreach


// get the total ACTIVE and INACTIVE downloads
$SQL = "SELECT `status`, `addon`, SUM(`downloads`) AS 'total' FROM `".TABLE_PREFIX."mod_manufaktur_downloads` GROUP BY `addon` ORDER BY `addon` ASC";
if (null == ($query = $database->query($SQL)))
  return $database->get_error();
$downloads = array();
$inactive = array();
while (false !== ($dl = $query->fetchRow(MYSQL_ASSOC))) {
  if ($dl['status'] == 'ACTIVE')
    $downloads[$dl['addon']] = $dl['total'];
  else
    $inactive[$dl['addon']] = $dl['total'];
}

$rows = '';
$flipper = 'dl_flop';

//while (false !== ($addon = $query->fetchRow(MYSQL_ASSOC))) {
foreach ($downloads as $addon_name => $addon_downloads) {
  $SQL = "SELECT `file_name`, `file_datetime` FROM `".TABLE_PREFIX."mod_manufaktur_downloads` WHERE `addon`='$addon_name' ORDER BY `file_datetime` DESC LIMIT 1";
  if (null == ($query = $database->query($SQL))) {
    return $database->get_error();
  }
  if ($query->numRows() < 1)
    continue;
  $archive = $query->fetchRow(MYSQL_ASSOC);
  $project_link = 'https://addons.phpmanufaktur.de/de/name/'.strtolower($addon_name).'.php';
  $download_link = 'https://addons.phpmanufaktur.de/download.php?file='.$addon_name;
  $download = number_format($addon_downloads, 0, ',', '.');
  $file_name = basename($archive['file_name']);
  $date = date('d.m.Y', strtotime($archive['file_datetime']));
  $flipper = ($flipper == 'dl_flop') ? 'dl_flip' : 'dl_flop';
  $rows .= <<<EOD
<tr class="$flipper">
  <td class="dl_project_link"><a href="$project_link">$addon_name</a></td>
  <td class="dl_addon_count">$download</td>
  <td class="dl_file_name"><a href="$download_link">$file_name</a></td>
  <td class="dl_file_date">$date</td>
</tr>
EOD;
} // while

$downloads_netto = number_format(array_sum($downloads), 0, ',', '.');
$downloads_total = number_format(array_sum($inactive)+array_sum($downloads), 0, ',', '.');
$last_update = date('d.m.Y - H:i:s');

$result = <<<EOD
<h1>Downloads</h1>
<p>Die aktuell verfügbaren ||Add-ons|| wurden bis jetzt <strong>$downloads_netto</strong> mal heruntergeladen <span class="dl_download_total">(seit 2009 insgesamt: $downloads_total Downloads)</span>.</p>
<p class="smaller">Die letzte Aktualisierung dieser Übersicht erfolgte am $last_update</p>
<p>&nbsp;</p>
<table id="dl_table">
  <tr>
    <th class="dl_project_link">Add-on</th>
    <th class="dl_addon_count">Downloads</th>
    <th class="dl_file_name">Installationsarchiv</th>
    <th class="dl_file_date">Stand</th>
  </tr>
  $rows
</table>
<div class="smaller">
  <p>&nbsp;</p>
  <p><strong>Hinweis für Webmaster:</strong><br />Wenn Sie auf Ihrer Seite einen Downloadlink für ein Addon der phpManufaktur setzen, verwenden Sie bitte ausschliesslich die Downloadlinks von dieser Seite. Dadurch stellen Sie sicher, dass Sie immer die aktuelleste Version des jeweiligen Add-on zum Download zur Verfügung stellen.</p>
</div>
EOD;

return $result;