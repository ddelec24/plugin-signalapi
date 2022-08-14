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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}

// Vérifie si le container a déjà enregistré un numéro et donc autoriser le passage en json-rpc
$fileAccounts = realpath(__DIR__ . '/../')  . '/data/signal-cli-config/data/accounts.json';
$displayJsonRpc = false;
//if(trim(shell_exec( 'sudo [ -f "' . $fileAccounts . '" ] && echo "ok"' )) == "ok") // @TODO si stabilité ok avec nouvel entrypoint, faire un php file_exists directement
if(file_exists($fileAccounts))
  $displayJsonRpc = true;

// Vérifie si docker signal actif
$dockerContainer = eqLogic::byLogicalId('1::signal', 'docker2');
$colorCheck = "red";
if(is_object($dockerContainer)) {
  $info = $dockerContainer->getCmd(null, 'state');
  //log::add('signal', 'debug', "Etat du container docker signal: " . $info->execCmd());
  if($info->execCmd() == "running")
    $colorCheck = "green";
}

?>

  <form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Port socket interne}}
		<sup><i class="fas fa-question-circle tooltips" title="{{Ne changez que si vous avez des difficultés (port déjà utilisé, accès refusé...), 55099 par défaut}}"></i></sup>
  	  </label>
      <div class="col-md-4">
      	<input class="configKey form-control" data-l1key="socketport">
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Cycle (s)}}
	  	<sup><i class="fas fa-question-circle tooltips" title="{{0.3 par défaut. Ne touchez que si nécessaire.}}"></i></sup>
	  </label>
      <div class="col-md-4">
      	<input class="configKey form-control" data-l1key="cycle">
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Port utilisé pour le docker}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Ne changez que si vous avez des difficultés (port déjà utilisé, accès refusé...), 8099 par défaut}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="port" type="number" step="1" min="1024" max="65535" />
      </div>
    </div>

    <?php

    if($displayJsonRpc) {
    echo '<div class="form-group">';
      echo '<label class="col-md-4 control-label">{{Réception des messages}}';
		echo '<sup><i class="fas fa-question-circle tooltips" title="{{Permettre la réception en temps réel des messages (consomme plus de ressources).<br /> Après changement, sauvegardez la configuration et appuyez de nouveau sur Installation/Réinstallation du service.}}"></i></sup>';
  	  echo '</label>';
      echo '<div class="col-md-7">';
        echo '<input type="checkbox" class="configKey form-control" data-l1key="jsonrpc" />';
      echo '</div>';
    echo '</div>';
	}
	?>

	
    <div class="form-group">
      <label class="col-md-4 control-label">{{Etat du service API}}
		<sup><i class="fas fa-question-circle tooltips" title="{{Grâce au plugin Docker Management de jeedom, l'api Signal est accessible dans un container docker}}"></i></sup>
  	  </label>
      <div class="col-md-7">
        <i class="fas fa-lg fa-check-circle" style=" color: <?=$colorCheck?>"></i> <br /><br /> <a class="btn btn-warning" id="btnInstallSignalDocker">{{Installation/Réinstallation du service}}</a>
      </div>
    </div>


  </fieldset>
</form>
          
<script>   
   $('#btnInstallSignalDocker').off('click').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/signal/core/ajax/signal.ajax.php",
      data: {
        action: "installSignalDocker"
      },
      dataType: 'json',
      error: function(request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function(data) {
        if (data.state != 'ok') {
          $('#div_alert').showAlert({
            message: data.result,
            level: 'danger'
          });
          return;
        } else {
          window.toastr.clear()
          $('.pluginDisplayCard[data-plugin_id=' + $('#span_plugin_id').text() + ']').click()
          $('#div_alert').showAlert({
            message: '{{Installation réussie}}',
            level: 'success'
          });

        }
      }
    });
  });

</script>