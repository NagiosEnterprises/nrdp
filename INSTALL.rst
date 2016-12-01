NRDP Installation
=================

1. Create a directory for the NRDP server files::

    mkdir /usr/local/nrdp
    
2. Download and extract the latest nrdp release. Move the copied files out of the NRDP directory::

    cd nrdp
    cp -r * /usr/local/nrdp
    
3. Set permissions on NRDP dir/files::

    chown -R nagios:nagios /usr/local/nrdp
    
4. Edit the NRDP server config file::

    vi /usr/local/nrdp/server/config.inc.php
    
And add at least one token string to the $cfg['authorized_tokens'] variable. Example::

    $cfg['authorized_tokens'] = array(
        "asd7fjk3l34",
        "df23m7jadI34"
    );
    
5. Configure Apache depending on the version and operating system you may need to change this location::

    cp nrdp.conf /etc/httpd/conf.d
    /etc/init.d/httpd restart

6. The NRDP server has now been installed! You can now try out the NRDP server API example by accessing::

    http://<ip address>/nrdp
