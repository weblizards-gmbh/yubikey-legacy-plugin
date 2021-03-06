<?php
/**
 * This source file is subject to the new BSD license that is
 * available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @category   Pimcore
 * @copyright  Copyright (c) 2015 Weblizards GmbH (http://www.weblizards.de)
 * @author     Thomas Keil <thomas@weblizards.de>
 * @license    http://www.pimcore.org/license     New BSD License
 */

if (!defined("YUBIKEY_PLUGIN_PATH")) define("YUBIKEY_PLUGIN_PATH", PIMCORE_PLUGINS_PATH."/YubiKey");
if (!defined("YUBIKEY_PLUGIN_VAR"))  define("YUBIKEY_PLUGIN_VAR", PIMCORE_WEBSITE_PATH . "/var/plugins/YubiKey");

/**
 * Class Plugin
 * @package YubiKey
 */
class YubiKey_Plugin extends Pimcore_API_Plugin_Abstract implements Pimcore_API_Plugin_Interface {

  /**
   * @var array Files the plugin needs to be present in /website/var/plugins/YubiKey
   */
  private static $files = array("users.xml", "config.xml");


  /**
   * Installs the plugins config files in /website/var/plugins/YubiKey
   *
   * @return string with the status of this process
   */
	public static function install (){
    if (!is_dir(YUBIKEY_PLUGIN_VAR)) mkdir(YUBIKEY_PLUGIN_VAR);

    foreach (self::$files as $config_file) {
      if (!file_exists(YUBIKEY_PLUGIN_VAR.DIRECTORY_SEPARATOR.$config_file)) {
        copy(YUBIKEY_PLUGIN_PATH.DIRECTORY_SEPARATOR."files".DIRECTORY_SEPARATOR.$config_file, YUBIKEY_PLUGIN_VAR.DIRECTORY_SEPARATOR.$config_file);
      }
    }

    if (!function_exists("openssl_encrypt")) {
      return "YubiKey Plugin is installed, but openssl extension is missing. You will not be able to use the remote server.";
    }

    try {
      $crypt = new Zend_Crypt_Rsa();
      $keys = $crypt->generateKeys();

      /** @var Zend_Crypt_Rsa_Key_Private $privateKey */
      $privateKey = $keys["privateKey"];

      /** @var Zend_Crypt_Rsa_Key_Public $publicKey */
      $publicKey = $keys["publicKey"];
    } catch (Exception $e) {
      return "YubiKey Plugin could not be installed: ".$e->getMessage();
    }

    $config = YubiKey_Config::getInstance();
    $data = $config->getData();
    $data["yubikey"]["local"]["privatekey"] = $privateKey->toString();
    $data["yubikey"]["local"]["publickey"] = $publicKey->toString();
    $config->setData($data);
    $config->save();

    if (self::isInstalled()) {
      return "YubiKey Plugin successfully installed.";
    } else {
      return "YubiKey Plugin could not be installed";
    }

	}

  /**
   * @return bool
   */
  public static function needsReloadAfterInstall() {
    return false;
  }


  /**
   * Removes the plugins files in /website/var/plugins/YubiKey
   *
   * @return string
   */
	public static function uninstall (){
    foreach (self::$files as $config_file) {
      unlink(YUBIKEY_PLUGIN_VAR . DIRECTORY_SEPARATOR . $config_file);
    }
    unlink(YUBIKEY_PLUGIN_VAR . DIRECTORY_SEPARATOR);

    if (!self::isInstalled()) {
        return "YubiKey Plugin successfully uninstalled.";
      } else {
        return "YubiKey Plugin could not be uninstalled";
      }
	}

  /**
   * Checks if the plugin is installed
   *
   * @return bool
   */
	public static function isInstalled () {
    if (!is_dir(YUBIKEY_PLUGIN_PATH)) return false;
    foreach (self::$files as $config_file) {
      if (!file_exists(YUBIKEY_PLUGIN_VAR . DIRECTORY_SEPARATOR . $config_file)) return false;
    }
    return true;
	}

  /**
   * Hook called when login in pimcore is about to fail. Must return
   * a valid pimcore User for successful authentication or null for failure.
   *
   * @param string $username username provided in login credentials
   * @param string $password password provided in login credentials
   * @return User authenticated user or null if login shall fail
   * @deprecated
   */
  public function authenticateUser($username, $password)  {

    $user = YubiKey_Authenticator::authenticate($username, $password);
    if (! $user instanceof User) {
      $user = YubiKey_RemoteAuthenticator::authenticate($username, $password);
    }
    if ($user instanceof User) {
      return $user;
    }

    return null;
  }

  /**
   *
   * @param string $language
   * @return string path to the translation file relative to plugin directory
   */
  public static function getTranslationFile($language) {
    if(file_exists(YUBIKEY_PLUGIN_PATH . "/texts/" . $language . ".csv")){
      return "/YubiKey/texts/" . $language . ".csv";
    }
    return "/YubiKey/texts/de.csv";
  }

  public function getCssPaths() {
    $paths = parent::getCssPaths();
    $config = YubiKey_Config::getInstance();
    $data = $config->getData();
    if ($data["yubikey"]["local"]["showicon"] == 1) {
      $paths[] = "/plugins/YubiKey/static/css/login.css";
    }
    return $paths;
  }

}