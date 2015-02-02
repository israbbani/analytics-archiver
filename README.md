# Universal Analytics-Archiver
###### Version 0.1

## Overview
The script interfaces with the Google-Analytics-API's PHP Library and generates a time-based backup of your Google-Analytics Data
The default setting is set to a Monthly Backup. Because of GA restrictions on the Maximum Number of results that a query can generate the backup is only up to 10,000 results per query. The Backup can be written into a MySQL database

## File Structure
The script contains the following files

**Analytics.class.php** A Class that communicates with the GA-API to fetch the data for the relevant date range. You should never have to modify this file for normal use

**SQLExport.class.php** A Class that writes the data recieved from the Analaytics class to a MySQL database. You should never have to modify this file for normal use

**CSVExport.class.php** (Under Construction) 

**api-script.php** The script that uses both classes to generate a backup. The header for the file contains some configuration options. 

**config/apiConfig.php** This file contains the data that is needed to connect to the UA-API

**config/queryConfig.php** This file contains the data that tells the Analytics.class.php script which querys to run and generate data

**config/last_backup_date.txt** This file contains the date for the last backup that was generate and is used by both the Analytics class and the SQLExport class. This file is automatically updated by the api-script.php file after each backup

**mysql/createtables.sql** This file defines the schema for the MySQL database that will be used to store the backups

**mysql/db_settings.sql** This file contains configuration information used to connect to the MySQL database by the SQLExport class

**mysql/droptables.sql** : A small script used to drop tables if you want to rebuild the database
