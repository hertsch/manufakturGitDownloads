<?php

/**
 * manufakturGitDownloads
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link http://phpmanufaktur.de
 * @copyright 2012
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

// include class.secure.php to protect this file and the whole CMS!
if (defined('WB_PATH')) {
  if (defined('LEPTON_VERSION'))
    include(WB_PATH.'/framework/class.secure.php');
}
else {
  $oneback = "../";
  $root = $oneback;
  $level = 1;
  while (($level < 10) && (!file_exists($root.'/framework/class.secure.php'))) {
    $root .= $oneback;
    $level += 1;
  }
  if (file_exists($root.'/framework/class.secure.php')) {
    include($root.'/framework/class.secure.php');
  }
  else {
    trigger_error(sprintf("[ <b>%s</b> ] Can't include class.secure.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
  }
}
// end include class.secure.php

// wb2lepton compatibility
if (!defined('LEPTON_PATH')) require_once WB_PATH . '/modules/' . basename(dirname(__FILE__)) . '/wb2lepton.php';

class githubDownloads {

  private static $error = '';
  private static $config_file = '';
  public static $config = array();
  public static $status = array(
      'status_code' => 'ok',
      'status_message' => '',
      'last_repository' => '',
      'execution_time' => 0
      );
  private static $script_time_start = 0;
  private static $script_time_max = 25;
  public static $x_ratelimit_min = 5;


  /**
   * Constructor for githubDownloads
   */
  public function __construct() {
    self::$config_file = LEPTON_PATH.'/modules/'.basename(dirname(__FILE__)).'/config.json';
    self::$config = $this->readConfiguration();
    self::$script_time_start = microtime(true);
  } // __construct()

  /**
   * Get the actual error message
   *
   * @return string
   */
  public function getError() {
    return self::$error;
  } // getError()

  /**
   * Set the actual error message
   *
   * @param string $error
   */
  protected function setError($error = '') {
    self::$error = $error;
  } // setError()

  /**
   * Check if actual an error exists
   *
   * @return boolean
   */
  public function isError() {
    return (bool) !empty(self::$error);
  }

  /**
   * Write the configuration file in json format
   *
   * @param array $config
   * @return boolean
   */
  public function writeConfiguration($config) {
    if (!file_put_contents(self::$config_file, json_encode($config))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf('Error writing the configuration file %s', self::$config_file)));
      return false;
    }
    return true;
  } // writeConfiguration()

  /**
   * Read the configuration file
   *
   * @return boolean|array FALSE on error or array with the configuration data
   */
  public function readConfiguration() {
    if (!file_exists(self::$config_file)) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf('The configuration file %s does not exists!', self::$config_file)));
      return false;
    }
    if (false === ($result = file_get_contents(self::$config_file))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Error reading the configuration file %s.', self::$config_file));
      return false;
    }

    $result = json_decode($result, true);
    return $result;
  } // readConfiguration()


  /**
   * Get the data for a repository from the database
   *
   * @param string $owner
   * @param string $repository_name
   * @return boolean|array: FALSE on error, data record on success
   */
  public function getRepositoryData($owner, $repository_name) {
    global $database;
    $SQL = "SELECT * FROM `".TABLE_PREFIX."mod_github_downloads` WHERE ".
        "`repository_name`='$repository_name' AND `owner`='$owner'";
    if (false === ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if ($query->numRows() > 0) {
      // get the data record for this repository
      return $query->fetchRow(MYSQL_ASSOC);
    }
    return false;
  } // getRepositoryData()

  /**
   * Get the downloads for the specified $repository and add in the config.json
   * saved historical download counts
   *
   * @param string $repository_name
   * @param boolen $add_historic_count default is TRUE
   * @return integer|boolean: count on success or FALSE on error
   */
  public function getRepositoryDownloadCount($repository_name, $add_historic_count=true) {
    global $database;
    $SQL = "SELECT `download_total` FROM `".TABLE_PREFIX."mod_github_downloads` WHERE ".
        "`repository_name`='$repository_name' AND `owner`='".self::$config['owner']."'";
    if (null === ($count = $database->get_one($SQL, MYSQL_ASSOC))) {
      if ($database->is_error()) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
      // repository not found - return zero
      $count = 0;
    }
    if ($add_historic_count && isset(self::$config['history'][$repository_name]))
      $count += self::$config['history'][$repository_name];
    return $count;
  } // getRepositoryDownloadCount()

  /**
   * Get all downloads for all repositories and add add all in the config.json
   * saved historical download counts
   *
   * @param boolean $add_historic_count
   * @return integer|boolean: count on success or FALSE on error
   */
  public function getRepositoriesDownloadTotal($add_historic_count=true) {
    global $database;
    $SQL = "SELECT SUM(`download_total`) AS `total` FROM `".TABLE_PREFIX."mod_github_downloads` WHERE ".
        "`owner`='".self::$config['owner']."'";
    if (null === ($count = $database->get_one($SQL, MYSQL_ASSOC))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if ($add_historic_count && (isset(self::$config['history'])))
        $count += array_sum(self::$config['history']);
    return $count;
  } // getRepositoriesDownloadTotal()

  /**
   * Isert a new repository record into the database and init it with the base informations
   *
   * @param string $owner of the repository
   * @param boolean $is_organisation TRUE if the owner is an organisation
   * @param string $repository_name the github name of the repository
   * @param string $repository_url the github url of the repository
   * @return boolean|array false on error, data record on success
   */
  protected function initRepositoryData($owner, $is_organisation, $repository_name, $repository_url) {
    global $database;
    $SQL = "INSERT INTO `".TABLE_PREFIX."mod_github_downloads` (`owner`,`is_organisation`,".
        "`repository_name`,`repository_url`,`download_active`) VALUES ('$owner','$is_organisation',".
        "'$repository_name','$repository_url','0')";
    if (!$database->query($SQL)) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    return $this->getRepositoryData($owner, $repository_name);
  } // initRepositioryData()

  /**
   * Update the repository record
   *
   * @param integer $repository_id
   * @param array $data record of the repository
   * @return boolean result
   */
  protected function updateRepositoryData($repository_id, $data) {
    global $database;
    $values = '';
    foreach ($data as $key => $value) {
      if (($key == 'id') || ($key == 'timestamp')) continue;
      if (!empty($values)) $values .= ' ,';
      $values .= "`$key`='$value'";
    }
    $SQL = sprintf("UPDATE `%smod_github_downloads` SET %s WHERE `id`='%d'",
        TABLE_PREFIX, $values, $repository_id);
    if (!$database->query($SQL)) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    return true;
  } // updateRepositoryData()

  /**
   * Update the status database record.
   * This function uses the property array self::$status
   *
   * @return boolean
   */
  protected function updateStatusData() {
    global $database;
    foreach (self::$status as $name => $value) {
      if (is_numeric($name)) continue;
      $SQL = sprintf("INSERT INTO `%smod_github_downloads_status` (`name`, `value`) VALUES ('%s','%s') ON DUPLICATE KEY UPDATE `value`='%s'",
          TABLE_PREFIX, $name, $value, $value);
      if (!$database->query($SQL)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
    }
    return true;
  } // updateStatusData()

  /**
   * Get the last status from the database record and set the property array self::$status
   *
   * @return boolean
   */
  protected function getStatusData() {
    global $database;
    $SQL = "SELECT * FROM `".TABLE_PREFIX."mod_github_downloads_status`";
    if (false === ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    $data = array();
    while (false !== ($item = $query->fetchRow(MYSQL_ASSOC))) $data[] = $item;
    foreach ($data as $item) {
      self::$status[$item['name']] = $item['value'];
    }
    return true;
  } // getStatusData()

  /**
   * GET command to Github
   *
   * @param string $get
   * @return mixed
   */
  protected function gitGet($get, $params='') {
    if (strpos($get, 'https://api.github.com') === 0)
      $command = $get;
    else
      $command = "https://api.github.com$get?callback=return";

    if (isset(self::$config['access_token']) && (!empty(self::$config['access_token'])))
      $command .= '&access_token='.self::$config['access_token'];
    if (!empty($params))
      $command .= "&$params";

    $ch = curl_init($command);

    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        );
    curl_setopt_array($ch, $options);

    $result = curl_exec($ch);
    curl_close($ch);
    $matches = array();
    preg_match('/{(.*)}/', $result, $matches);
    return json_decode($matches[0], true);

  } // gitGet()

  protected function gitPut($put) {
    $ch = curl_init();
    if (strpos($put, 'https://api.github.com') === 0)
      $command = $put;
    else
      $command = "https://api.github.com$put";
    $curl_opt = array(
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'manufakturGitDownloads',
        CURLOPT_URL => $command,
        CURLOPT_POST => true
    );
    curl_setopt_array($ch, $curl_opt);
    $result = curl_exec($ch);
    $status = curl_getinfo($ch);
    curl_close($ch);
    if (($status['http_code'] == '200') || ($status['http_code'] == '201')) {
      return $result;
    }
    else {
      $this->setError(sprintf('[%s - %s] %s: %s', __METHOD__, __LINE__, $status['http_code'], $result));
      return false;
    }
  } // gitPut()

  protected function gitAuthenticate($user, $password) {
    $ch = curl_init();
    $curl_opt = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => 'https://api.github.com',
        CURLOPT_USERPWD => 'hertsch:sfYDJhOYgJDvD6S8zWSg'

    );
    curl_setopt_array($ch, $curl_opt);
    $result = curl_exec($ch);
    $status = curl_getinfo($ch);
    curl_close();
    return $result;
  }

  /**
   * Connect to github, walk through all repositories of the owner and get the download statistics
   *
   * @return boolean true on success
   */
  public function getRepositories() {
    global $database;
    // get the last status
    $this->getStatusData();
    if (self::$status['status_code'] == 'next_page') {
      // get the command to call the next page
      $command = self::$status['status_message'].'/repos';
    }
    else {
      // build the command to get the available repositories
      $command = "/orgs/".self::$config['owner'].'/repos';
    }
    $worker = $this->gitGet($command);
//echo "<pre>";
//print_r($worker);
//echo "</pre>";

    $repos = array();
    if (!isset($worker['meta'])) {
      // general error connecting to github
      $this->setError(sprintf('[%s - %s][%s] %s', __METHOD__, __LINE__, $command,
          'Error connecting to github.'));
      self::$status['status_code'] = 'error';
      self::$status['status_message'] = $this->getError();
      self::$status['execution_time'] = microtime(true)-self::$script_time_start;
      $this->updateStatusData();
      return false;
    }
    elseif ($worker['meta']['status'] == 200) {
      foreach ($worker['data'] as $repo) {
echo "repo: {$repo['name']}<br>";
        if (self::$status['status_code'] == 'abort') {
echo "abort mode <br>";
          if ($repo['name'] == self::$status['last_repository']) {
echo "hit!<br>";
            self::$status['status_code'] = 'ok - abort';
          }
          else {
            // walk through without further action
echo "continue!<br>";
            continue;
          }
        }

        if (((microtime(true) - self::$script_time_start) > self::$script_time_max) ||
            ($worker['meta']['X-RateLimit-Remaining'] < self::$x_ratelimit_min)) {
          // abort script before running out of time or out of X-RateLimit...
echo "abort w\limit {$worker['meta']['X-RateLimit-Remaining']}<br>";
          self::$status['status_code'] = 'abort';
          self::$status['status_message'] = '';
          self::$status['last_repository'] = $repo['name'];
          self::$status['execution_time'] = microtime(true)-self::$script_time_start;
          $this->updateStatusData();
          return true;
        }
        // check if a data record for this repo exists
        if (false === ($data = $this->getRepositoryData(self::$config['owner'], $repo['name']))) {
          if ($this->isError()) return false;
          // ok - create a new data record
          if (false === ($data = $this->initRepositoryData(self::$config['owner'],
              self::$config['is_organisation'], $repo['name'], $repo['html_url']))) {
            self::$status['status_code'] = 'error';
            self::$status['status_message'] = $this->getError();
            self::$status['last_repository'] = $repo['name'];
            self::$status['execution_time'] = microtime(true)-self::$script_time_start;
            $this->updateStatusData();
            return false;
          }
        }
        $downloads = 0;

        if ($repo['has_downloads'] == 1) {
          // this repositories has downloads
          $get = "/repos/".self::$config['owner']."/".$repo['name']."/downloads";
          $dl = $this->gitGet($get);
          if ($dl['meta']['status'] == 200) {
            if ($dl['meta']['X-RateLimit-Remaining'] < self::$x_ratelimit_min) {
              // abort script before running out of X-RateLimit...
echo "abort2 w\limit: {$dl['meta']['X-RateLimit-Remaining']}<br>";
              self::$status['status_code'] = 'abort';
              self::$status['status_message'] = '';
              self::$status['last_repository'] = $repo['name'];
              self::$status['execution_time'] = microtime(true)-self::$script_time_start;
              $this->updateStatusData();
              return true;
            }
            $start = true;
            $update = false;
            // now walk through the downloadable files
            foreach ($dl['data'] as $file) {
              // increase the download counter for the repository
              $downloads += $file['download_count'];
              if (($start) && ($file['name'] != $data['download_file_name']))
                $update = true;
            }
            if ($downloads != $data['download_total']) $update = true;
            if ($update) {
              // update the repository data
              $upd = array(
                  'download_active' => 1,
                  'download_file_url' => $dl['data'][0]['html_url'],
                  'download_file_name' => $dl['data'][0]['name'],
                  'download_file_size' => $dl['data'][0]['size'],
                  'download_file_date' => date('Y-m-d H:i:s', strtotime($dl['data'][0]['created_at'])),
                  'download_total' => $downloads
                  );
              if (!$this->updateRepositoryData($data['id'], $upd)) {
                self::$status['status_code'] = 'error';
                self::$status['status_message'] = $this->getError();
                self::$status['last_repository'] = $repo['name'];
                self::$status['execution_time'] = microtime(true)-self::$script_time_start;
                $this->updateStatusData();
                return false;
              }
            }
          }
          else {
            $this->setError(sprintf('[%s - %s][%s] Status: %s - %s', __METHOD__,
                __LINE__, $get, $dl['meta']['status'], $dl['data']['message']));
            self::$status['status_code'] = 'error';
            self::$status['status_message'] = $this->getError();
            self::$status['last_repository'] = $repo['name'];
            self::$status['execution_time'] = microtime(true)-self::$script_time_start;
            $this->updateStatusData();
            return false;
          }
        }
        else {
          // this repository has no downloads
          if (($data['download_active'] == 1) && (!$this->updateRepositoryData($data['id'], $data))) {
            self::$status['status_code'] = 'error';
            self::$status['status_message'] = $this->getError();
            self::$status['last_repository'] = $repo['name'];
            self::$status['execution_time'] = microtime(true)-self::$script_time_start;
            $this->updateStatusData();
            return false;
          }
        }

      } // foreach repository
    }
    else {
      // problem connecting to github, prompt the command and status
      $this->setError(sprintf('[%s - %s][%s] Status: %s - %s', __METHOD__, __LINE__,
          $command, $worker['meta']['status'], $worker['data']['message']));
      self::$status['status_code'] = 'error';
      self::$status['status_message'] = $this->getError();
      self::$status['execution_time'] = microtime(true)-self::$script_time_start;
      $this->updateStatusData();
      return false;
    }
    if (isset($worker['meta']['Link'][0][1]['rel']) && ($worker['meta']['Link'][0][1]['rel'] == 'next')) {
      // there exist a further page - call this at the next execution!
      self::$status['status_code'] = 'next_page';
      self::$status['status_message'] = $worker['meta']['Link'][0][0];
      self::$status['last_repository'] = $repo['name'];
      self::$status['execution_time'] = microtime(true)-self::$script_time_start;
    }
    else {
      // all done
      self::$status['status_code'] = 'ok';
      self::$status['status_message'] = '';
      self::$status['last_repository'] = $repo['name'];
      self::$status['execution_time'] = microtime(true)-self::$script_time_start;
    }
    $this->updateStatusData();
    return true;
  } // getRepositories()

} // class githubDownloads



/**
 * Create a table for the download statistics and acces to the active
 * download file
 *
 * @return boolean|string TRUE on success or error message
 */
function createTable() {
  global $database;
  // create the table for the repositories
  $SQL = "CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "mod_github_downloads` ( " .
      "`id` INT(11) NOT NULL AUTO_INCREMENT, " .
      "`owner` VARCHAR(255) NOT NULL DEFAULT '', ".
      "`is_organisation` TINYINT NOT NULL DEFAULT '1', ".
      "`repository_name` VARCHAR(255) NOT NULL DEFAULT '', ".
      "`repository_url` TEXT, ".
      "`download_active` TINYINT NOT NULL DEFAULT '0', ".
      "`download_file_url` TEXT, ".
      "`download_file_name` TEXT, ".
      "`download_file_size` INT(11) NOT NULL DEFAULT '0', ".
      "`download_file_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', ".
      "`download_total` INT(11) NOT NULL DEFAULT '0', ".
      "`timestamp` TIMESTAMP, " .
      "PRIMARY KEY (`id`)" .
      " ) ENGINE=MyIsam AUTO_INCREMENT=1 DEFAULT CHARSET utf8 COLLATE utf8_general_ci";
  if (!$database->query($SQL)) {
    return sprintf('[%s - %s] %s', __FUNCTION__, __LINE__, $database->get_error());
  }
  // create the table for the status
  $SQL = "CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX. "mod_github_downloads_status` ( ".
      "`name` VARCHAR(255) NOT NULL DEFAULT '', ".
      "`value` VARCHAR(255) NOT NULL DEFAULT '', ".
      "`timestamp` TIMESTAMP, ".
      "PRIMARY KEY (`name`)".
      " ) ENGINE=MyIsam DEFAULT CHARSET utf8 COLLATE utf8_general_ci";
  if (!$database->query($SQL)) {
    return sprintf('[%s - %s] %s', __FUNCTION__, __LINE__, $database->get_error());
  }
  return true;
} // createTable()

/**
 * Delete the table for download statistics
 *
 * @return string|boolean error message or TRUE on success
 */
function deleteTable() {
  global $database;
  if (!$database->query('DROP TABLE IF EXISTS `'.TABLE_PREFIX.'mod_github_downloads`')) {
    return sprintf('[%s - %s] %s', __FUNCTION__, __LINE__, $database->get_error());
  }
  if (!$database->query('DROP TABLE IF EXISTS `'.TABLE_PREFIX.'mod_github_downloads_status`')) {
    return sprintf('[%s - %s] %s', __FUNCTION__, __LINE__, $database->get_error());
  }
  return true;
} // deleteTable()

