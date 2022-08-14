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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class signal extends eqLogic {
  /*     * *************************Attributs****************************** */

  /*     * *********************Méthodes d'instance************************* */

   public static function deamon_info() {
      $return = array();
      $return['log'] = __CLASS__;
      $return['state'] = 'nok';
      $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
      if (file_exists($pid_file)) {
         if (@posix_getsid(trim(file_get_contents($pid_file)))) {
            $return['state'] = 'ok';
         } else {
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
         }
      }
      $return['launchable'] = 'ok';
      return $return;
   }

   public static function deamon_start() {
      log::remove(__CLASS__ . '_update');
      self::deamon_stop();
      $deamon_info = self::deamon_info();
      if ($deamon_info['launchable'] != 'ok') {
         throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
      }

      $signal_path = realpath(dirname(__FILE__) . '/../../resources/demond');
      chdir($signal_path);
     
      $cmd = system::getCmdSudo() . ' /usr/bin/node ' . $signal_path . '/signald.js';
      $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
      $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__);
      $cmd .= ' --signal_server 127.0.0.1:' . config::byKey('port', __CLASS__) . '/v1/receive/+33627238828';
      $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/signal/core/php/jeeSignal.php';
      $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
      $cmd .= ' --cycle ' . config::byKey('cycle', __CLASS__);
      $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
      log::add(__CLASS__, 'info', 'Lancement démon signal : ' . $cmd);
      exec($cmd . ' >> ' . log::getPathToLog('signal') . ' 2>&1 &');
      $i = 0;
      while ($i < 10) {
         $deamon_info = self::deamon_info();
         if ($deamon_info['state'] == 'ok') {
            break;
         }
         sleep(1);
         $i++;
      }
      if ($i >= 30) {
         log::add(__CLASS__, 'error', 'Impossible de lancer le démon signal, vérifiez le log', 'unableStartDeamon');
         return false;
      }
      message::removeAll(__CLASS__, 'unableStartDeamon');
      return true;
   }

   public static function deamon_stop() {
      $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
      if (file_exists($pid_file)) {
         $pid = intval(trim(file_get_contents($pid_file)));
         system::kill($pid);
      }
      system::kill('signald.js');
      system::fuserk(config::byKey('socketport', __CLASS__));
   }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
		// Commande d'historisation des messages reçus (juste le message et brut json)
		$info = $this->getCmd(null, 'received');
		if (!is_object($info)) {
			$info = new signalCmd();
			$info->setName(__('Message reçu', __FILE__));
		}
		$info->setOrder(1);
		$info->setLogicalId('received');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setIsVisible(1);
		$info->setIsHistorized(1);
    	$info->setDisplay('forceReturnLineAfter', true);
    	$info->setConfiguration("historyPurge", "-3 months");
		$info->save();
    
		$info = $this->getCmd(null, 'receivedRaw');
		if (!is_object($info)) {
			$info = new signalCmd();
			$info->setName(__('Message brut reçu', __FILE__));
		}
		$info->setOrder(2);
		$info->setLogicalId('receivedRaw');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setIsVisible(0);
		$info->setIsHistorized(1);
    	$info->setConfiguration("historyPurge", "-3 months");
		$info->save();
    
		// envoi message
		$cmd = $this->getCmd(null, 'sendMessage');
		if (!is_object($cmd)) {
			$cmd = new signalCmd();
			$cmd->setName(__('Envoi message', __FILE__));
		}
		$cmd->setOrder(3);
		$cmd->setLogicalId('sendMessage');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setType('action');
		$cmd->setTemplate('dashboard', 'tile'); //template pour le dashboard
		$cmd->setDisplay('title_disable', 1);
		$cmd->setDisplay('message_disable', 0);
		$cmd->setDisplay('message_placeholder', 'Message à envoyer');
		$cmd->setSubType('message');
		$cmd->setIsVisible(1);
		$cmd->setDisplay('forceReturnLineBefore', true);
		$cmd->save();
    
		// envoi message
		$cmd = $this->getCmd(null, 'sendFile');
		if (!is_object($cmd)) {
			$cmd = new signalCmd();
			$cmd->setName(__('Envoi de fichier', __FILE__));
		}
		$cmd->setOrder(4);
		$cmd->setLogicalId('sendFile');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setType('action');
		//$cmd->setTemplate('dashboard', 'sendFile'); //template pour le dashboard
		$cmd->setSubType('message');
		$cmd->setIsVisible(0);
		$cmd->setDisplay('forceReturnLineBefore', true);
		$cmd->save();
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }
  
  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*     * **********************Getteur Setteur*************************** */

  public function send($options) {
    	//$dockerServ = $_SERVER['SERVER_NAME'] . ":" . config::byKey('port', 'signal');
    	$port = config::byKey('port', 'signal');
    	$message = trim($options['message']);
    	$expediteur = "+33627238828";
    	$destinataires = '"+33627238828"';
    	$curl = 'curl -X POST -H "Content-Type: application/json" \'http://localhost:' . 
          		$port . '/v2/send\' -d \'{"message": "' .
          		$message . '", "number": "' . $expediteur . '", "recipients": [ ' . $destinataires . ' ]}\'';
    
		log::add('signal', 'debug', '[ENVOI MESSAGE] Requête:<br/>' . $curl);
    	$send = shell_exec($curl);
    	log::add('signal', 'debug', '[RETOUR MESSAGE] ' . $send);

  }
  
  public function sendFile($options) {
    if (isset($_options['files']) && is_array($_options['files'])) {
      log::add('signal', 'debug', 'Envoi de fichier demandé');
        /*
    TMPFILE="$(base64 image_9.jpg)" curl -X POST -H "Content-Type: application/json" -d '{"message": "Test image", "base64_attachments": ["'"${TMPFILE}"'"], "number": "+431212131491291", "
    recipients": ["+4354546464654"]}' 'http://127.0.0.1:8080/v2/send'
  */
      
      /*
	TMPFILE="$(base64 video.mp4)" echo '{"message": "Test video", "base64_attachments": ["'"$TMPFILE"'"], "number": "+431212131491291", "recipients": ["+4354546464654"]}' | curl -X POST -H 
    "Content-Type: application/json" -d @- 'http://127.0.0.1:8080/v2/send'
 */
    } else {
      log::add('signal', 'debug', 'Pas de fichier trouvé');
    }
  }
   // @TODO QUAND PJ => split SI > 4Mb   ou non

}

class signalCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {

        if ($this->getType() != 'action') {
           return;
        }
    
    	$eqLogic = $this->getEqLogic(); // Récupération de l’eqlogic

		switch ($this->getLogicalId()) {
			case 'sendMessage':
			$eqLogic->send($_options);
			break;
			case 'sendFile':
			$eqLogic->sendFile($_options);
			break;
			default:
			throw new Error('This should not append!');
			log::add('signal', 'warn', 'Error while executing cmd ' . $this->getLogicalId());
			break;
		}
    

    
/* réception msg websocket */
    /*
{
    "envelope":
    {
        "source": "+33627238828",
        "sourceNumber": "+33627238828",
        "sourceUuid": "6be5b6a8-5ff1-4822-b557-8b318e229b0a",
        "sourceName": "Damien D.",
        "sourceDevice": 1,
        "timestamp": 1660228216341,
        "syncMessage":
        {
            "sentMessage":
            {
                "destination": "+33627238828",
                "destinationNumber": "+33627238828",
                "destinationUuid": "6be5b6a8-5ff1-4822-b557-8b318e229b0a",
                "timestamp": 1660228216341,
                "message": "Réception Message ok",
                "expiresInSeconds": 0,
                "viewOnce": false
            }
        }
    },
    "account": "+33627238828",
    "subscription": 0
}
    */
    
  }

  /*     * **********************Getteur Setteur*************************** */

}