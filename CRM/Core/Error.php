<?php
/*
   +--------------------------------------------------------------------+
   | CiviCRM version 3.3                                                |
   +--------------------------------------------------------------------+
   | Copyright CiviCRM LLC (c) 2004-2010                                |
   +--------------------------------------------------------------------+
   | This file is a part of CiviCRM.                                    |
   |                                                                    |
   | CiviCRM is free software; you can copy, modify, and distribute it  |
   | under the terms of the GNU Affero General Public License           |
   | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
   |                                                                    |
   | CiviCRM is distributed in the hope that it will be useful, but     |
   | WITHOUT ANY WARRANTY; without even the implied warranty of         |
   | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
   | See the GNU Affero General Public License for more details.        |
   |                                                                    |
   | You should have received a copy of the GNU Affero General Public   |
   | License and the CiviCRM Licensing Exception along                  |
   | with this program; if not, contact CiviCRM LLC                     |
   | at info[AT]civicrm[DOT]org. If you have questions about the        |
   | GNU Affero General Public License or the licensing of CiviCRM,     |
   | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
   +--------------------------------------------------------------------+
 */

/**
 * Start of the Error framework. We should check out and inherit from
 * PEAR_ErrorStack and use that framework
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

require_once 'PEAR/ErrorStack.php';
require_once 'PEAR/Exception.php';

require_once 'CRM/Core/Config.php';
require_once 'CRM/Core/Smarty.php';

require_once 'Log.php';
class CRM_Exception extends PEAR_Exception {
  // Redefine the exception so message isn't optional
  public function __construct($message = NULL, $code = 0, Exception$previous = NULL) {
    parent::__construct($message, $code, $previous);
  }
}

class CRM_Core_Error extends PEAR_ErrorStack {

  /**
   * status code of various types of errors
   * @var const
   */
  CONST FATAL_ERROR = 2, DUPLICATE_CONTACT = 8001, DUPLICATE_CONTRIBUTION = 8002, DUPLICATE_PARTICIPANT = 8003;

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   * @var object
   * @static
   */
  private static $_singleton = NULL;

  /**
   * The logger object for this application
   * @var object
   * @static
   */
  private static $_log = NULL;

  /**
   * If modeException == true, errors are raised as exception instead of returning civicrm_errors
   * @static
   */
  public static $modeException = NULL;

  /**
   * singleton function used to manage this object. This function is not
   * explicity declared static to be compatible with PEAR_ErrorStack
   *
   * @return object
   */
  function &singleton($package = NULL, $msgCallback = false, $contextCallback = false, $throwPEAR_Error = false, $stackClass = 'PEAR_ErrorStack') {
    if (self::$_singleton === NULL) {
      self::$_singleton = new CRM_Core_Error('CiviCRM');
    }
    return self::$_singleton;
  }

  /**
   * construcor
   */
  function __construct() {
    parent::__construct('CiviCRM');

    $log = CRM_Core_Config::getLog();
    $this->setLogger($log);

    // PEAR<=1.9.0 does not declare "static" properly.
    if (!is_callable(array('PEAR', '__callStatic'))) {
      $this->setDefaultCallback(array($this, 'handlePES'));
    }
    else {
      PEAR_ErrorStack::setDefaultCallback(array($this, 'handlePES'));
    }
  }

  function getMessages(&$error, $separator = '<br />') {
    if (is_a($error, 'CRM_Core_Error')) {
      $errors = $error->getErrors();
      $message = array();
      foreach ($errors as $e) {
        $message[] = $e['code'] . ':' . $e['message'];
      }
      $message = implode($separator, $message);
      return $message;
    }
    return NULL;
  }

  function displaySessionError(&$error, $separator = '<br />') {
    $message = self::getMessages($error, $separator);
    if ($message) {
      $status = ts("Payment Processor Error message") . "{$separator}: $message";
      $session = CRM_Core_Session::singleton();
      $session->setStatus($status);
    }
  }

  /**
   * create the main callback method. this method centralizes error processing.
   *
   * the errors we expect are from the pear modules DB, DB_DataObject
   * which currently use PEAR::raiseError to notify of error messages.
   *
   * @param object PEAR_Error
   *
   * @return void
   * @access public
   */
  public static function handle($pearError) {

    // setup smarty with config, session and template location.
    $template = CRM_Core_Smarty::singleton();
    $config = CRM_Core_Config::singleton();

    if ($config->backtrace) {
      self::backtrace();
    }

    // create the error array
    $error = self::getErrorDetails($pearError);

    if (function_exists('mysqli_error')) {
      $dao = new CRM_Core_DAO();

      // we do it this way, since calling the function
      // getDatabaseConnection could potentially result
      // in an infinite loop
      global $_DB_DATAOBJECT;
      if (isset($_DB_DATAOBJECT['CONNECTIONS'][$dao->_database_dsn_md5])) {
        $conn = $_DB_DATAOBJECT['CONNECTIONS'][$dao->_database_dsn_md5];
        $link = $conn->connection;

        if (mysqli_error($link)) {
          $mysql_error = mysqli_error($link) . ', ' . mysqli_errno($link);
          $template->assign_by_ref('mysql_code', $mysql_error);

          // execute a dummy query to clear error stack
          mysqli_query($link, 'select 1');
        }
      }
    }
    elseif (function_exists('mysql_error') && mysql_error()) {
      $mysql_error = mysql_error() . ', ' . mysql_errno();
      $template->assign_by_ref('mysql_code', $mysql_error);

      // execute a dummy query to clear error stack
      mysql_query('select 1');
    }

    $template->assign_by_ref('error', $error);
    $errorDetails = CRM_Core_Error::debug('', $error, FALSE);
    $template->assign_by_ref('errorDetails', $errorDetails);

    CRM_Core_Error::debug_var('Fatal Error Details', $error);
    CRM_Core_Error::backtrace('backTrace', TRUE);

    if ($config->initialized) {
      http_response_code(500);
      $content = $template->fetch('CRM/common/fatal.tpl');
      echo CRM_Utils_System::theme('page', $content, TRUE);
    }
    else {
      echo "Sorry. A non-recoverable error has occurred. The error trace below might help to resolve the issue<br>";
      CRM_Core_Error::debug(NULL, $error);
    }

    self::abend(1);
  }

  /**
   * Handle errors raised using the PEAR Error Stack.
   *
   * currently the handler just requests the PES framework
   * to push the error to the stack (return value PEAR_ERRORSTACK_PUSH).
   *
   * Note: we can do our own error handling here and return PEAR_ERRORSTACK_IGNORE.
   *
   * Also, if we do not return any value the PEAR_ErrorStack::push() then does the
   * action of PEAR_ERRORSTACK_PUSHANDLOG which displays the errors on the screen,
   * since the logger set for this error stack is 'display' - see CRM_Core_Config::getLog();
   *
   */
  public static function handlePES($pearError) {
    return PEAR_ERRORSTACK_PUSH;
  }

  /**
   * display an error page with an error message describing what happened
   *
   * @param string message  the error message
   * @param string code     the error code if any
   * @param string suppress suppress error message with given string
   *
   * @return void
   * @static
   * @acess public
   */
  static function fatal($message = NULL, $code = NULL, $suppress = NULL) {
    $vars = array(
      'message' => $message,
      'code' => $code,
      'server' => $_SERVER,
      'post' => $_POST,
    );

    $config = CRM_Core_Config::singleton();

    if ($config->fatalErrorHandler && function_exists($config->fatalErrorHandler)) {
      $name = $config->fatalErrorHandler;
      $ret = $name($vars);
      if ($ret) {
        // the call has been successfully handled
        // so we just exit
        self::abend(CRM_Core_Error::FATAL_ERROR);
      }
    }

    try {
      throw new CRM_Core_Exception($message);
    }
    catch(Exception $e){
      // fallback
      if (ini_get('xdebug.default_enable') && !empty(CRM_Utils_System::isUserLoggedIn()) && $config->debug) {
        ob_start();
        CRM_Core_Error::debug_var('Fatal Error Details', $vars, TRUE, FALSE);
        $vars['debug'] = ob_get_contents();
        ob_end_clean();
        ob_start();
        CRM_Core_Error::backtrace('backTrace', FALSE);
        $vars['backtrace'] = ob_get_contents();
        ob_end_clean();
      }
      CRM_Core_Error::debug_var('Fatal Error Details', $vars);
      CRM_Core_Error::backtrace('backTrace', TRUE);
      if ($suppress) {
        $vars['suppress'] = $suppress;
      }
      else {
        http_response_code(500);
        $vars['suppress'] = FALSE;
      }
      self::output($config->fatalErrorTemplate, $vars);
    }
    self::abend(CRM_Core_Error::FATAL_ERROR);
  }

  /**
   * display timeout message
   *
   * @param string message 
   *
   * @return void
   * @static
   * @acess public
   */
  static function timeout($message){
    $vars = array(
      'message' => $message,
    );
    self::output('CRM/common/timeout.tpl', $vars);
    CRM_Utils_System::civiExit();
  }

  /**
   * outputs pre-formatted debug information. Flushes the buffers
   * so we can interrupt a potential POST/redirect
   *
   * @param  string name of debug section
   * @param  mixed  reference to variables that we need a trace of
   * @param  bool   should we log or return the output
   * @param  bool   whether to generate a HTML-escaped output
   *
   * @return string the generated output
   * @access public
   * @static
   */
  static function debug($name, $variable = NULL, $print = FALSE, $html = TRUE) {
    $error = &self::singleton();
    $out = self::debug_var($name, $variable);

    if ($variable === NULL) {
      $variable = $name;
      $name = NULL;
    }

    $prefix = NULL;
    if ($html) {
      $out = htmlspecialchars($out);
      if ($name) {
        $prefix = "<p>$name</p>";
      }
      $out = "{$prefix}<p><pre>$out</pre></p><p></p>";
    }
    else {
      if ($name) {
        $prefix = "$name:\n";
      }
      $out = "{$prefix}$out\n";
    }
    if ($print) {
      echo $out;
    }

    return $out;
  }

  /**
   * Similar to the function debug. Only difference is
   * in the formatting of the output.
   *
   * @param  string variable name
   * @param  mixed  reference to variables that we need a trace of
   * @param  bool   should we use print_r ? (else we use var_dump)
   * @param  bool   should we log or return the output
   *
   * @return string the generated output
   *
   * @access public
   *
   * @static
   *
   * @see CRM_Core_Error::debug()
   * @see CRM_Core_Error::debug_log_message()
   */
  static function debug_var($variable_name,
    $variable = NULL,
    $print = FALSE,
    $log = TRUE,
    $comp = ''
  ) {
    // check if variable is set
    if ($variable === NULL) {
      $out = (string) $variable_name;
    }
    else {
      if ($print) {
        $out = print_r($variable, TRUE);
        $out = "\$$variable_name = $out";
      }
      else {
        // use var_dump
        ob_start();
        var_dump($variable);
        $dump = ob_get_contents();
        ob_end_clean();
        $out = "\n\$$variable_name = $dump";
      }
      // reset if it is an array
      if (is_array($variable)) {
        reset($variable);
      }
    }
    return self::debug_log_message($out, FALSE, $comp);
  }

  /**
   * display the error message on terminal
   *
   * @param  string message to be output
   * @param  bool   should we log or return the output
   *
   * @return string format of the backtrace
   *
   * @access public
   *
   * @static
   */
  static function debug_log_message($message, $out = FALSE, $comp = '') {
    $config = CRM_Core_Config::singleton();

    if ($comp) {
      $comp = $comp . '.';
    }

    $fileName = "{$config->configAndLogDir}CiviCRM." . $comp . md5($config->dsn . $config->userFrameworkResourceURL) . '.log';

    // Roll log file monthly or if greater than 256M
    // note that PHP file functions have a limit of 2G and hence
    // the alternative was introduce :)
    if (file_exists($fileName)) {
      $fileTime = date("Ym", filemtime($fileName));
      $fileSize = filesize($fileName);
      if (($fileTime < date('Ym')) ||
        ($fileSize > 256 * 1024 * 1024) ||
        ($fileSize < 0)
      ) {
        rename($fileName, $fileName . '.' . date('Ymdhi', mktime(0, 0, 0, date("m") - 1, date("d"), date("Y"))));
      }
    }

    $file_log = Log::singleton('file', $fileName);
    $file_log->log("$message\n");
    $str = "$message\n";
    if ($out) {
      echo $str;
    }
    $file_log->close();

    if ($config->userFrameworkLogging) {
      if ($config->userFramework == 'Drupal' and function_exists('watchdog')) {
        watchdog('civicrm', $message, NULL, WATCHDOG_DEBUG);
      }
    }

    return $str;
  }

  static function backtrace($msg = 'backTrace', $log = FALSE) {
    $backTrace = debug_backtrace();

    $msgs = array();
    foreach ($backTrace as $trace) {
      $msgs[] = implode(', ',
        array(CRM_Utils_Array::value('file', $trace),
          CRM_Utils_Array::value('function', $trace),
          CRM_Utils_Array::value('line', $trace),
        )
      );
    }

    $message = implode("\n", $msgs);
    if (!$log) {
      CRM_Core_Error::debug($msg, $message);
    }
    else {
      CRM_Core_Error::debug_var($msg, $message);
    }
  }

  static function createError($message, $code = 8000, $level = 'Fatal', $params = NULL) {
    $error = &CRM_Core_Error::singleton();
    $error->push($code, $level, array($params), $message);
    return $error;
  }

  /**
   * Set a status message in the session, then bounce back to the referrer.
   *
   * @param string $status        The status message to set
   *
   * @return void
   * @access public
   * @static
   */
  public static function statusBounce($status, $redirect = NULL) {
    $session = CRM_Core_Session::singleton();
    $session->setStatus($status, $append = TRUE, 'warning');
    if ($redirect === FALSE) {
      return; 
    }
    elseif (!$redirect) {
      $redirect = $session->readUserContext();
      if (!$redirect) {
        $redirect = '/';
      }
    }
    CRM_Utils_System::redirect($redirect);
  }

  /**
   * Function to reset the error stack
   *
   * @access public
   * @static
   */
  public static function reset() {
    $error = &self::singleton();
    $error->_errors = array();
    $error->_errorsByLevel = array();
  }


  public static function ignoreException($callback = NULL) {
    if (!$callback) {
      $callback = array('CRM_Core_Error', 'nullHandler');
    }

    $GLOBALS['_PEAR_default_error_mode'] = PEAR_ERROR_CALLBACK;
    $GLOBALS['_PEAR_default_error_options'] = $callback;
  }

  public static function exceptionHandler($pearError) {
    CRM_Core_Error::debug_var('Fatal Error Details', self::getErrorDetails($pearError));
    CRM_Core_Error::backtrace('backTrace', TRUE);
    throw new PEAR_Exception($pearError->getMessage(), $pearError);
  }

  /**
   * this function is used to return error details
   *
   * @param $pearError
   *
   * @return array $error
   */
  public static function getErrorDetails($pearError) {
    // create the error array
    $error = array();
    $error['callback'] = $pearError->getCallback();
    $error['code'] = $pearError->getCode();
    $error['message'] = $pearError->getMessage();
    $error['mode'] = $pearError->getMode();
    $error['debug_info'] = $pearError->getDebugInfo();
    $error['type'] = $pearError->getType();
    $error['user_info'] = $pearError->getUserInfo();
    $error['to_string'] = $pearError->toString();

    return $error;
  }


  /**
   * Error handler to quietly catch otherwise fatal smtp transport errors.
   *
   * @param object $obj       The PEAR_ERROR object
   *
   * @return object $obj
   * @access public
   * @static
   */
  public static function nullHandler($obj) {
    CRM_Core_Error::debug_var('Ignoring exception thrown here', $obj);
    CRM_Core_Error::backtrace('backTrace', TRUE);
    return $obj;
  }

  /**
   * (Re)set the default callback method
   *
   * @return void
   * @access public
   * @static
   */
  public static function setCallback($callback = NULL) {
    if (!$callback) {
      $callback = array('CRM_Core_Error', 'handle');
    }
    $GLOBALS['_PEAR_default_error_mode'] = PEAR_ERROR_CALLBACK;
    $GLOBALS['_PEAR_default_error_options'] = $callback;
  }

  public static function &createAPIError($msg, $data = NULL) {
    if (self::$modeException) {
      throw new Exception($msg, $data);
    }

    $values = array();

    $values['is_error'] = 1;
    $values['error_message'] = $msg;
    if (isset($data) && is_array($data)) {
      $values = array_merge($values, $data);
    }
    elseif(is_string($data)) {
      $values['error_data'] = $data;
    }
    return $values;
  }

  public static function &createAPISuccess($result = 1) {
    $values = array();

    $values['is_error'] = 0;
    $values['result'] = $result;
    return $values;
  }

  public static function movedSiteError($file) {
    $url = CRM_Utils_System::url('civicrm/admin/setting/updateConfigBackend',
      'reset=1',
      TRUE
    );
    echo "We could not write $file. Have you moved your site directory or server?<p>";
    echo "Please fix the setting by running the <a href=\"$url\">update config script</a>";
    exit();
  }

  /**
   * Terminate execution abnormally
   */
  protected static function abend($code) {
    // do a hard rollback of any pending transactions
    // if we've come here, its because of some unexpected PEAR errors
    require_once 'CRM/Core/Transaction.php';
    CRM_Core_Transaction::forceRollbackIfEnabled();
    CRM_Utils_System::civiExit($code);
  }

  /**
   * Error template
   */
  protected static function output($tplFile, $vars){
    $template = CRM_Core_Smarty::singleton();
    $template->assign('tplFile', $tplFile);
    $template->assign($vars);
    if (isset($_GET['snippet']) && $_GET['snippet']) {
      if($_GET['snippet'] == CRM_Core_Smarty::PRINT_SNIPPET ||
        $_GET['snippet'] == CRM_Core_Smarty::PRINT_NOFORM) {
        $content = $vars['message'];
        $json = array(
          'status' => '-1',
          'error' => "$content",
        );
        echo json_encode($json);
      }
      else {
        $content = $template->fetch('CRM/common/print.tpl');
        echo $content;
      }
    }
    else{
      $config = CRM_Core_Config::singleton();
      $tplCommon = 'CRM/common/' . strtolower($config->userFramework) . '.tpl';
      $content = $template->fetch($tplCommon);
      $null = &CRM_Core_DAO::$_nullObject;
      CRM_Utils_Hook::alterContent($content, 'page', $tplCommon, $null);
      CRM_Utils_System::theme('page', $content);
    }
  }

  /**
   * Purge logs
   */
  public static function purge() {
    $config = CRM_Core_Config::singleton();
    $dir1 = $config->configAndLogDir;
    $dir2 = str_replace("smartycli", "smartyfpm-fcgi", $dir1);
    foreach(array($dir1, $dir2) as $dir) {
      $filename = "{$dir}CiviCRM." . md5($config->dsn . $config->userFrameworkResourceURL) . '.log';
      $files = glob($filename.'*');
      if (!empty($files)) {
        foreach($files as $f) {
          if ($f != $filename && filemtime($f) < strtotime('now - 3month')) {
            unlink($f);
          }
        }
      }
    }
  }
}

PEAR_ErrorStack::singleton('CRM', FALSE, NULL, 'CRM_Core_Error');

