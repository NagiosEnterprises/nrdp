Overview
========

NRDP (Nagios Remote Data Processor) is a simple, PHP-based passive result collector for use with Nagios. It is designed to be a flexible data transport mechanism and processor, with a simple and powerful architecture that allows for it to be easily extended and customized to fit individual users' needs.

By default, NRDP has the capability of allowing remote agents, applications, and Nagios instances to submit commands and host and service check results to a Nagios server. This allows Nagios administrators to use NRDP to configure distributed monitoring, passive checks, and remote control of their Nagios instance in a quick and efficient manner. The capabilities for NRDP can be extended through the development of additional NRDP plugins.

Installation
------------

Download the latest tarball and extract to start the install::

    cd /tmp
    wget https://github.com/NagiosEnterprises/nrdp/archive/1.4.0.tar.gz
    tar xvf 1.4.0.tar.ggz
    cd nrdp-*

Create a directory and move the NRDP files into place. You don't need to install most of the files outside of server, so we omit them from the cp command::

    mkdir /usr/local/nrdp
    cp -r clients server LICENSE* CHANGES* /usr/local/nrdp
    chown -R nagios:nagios /usr/local/nrdp

Edit the NRDP server config file and add your token to the `$cfg['authorized_tokens']` variable. See example in configuration if you don't know how to create one::

    vi /usr/local/nrdp/server/config.inc.php
    
Configure Apache depending on the curren Apache version and operating system. If you're using a newer version of Apache you may need to change this file slightly. This has been tested to work with CentOS 6::

    cp nrdp.conf /etc/httpd/conf.d
    service httpd restart

The NRDP server has now been installed.

**Testing the Installation**

You can now try out the NRDP server API example by accessing::

    http://<ip address>/nrdp
