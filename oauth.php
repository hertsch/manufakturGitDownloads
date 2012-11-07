<?php

$oneback = "../";
$root = '';
$level = 1;
while (($level < 10) && (!file_exists($root.'config.php'))) {
  $root .= $oneback;
  $level += 1;
}
if (file_exists($root.'config.php')) {
  require_once $root.'config.php';
}
else {
  trigger_error(sprintf("[ <b>%s</b> ] Can't find config.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
}

class githubAuthorize {

  static private $error = '';
  static private $config_file = '';
  static public $config = array();
  static public $status = array(
      'status_code' => 'ok',
      'status_message' => '',
      'last_repository' => '',
      'execution_time' => 0
  );
  static private $script_time_start = 0;
  static private $script_time_max = 25;



  /**
   * Constructor for githubDownloads
   */
  public function __construct() {
    self::$config_file = WB_PATH.'/modules/'.basename(dirname(__FILE__)).'/config.json';
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
   * GET command to Github
   *
   * @param string $get
   * @return mixed
   */
  protected function gitGet($get) {
    if (strpos($get, 'https://api.github.com') === 0)
      $command = $get;
    else
      $command = "https://api.github.com$get";
    $ch = curl_init($command);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if (!curl_errno($ch))
    {
      $info = curl_getinfo($ch);
      print_r($info);
    }
    curl_close($ch);
    $matches = array();
    preg_match('/{(.*)}/', $result, $matches);
    return json_decode($matches[0], true);
  } // gitGet()

  protected function gitPut($put) {
    $ch = curl_init();
    if (strpos($put, 'https://github.com') === 0)
      $command = $put;
    else
      $command = "https://github.com$put";
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

  public function gitAuthendticate() {
    $ch = curl_init('https://github.com');
    $curl_opt = array(
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'manufakturGitDownloads',
        CURLOPT_USERPWD => "hertsch:sfYDJhOYgJDvD6S8zWSg",
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC
    );
    curl_setopt_array($ch, $curl_opt);
    $result = curl_exec($ch);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
  } // gitAuthenticate()


  public function checkAuthentication() {
    if (isset(self::$config['access_token']) && !empty(self::$config['access_token'])) {
      exit('Access token already exists!');
    }
    if (!isset(self::$config['owner'])) {
      exit('Missing the owner!');
    }
    elseif (!isset(self::$config['client_id']) || empty(self::$config['client_id'])) {
      exit('Missing the client ID!');
    }
    elseif (!isset(self::$config['client_secret']) || empty(self::$config['client_secret'])) {
      exit('Missing the client secret!');
    }
    elseif (isset($_GET['code'])) {
      // got the code
      $command = '/login/oauth/access_token?'.http_build_query(array(
          'client_id' => self::$config['client_id'],
          'client_secret' => self::$config['client_secret'],
          'code' => $_GET['code']
          ));
      if (false === ($result = $this->gitPut($command))) {
        exit($this->getError());
      }
      if (!isset($result['access_token'])) {
        exit($result);
      }
      $param_array = explode('&', $result);
echo "<pre>";
print_r($param_array);
echo "</pre>";
      $param = array();
      foreach ($param_array as $item) {
        list($key, $value) = explode('=', $item);
        $param[$key] = $value;
      }
      if (isset($param['access_token'])) {
        self::$config['client_id'] = '';
        self::$config['client_secret'] = '';
        self::$config['access_token'] = $param['access_token'];
        if (!$this->writeConfiguration(self::$config)) {
          exit($this->getError());
        }
      }
      else {
        exit('Got no access_token!');
      }
      exit('OK');
    }
    else {
      // we have no access token
      header('Location: ' .'https://github.com/login/oauth/authorize?client_id='.self::$config['client_id'].'&callback_uri=https://addons.phpmanufaktur.de/modules/manufaktur_git_downloads/oauth.php');
      die('Redirect');
    }
  } // checkAuthentication()


}

$oauth = new githubAuthorize();
$oauth->checkAuthentication();
