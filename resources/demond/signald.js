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
var Jeedom = require('./jeedom/jeedom.js')
const WebSocket = require('ws');
//var LAST_SEND_TOPIC = {}

const args = Jeedom.getArgs()
if (typeof args.loglevel == 'undefined') {
  args.loglevel = 'debug'
}
Jeedom.log.setLevel(args.loglevel)

Jeedom.log.info('Start signal')
Jeedom.log.info('Log level on  : ' + args.loglevel)
Jeedom.log.info('Socket port : ' + args.socketport)
Jeedom.log.info('Docker server : ' + args.signal_server)

Jeedom.log.info('PID file : ' + args.pid)
Jeedom.log.info('Apikey : ' + args.apikey)
Jeedom.log.info('Callback : ' + args.callback)
Jeedom.log.info('Cycle : ' + args.cycle)


Jeedom.write_pid(args.pid)
Jeedom.com.config(args.apikey, args.callback, args.cycle)
Jeedom.com.test()

const wsSignalApi = "ws://" + args.signal_server;
Jeedom.log.info('Connect to signal server : ' + wsSignalApi);

// on démarre le démon
Jeedom.http.config(args.socketport, args.apikey)

// connexion au websocket api pour la réception
const ws = new WebSocket(wsSignalApi);

ws.on('open', function() {
  	Jeedom.log.debug("[WebSocket] Connexion au websocket signal-Api établie");
});

ws.on('close', function() {
  	Jeedom.log.debug("[WebSocket] Connexion au websocket signal-Api terminée.");
});

ws.on('message', function(msg) {
    if (isValidJSONString(msg.toString())) {
      Jeedom.log.debug("[WebSocket] Message reçu: " + msg);
      Jeedom.com.add_changes("received", JSON.parse(msg.toString()))
      //Jeedom.com.send_change_immediate("received", JSON.parse(msg.toString()))
    } else {
      Jeedom.log.debug("[WebSocket] Impossible de lire le message reçu: " + msg);
    }
  
});

function isValidJSONString(str) {
  try {
    JSON.parse(str)
  } catch (e) {
    return false
  }
  return true
}