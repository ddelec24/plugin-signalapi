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
/*
if (!class_exists('signal')) {
  include_file('core', 'signal', 'class', 'signal');
}*/

$eqLogics = eqLogic::byType('signal');

$result = json_decode(file_get_contents("php://input"));
log::add('signal', 'debug', "reçu par jeeSignal: " . json_encode($result->received));

$sourceNumber = $result->received->envelope->sourceNumber;
$name = $result->received->envelope->sourceName;
$msg = $result->received->envelope->syncMessage->sentMessage->message;
$recipientNumber = $result->received->envelope->syncMessage->sentMessage->destinationNumber;

foreach($eqLogics as $eqLogic) {
  	$eqNumero = $eqLogic->getConfiguration(null, 'numero');
  	//log::add('signal', 'debug', "comparaison numero $recipientNumber et " . $eqNumero['numero']);
	if($eqNumero['numero'] == $recipientNumber) { // si on est sur le numéro destinataire on historique le message
    	$eqLogic->checkAndUpdateCmd("received", $msg);
      	$eqLogic->checkAndUpdateCmd("receivedRaw", json_encode($result->received));
      	break;
    }
}

/*
{
    "envelope":
    {
        "source": "+33600000000",
        "sourceNumber": "+33600000000",
        "sourceUuid": "aaaaaaaa-ffff-aaaa-1111-1111222233334444",
        "sourceName": "Damien",
        "sourceDevice": 1,
        "timestamp": 1660480697289,
        "syncMessage":
        {
            "sentMessage":
            {
                "destination": "+33600000000",
                "destinationNumber": "+33600000000",
                "destinationUuid": "aaaaaaaa-ffff-aaaa-1111-1111222233334444",
                "timestamp": 1660480697289,
                "message": "Message test",
                "expiresInSeconds": 0,
                "viewOnce": false
            }
        }
    },
    "account": "+33600000000",
    "subscription": 0
}

*/

?>