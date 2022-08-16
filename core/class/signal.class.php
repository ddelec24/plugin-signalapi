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
	public static function templateWidget() {

		$return['send'] =    array(
			'template' => 'cmd.send',
			'replace' => array("#_desktop_width_#" => "100",
								"#_mobile_width_#" => "50",
								"#title_disable#" => "1",
								"#message_disable#" => "0",
								"#currentNumbers#" => "COCOOOO")
		);

		$return['sendWithAttachements'] =    array(
			'template' => 'cmd.sendWithAttachements',
			'replace' => array("#_desktop_width_#" => "100",
								"#_mobile_width_#" => "50",
								"#title_disable#" => "1",
								"#message_disable#" => "0",
								"#currentNumbers#" => "TITIIIIII")
		);

		return $return;
	}
	
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
		$cmd->setOrder(4);
		$cmd->setLogicalId('sendFile');
		$cmd->setConfiguration('type', 'sendWithAttachements');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setType('action');
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
		log::add('signal', 'debug', "[send Options] " . json_encode($options));
		$port = config::byKey('port', 'signal');
		$destinataire = config::byKey('port', 'signal');
		$message = trim($options['message']);
		$sender = trim($this->getConfiguration("numero"));
		$recipient = isset($options['number']) ? trim($options['number']) : $sender;
      
		$curl = 'curl -X POST -H "Content-Type: application/json" \'http://localhost:' . 
				$port . '/v2/send\' -d \'{"message": "' .
				$message . '", "number": "' . $sender . '", "recipients": [ "' . $recipient . '" ]}\'';
		
		log::add('signal', 'debug', '[ENVOI MESSAGE] Requête:<br/>' . $curl);
		$send = shell_exec($curl);
		log::add('signal', 'debug', '[RETOUR MESSAGE] ' . $send);

	}
	
	public function sendFile($options) {
		log::add('signal', 'debug', "[sendFile Options] " . json_encode($options));
		$port = config::byKey('port', 'signal');
		
		//file = passage par scenario/lien
		if ((isset($options['file'])) && ($options['file'] == ""))
			$options['file'] = 'error';
		if (!(isset($options['file'])))
			$options['file'] = "";

		// files = passage par scenario/commande
		if (isset($options['files']) && is_array($options['files'])) {
			foreach ($options['files'] as $file) { // @TODO améliorer la gestion multi files
				if (version_compare(phpversion(), '5.5.0', '>=')) {
					$attachement = $file;
					$files = new CurlFile($file);
					$nameexplode = explode('.',$files->getFilename());
					log::add('signal', 'debug', $options['title'].' taille : '.$nameexplode[sizeof($nameexplode)-1]);
					$message = (isset($options['message']) ? $options['message'].'.'.$nameexplode[sizeof($nameexplode)-1] : $files->getFilename());
				}
			}
			$message = $options['message'];

		} else {
			$attachement = $options['file'];
			$message = $options['message'];
		}
		
		$tmpFolder = jeedom::getTmpFolder('signal');
		$filename = basename($attachement);
		$contentFile = @file_get_contents($attachement);
		$writeFile = file_put_contents($tmpFolder . "/" . $filename, $contentFile);
		log::add('signal', 'debug', 'écriture fichier '. $tmpFolder. "/" . $filename . " => " . round($writeFile/1024/1024, 2) . 'Mo');
		$cleanedMessage = str_replace('"', '\"', $message);
		$cleanedMessage = str_replace("'", "’", $cleanedMessage);
		//$cleanedMessage = addslashes($message);


		$sender = trim($this->getConfiguration("numero"));
		$recipient = isset($options['number']) ? trim($options['number']) : $sender;

		$curl = 'B64TEMPFILE="$(' . system::getCmdSudo() . ' base64 ' . $tmpFolder . "/" . $filename .')" ' . //on met le fichier en b64 dans une variable
				'&& echo \'{"message": "' . $cleanedMessage . '", "base64_attachments": ["\'"$B64TEMPFILE"\'"], "number": "' . $sender . '", "recipients": [ "' . $recipient . '" ]}\' | ' . // on prépare le json à envoyer à l'api
				'curl -X POST -H "Content-Type: application/json" -d @- \'http://localhost:' . $port . '/v2/send\''; // envoi du pipe à l'api

		log::add('signal', 'debug', '[ENVOI MESSAGE] Requête:<br/>' . $curl);
		$send = shell_exec($curl);
		log::add('signal', 'debug', '[RETOUR MESSAGE] ' . $send);
		@unlink($tmpFolder . "/" . $filename);
	}

	/*
		TMPFILE="$(base64 image_9.jpg)" curl -X POST -H "Content-Type: application/json" -d '{"message": "Test image", "base64_attachments": ["'"${TMPFILE}"'"], "number": "+431212131491291", "
		recipients": ["+4354546464654"]}' 'http://127.0.0.1:8080/v2/send'
	*/

	/*
	TMPFILE="$(base64 video.mp4)" echo '{"message": "Test video", "base64_attachments": ["'"$TMPFILE"'"], "number": "+431212131491291", "recipients": ["+4354546464654"]}' | curl -X POST -H 
		"Content-Type: application/json" -d @- 'http://127.0.0.1:8080/v2/send'
 	*/

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
						//$request = scenarioExpression::setTags('
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
			foreach($eqLogics as $eqLogic) {
				if($eqLogic->getIsEnable()) { // que les actifs
					$number = $eqLogic->getConfiguration(null, 'numero');
					$optionsNumbers .= '<option value="' . $number['numero'] . '">' . $number['numero'] . ' ( ' . $eqLogic->getName() . ' )</option>';
				}
			}
			if(empty($optionsNumbers))
				$optionsNumbers = '<option value="">Aucun équipement détecté</option>';
			
			$data = str_replace("#possibleNumbers#", $optionsNumbers, $data);
			if (version_compare(jeedom::version(),'4.2.0','>=')) {
				if(!is_array($data)) return array('template' => $data, 'isCoreWidget' => false);
			} else return $data;
		}
		return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
	}
	/*     * **********************Getteur Setteur*************************** */

}