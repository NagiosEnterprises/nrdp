#!/usr/bin/perl
#
# Version 1.3.2
# Last update: 2009-02-03
#
# (c)2000 Benjamin Schmid, blueshift@gmx.net (emergency use only ;-)
# Copyleft by GNU GPL
#
#
# check_email_loop Nagios Plugin
#
# This script sends a mail with a specific id in the subject via
# an given smtp-server to a given email-adress. When the script
# is run again, it checks for this Email (with its unique id) on
# a given pop3 account and send another mail.
# 
#
# Example: check_email_loop.pl -poph=mypop -popu=user -pa=password
# 	   -smtph=mailer -from=returnadress@yoursite.com
#	   -to=remaileradress@friend.com -pendc=2 -lostc=0
#
# This example will send each time this check is executed a new
# mail to remaileradress@friend.com using the SMTP-Host mailer.
# Then it looks for any back-forwarded mails in the POP3 host
# mypop. In this Configuration CRITICAL state will be reached if  
# more than 2 Mails are pending (meaning that they did not came 
# back till now) or if a mails got lost (meaning a mail, that was
# send later came back prior to another mail).
# 
# Michael Markstaller, mm@elabnet.de various changes/additions
# MM 021003: fixed some unquoted strings
# MM 021116: fixed/added pendwarn/lostwarn
# MM 030515: added deleting of orphaned check-emails 
#            changed to use "top" instead of get to minimize traffic
#            (required changing match-string from "Subject: Email-ping [" to "Email-Ping ["
# 2006-11-09: Allow multiple mail-servers (separate stats files via target server hash).
#
# Emmanuel Kedaj (Emmanuel.kedaj@gmail.com)
# Added some debug messages
# retrieving POP3 mails before sending actual test mail
# as mentionned by Dave Ewall <dave_at_email.domain.hidden> on 19 Jul 2005 on Nagios-users mailing list
# https://lists.sourceforge.net/lists/listinfo/nagios-users
#
#
# Jb007 (007_james_bond NO @ SPAM libero.it) Oct 2006
# ChangeLog:
# * Used Mail::POP3Client for SSL support
# * Added trashall param
# * Fixed lost mail stat file
# * Added forgetafter param
# Bugs:
# * Try to delete matched id already marked for deletion
# * No interval even if present in options
# Todo:
# Implement "interval" param
#
# Johannes Derek May 2008
# * Added Support for SMTP Authentication (code taken from check_email_delivery)
# * SMTP TLS Support working, SMTP SSL Support not tested
#
# James W., September 2008  (v.1.3.1)
# * sanity check for required Authen:SASL module
#
# Benjamin Schmid, Februrary 2009 (v.1.3.2)
# * Remove "-w" parameter to ommit annoying perl warning in line 261 
#   probably due to missing mail subjects/bodies

use Mail::POP3Client;
use Net::SMTP;
use strict;
use Getopt::Long;
use Digest::MD5;
&Getopt::Long::config('auto_abbrev');

# ----------------------------------------

my $TIMEOUT = 120;
my %ERRORS = ('OK' , '0',
              'WARNING', '1',
              'CRITICAL', '2',
              'UNKNOWN' , '3');

my $state = "UNKNOWN";
my ($sender,$receiver, $pophost, $popuser, $poppasswd,$keeporphaned);
my ($smtphost, $smtpuser, $smtppasswd, $smtpport);
my ($trashall,$usessl,$forgetafter);
my ($usesmtpssl,$usesmtptls);
my ($poptimeout,$smtptimeout,$pinginterval,$maxmsg)=(60,60,5,50);
my ($lostwarn, $lostcrit,$pendwarn, $pendcrit,$debug);

$debug = 0;

# Internal Vars
my ($pop,$msgcount,@msglines,$statinfo,@messageids,$newestid);
my (%other_smtp_opts);
my ($matchcount,$statfile) = (0,"check_email_loop");

my $default_smtp_port = "25";
my $default_smtp_ssl_port = "465";
my $default_smtp_tls_port = "587";

# Subs declaration
sub usage;
sub messagematchs;
sub nsexit;

# Just in case of problems, let's not hang Nagios
$SIG{'ALRM'} = sub {
     # Write list to id-Database
     foreach my $id (@messageids) {
         print STATF  "$id\n";
     }
     close STATF;
     print ("ERROR: $0 Time-Out $TIMEOUT s \n");
     exit $ERRORS{"UNKNOWN"};
};
alarm($TIMEOUT);


