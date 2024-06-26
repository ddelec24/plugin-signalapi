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
      
      	// on peut pas démarrer le démon tant qu'on a pas un numéro enregistré dans la configuration et que la réception n'est pas active
      	$allEq = eqLogic::byType('signal', true);
      	$jsonrpcState = config::byKey('jsonrpc', __CLASS__);
      	$listenNumber = config::byKey('listenNumber', __CLASS__);
      	if(empty($listenNumber) || !$jsonrpcState || count($allEq) < 1) {
      		$return['launchable'] = 'nok';
          	return $return;
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
      	
      	$docker = eqLogic::byLogicalId('1::signal', 'docker2');
      	$statusDocker = $docker->getCmd(null, 'state');
      	log::add('signal', 'debug', 'state courant: ' . $statusDocker->execCmd());
      	if($statusDocker->execCmd() != "running") {
          $docker->create();
          sleep(5); 
        }

      	$listenNumber = config::byKey('listenNumber', __CLASS__);
      
		$cmd = system::getCmdSudo() . ' /usr/bin/node ' . $signal_path . '/signald.js';
		$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
		$cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__);
		$cmd .= ' --signal_server 127.0.0.1:' . config::byKey('port', __CLASS__) . '/v1/receive/' . $listenNumber;
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

    /*
    * Fonction exécutée automatiquement tous les jours par Jeedom */
    public static function cronDaily() { //cron5 for tests
      	$jsonrpcState = config::byKey('jsonrpc', __CLASS__);
      	$listenNumber = config::byKey('listenNumber', __CLASS__);

      	$eqLogics = eqLogic::byType('signal'); // on récup les numéros enregistrés
		foreach($eqLogics as $eqLogic) {
          $type = $eqLogic->getConfiguration("type");
          $currentnumber = $eqLogic->getConfiguration(null, 'numero');
          if($type != 'groups' && $eqLogic->getIsEnable()) {
            // Il faut impérativement avoir une réception tous les 35 jours maximum sinon l'autorisation API expire.
            // Réception journalière des équipements actifs qui ne sont pas en réception automatique (mode RPC)
            if($listenNumber != $currentnumber['numero'] || $jsonrpcState != 1)
          		$eqLogic->normalReceive();
          }
        }
    }
    
  public static function backupExclude() {
  	return ["data/signal-cli-config/attachments"];  
  }
  
	// Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
	public function postSave() {
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
		$info->setConfiguration("historyPurge", "-3 month");
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
		$info->setConfiguration("historyPurge", "-3 month");
		$info->save();
      	
		$info = $this->getCmd(null, 'sourceName');
		if (!is_object($info)) {
			$info = new signalCmd();
			$info->setName(__("Nom de l expéditeur", __FILE__));
		}
		$info->setOrder(3);
		$info->setLogicalId('sourceName');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setIsVisible(1);
		$info->setIsHistorized(1);
		$info->setDisplay('forceReturnLineAfter', true);
		$info->setConfiguration("historyPurge", "-3 month");
		$info->save();
      	
		$info = $this->getCmd(null, 'SourceNumber');
		if (!is_object($info)) {
			$info = new signalCmd();
			$info->setName(__("Numéro de l expéditeur", __FILE__));
		}
		$info->setOrder(4);
		$info->setLogicalId('SourceNumber');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setIsVisible(1);
		$info->setIsHistorized(1);
		$info->setDisplay('forceReturnLineAfter', true);
		$info->setConfiguration("historyPurge", "-3 month");
		$info->save();
      
		// envoi message
		$cmd = $this->getCmd(null, 'sendMessage');
		if (!is_object($cmd)) {
			$cmd = new signalCmd();
			$cmd->setName(__('Envoi message', __FILE__));
		}
		$cmd->setOrder(5);
		$cmd->setLogicalId('sendMessage');
		$cmd->setConfiguration('type', 'send');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setType('action');
		$cmd->setTemplate('dashboard', 'tile'); //template pour le dashboard
		$cmd->setDisplay('title_disable', 1);
		$cmd->setDisplay('message_disable', 0);
		$cmd->setDisplay('message_placeholder', 'Message');
		$cmd->setSubType('message');
		$cmd->setIsVisible(1);
		$cmd->setDisplay('forceReturnLineBefore', true);
		$cmd->save();
		
		// envoi message avec fichier
		$cmd = $this->getCmd(null, 'sendFile');
		if (!is_object($cmd)) {
			$cmd = new signalCmd();
			$cmd->setName(__('Envoi de fichier', __FILE__));
		}
		$cmd->setOrder(6);
		$cmd->setLogicalId('sendFile');
		$cmd->setConfiguration('type', 'sendWithAttachements');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setType('action');
		$cmd->setSubType('message');
		$cmd->setIsVisible(0);
		$cmd->setDisplay('forceReturnLineBefore', true);
		$cmd->save();
      
      	$this->syncContacts();
      
      	$jsonrpcState = config::byKey('jsonrpc', __CLASS__);
      		
      	if($jsonrpcState != 1 && $this->getConfiguration("type") != "groups")
      		$this->normalReceive();
      
	}

	// Fonction exécutée automatiquement avant la suppression de l'équipement
	public function preRemove() {
      	$number = $this->getConfiguration('numero');
      	$plugin = plugin::byId('signal');
      	if(config::byKey('jsonrpc', __CLASS__)) {
         	log::add('signal', 'warning', 'Impossible de supprimer le numéro car la réception en temps réelle est active, merci de le désactiver dans la configuration du plugin pour pouvoir supprimer cet équipement');
          	throw new Exception(__('Impossible de supprimer le numéro car la réception en temps réelle est active, merci de le désactiver dans la configuration du plugin pour pouvoir supprimer cet équipement.', __FILE__));
          	return false;
        }
      
      	if(!empty($number)) {
          self::removeLocalDevice();
          // Delete associated groups
          $type = $this->getConfiguration("type");
          if($type != 'groups') {
              $eqLogicsGroups = eqLogic::byTypeAndSearchConfiguration($plugin->getId(), ["associatedNumber" => $number]);
              if(count($eqLogicsGroups) > 0) {
                foreach($eqLogicsGroups as $eqLogicGroups) {
                  	  log::add('signal', 'info', 'Suppression du groupe associé : ' . $eqLogicGroups->getName());
                      $eqLogicGroups->remove();
                }
              }
          }
        }
	}

	// Fonction exécutée automatiquement après la suppression de l'équipement
	public function postRemove() {
      // si on a plus aucun équipement, il faut absolument repasser en mode native et plus jsonrpc.
      $allEq = eqLogic::byType('signal');
      if(count($allEq) == 0) {
      	config::save('jsonrpc', 0, 'signal');
        log::add('signal', 'info', 'Vous venez de supprimer le dernier numéro, merci de relancer le service dans la page de configuration du plugin si vous aviez activé la reception des messages.');
        message::add('signal', 'Vous venez de supprimer le dernier numéro, merci de relancer le service dans la page de configuration du plugin si vous aviez activé la reception des messages.');
      }
	}

	/*     * **********************Getteur Setteur*************************** */

	public function send($options) {
		log::add('signal', 'debug', "[send Options] " . json_encode($options));
		$port = config::byKey('port', 'signal');
		$message = trim($options['message']);
      	// nettoyage des caractères qui passent mal
		$message = str_replace('"', '\"', $message);
		$message = str_replace("'", "’", $message);
		$message = preg_replace("/\r\n|\r|\n/", '\\r\\n', $message);
		
		
		$sender = trim($this->getConfiguration("numero"));
		$recipient = isset($options['number']) ? trim($options['number']) : $sender;
      
		$curl = 'curl -X POST -H "Content-Type: application/json" \'http://localhost:' . 
				$port . '/v2/send\' -d \'{"message": "' .
				$message . '", "number": "' . $sender . '", "recipients": [ "' . $recipient . '" ]}\'';
		
		log::add('signal', 'debug', '[ENVOI MESSAGE] Requête:<br/>' . $curl);
		$send = shell_exec($curl);
		log::add('signal', 'debug', '[RETOUR MESSAGE] ' . $send);

	}

  	public function removeLocalDevice() {
		$port = config::byKey('port', 'signal');
		$sender = trim($this->getConfiguration("numero"));

		$curl = 'curl -X POST -H "Content-Type: application/json" \'http://localhost:' . 
				$port . '/v1/unregister/' . $sender . '\' -d \'{"delete_account": false, "delete_local_data": true}\''; // ATTENTION SURTOUT PAS delete_account à true, ça supprime des serveurs signal aussi
		
		log::add('signal', 'debug', '[REMOVE NUMBER] Requête:<br/>' . $curl);
		$send = shell_exec($curl);
	}
	
  	public function syncContacts() {
		$port = config::byKey('port', 'signal');
		$sender = trim($this->getConfiguration("numero"));

		$curl = 'curl -X GET -H "Content-Type: application/json" \'http://localhost:' . 
				$port . '/v1/identities/' . $sender . '\''; // récupère les contacts signal
		log::add('signal', 'debug', '[GET CONTACTS] Requête:<br/>' . $curl);
		$contacts = shell_exec($curl);
      	if(strlen($contacts) > 5) { // on a reçu quelquechose
          $arrContacts = json_decode($contacts);
          
          if(isset($arrContacts) && is_array($arrContacts)) {
            $savedContactList = $this->getConfiguration("contactsList");
            if(empty($savedContactList))
              $savedContactList = array();

            foreach($arrContacts as $contact) {
              if($contact->number == $sender || empty($contact->number)) // évite dajouter son propre numéro ou un vide
                continue;
              
              if(in_array($contact->number, array_column($savedContactList, "number"))) // on l'a déjà synchro
                continue;
              
              $newContact = ["number" => $contact->number, "name" => "", "display" => false];
              log::add('signal', 'debug', '[GET CONTACTS] Sauvegarde contact: ' . json_encode($newContact));
              $savedContactList[] = $newContact;
            }
            //log::add('signal', 'debug', '[GET CONTACTS] Liste actuelle: ' . gettype($savedContactList) . "/" . json_encode($savedContactList));
            
            $this->setConfiguration('contactsList', $savedContactList);
            $this->save(true); // force enregistrement car on est dans le postSave()
          }
        }
	}
	
    public function normalReceive() {

		$port = config::byKey('port', 'signal');
		$receive = trim($this->getConfiguration("numero"));

      	$curl = 'curl -X GET -H "Content-Type: application/json" \'http://localhost:' . 
          		$port . '/v1/receive/' . $receive . '\'';

		
		log::add('signal', 'debug', '[RECEIVE] Requête: ' . $curl);
		$curlret = shell_exec($curl);
		log::add('signal', 'debug', '[RETOUR RECEIVE] ' . $curlret);
      
      	if(strlen($curlret) > 5) { // on a reçu quelquechose depuis la dernière fois
          $arrayMsg = json_decode($curlret);

          if(isset($arrayMsg) && is_array($arrayMsg)) {
              $nbNewMsgs = 		sizeof($arrayMsg);
              $received = 		$arrayMsg[$nbNewMsgs - 1]; // get last message
              
              $sourceNumber = 	$received->envelope->sourceNumber;
              $sourceName = 	$received->envelope->sourceName;
              $timestamp = 		$received->envelope->timestamp;
              // suivant is on envoi à nous meme ou non c'est pas la même key dans le json
              $msg = 				(!empty($received->envelope->dataMessage->message)) ? $received->envelope->dataMessage->message : $received->envelope->syncMessage->sentMessage->message; 
              $recipientNumber = 	$received->account;
              $isGroupMessage = 	(!empty($received->envelope->syncMessage->sentMessage->groupInfo) || !empty($received->envelope->dataMessage->groupInfo)) ? true : false;

              if(!is_object($received->exception) && $msg != "" && !$isGroupMessage) {
               	  $eqLogics = eqLogic::byType('signal');
                  foreach($eqLogics as $eqLogic) {
                      $eqNumero = $eqLogic->getConfiguration(null, 'numero');
                      if($eqNumero['numero'] == $recipientNumber && $msg !== "") { // si présence message et qu'on est sur le numéro destinataire on historique le message
                          log::add('signal', 'debug', '[RECEIVE] Message entrant, numéro '  . $eqNumero['numero'] . " : " . nl2br($msg));
                          $cmd = $eqLogic->getCmd(null, 'received');
                          $cmdRaw = $eqLogic->getCmd(null, 'receivedRaw');
                          $cmdSourceName = $eqLogic->getCmd(null, 'sourceName');
                          $cmdSourceNumber = $eqLogic->getCmd(null, 'sourceNumber');
                          $oldReceivedMsg = $cmd->execCmd();

                          if($oldReceivedMsg == $msg ) { // on force un event intemédiaire pour prise en compte d'un message identique
                              $cmd->event(" ", null);
                              $cmdRaw->event(" ", null);
                              $cmdSourceName->event(" ", null);
                              $cmdSourceNumber->event(" ", null);
                          }
                          $msg = htmlentities($msg);
                          $msg = preg_replace("/\\r\\n/u", "\n", $msg);
                          if(strlen($msg) > 115) {
                            $msg = substr($msg, 0, 115) . "...";
                          }
                          $cmd->event($msg, null);
                          $cmdSourceNumber->event($sourceNumber, null);

                          if($sourceName != "")
                              $cmdSourceName->event($sourceName, null);

                          // les commandes sont limitées à 255chars dans la BDD, impossible de stocker le json entier donc je prends que quelques éléments
                          $moreDatas = Array('sourceNumber' => $sourceNumber, 'sourceName' => $sourceName, 'timestamp' => $timestamp);
                          $cmdRaw->event(json_encode($moreDatas), null);
                          break;
                      }
                  } // fin foreach
              } // msg non vide
          } // fin isset 
        }
    }
  
	public function sendFile($options) {
      	/*
        //
        // A PARTIR DE l'API 0.65, possibilité de customiser le nom du filename, code:
        // https://github.com/bbernhard/signal-cli-rest-api/blob/master/src/api/api.go#L99
        //
        // api.SendMessageV2{
		//	base64_attachments	[
				example: List [ "<BASE64 ENCODED DATA>", "data:<MIME-TYPE>;base64<comma><BASE64 ENCODED DATA>", "data:<MIME-TYPE>;filename=<FILENAME>;base64<comma><BASE64 ENCODED DATA>" ]
        //  ]
        
        */
		log::add('signal', 'debug', "[sendFile Options] " . json_encode($options));
		$port = config::byKey('port', 'signal');
            
      	if(!(isset($options['file'])) && !(isset($options['files']))) {
			$file = "";
        } else {
          if(isset($options['file'])) {
          	if($options['file'] == "") {
              $file = 'error';
            } else {
              $file = $options['file'];
            }
          } elseif(isset($options['files'])) {
          	if(sizeof($options['files']) == 0) {
              $file = 'error';
            } else {
                $file = $options['files'][0]; // 1 seule pièce jointe, à voir si possibilité de multiples fichiers dans le futur
            }
          } else {
            $file = 'error';
          }
        }

        $attachement = $file;
      	log::add('signal', 'debug', 'file: '. $attachement);
        $message = trim($options['message']);
      
      	if($attachement == 'error') { // quand on passe une commande et qu'elle est en erreur
			log::add('signal', 'warning', "Erreur sur la commande utilisée pour envoyer un fichier.");
          	return;
        }
      
      
		$tmpFolder = jeedom::getTmpFolder('signal');
      	// si c'est une url il faut pas récupérer les éventuels arguments pour le nom du fichier
		$filename = (substr($attachement, 0, 4) == 'http') ? basename(parse_url($attachement)['path']) : basename($attachement); 
      	log::add('signal', 'debug', 'chemin du fichier: '. $filename);
		$contentFile = @file_get_contents($attachement);
      
      	if(!$contentFile || strlen($contentFile) == 0) {
			log::add('signal', 'warning', "Fichier téléchargé vide (" . $attachement . "). Pas d'envoi possible.");
          	return;
        }

      	$writeFile = file_put_contents($tmpFolder . "/" . $filename, $contentFile);
		log::add('signal', 'debug', 'écriture fichier '. $tmpFolder. "/" . $filename . " => " . round($writeFile/1024/1024, 2) . 'Mo');
      	// nettoyage des caractères qui passent mal
      	$cleanedMessage = str_replace("'", "’", $message);
		$cleanedMessage = str_replace('"', '\\\\\\"', $cleanedMessage);
		
      	//log::add('signal', 'debug', "message : " . $cleanedMessage);
		$cleanedMessage = preg_replace("/\r\n|\r|\n/", "\\r\\n", $cleanedMessage);
      	//log::add('signal', 'debug', "message2 : " . $cleanedMessage);

		$sender = trim($this->getConfiguration("numero"));
		$recipient = isset($options['number']) ? trim($options['number']) : $sender;

		$curl = 'B64TEMPFILE="$(' . system::getCmdSudo() . 'base64 ' . $tmpFolder . "/" . $filename .')" ' . //on met le fichier en b64 dans une variable
          		'&& printf \'{"message": "%s", "base64_attachments": ["\'"$B64TEMPFILE"\'"], "number": "' . $sender . '", "recipients": [ "' . $recipient . '" ]}\' "' . $cleanedMessage . '" | ' . // on prépare le json à envoyer à l'api
				'curl -X POST -H "Content-Type: application/json" -d @- \'http://localhost:' . $port . '/v2/send\''; // envoi du pipe à l'api

		log::add('signal', 'debug', '[ENVOI MESSAGE] Requête:<br/>' . $curl);
		$send = shell_exec($curl);
		log::add('signal', 'debug', '[RETOUR MESSAGE] ' . $send);
		@unlink($tmpFolder . "/" . $filename);
	}

}

