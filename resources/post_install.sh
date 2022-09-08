#!/bin/bash

# on pull l'image pour éviter des soucis  de premier lancement (car ça prend du temps et pas pris en compte dans le dependancy_install)

sudo docker pull bbernhard/signal-cli-rest-api:0.62


echo "Post install (docker pull image) Ok!"