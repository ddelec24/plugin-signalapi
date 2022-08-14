# Plugin Signal

Ce plugin jeedom permet de communiquer en utilisant un compte signal.  


il faut initialiser le plugin en mode native ou normal. le json-rpc est à activer que pour la réception. tout doit donc etre déjà conf


erreur sur lien qrcode => {"error":"Couldn't create QR code: no data to encode"} => remettre les droits jeedom

procédure debug dans le container si besoin

filtrer msg entrants pour éviter de tout historiser.
en gros on autorise des numéros capable d'intéragir avec jeedom (choix ou ceux enregistrés en équipements? à voir)

quand on désactive le plugin, désactiver le docker->rm()

ajout de numéro, une fois link avec le qrcode, les fichiers sur jeedom/host sont en chown 1000. à voir vu qu'on peut pas changer en 33:33 dans le docker
peut etre à la sauvegarde équipement