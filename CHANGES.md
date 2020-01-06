2.0.3 - 01/06/2020
------------------
- Fixed issue with require_https exiting improperly when set to true -JO

2.0.2 - 07/18/2019
------------------
- Fixed issue with passive check timestamp not being applied properly -SW
- Fixed error with missing have_value() function when using nrds with nrdp -JO

2.0.1 - 07/16/2019
------------------
- Fixed issue where xml data after decode would not pass valid data check -JO

2.0.0 - 06/20/2019
------------------
- Added the config option to hide the index.php submit commands page -JO
- Updated license to GPLv3 (#32) -BH,EG
- Updated default location of alert on the main index.php when running checks -JO
- Updated to use json and xml including JSONDATA and XMLDATA -BH
- Fixed debug_log variable not being used (#36) -JO

1.5.2 - 04/02/2018
------------------
- Set executable bits on send_nrdp.php and send_nrdp.sh (#29) -tjyang
- Added debugging to check_token() and check_auth() even in failure cases -BH
- Moved rst files to md files for project consistency -BH
- Updated README -Box293

1.5.1 - 12/27/2017
------------------
- Fixed an issue where posix_getgrnam() isn't found (#28) -BH

1.5.0 - 12/22/2017
------------------
- Added rudimentary debugging to nrdp plugins (#27) -BH
- Added ability to receive JSON check results (#23) -Box293 (Troy)
- Added documentation for --usestdin and --delim (#17) -BH
- Added ability to specify a delimiter when using stdin (#17) -BH
- Added granular command denial mechanism for executing external commands (#25) -BH
- Added a little bit of bootstrap and jQuery to make the NRDP experience a bit nicer -BH
- Changed to AJAX based submission -BH
- Added usable page hashes (/nrdp/#json, /nrdp/#xml, /nrdp/#command) -BH
- Added easier way to customize your page defaults (in index.php variables) -BH
- Fixed submission of check results when check result dir is in (or is) a symlink (#13) -BH

1.4.0 - 01/06/2017
------------------
- Added option to callback function for prepending instead of appending to callback array (added by tmcnag) -JO
- Updated send_nrdp.sh to the latest revision -JO
- Updated with code for injecting directly into NDO if sent past check results -JO
- Fixed issue with send_nrdp.php not respecting ports (patched by ericloyd) -JO
- Fixed issue where check_results_dir is not writeable and they get written to /tmp and gives errors instead -JO

1.3.1 - 01/22/2015
------------------
- Added checks for function calls that are already in XI -JO
- Fixed issue with syntax in php config script -JO

1.3.0 - 10/10/2013
------------------
- Added the ability to disabled external commands in the config - NS

1.2.2 - 06/28/2012
------------------
- Changed to add support for multi-line output -SW

1.2.1 - 02/08/2012
------------------
- Added bash and python clients - SW
- Bash client can process STDIN, args, single NRDP formatted file or from directory of NRDP formatted files. -SW

1.2.0 - 01/31/2011
------------------
- Added bulk transfer mode when reading NSCA-style data from STDIN (--usestdin option)
- Fixed bug where debug statements where on by default

1.1.0 - 12/15/2010
------------------
- Fixed syntax errors in server index.php file (Jean-François Burdet)
- Fixed problem with passive checks not having proper timestamps (Jean-François Burdet)

1.0.0 - 07/30/2010
------------------
- Initial release
