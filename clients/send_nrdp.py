#!/usr/bin/env python
# Copyright (c) 2010-2011 Nagios Enterprises, LLC.
import optparse, sys, urllib, cgi
from xml.dom.minidom  import parseString
class send_nrdp:
    options = [
        optparse.make_option('-u', '--url', action="store",
            dest="url", help="** REQUIRED ** The URL used to access the remote NRDP agent."),
        optparse.make_option('-t', '--token', action="store",
            dest="token", help="** REQUIRED ** The authentication token used to access the remote NRDP agent."),
        optparse.make_option('-H', '--hostname', action="store",
            dest="hostname", help="The name of the host associated with the passive host/service check result."),
        optparse.make_option('-s', '--service', action="store",
            dest="service", help="For service checks, the name of the service associated with the passive check result."),
        optparse.make_option('-S', '--state', action="store",
            dest="state", help="An integer indicating the current state of the host or service."),
        optparse.make_option('-o', '--output', action="store",
            dest="output", help="Text output to be sent as the passive check result.  Newlines should be encoded with encoded newlines (\\n)."),
        optparse.make_option('-d', '--delim', action="store",
            dest="delim", help="With only the required parameters send_nrdp.py is capable of processing data piped to it either from a file or other process.  By default, we use t as the delimiter however this may be specified with the -d option data should be in the following formats one entry per line."),
        optparse.make_option('-c', '--checktype', action="store",
            dest="checktype", help="1 for passive 0 for active")
    ]

    def run(self):
        parser = optparse.OptionParser(option_list=self.options)
        (options, args) = parser.parse_args()

        if not options.url:
            parser.error('You must specify a url.')
        if not options.token:
            parser.error('You must specify a token.')
        try:
            self.setup(options, args)
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
        params = urllib.urlencode({'token': token.strip(),'cmd': 'submitcheck', 'XMLDATA': xml});
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

    def setup(self, options, args):
        if not options.delim:
            options.delim = "\t"
        if not options.checktype:
            options.checktype = "1"
        xml="<?xml version='1.0'?>\n<checkresults>\n";
        
        # it is possible this may not work on windows systems...
        if not sys.stdin.isatty():
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
                    
        # TODO add file option
        #elif options.file:
        #    xml += READ THE FILE
        else:        
            if options.hostname and options.state:
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
                self.post_data(options.url, options.token, xml)
if __name__ == "__main__":
    send_nrdp().run()     