# Evaluate Command Line Parameters
my $status = GetOptions(
		        "from=s",\$sender,
			"to=s",\$receiver,
                        "debug", \$debug,
                        "pophost=s",\$pophost,
                        "popuser=s",\$popuser,
			"passwd=s",\$poppasswd,
			"poptimeout=i",\$poptimeout,
			"smtphost=s",\$smtphost,
			"smtptimeout=i",\$smtptimeout,
			"smtpuser=s",\$smtpuser,
			"smtppasswd=s",\$smtppasswd,
			"smtpport=s",\$smtpport,
			"statfile=s",\$statfile,
			"interval=i",\$pinginterval,
			"lostwarn=i",\$lostwarn,
			"lostcrit=i",\$lostcrit,
			"pendwarn=i",\$pendwarn,
			"pendcrit=i",\$pendcrit,
			"maxmsg=i",\$maxmsg,
			"forgetafter=i",\$forgetafter,
			"keeporphaned=s",\$keeporphaned,
			"trashall",\$trashall,
			"usessl",\$usessl,
			"usesmtpssl",\$usesmtpssl,
			"usesmtptls",\$usesmtptls,
			);
usage() if ($status == 0 || ! ($pophost && $popuser && $poppasswd &&
	$smtphost && $receiver && $sender ));

my @required_module = ();
push @required_module, 'Net::SMTP::SSL' if $usesmtpssl;
push @required_module, ('MIME::Base64','Authen::SASL') if $usesmtpssl && $smtpuser;
push @required_module, 'Net::SMTP::TLS' if $usesmtptls;
push @required_module, 'Authen::SASL' if $smtpuser && !$usesmtpssl && !$usesmtptls;
exit $ERRORS{"UNKNOWN"} unless load_modules(@required_module);


# Hash stat file
my $statfilehash = Digest::MD5->new;
$statfilehash->add($sender.$receiver.$pophost.$popuser.$poppasswd.$smtphost);
$statfile = $statfile."_".$statfilehash->hexdigest.".stat";

# Try to read the ids of the last send emails out of statfile
if (open STATF, "$statfile") {
  @messageids = <STATF>;
  chomp @messageids;
  close STATF;
}

# Try to open statfile for writing 
if (!open STATF, ">$statfile") {
  nsexit("Failed to open mail-ID database $statfile for writing",'CRITICAL');
} else {
  printd ("Opened $statfile for writing..");
}

# Forget old mail
if (defined $forgetafter) {
        my $timeis=time();
        printd ("----------------------------------------------------------------------\n");
        printd ("----------------------------------------------------------------------\n");
        printd ("-------------------- Purging Old Mails -------------------------------\n");
        printd ("-------------------- Time: $timeis -------------------------------\n");
        printd ("-------------------- Forget: $forgetafter ------------------------\n");
        printd ("----------------------------------------------------------------------\n");
        printd ("----------------------------------------------------------------------\n");
        for (my $i=0; $i < scalar @messageids; $i++) {
                my $msgtime = $messageids[$i];
                $msgtime =~ /\#(\d+)\#/;
                $msgtime = $1;
                my $diff=($timeis-$msgtime)/86400;
                if ($diff>$forgetafter) {
                        printd ("Purging mail $i with date $msgtime\n");
                        splice @messageids, $i, 1;
                                last;
                }
        }
}

printd ("----------------------------------------------------------------------\n");
printd ("----------------------------------------------------------------------\n");
printd ("-------------------- Checking POP3 Mails -----------------------------\n");
printd ("----------------------------------------------------------------------\n");
printd ("----------------------------------------------------------------------\n");
if (defined $usessl) { printd ("Retrieving POP mails from $pophost using ssl and user: $popuser and password $poppasswd\n"); }
else { printd ("Retrieving POP mails from $pophost using user: $popuser and password $poppasswd\n"); }

# no the interessting part: let's if they are receiving ;-)

$pop = Mail::POP3Client->new( HOST => $pophost,
        TIMEOUT => $poptimeout,
        USESSL => $usessl ,
        DEBUG => $debug ) || nsexit("POP3 connect timeout (>$poptimeout s, host: $pophost)",'CRITICAL');

$pop->User($popuser);
$pop->Pass($poppasswd);
$pop->Connect() >=0 || nsexit("POP3 connect timeout (>$poptimeout s, host: $pophost)",'CRITICAL');
$pop->Login()|| nsexit("POP3 login failed (user:$popuser)",'CRITICAL');
$msgcount=$pop->Count();

