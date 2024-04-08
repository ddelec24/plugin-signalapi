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

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
	axis: "y",
	cursor: "move",
	items: ".cmd",
	placeholder: "ui-state-highlight",
	tolerance: "intersect",
	forcePlaceholderSize: true
})

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
	if (!isset(_cmd)) {
		var _cmd = {configuration: {}}
	}
	if (!isset(_cmd.configuration)) {
		_cmd.configuration = {}
	}
	var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
	tr += '<td class="hidden-xs">'
	tr += '<span class="cmdAttr" data-l1key="id"></span>'
	tr += '</td>'
	tr += '<td>'
	tr += '<div class="input-group">'
	tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
	tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
	tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
	tr += '</div>'
	tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">'
	tr += '<option value="">{{Aucune}}</option>'
	tr += '</select>'
	tr += '</td>'
	tr += '<td>'
	tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
	tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
	tr += '</td>'
	tr += '<td>'
	tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
	tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
	tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
	tr += '<div style="margin-top:7px;">'
	tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
	tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
	tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
	tr += '</div>'
	tr += '</td>'
	tr += '<td>'
	if (is_numeric(_cmd.id)) {
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>'
	}
	tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
	tr += '</tr>'
	$('#table_cmd tbody').append(tr)
	var tr = $('#table_cmd tbody tr').last()
	jeedom.eqLogic.buildSelectCmd({
		id:  $('.eqLogicAttr[data-l1key=id]').value(),
		filter: {type: 'info'},
		error: function (error) {
			$('#div_alert').showAlert({message: error.message, level: 'danger'})
		},
		success: function (result) {
			tr.find('.cmdAttr[data-l1key=value]').append(result)
			tr.setValues(_cmd, '.cmdAttr')
			jeedom.cmd.changeType(tr, init(_cmd.subType))
		}
	})
}


$(".eqLogicAttr[data-l1key=configuration][data-l2key=numero]").on('change keyup', function(e) {
	var currentNumber = $(this).val();
	if(isValidNumber(currentNumber)) {
		$(this).attr("style", "border: green solid 1px !important");
	} else {
		$(this).attr("style", "border: red solid 1px !important");
	}
});

function isValidNumber(p) {
	var phoneRe = /^[+]{1}[\d]{5,14}$/;
	return phoneRe.test(p);
}

$("#modalContacts").on("click", function(e) {
	e.preventDefault();
	$('#md_modal').dialog({title: "{{Contacts}}"});
	$('#md_modal').load('index.php?v=d&plugin=signal&modal=modal.contacts&eqLogic_id=' + $(".eqLogicAttr[data-l1key=id]").val()).dialog('open');
});

function printEqLogic(_eqLogic) {
	let eqLogic = _eqLogic.id;
	let colorCheck = (paramsSignal[eqLogic] !== undefined) ? paramsSignal[eqLogic]['colorCheck'] : 'red';
	let classCheck = (paramsSignal[eqLogic] !== undefined) ? paramsSignal[eqLogic]['classCheck'] : "fa-times-circle";
	
	$('.checkNumberLinked').removeClass("fa-check-circle").removeClass("fa-times-circle");
	$('.checkNumberLinked').attr('style', "color: " + colorCheck + " !important").addClass(classCheck);
	
	if(paramsSignal[eqLogic]['listSignalGroups'] != undefined && colorCheck != "red") {
		$('.divSignalGroups').show();
		$('.resultSignalGroups').html('<i class="fa fa-sync getSignalGroups" aria-hidden="true"></i> &nbsp;' +paramsSignal[eqLogic]['listSignalGroups']);
		bindSync();   
	} else {
		$('.divSignalGroups').hide();
		$('.resultSignalGroups').text("");
	}

}

function bindSync() {
	$('.getSignalGroups').on('click', function(e) {
		e.preventDefault();
		$.ajax({
			type: "POST",
			url: "plugins/signal/core/ajax/signal.ajax.php",
			data: {
				action: "getSignalGroups",
				eqLogic: $(".eqLogicAttr[data-l1key=id]").val()
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
					location.reload();
				}
			}
		});
	});
}