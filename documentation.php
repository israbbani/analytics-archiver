<?
/**
* Universal Analytics Archive Script Version 1.0
* @author Ibrahim S. Rabbani (rabbanii@carleton.edu)
**/

/**
 *******
 ******* OVERVIEW
 *******
 * The script interfaces with the Google-Analytics-API's PHP Library and generates a time-based backup of your Google-Analytics Data
 * The default setting is set to a Monthly Backup. Because of GA restrictions on the Maximum Number of results that a query can generate
 * the backup is only up to 10,000 results per query. The Backup can be written into a MySQL database
 * Note: The next version will be able to generate backups in either a MySQL database or in a flat-file system using CSV files
 **/

/**
 *******
 ******* FILE STRUCTURE
 *******
 * The script contains the following files
 * Analytics.class.php : A Class that communicates with the GA-API to fetch the data for the relevant date range. You should never have to modify this file for normal use
 * SQLExport.class.php : A Class that writes the data recieved from the Analaytics class to a MySQL database. You should never have to modify this file for normal use
 * CSVExport.class.php (Under Construction) 
 * api-script.php : The script that uses both classes to generate a backup. The header for the file contains some configuration options. 
 * config/apiConfig.php : This file contains the data that is needed to connect to the UA-API
 * config/queryConfig.php : This file contains the data that tells the Analytics.class.php script which querys to run and generate data
 * config/last_backup_date.txt : This file contains the date for the last backup that was generate and is used by both the Analytics class and the SQLExport class. This file is 
 * 								 automatically updated by the api-script.php file after each backup
 * mysql/createtables.sql : This file defines the schema for the MySQL database that will be used to store the backups
 * mysql/db_settings.sql : This file contains configuration information used to connect to the MySQL database by the SQLExport class
 * mysql/droptables.sql : A small script used to drop tables if you want to rebuild the database
 *  
 **/


/**
 *******
 ******* api-script.php
 *******
 * @var BACKUP_TYPE: The value can be set to 'SQL' , 'CSV' or 'BOTH' to define the desired output format of the backup
 * @var EMAIL_ID: The value can be set to an email address to send post-backup information such as error-logs 
 * @var OUTPUT_PATH: The value can be changed to choose the desired output path of the CSV files and the Error Logs
 * The File is configured by default to be only executable through a command-line interface. 
 * This file also contains the error handling for the entire program and generates an error-log.txt file and a backup_report.txt file when done
 */

/**
 *******
 ******* config/apiConfig.php
 *******
 * @var ANALYTICS_ACCOUNTS: The array can be used to store labels for UA account IDS so that the .csv files have the appropriate labels
 * @var ANALYTICS_PROPERTIES: The array can be used to store labels for UA property IDS so that the .csv files have the appropriate labels
 * @var ANALYTICS_PROFILES: The array can be used to store labels for UA profile IDS so that the .csv files have the appropriate labels
 * @var EXCLUDED_PROFILES: The array can be used to exclude certain profiles, such as test profiles, from the backup
 * The last four variables in the file are to store GA-API credentials to be able to successfully connect to the API. 
 * This file is a dependency for the Analytics.class.php file
 **/

/**
 *******
 ******* config/queryConfig.php
 *******
 * @var MAX_RESULTS: Sets the maximum number of results returned by each query. The maximum and the default is 10,000
 * @var QUERY_ARRAY: The array can be used store the relevant information about the queries. This variable is used by both the Analytics class and the SQLExport class 
 * 	                 so the values here must be consistent with the values in mysql/createtables.sql
 * @var CONVERSIONS_ARRAY: The array can be used to store labels for goal conversions so that the .csv files have the appropriate labels
 * 
 **/

/**
 *******
 ******* USAGE
 ******* 
 * The script must be run from a CLI with the command 'php api_script'
 * It requires no parameters and should print out a success message and the total execution time when it is finished
 **/