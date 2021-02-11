#!/usr/bin/env python3

 #############################################################################
 #
 #
 #  send_nrdp.py - Send host/service checkresults to NRDP with XML
 #
 #
 #  Copyright (c) 2008-2020 - Nagios Enterprises, LLC. All rights reserved.
 #  Originally Authored: Scott Wilkerson (nagios@nagios.org)
 #
 #  License: GNU General Public License version 3
 #
 #
 #  This program is free software: you can redistribute it and/or modify
 #  it under the terms of the GNU General Public License as published by
 #  the Free Software Foundation, either version 3 of the License, or
 #  (at your option) any later version.
 #
 #  This program is distributed in the hope that it will be useful,
 #  but WITHOUT ANY WARRANTY; without even the implied warranty of
 #  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 #  GNU General Public License for more details.
 #
 #  You should have received a copy of the GNU General Public License
 #  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 #
 #############################################################################
 #
 # 2017-09-22 Troy Lea aka BOX293
 #  - Fixed setup function that was not working for piped results
 #    as the xml string was missing "</checkresults>"
 #  - Fixed setup function that was not working with arguments when
 #    run as a cron job or if being used as a nagios command like
 #    obsessive compulsive ... "if not sys.stdin.isatty():" was the
 #    reason why.
 #
 # 2017-09-25 Troy Lea aka BOX293
 #  - Added file argument to allow XML results to be read from file
 #  - Replaced optparse with argparse so help text can include newline
 #    characters, allowing for better formatting.
 #
 # 2017-09-26 Troy Lea aka BOX293
 #  - Made sure url ends with a / before posting
 #
 #############################################################################

import argparse, sys
from html import escape
from future.standard_library import install_aliases
install_aliases()
from urllib import request as UrlRequest
from urllib import parse as UrlParse
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
        except Exception as e:
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
        params = UrlParse.urlencode({'token': token.strip(), 'cmd': 'submitcheck', 'XMLDATA': xml});
        url_with_data = "{0}?{1}".format(url, params)
        try:
            with UrlRequest.urlopen(url_with_data) as f:
                result = parseString(f.read().decode('utf-8'))
        except Exception as e:
            print("ERROR - Cannot connect to url: {0}".format(str(e)))
            return -3
        if self.getText(result.getElementsByTagName("status")[0].childNodes) == "0":
            return 1
        else:
            print("ERROR - NRDP Returned: " + self.getText(result.getElementsByTagName("message")[0].childNodes))
            return -1

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
                    xml += "<hostname>"+html.escape(parts[0],True)+"</hostname>"
                    xml += "<servicename>"+html.escape(parts[1],True)+"</servicename>"
                    xml += "<state>"+parts[2]+"</state>"
                    xml += "<output>"+html.escape(parts[3],True)+"</output>"
                    xml += "</checkresult>"
                if len(parts) == 3:
                    xml += "<checkresult type='host' checktype='"+options.checktype+"'>"
                    xml += "<hostname>"+html.escape(parts[0],True)+"</hostname>"
                    xml += "<state>"+parts[1]+"</state>"
                    xml += "<output>"+html.escape(parts[2],True)+"</output>"
                    xml += "</checkresult>"
                xml += "</checkresults>"

        elif options.hostname and options.state:
            xml="<?xml version='1.0'?>\n<checkresults>\n";
            if options.service:
                xml += "<checkresult type='service' checktype='"+options.checktype+"'>"
                xml += "<hostname>"+html.escape(options.hostname,True)+"</hostname>"
                xml += "<servicename>"+html.escape(options.service,True)+"</servicename>"
                xml += "<state>"+options.state+"</state>"
                xml += "<output>"+html.escape(options.output,True)+"</output>"
                xml += "</checkresult>"
            else:
                xml += "<checkresult type='host'  checktype='"+options.checktype+"'>"
                xml += "<hostname>"+html.escape(options.hostname,True)+"</hostname>"
                xml += "<state>"+options.state+"</state>"
                xml += "<output>"+html.escape(options.output,True)+"</output>"
                xml += "</checkresult>"
            xml += "</checkresults>"

        elif options.file:
            try:
                file_handle = open(options.file, 'r')
            except Exception as e:
                print("ERROR - in opening file: " + options.file)
                return -4
            else:
                xml = file_handle.read()
                file_handle.close()

        return self.post_data(options.url, options.token, xml)

if __name__ == "__main__":
    send_nrdp().run()