$statinfo="$msgcount mails on POP3";

printd ("Found $statinfo\n");

nsexit("POP3 login failed (user:$popuser)",'CRITICAL') if (!defined($msgcount));

# Check if more than maxmsg mails in pop3-box
nsexit(">$maxmsg Mails ($msgcount Mails on POP3); Please delete !",'WARNING') if ($msgcount > $maxmsg);

my ($mid, $nid);
# Count messages, that we are looking 4:
while ($msgcount > 0) {
  my $msgtext = "";
  foreach ($pop->Head($msgcount)) { $msgtext= $msgtext . $_ . "\n"; }
  @msglines = $msgtext;
  for (my $i=0; $i < scalar @messageids; $i++) {
    if (messagematchsid(\@msglines,$messageids[$i])) { 
      $matchcount++;
      printd ("Messages are matching\n");
      # newest received mail than the others, ok remeber id.
      if (!defined $newestid) { 
        $newestid = $messageids[$i];
      } else {
	    $messageids[$i] =~ /\#(\d+)\#/;
        $mid = $1;
	    $newestid =~ /\#(\d+)\#/;
        $nid = $1;
        if ($mid > $nid) { 
          $newestid = $messageids[$i]; 
        }
      }
      printd ("Deleted retrieved mail $msgcount with messageid ".$messageids[$i]."\n");
      $pop->Delete($msgcount);  # remove E-Mail from POP3 server
      splice @messageids, $i, 1;# remove id from List
	  last;                     # stop looking in list
	} 
  }
        # Messages Deleted before are marked for deletion here again
	# we should try to avoid this.
	# Delete orphaned Email-ping msg
	my @msgsubject = grep /^Subject/, @msglines;
	chomp @msgsubject;
        # Maybe we should delete all messages?
        if (defined $trashall) {
            $pop->Delete($msgcount);
            printd ("Deleted mail $msgcount\n");
	# Scan Subject if email is an Email-Ping. In fact we match and delete also successfully retrieved messages here again.
	} elsif (!defined $keeporphaned && $msgsubject[0] =~ /E-Mail Ping \[/) {
	    $pop->Delete($msgcount);  # remove E-Mail from POP3 server
            printd ("Deleted orphaned mail $msgcount with subject ".$msgsubject[0]."\n");
	}

	$msgcount--;
}

$pop->close();  # necessary for pop3 deletion!

# traverse through the message list and mark the lost mails
# that mean mails that are older than the last received mail.
if (defined $newestid) {
  $newestid =~ /\#(\d+)\#/;
  $newestid = $1;
  for (my $i=0; $i < scalar @messageids; $i++) {
    $messageids[$i] =~ /\#(\d+)\#/;
    my $akid = $1;
    if ($akid < $newestid) {
      $messageids[$i] =~ s/^ID/LI/; # mark lost
      printd ("MAIL $messageids[$i] MARKED AS LOST\n");
    }
  }
}

# Write list to id-Database
foreach my $id (@messageids) {
  print STATF  "$id\n";
}

# creating new serial id
my $timenow = time();  
my $serial = "ID#" . $timenow . "#$$";

# Ok - check if it's time to release another mail

# ...

if (defined $pinginterval) {
#    if (!defined $newestid) {
#        $newestid=$messageids[-1];
#    } elsif ($messageids[-1] > $newestid) {
#        $newestid = $messageids[-1];
#    }
#    $newestid =~ /\#(\d+)\#/;
#    $newestid = $1;
#
#    printd ("----------------------------------------------------------------------\n");
#    printd ("----------------------------------------------------------------------\n");
#    printd ("-------------------- INTERVAL: $pinginterval -----------------\n");
#    printd ("-------------------- TIME: $timenow --------------------------\n");
#    printd ("-------------------- LAST: $newestid -------------------------\n");
#    printd ("----------------------------------------------------------------------\n");
#    printd ("----------------------------------------------------------------------\n");
#
# FIXME ... TODO

}

# sending new ping email
%other_smtp_opts=();
if ( $debug == 1  ) {
    $other_smtp_opts{'Debug'} = 1;
}

my $smtp;
eval {
       if( $usesmtptls ) {
               $smtpport = $default_smtp_tls_port unless $smtpport;
               $smtp = Net::SMTP::TLS->new($smtphost, Timeout=>$smtptimeout, Port=>$smtpport, User=>$smtpuser, Password=>$smtppasswd);
       }
       elsif( $usesmtpssl ) {
               $smtpport = $default_smtp_ssl_port unless $smtpport;
               $smtp = Net::SMTP::SSL->new($smtphost, Port => $smtpport, Timeout=>$smtptimeout, %other_smtp_opts);
               if( $smtp && $smtpuser )  {
                       $smtp->auth($smtpuser, $smtppasswd);
               }
       }
       else {
               $smtpport = $default_smtp_port unless $smtpport;
               $smtp = Net::SMTP->new($smtphost, Port=>$smtpport, Timeout=>$smtptimeout,%other_smtp_opts);
               if( $smtp && $smtpuser ) {
                       $smtp->auth($smtpuser, $smtppasswd);
               }
       }
};
if( $@ ) {
       $@ =~ s/\n/ /g; # the error message can be multiline but we want our output to be just one line
       nsexit("SMTP CONNECT CRITICAL - $@", 'CRITICAL');
}
unless( $smtp ) {
       nsexit("SMTP CONNECT CRITICAL - Could not connect to $smtphost port $smtpport", 'CRITICAL');
}



$smtp->mail($sender);
if( $usesmtptls ) {
       # Net::SMTP::TLS croaks when the recipient is rejected
       eval {
               $smtp->to($receiver);
       };
       if( $@ ) {
               nsexit("SMTP SEND CRITICAL - Could not send to $receiver",'CRITICAL');
       }
}
else {
       # Net::SMTP returns false when the recipient is rejected
       my $to_returned = $smtp->to($receiver);
       if( !$to_returned ) {
               nsexit("SMTP SEND CRITICAL - Could not send to $receiver",'CRITICAL');
       }
}
 $smtp->data();
 $smtp->datasend("To: $receiver\n");
 $smtp->datasend("Subject: E-Mail Ping [$serial]\n");
 $smtp->datasend("This is an automatically sent E-Mail.\n".
                 "It is not intended for a human reader.\n\n".
                 "Serial No: $serial\n");

 $smtp->dataend();
 $smtp->quit;
# ) || nsexit("Error delivering message",'CRITICAL');

print STATF "$serial\n";     # remember send mail of this session
close STATF;

# ok - count lost and pending mails;
my @tmp = grep /^ID/, @messageids;
my $pendingm = scalar @tmp;
@tmp = grep /^LI/, @messageids;
my $lostm = scalar @tmp; 

# Evaluate the Warnin/Crit-Levels
if (defined $pendwarn && $pendingm > $pendwarn) { $state = 'WARNING'; }
if (defined $lostwarn && $lostm > $lostwarn) { $state = 'WARNING'; }
if (defined $pendcrit && $pendingm > $pendcrit) { $state = 'CRITICAL'; }
if (defined $lostcrit && $lostm > $lostcrit) { $state = 'CRITICAL'; }

if ((defined $pendwarn || defined $pendcrit || defined $lostwarn 
     || defined $lostcrit) && ($state eq 'UNKNOWN')) {$state='OK';}

printd ("STATUS:\n");
printd ("Found    : $statinfo\n");
printd ("Matching : $matchcount\n");
printd ("Pending  : $pendingm\n");
printd ("Lost     : $lostm\n");
printd ("Mail $serial remembered as sent\n");
printd ("----------------------------------------------------------------------\n");
printd ("----------------------------------------------------------------------\n");
printd ("-------------------------- END DEBUG INFO ----------------------------\n");
printd ("----------------------------------------------------------------------\n");
printd ("----------------------------------------------------------------------\n");

# Append Status info
$statinfo = $statinfo . ", $matchcount mail(s) came back,".
            " $pendingm pending, $lostm lost.";

# Exit in a Nagios-compliant way
nsexit($statinfo);

# ----------------------------------------------------------------------

sub usage {
  print "check_email_loop 1.5 Nagios Plugin - Real check of a E-Mail system\n";
  print "=" x 75,"\nERROR: Missing or wrong arguments!\n","=" x 75,"\n";
  print "This script sends a mail with a specific id in the subject via an given\n";
  print "smtp-server to a given email-adress. When the script is run again, it checks\n";
  print "for this Email (with its unique id) on a given pop3 account and sends \n";
  print "another mail.\n";
  print "\nThe following options are available:\n";
  print	"   -from=text         email adress of send (for mail returnr on errors)\n";
  print	"   -to=text           email adress to which the mails should send to\n";
  print "   -pophost=text      IP or name of the POP3-host to be checked\n";
  print "   -popuser=text      Username of the POP3-account\n";
  print	"   -passwd=text       Password for the POP3-user\n";
  print	"   -poptimeout=num    Timeout in seconds for the POP3-server\n";
  print "   -smtphost=text     IP oder name of the SMTP host\n";
  print "   -smtpuser=text     name of the SMTP user\n";
  print "   -smtppasswd=text   password of the SMTP user\n";
  print "   -smtpport=text     IP oder name of the SMTP host\n";
  print "   -smtptimeout=num   Timeout in seconds for the SMTP-server\n";
  print "   -usesmtpssl        Set this to login with ssl enabled on smtp server\n";
  print "   -usesmtptls        Set this to login with tls enabled on smtp server\n";
  print "   -statfile=text     File to save ids of messages ($statfile)\n";
  print "   -interval=num      Time (in minutes) that must pass by before sending\n";
  print "                      another Ping-mail (give a new try);\n"; 
  print "   -lostwarn=num      WARNING-state if more than num lost emails\n";
  print "   -lostcrit=num      CRITICAL \n";
  print "   -pendwarn=num      WARNING-state if more than num pending emails\n";
  print "   -pendcrit=num      CRITICAL \n";
  print "   -maxmsg=num        WARNING if more than num emails on POP3 (default 50)\n";
  print "   -forgetafter=num   Forget Pending and Lost emails after num days\n";
  print "   -keeporphaned      Set this to NOT delete orphaned E-Mail Ping msg from POP3\n";
  print "   -trashall          Set this to DELETE all E-Mail msg on server\n";
  print "   -usessl            Set this to login with ssl enabled on server\n";
  print "   -debug             send SMTP tranaction info to stderr\n\n";
  print " Options may abbreviated!\n";
  print " LOST mails are mails, being sent before the last mail arrived back.\n";
  print " PENDING mails are those, which are not. (supposed to be on the way)\n";
  print "\nExample: \n";
  print " $0 -poph=host -pa=pw -popu=popts -smtph=host -from=root\@me.com\n ";
  print "      -to=remailer\@testxy.com -lostc=0 -pendc=2\n";
  print "\nCopyleft 19.10.2000, Benjamin Schmid / 2003 Michael Markstaller, mm\@elabnet.de\n";
  print "This script comes with ABSOLUTELY NO WARRANTY\n";
  print "This programm is licensed under the terms of the ";
  print "GNU General Public License\n\n";
  exit $ERRORS{"UNKNOWN"};
}

# ---------------------------------------------------------------------

sub printd {
  my ($msg) = @_;
  if ($debug == 1) {
    print $msg;
  }
}

# ---------------------------------------------------------------------

sub nsexit {
  my ($msg,$code) = @_;
  $code=$state if (!defined $code);
  print "$code: $msg\n" if (defined $msg);
  exit $ERRORS{$code};
}

# ---------------------------------------------------------------------

sub messagematchsid {
  my ($mailref,$id) = (@_);
  my (@tmp);
  my $match = 0;
 
  # ID
  $id =~ s/^LI/ID/;    # evtl. remove lost mail mark
  @tmp = grep /E-Mail Ping \[/, @$mailref;
  chomp @tmp;
  printd ("Comparing Mail content ".$tmp[0]." with Mail ID $id:\n");
  if ($tmp[0] && $id ne "" && $tmp[0] =~ /$id/)
    { $match = 1; }

  # Sender:
#  @tmp = grep /^From:\s+/, @$mailref;
#  if (@tmp && $sender ne "") 
#    { $match = $match && ($tmp[0]=~/$sender/); }

  # Receiver:
#  @tmp = grep /^To: /, @$mailref;
#  if (@tmp && $receiver ne "") 
#    { $match = $match && ($tmp[0]=~/$receiver/); }

  return $match;
}

# ---------------------------------------------------------------------
# utility to load required modules. exits if unable to load one or more of the modules.
sub load_modules {
       my @missing_modules = ();
       foreach( @_ ) {
               eval "require $_";
               push @missing_modules, $_ if $@;
       }
       if( @missing_modules ) {
               print "Missing perl modules: @missing_modules\n";
               return 0;
       }
       return 1;
}

