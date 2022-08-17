<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

  /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
    En V3 : indiquer l'argument 'true' pour contrôler le token d'accès Jeedom
    En V4 : autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
  */
    ajax::init();

	if (init('action') == 'installSignalDocker') { 
		ajax::success(installSignalDocker());
	}


    throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
    /*     * *********Catch exeption*************** */
}
catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}


// install Docker Management
function installDocker2() {
  log::add('signal', 'debug', 'Verification de la présence de docker2');
  try {
    $plugin = plugin::byId('docker2');
    if (!$plugin->isActive()) {
      $plugin->setIsEnable(1);
      $plugin->dependancy_install();
    }
  } catch (Exception $e) {
    event::add('jeedom::alert', array(
      'level' => 'warning',
      'page' => 'plugin',
      'message' => __('Installation du plugin Docker Management', __FILE__),
    ));
    $update = update::byLogicalId('docker2');
    if (!is_object($update)) {
      $update = new update();
    }
    $update->setLogicalId('docker2');
    $update->setSource('market');
    $update->setConfiguration('version', 'beta');
    $update->save();
    $update->doUpdate();
    $plugin = plugin::byId('docker2');

    if (!is_object($plugin)) {
      throw new Exception(__('Le plugin Docker management doit être installé', __FILE__));
    }
    if (!$plugin->isActive()) {
      $plugin->setIsEnable(1);
      $plugin->dependancy_install();
    }
    if (!$plugin->isActive()) {
      throw new Exception(__('Le plugin Docker management doit être actif', __FILE__));
    }
    event::add('jeedom::alert', array(
      'level' => 'warning',
      'page' => 'plugin',
      'ttl' => 250000,
      'message' => __('Pause de 120s le temps de l\'installation des dépendances du plugin Docker Management', __FILE__),
    ));
    $i = 0;
    while (system::installPackageInProgress('docker2')) {
      sleep(5);
      $i++;
      if ($i > 50) {
        throw new Exception(__('Delai maximum autorisé pour l\'installation des dépendances dépassé', __FILE__));
      }
    }
  }
}

// création du container Docker
function installSignalDocker() {
  installDocker2();

  if (!class_exists('docker2')) {
    include_file('core', 'docker2', 'class', 'docker2');
  }

  $uid = shell_exec('sudo id -u www-data');
  $guid = shell_exec('sudo id -g www-data');

  // redonne le chmod executable à l'entrypoint
  $addX = shell_exec(system::getCmdSudo() . " chmod +x " . realpath(__DIR__ . '/../../') . "/data/entrypoint.sh");
  
  $compose = file_get_contents(realpath(__DIR__ . '/../../') . '/resources/docker-compose.yaml');
  $compose = str_replace('#jeedom_path#', realpath(__DIR__ . '/../../'), $compose);
  
  $compose = str_replace('#userid#', trim($uid), $compose);
  $compose = str_replace('#groupid#', trim($guid), $compose);
    
  $port = trim(config::byKey('port', 'signal'));
  $port = '      - ' . (($port == "") ? 8099 : $port) . ":8080\n";
  $compose = str_replace('#ports#', $port, $compose);


  $mode = trim(config::byKey('jsonrpc', 'signal'));

  $compose = str_replace('#mode#', (($mode == 1) ? "json-rpc" : "normal"), $compose);

  $docker = eqLogic::byLogicalId('1::signal', 'docker2');
  if (!is_object($docker)) {
      $docker = new docker2();
    }
  $docker->setLogicalId('1::signal');
  $docker->setName('signal');
  $docker->setIsEnable(1);
  $docker->setEqType_name('docker2');
  $docker->setConfiguration('name', 'signal');
  $docker->setConfiguration('docker_number', 1);
  $docker->setConfiguration('create::mode', 'jeedom_compose');
  $docker->setConfiguration('create::compose', $compose);
  $docker->save();
  try {
    $docker->rm();
    sleep(5);
  } catch (\Throwable $th) {
  }

  $docker->create(); // on a toutes les infos, on démarre le container


}