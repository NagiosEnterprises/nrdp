NRDP 2.x
========

![Nagios!](https://www.nagios.com/wp-content/uploads/2015/05/Nagios-Black-500x124.png)

NRDP (Nagios Remote Data Processor) is a simple, PHP-based passive result collector for use with Nagios. It is designed to be a flexible data transport mechanism and processor, with a simple and powerful architecture that allows for it to be easily extended and customized to fit individual users' needs.

By default, NRDP has the capability of allowing remote agents, applications, and Nagios instances to submit commands and host and service check results to a Nagios server. This allows Nagios administrators to use NRDP to configure distributed monitoring, passive checks, and remote control of their Nagios instance in a quick and efficient manner. The capabilities for NRDP can be extended through the development of additional NRDP plugins.


Installation
============

The KB article "Installing NRDP From Source" has more detailed instuctions that apply to many operating systems:
https://support.nagios.com/kb/article.php?id=602

Download the latest tarball and extract to start the install:

```
cd /tmp
wget https://github.com/NagiosEnterprises/nrdp/archive/2.0.3.tar.gz
tar xvf 2.0.3.tar.gz
cd nrdp-*
```

Create a directory and move the NRDP files into place. You don't need to install most of the files outside of server, so we omit them from the cp command:

```
mkdir /usr/local/nrdp
cp -r clients server LICENSE* CHANGES* /usr/local/nrdp
chown -R nagios:nagios /usr/local/nrdp
```

Edit the NRDP server config file and add your token to the `$cfg['authorized_tokens']` variable. See example in configuration if you don't know how to create one:

```
vi /usr/local/nrdp/server/config.inc.php
```
    
Configure Apache depending on the current Apache version and operating system. If you're using a newer version of Apache you may need to change this file slightly. This has been tested to work with CentOS 6 and 7:

```
cp nrdp.conf /etc/httpd/conf.d
service httpd restart
```

And on Ubuntu 16 and 18 LTS:

```
cp nrdp.conf /etc/apache2/sites-enabled/nrdp.conf
sed -i '/Order allow,deny/c\ #' /etc/apache2/sites-enabled/nrdp.conf
sed -i '/Allow from all/c\Require all granted' /etc/apache2/sites-enabled/nrdp.conf
/etc/init.d/apache2 restart
```

The NRDP server has now been installed.


Testing the Installation
------------------------

You can now try out the NRDP server API example by accessing:

```
http://<ip address>/nrdp
```


Usage
=====

There are several ways to use NRDP:

* Use the client scripts to submit check results
* Submit check results (XML or JSON) via http post request
* Submit a Nagios Core EXTERNAL COMMAND via http post request


Client Scripts
--------------

The client scripts that are distributed with NRDP in the `clients` folder are clearly documented. They are basically a wrapper script for submitting a http post request.
More detailed usage examples can be found in the "send_nrdp Client" KB article:
https://support.nagios.com/kb/article.php?id=599

Here is a service example with a WARNING state:

```
./send_nrdp.sh -u http://nagios_server/nrdp/ -t XXXXX -H somehost -s "Disk Usage" -S 1 -o "WARNING: The disk is 75% full"
```

Which will produce output like:

```
Sent 1 checks to http://nagios_server/nrdp/
```

Submit check results (XML or JSON) using a http post
----------------------------------------------------

You don't need to use the client script to submit check results, they can be submitted using a http post request using a client like curl.
Both XML and JSON formats are is supported.
The request data is sent using the following http post request arguments:

* `cmd=submitcheck`
* `xml=XXXXX` OR `json=XXXXX`


**XML Format**

The syntax for submitting a check result as XML is as follows.

Host:

```
<?xml version='1.0'?>
<checkresults>
    <checkresult type='host'>
        <hostname>somehost</hostname>
        <state>0</state>
        <output>Everything looks okay! | perfdata=1;</output>
    </checkresult>
</checkresults>
```

Service:

```
<?xml version='1.0'?>
<checkresults>
    <checkresult type='service'>
        <hostname>somehost</hostname>
        <servicename>Disk Usage</servicename>
        <state>1</state>
        <output>WARNING: The disk is 75% full | disk_usage=75%;</output>
    </checkresult>
</checkresults>
```


Multipe check results can be posted using additional `<checkresult type='host/service'>XXXXX</checkresult>`.

The XML data is sent as the http post request argument `xml`.

Here are those two examples above submitted using a curl command.

Host:

```
curl -f -d "token=XXXXX&cmd=submitcheck&xml=<?xml version='1.0'?><checkresults><checkresult type='host'><hostname>somehost</hostname><state>0</state><output>Everything looks okay! | perfdata=1;</output></checkresult></checkresults>" http://nagios_server/nrdp/
```

Service:

```
curl -f -d "token=XXXXX&cmd=submitcheck&xml=<?xml version='1.0'?><checkresults><checkresult type='service'><hostname>somehost</hostname> <servicename>Disk Usage</servicename><state>1</state><output>WARNING: The disk is 75% full | disk_usage=75%;</output></checkresult></checkresults>" http://nagios_server/nrdp/
```


**JSON Format**

The syntax for submitting a check result as JSON is as follows.

Host:

```
{
    "checkresults": [
        {
            "checkresult": {
                "type": "host"
            },
            "hostname": "somehost",
            "state": "0",
            "output": "Everything looks okay! | perfdata=1;"
        }
    ]
}
```

Service:

```
{
    "checkresults": [
        {
            "checkresult": {
                "type": "service"
            },
            "hostname": "somehost",
            "servicename": "Disk Usage",
            "state": "1",
            "output": "WARNING: The disk is 75% full | disk_usage=75%;"
        }
    ]
}
```


Multipe check results can be posted using additional `, { "checkresult": { XXXX } }`.

The JSON data is sent as the http post request argument `json`.

Here are those two examples above submitted using a curl command.

Host:

```
curl -f -d 'token=XXXXX&cmd=submitcheck&json={ "checkresults": [ { "checkresult": { "type": "host" }, "hostname": "somehost", "state": "0", "output": "Everything looks okay! | perfdata=1;" } ] }' http://nagios_server/nrdp/
```

Service:

```
curl -f -d 'token=XXXXX&cmd=submitcheck&json={ "checkresults": [ { "checkresult": { "type": "service" }, "hostname": "somehost", "servicename": "Disk Usage", "state": "1", "output": "WARNING: The disk is 75% full | disk_usage=75%;" } ] }' http://nagios_server/nrdp/
```


Submit a Nagios Core EXTERNAL COMMAND
-------------------------------------

NDRP can also submit any of the defined [External Commands](https://assets.nagios.com/downloads/nagioscore/docs/nagioscore/4/en/extcommands.html) to Nagios Core.

The request data is sent using the following http post request arguments:

* `cmd=submitcmd`
* `command=XXXXX`

The data in the command is identical to how it is documented, for example `SCHEDULE_FORCED_SVC_CHECK;somehost;someservice;1110741500`

Here is an example using a curl command:

```
curl -f -d 'token=XXXXX&cmd=submitcmd&command=SCHEDULE_FORCED_SVC_CHECK;somehost;someservice;1110741500' http://nagios_server/nrdp/
```


Permissions
===========

Tokens are used to authorise requests submitted to NRDP. By default, all authorized tokens are allowed to submit any external command (unless it's disabled).

* Defined in `config.inc.php`
* This is a deny mapping in the form of `COMMAND => TOKEN` or `TOKENS`
* You can specify a whole command, or use * as a wildcard
* Or you can specify 'all' to stop any token from using any external command
* the tokens specified can either be a string with 1 token, or an array of 1 or more tokens

Examples:

```
$cfg['external_commands_deny_tokens'] = array(
    "ACKNOWLEDGE_HOST_PROBLEM"  => array("mysecrettoken", "myothertoken"),
    "ACKNOWLEDGE_SVC_PROBLEM"   => "mysecrettoken",
    "all"                       => array("mysecrettoken", "myothertoken"),
    "ACKNOWLEDGE_*"             => "mysecrettoken",
    "*_HOST_*"                  => array("mysecrettoken", "myothertoken"),
);
```
