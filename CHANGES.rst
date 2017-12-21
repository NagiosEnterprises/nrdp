1.5.0 -
------------------
- Added rudimentary debugging to nrdp plugins (#27) -BH
- Added ability to receive JSON check results (#23) -Box293 (Troy)
- Added documentation for --usestdin and --delim (#17) -BH
- Added ability to specify a delimiter when using stdin (#17) -BH
- Fixed submission of check results when check result dir is in (or is) a symlink (#25) -BH

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