class signalCmd extends cmd {

	// Exécution d'une commande
    public function execute($_options = array()) {

        if ($this->getType() != 'action')
            return;

        $options = array();

        if (isset($_options['file']))
            $options = arg2array($_options['file']);

        if (isset($options['rtspVideo'])) {
            $save = '/tmp/signal_' . $this->getId() . '.mp4';
            unlink($save);
            $cmd = 'ffmpeg -rtsp_transport tcp -loglevel fatal -i "' . $options['rtspVideo'] . '" -c:v copy -bsf:a aac_adtstoasc -y -t 10 -movflags faststart ' . $save;
            shell_exec($cmd);
            unset($_options['file']);
            $_options['files'][] = $save;
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
            log::add('signal', 'warning', 'Error while executing cmd ' . $this->getLogicalId());
            break;
        }
    }


    public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {
        $data = null;
        if ($_version != 'scenario') 
            return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
        if ($this->getConfiguration('type') == 'sendWithAttachements')
            $data = getTemplate('core', 'scenario', 'cmd.sendWithAttachements', 'signal');
        if ($this->getConfiguration('type') == 'send')
            $data = getTemplate('core', 'scenario', 'cmd.send', 'signal');

        if (!is_null($data)) {
            $eqLogics = eqLogic::byType('signal'); // on récup les numéros enregistrés
            $optionsNumbers = "";
            $optionsGroups = "";
          	$optionsContacts = "";
            foreach($eqLogics as $eqLogic) {
                if($eqLogic->getIsEnable()) { // que les actifs
                    $number = $eqLogic->getConfiguration(null, 'numero');
                    $group = $eqLogic->getConfiguration(null, 'id');
                  	$contacts = $eqLogic->getConfiguration('contactsList');
                  
                    if(!empty($number['numero']))
                        $optionsNumbers .= '<option value="' . $number['numero'] . '">' . $number['numero'] . ' ( ' . $eqLogic->getName() . ' )</option>';
                  
                    if(!empty($group['id']))
                        $optionsGroups .= '<option value="' . $group['id'] . '">' . $eqLogic->getName() . '</option>';
                  
                  	if(!empty($contacts)) {
                      	// on filtre pour avoir que ceux à afficher
                      	$contacts = array_filter($contacts, function($k) {
                            return $k['display'] === true;
                          });
                      	foreach($contacts as $contact) {  	                          
                      		$optionsContacts .= '<option value="' . $contact['number'] .'">' . $contact['number'] . ' ( ' . $contact['name'] . ' )</options>';
                        }
                      }
                  	
                }
            }

            if(empty($optionsNumbers))
                $optionsNumbers = '<option value="">Aucun équipement détecté</option>';

            if(!empty($optionsGroups))
                $optionsNumbers .= '<optgroup label="Groupes">' . $optionsGroups . '</optgroup>';
          
          	if(!empty($optionsContacts))
              	$optionsNumbers .= '<optgroup label="Contacts">' . $optionsContacts . '</optgroup>';

            $data = str_replace("#possibleNumbers#", $optionsNumbers, $data);
            if (version_compare(jeedom::version(),'4.2.0','>=')) {
                if(!is_array($data)) return array('template' => $data, 'isCoreWidget' => false);
            } else return $data;
        }
        return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
    }
	/*     * **********************Getteur Setteur*************************** */

}