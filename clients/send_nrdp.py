#!/usr/bin/env python
#
# send_nrdp.py
#
# Copyright (c) 2010-2017 - Nagios Enterprises, LLC.
# Written by: Scott Wilkerson (nagios@nagios.org)
#
# 2017-09-22 Troy Lea aka BOX293
#  - Fixed setup function that was not working for piped results
#    as the xml string was missing "</checkresults>"
#  - Fixed setup function that was not working with arguments when
#    run as a cron job or if being used as a nagios command like
#    obsessive compulsive ... "if not sys.stdin.isatty():" was the
#    reason why.
# 2017-09-25 Troy Lea aka BOX293
#  - Added file argument to allow XML results to be read from file
#  - Replaced optparse with argparse so help text can include newline
#    characters, allowing for better formatting.
# 2017-09-26 Troy Lea aka BOX293
#  - Made sure url ends with a / before posting


import argparse, sys, urllib, cgi
from xml.dom.minidom import parseString

class send_nrdp:
    def run(self):
        parser = argparse.ArgumentParser(formatter_class=argparse.RawTextHelpFormatter)
        
        parser.add_argument('-u', '--url', action="store",
            dest="url", help="\
** REQUIRED ** The URL used to access the remote NRDP agent.")
        parser.add_argument('-t', '--token', action="store",
            dest="token", help="\
** REQUIRED ** The authentication token used to access the\n\
remote NRDP agent.")
        parser.add_argument('-H', '--hostname', action="store",
            dest="hostname", help="\
The name of the host associated with the passive host/service\n\
check result.")
        parser.add_argument('-s', '--service', action="store",
            dest="service", help="\
For service checks, the name of the service associated with the\n\
passive check result.")
        parser.add_argument('-S', '--state', action="store",
            dest="state", help="\
An integer indicating the current state of the host or service.")
        parser.add_argument('-o', '--output', action="store",
            dest="output", help="\
Text output to be sent as the passive check result.\n\
Newlines should be encoded with encoded newlines (\\n).")
        parser.add_argument('-f', '--file', action="store",
            dest="file", help="\
This file will be sent to the NRDP server specified in -u\n\
The file should be an XML file in the following format:\n\
##################################################\n\
<?xml version='1.0'?>\n\
<checkresults>\n\
  <checkresult type=\"host\" checktype=\"1\">\n\
    <hostname>YOUR_HOSTNAME</hostname>\n\
    <state>0</state>\n\
    <output>OK|perfdata=1.00;5;10;0</output>\n\
  </checkresult>\n\
  <checkresult type=\"service\" checktype=\"1\">\n\
    <hostname>YOUR_HOSTNAME</hostname>\n\
    <servicename>YOUR_SERVICENAME</servicename>\n\
    <state>0</state>\n\
    <output>OK|perfdata=1.00;5;10;0</output>\n\
  </checkresult>\n\
</checkresults>\n\
##################################################")
        parser.add_argument('-d', '--delim', action="store",
            dest="delim", help="\
With only the required parameters send_nrdp.py is capable of\n\
processing data piped to it either from a file or other process.\n\
By default, we use t (\\t) as the delimiter however this may be\n\
specified with the -d option data should be in the following\n\
formats of one entry per line:\n\
printf \"<hostname>\\t<state>\\t<output>\\n\"\n\
printf \"<hostname>\\t<service>\\t<state>\\t<output>\\n\"\n")
        parser.add_argument('-c', '--checktype', action="store",
            dest="checktype", help="1 for passive 0 for active")
        
        options = parser.parse_args()
	
        if not options.url:
            parser.error('You must specify a url.')
        if not options.token:
            parser.error('You must specify a token.')
        try:
            self.setup(options)
            sys.exit()
        except Exception, e:
            sys.exit(e)
        
    def getText(self, nodelist):
        rc = []
        for node in nodelist:
            if node.nodeType == node.TEXT_NODE:
                rc.append(node.data)
        return ''.join(rc)

    def post_data(self, url, token, xml):
        # Make sure URL ends with a /
        if not url.endswith('/'):
            url += '/'
        
        params = urllib.urlencode({'token': token.strip(), 'cmd': 'submitcheck', 'XMLDATA': xml});
        opener = urllib.FancyURLopener()
        try:
            f = opener.open(url, params)
            result = parseString(f.read())
        except Exception, e:
            print "Cannot connect to url."
            # TODO add directory option
            sys.exit(e)
        if self.getText(result.getElementsByTagName("status")[0].childNodes) == "0":
            sys.exit()
        else:
            print "ERROR - NRDP Returned: "+self.getText(result.getElementsByTagName("message")[0].childNodes)
            sys.exit(1)

    def setup(self, options):
        if not options.delim:
            options.delim = "\t"
        if not options.checktype:
            options.checktype = "1"
        
        # If only url and token have been provided then it is assumed that data is being piped
        if not options.hostname and not options.state and not options.file:
            xml="<?xml version='1.0'?>\n<checkresults>\n";
            for line in sys.stdin.readlines():
                parts = line.split(options.delim)
                if len(parts) == 4:
                    xml += "<checkresult type='service' checktype='"+options.checktype+"'>"
                    xml += "<hostname>"+cgi.escape(parts[0],True)+"</hostname>"
                    xml += "<servicename>"+cgi.escape(parts[1],True)+"</servicename>"
                    xml += "<state>"+parts[2]+"</state>"
                    xml += "<output>"+cgi.escape(parts[3],True)+"</output>"
                    xml += "</checkresult>"
                if len(parts) == 3:
                    xml += "<checkresult type='host' checktype='"+options.checktype+"'>"
                    xml += "<hostname>"+cgi.escape(parts[0],True)+"</hostname>"
                    xml += "<state>"+parts[1]+"</state>"
                    xml += "<output>"+cgi.escape(parts[2],True)+"</output>"
                    xml += "</checkresult>"
                xml += "</checkresults>"
        
        elif options.hostname and options.state:
            xml="<?xml version='1.0'?>\n<checkresults>\n";
            if options.service:
                xml += "<checkresult type='service' checktype='"+options.checktype+"'>"
                xml += "<hostname>"+cgi.escape(options.hostname,True)+"</hostname>"
                xml += "<servicename>"+cgi.escape(options.service,True)+"</servicename>"
                xml += "<state>"+options.state+"</state>"
                xml += "<output>"+cgi.escape(options.output,True)+"</output>"
                xml += "</checkresult>"
            else:
                xml += "<checkresult type='host'  checktype='"+options.checktype+"'>"
                xml += "<hostname>"+cgi.escape(options.hostname,True)+"</hostname>"
                xml += "<state>"+options.state+"</state>"
                xml += "<output>"+cgi.escape(options.output,True)+"</output>"
                xml += "</checkresult>"
            xml += "</checkresults>"

        elif options.file:
            try:
                file_handle = open(options.file, 'r')
            except Exception, e:
                print "Error opening file '"+options.file+"'"
                sys.exit(e)
            else:
                xml = file_handle.read()
                file_handle.close()

        self.post_data(options.url, options.token, xml)

if __name__ == "__main__":
    send_nrdp().run()
