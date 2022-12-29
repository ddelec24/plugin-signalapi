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

if(isset($received) && is_object($received)) {

	
	//log::add('signal', 'debug', "dataMessage: " . $received->envelope->dataMessage->message);

	$sourceNumber = 	$received->envelope->sourceNumber;
	$sourceName = 		$received->envelope->sourceName;
	$timestamp = 		$received->envelope->timestamp;
	// suivant is on envoi à nous meme ou non c'est pas la même key dans le json
	$msg = 				(!empty($received->envelope->dataMessage->message)) ? $received->envelope->dataMessage->message : $received->envelope->syncMessage->sentMessage->message; 
	$recipientNumber = 	$received->account;
  	$isGroupMessage = (!empty($received->envelope->syncMessage->sentMessage->groupInfo) || !empty($received->envelope->dataMessage->groupInfo)) ? true : false;
	log::add('signal', 'debug', 'Message de groupe => ' . (($isGroupMessage) ? "OUI" : "NON"));
  
	if(!is_object($received->exception) && $msg != "" && !$isGroupMessage) {
		foreach($eqLogics as $eqLogic) {
			$eqNumero = $eqLogic->getConfiguration(null, 'numero');
			if($eqNumero['numero'] == $recipientNumber && $msg !== "") { // si présence message et qu'on est sur le numéro destinataire on historique le message
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

				$cmd->event(htmlentities($msg), null);
				$cmdSourceNumber->event($sourceNumber, null);
              
				if($sourceName != "")
					$cmdSourceName->event($sourceName, null);

				// les commandes sont limitées à 255chars dans la BDD, impossible de stocker le json entier, on prend le plus important pour le raw
				$moreDatas = Array('sourceNumber' => $sourceNumber, 'sourceName' => $sourceName, 'timestamp' => $timestamp, "message" => $msg);
				$cmdRaw->event(json_encode($moreDatas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), null);
				break;
			}
		} // fin foreach
	} // msg non vide
} // fin isset