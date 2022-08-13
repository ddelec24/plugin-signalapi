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

  /*public static function dependancy_install() {
  	log::add('signal', 'debug', 'install');
  }
  
  public static function dependancy_end() {
    log::add('signal', 'debug', 'dependancy end');
    $docker = self::byLogicalId('1::signal', 'docker2'); // vérif si le docker signal existe ou non
    if (is_object($docker))
      return;
	
    log::add('signal', 'debug', 'dependancy end2');
    self::installSignalDocker();
  }

 	public static function dependancy_info() {
     $return = array();
     $return['state'] = 'nok';
     return $return;
    }*/
  
  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */

  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
		// Commande d'historisation des messages reçus (format brut json)
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
		$cmd->setOrder(2);
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
		$cmd->setOrder(3);
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