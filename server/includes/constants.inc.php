<?php
/*****************************************************************************
 *
 *
 *  NRDP Constants
 *
 *
 *  Copyright (c) 2008-2020 - Nagios Enterprises, LLC. All rights reserved.
 *
 *  License: GNU General Public License version 3
 *
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *****************************************************************************/

// PRODUCT INFO
define("PRODUCT_NAME", "nrdp");
define("PRODUCT_VERSION", "2.0.3");

// ERROR STRINGS
define("ERROR_CAPABILITY_NOT_ENABLED","NOT ENABLED");
define("ERROR_NO_INSTANCE","NO INSTANCE");
define("ERROR_BAD_INSTANCE","BAD INSTANCE");

define("ERROR_HTTPS_REQUIRED","HTTPS REQUIRED");
define("ERROR_NOT_AUTHENTICATED","NOT AUTHENTICATED");
define("ERROR_BAD_USER","BAD_USER");

define("ERROR_NO_TOKEN_SUPPLIED","NO TOKEN");
define("ERROR_NO_TOKENS_DEFINED","NO TOKENS");
define("ERROR_BAD_TOKEN_SUPPLIED","BAD TOKEN");
define("ERROR_DENIED_TOKEN", "DENIED TOKEN");

define("ERROR_NO_COMMAND","NO COMMAND");
define("ERROR_DISABLED_COMMAND", "COMMANDS DISABLED");

define("ERROR_NO_COMMAND_FILE","NO COMMAND FILE");
define("ERROR_BAD_COMMAND_FILE","BAD COMMAND FILE");
define("ERROR_COMMAND_FILE_OPEN_WRITE","COMMAND FILE UNWRITEABLE");
define("ERROR_COMMAND_FILE_OPEN","CANNOT OPEN COMMAND FILE");
define("ERROR_BAD_WRITE","WRITE ERROR");
define("ERROR_TEMP_FILE_OPEN","CANNOT OPEN TEMP FILE");

define("ERROR_NO_CHECK_RESULTS_DIR","NO CHECK RESULTS DIR");
define("ERROR_BAD_CHECK_RESULTS_DIR","BAD CHECK RESULTS DIR");

define("ERROR_BAD_FILE","BAD FILE");
define("ERROR_FILE_OPEN_READ","FILE UNREADABLE");
define("ERROR_FILE_OPEN","CANNOT OPEN FILE");

define("ERROR_READ_MAIN_CONFIG","UNABLE TO READ MAIN CONFIG FILE");

define("ERROR_BAD_STATUS_FILE","STATUS FILE DOES NOT EXIT");
define("ERROR_READ_STATUS_FILE","UNABLE TO READ STATUS FILE");

define("ERROR_NO_DATA","NO DATA");
define("ERROR_BAD_XML","BAD XML");
define("ERROR_BAD_JSON","BAD JSON");

// CALLBACKS
define("CALLBACK_PROCESS_REQUEST","PROCESS_REQUEST");

// OUTPUT TYPES
define("TYPE_XML", "TYPE_XML");
define("TYPE_JSON", "TYPE_JSON");
