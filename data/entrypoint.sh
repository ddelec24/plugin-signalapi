#!/bin/sh

set -x

[ -z "${SIGNAL_CLI_CONFIG_DIR}" ] && echo "SIGNAL_CLI_CONFIG_DIR environmental variable needs to be set! Aborting!" && exit 1;

# jeedom fix, we need to use 33 uuid/guid, then delete www-data if exists
exists=$(grep -c "www-data" /etc/passwd)
echo ">>> Check if www-data exists : ${exists}"
if [ "${exists}" -eq 1 ];
then
	userdel www-data
fi;

echo ">>> Creating user signal-api"
usermod -u "${SIGNAL_CLI_UID}" signal-api
groupmod -g "${SIGNAL_CLI_GID}" signal-api

set -e

# Fix permissions to ensure backward compatibility
echo ">>> Update signal-api UID/GID"
chown ${SIGNAL_CLI_UID}:${SIGNAL_CLI_GID} -R ${SIGNAL_CLI_CONFIG_DIR}

# Show warning on docker exec
cat <<EOF >> /root/.bashrc
echo "WARNING: signal-cli-rest-api runs as signal-api (not as root!)" 
echo "Run 'su signal-api' before using signal-cli!"
echo "If you want to use signal-cli directly, don't forget to specify the config directory. e.g: \"signal-cli --config ${SIGNAL_CLI_CONFIG_DIR}\""
EOF

cap_prefix="-cap_"
caps="$cap_prefix$(seq -s ",$cap_prefix" 0 $(cat /proc/sys/kernel/cap_last_cap))"

# TODO: check mode
if [ "$MODE" = "json-rpc" ]
then
/usr/bin/jsonrpc2-helper
service supervisor start
supervisorctl start all
fi

export HOST_IP=$(hostname -I | awk '{print $1}')

# Start API as signal-api user
exec setpriv --reuid=${SIGNAL_CLI_UID} --regid=${SIGNAL_CLI_GID} --init-groups --inh-caps=$caps signal-cli-rest-api -signal-cli-config=${SIGNAL_CLI_CONFIG_DIR}