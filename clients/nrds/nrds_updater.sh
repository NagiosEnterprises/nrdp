#!/bin/bash
# Written by: Scott Wilkerson (nagios@nagios.org)
# Copyright (c) 2010-2011 Nagios Enterprises, LLC.
# 
#
###########################

PROGNAME=$(basename $0)
RELEASE="Revision 0.1"
# defaults
CONFIG=/usr/local/nrdp/clients/nrds/nrds.cfg
# Functions plugin usage
print_release() {
    echo "$RELEASE"
}
print_usage() {
    echo ""
    echo "$PROGNAME $RELEASE - NRDS Updater from Nagios NRPD server"
    echo ""
    echo "Usage: nrds.sh -c /path/to/nrds.cfg"
    echo ""
    echo "Usage: $PROGNAME -h"
    echo ""
}
print_help() {
    print_usage
    echo ""
    echo "This script is used to update nrds.cfg"
    echo "from Nagios NRPD server"
    echo ""
    exit 0
}
update_plugins() {
    for (( i=0; i < ${#service[*]}; i++ ))
    do
        full_plugin_path=(${value[$i]})
        plugin_name=`basename $full_plugin_path`
        # this if makes sure we aren't downloading the same plugin twice
        if [[ "${unique_plugins[@]}" =~ "${plugin_name}" ]];then
            continue
        fi
        #make dir if it doesn't exist
        DIRNAME=`dirname $full_plugin_path`

        if [ ! -d "$DIRNAME" ];then
            mkdir -p "$DIRNAME"
            chown nagios:nagios "$DIRNAME"
        fi
        if [ "$curl" ];then
            `curl -o ${full_plugin_path} --silent -d "token=$TOKEN&cmd=getplugin&plugin=$plugin_name" $URL`
        else
            `wget -qO ${full_plugin_path} --post-data="token=$TOKEN&cmd=getplugin&plugin=$plugin_name" $URL`
        fi
        # add permission changes here ?
        chown nagios:nagios "${full_plugin_path}"
        chmod +x "${full_plugin_path}"
        if [ "${plugin_name}" == "check_icmp" -o "${plugin_name}" == "check_dhcp" ];then
            chmod u+s "${full_plugin_path}"
        fi
        unique_plugins+=("${plugin_name}")
    done
    echo "Updated ${#unique_plugins[*]} plugins"
}
process_config(){
    # Process the config
    valid_fields=(URL TOKEN TMPDIR SEND_NRDP COMMAND_PREFIX CONFIG_NAME CONFIG_VERSION CONFIG_NAME UPDATE_CONFIG UPDATE_PLUGINS PLUGIN_DIR)
    while read line; do
        if [[ ! "$line" =~ ^[^\#\;]*= ]]; then
            continue
        fi
        #grab all the commands and put in arrays
        if [ ${line:0:7} == "command" ];then
            name[i]=`echo $line | cut -d'=' -f 1`
            service[i]=${name[i]:8:(${#name[i]}-8-1)}
            value[i]=`echo $line | cut -d'=' -f 2-`
            ((i++))
        # not a command lets process the rest of the config
        # first make sure it is part of valid_fields
        elif [[ "${valid_fields[@]}" =~ `echo $line | cut -d'=' -f 1` ]];then
            eval $line
        else	
            echo "ERROR: `echo $line | cut -d'=' -f 1` is not a valid cfg field."
            exit 1
        fi
    done < "$CONFIG"
}
send_data() {
    pdata="$1"
    if [ "$curl" ];then
        rslt=`curl --silent -d "$pdata" $URL`
    else
        rslt=`wget -q -O - --post-data="$pdata" $URL`
    fi
    ret=$?
    status=`echo "$rslt" | sed -n 's|.*<status>\(.*\)</status>.*|\1|p'`
    message=`echo "$rslt" | sed -n 's|.*<message>\(.*\)</message>.*|\1|p'`
    if [[ "$rslt" =~ "NO REQUEST HANDLER" ]];then
        echo "ERROR $rslt. Check the server config and version" 
        exit 1
    fi
    case "$status" in
        "")
            echo "ERROR: Could not connect to $URL check your cfg file."
            exit 1
            ;;
        -1)
            echo "ERROR: NRDP Server said - $message"
            exit 1
            ;;
        1)
            if [ "$curl" ];then
                save_config=`curl -o $CONFIG --silent -d "token=$TOKEN&cmd=getconfig&configname=$CONFIG_NAME" "$URL"`
            else
                save_config=`wget -qO $CONFIG --post-data="token=$TOKEN&cmd=getconfig&configname=$CONFIG_NAME" "$URL"`
            fi
            chown nagios:nagios "$CONFIG"
            process_config
            echo "Updated config to version $CONFIG_VERSION"
            # check if we need to update plugins
            if [ "$UPDATE_PLUGINS" == "1" ];then
                update_plugins
            fi
            ;;
    esac
    # If we weren't successful error
    if [ $ret != 0 ];then
        echo "exited with error "$ret
        exit $ret
    fi
}

while getopts "c:hvf" option
do
    case "$option" in
        c) CONFIG="$OPTARG" ;;
        h) print_help 0;;
        f) force="1" ;;
        v) print_release
            exit 0 ;;
    esac
done

if [ ! -f "$CONFIG" ]; then
    echo "Could not find config file at $CONFIG"
    exit 1
fi

# detecting curl 
if which curl > /dev/null
then
    curl=1; 
fi
# detecting wget if we don't have curl
if [ ! "$curl" ] && [ which wget > /dev/null ]
then
    wget=1;
fi

if [[ ! "$curl" && ! "$wget" ]]
then
    echo "Either curl or wget are required to run this script"
    exit 1
fi

process_config
if [[ "${URL}" =~ "localhost" ]];then
    echo "ERROR: This should not be run on the localhost"
    exit 1
fi
if [ "$force" == "1" ];then
    CONFIG_VERSION="0"
fi
if [ "$UPDATE_CONFIG" == "1" ] && [ ! "x$CONFIG_NAME" == "x" ] && [ ! "x$CONFIG_VERSION" == "x" ];then
    xml="<?xml version='1.0' ?><configs><config><name>$CONFIG_NAME</name><version>$CONFIG_VERSION</version></config></configs>"
    send_data "token=$TOKEN&cmd=updatenrds&XMLDATA=$xml"
fi

