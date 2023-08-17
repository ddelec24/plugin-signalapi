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

if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

$eqLogic = eqLogic::byId(init('eqLogic_id'));
if (!is_object($eqLogic)) {
	throw new Exception('{{Equipement introuvable}} : ' . init('eqLogic_id'));
}

//log::add('signal', 'debug', "MODAL CONTACT : " . json_encode($eqLogic));
$contacts = $eqLogic->getConfiguration('contactsList');

if($contacts) {
	log::add('signal', 'debug', "MODAL CONTACT : " . json_encode($contacts));
}
?>

<div class="col-lg-6">
	<legend>Liste des contacts synchronisés</legend>
	<input type="hidden" id="eqLogic_id" value="<?php echo $eqLogic->getId();?>" />
	<?php 
	if(is_array($contacts) && sizeof($contacts) > 0) { 
		?>
		<div class="form-group">
			<div class="col-sm-12">
				<div class="input-group" style="display:inline-flex;">
					<span class="input-group-btn">
						<a data-action="updateContactNames" class="btn btn-sm btn-primary">Sauvegarder</a>
					</span>
				</div>
			</div>
		</div>

		<table id="table_contacts" class="table table-bordered table-condensed ui-sortable">
			<thead>
				<tr>
          			<th class="hidden-xs" style="min-width:50px;width:70px;">{{Afficher}}</th>
					<th class="hidden-xs" style="min-width:50px;width:70px;">{{Numéro}}</th>
					<th style="min-width:200px;width:350px;">{{Nom}}</th>
				</tr>
			</thead>
			<tbody>

				<?php
				foreach($contacts as $contact) {
					?>
					<tr class="cmd">
                      	<td class="hidden-xs"><input type="checkbox" <?php echo ($contact['display']) ? 'checked="checked"' : "";?> class="eqLogicAttr" id="<?php echo substr($contact['number'], 1);?>display" /></td>
						<td class="hidden-xs"><?php echo $contact['number'];?></td>
						<td>
							<div class="input-group">
								<input type="text" class="cmdAttr form-control input-sm roundedLeft contactName" id="<?php echo $contact['number'];?>" placeholder="Nom du contact" value="<?php echo $contact['name'];?>" />
							</div>
						</td>
					</tr>
					<?php 
			} //foreach contacts
			?>
		</tbody>
	</table>
	<?php
	} else { // contacts exists
		?>
		Aucun contact synchronisé, merci de sauvegarder votre équipement afin d&apos;actualiser la liste.
		<?php
	} // no contacts
	?>
</div>
  
<script>
$('a[data-action=updateContactNames]').on('click', function(e) {
      e.preventDefault();
      var eqLogic_id = $('#eqLogic_id').val();
      var listContacts = $('#table_contacts').find('.contactName');
      var countContacts = listContacts.length;

      if(countContacts > 0) {
        var arrContacts = [];
        listContacts.each(function(i, el) {
          var checkboxName = '#' + ($(this).prop('id')).slice(1) + 'display';
          var checkboxVal = $(checkboxName).is(":checked") ? true : false;
          arrContacts.push({number: $(this).prop('id'), name: $(this).val(), display: checkboxVal});
        });

        $.ajax({
          type: "POST",
          url: "plugins/signal/core/ajax/signal.ajax.php",
          data: {
            action: "updateContacts",
            eqLogic: eqLogic_id,
            contacts: arrContacts
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
              console.log(data.result);
            }
          }
        }); //ajax

      } // contact > 0

});
</script>