#!/bin/sh


if [ $# -lt 1 ]; then
    echo "check_webinject.sh Copyright (c) 2009 Nagios Enterprises"
    echo "Usage: $0 [configfile]";
    exit 3;
fi

config=$1

#echo "USING CONFIG FILE: ${config}"

cd /usr/local/nagiosxi/etc/components/webinject
#pwd

./webinject.pl -c "${config}" -n

ret=$?
exit $ret
