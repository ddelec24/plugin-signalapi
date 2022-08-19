<?php

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";


if (!jeedom::apiAccess(init('apikey'), 'signal')) {
	echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
	die();
}
if (isset($_GET['test'])) {
	echo 'OK';
	die();
}

$eqLogics = eqLogic::byType('signal');

$result = json_decode(file_get_contents("php://input"));

$received = $result->received;
// on log en debug que les infos de messages reçus
if(isset($received) && is_object($received)) {

	//log::add('signal', 'debug', "reçu par jeeSignal: " . json_encode($received));

	$sourceNumber = 	$received->envelope->sourceNumber;
	$name = 			$received->envelope->sourceName;
	$msg = 				$received->envelope->syncMessage->sentMessage->message;
	$recipientNumber = 	$received->envelope->syncMessage->sentMessage->destinationNumber;
	//log::add('signal', 'debug', "s=" .$sourceNumber ."/n=" . $name . "/m=" .$msg . "/r=" . $recipientNumber);
	foreach($eqLogics as $eqLogic) {
		$eqNumero = $eqLogic->getConfiguration(null, 'numero');
		if($eqNumero['numero'] == $recipientNumber && $msg !== "") { // si présence message et qu'on est sur le numéro destinataire on historique le message
			$cmd = $eqLogic->getCmd(null, 'received');
			$cmdRaw = $eqLogic->getCmd(null, 'receivedRaw');
			$oldReceivedMsg = $cmd->execCmd();
			if($oldReceivedMsg == $msg ) { // on force un event intemédiaire pour prise en compte d'un message identique
				$cmd->event(" ", null);
				$cmdRaw->event(" ", null);
          		log::add('signal', 'debug', "event vide");

				//$eqLogic->checkAndUpdateCmd("received", $msg);
				//$eqLogic->checkAndUpdateCmd("receivedRaw", json_encode($result->received));
			}
          	$cmd->event($msg, null);
          	$preparedRawMsg = html_entity_decode(json_encode($received));
          	$cmdRaw->event((!is_null($preparedRawMsg)? $preparedRawMsg : "error"), null);
          	log::add('signal', 'debug', "break");
			//break;
		}
	} // fin foreach
} // fin isset