<?php

/**
 * Universal Analytics Backup Script
 * @author Ibrahim Rabbani
 *
 * Uses Google Analytics API to create a monthly backup for the selected account using CSV files
 * Backs up data for all accounts, properties and profiles associated with a particular Google Account
 *
 * Queries can be configured at 'config/queryConfig.php'
 * API can be configured at 'config/apiConfig.php'
 * 
 * Script details are logged in 'backups/MONTHLY_BACKUP_NAME/readme.txt'
 * 
 */

/**
* Ensure script can only be run through the command-line
**/
if (php_sapi_name() == 'cli') {

	/**
	* Start Execution Timer
	**/
	$time_start = microtime_float();

	/**
	* Create Arrays to Store Errors
	**/
	$errors = array();
	$criticalErrors = array();

	/**
	* API and Script Dependencies
	**/
	set_include_path('/usr/local/webapps/branches/ahnjo-apps/analytics/api-script/');
	$dependencies = array(
		'/usr/local/webapps/branches/ahnjo-apps/reason_package/google-api-php-client/src/Google_Client.php',
		'/usr/local/webapps/branches/ahnjo-apps/reason_package/google-api-php-client/src/contrib/Google_AnalyticsService.php',
		'config/queryConfig.php',
		'config/apiConfig.php'
		);

	foreach($dependencies as $dependency) {
		if(file_exists($dependency)) { require_once($dependency); }
		else { 
			array_push($criticalErrors,'CRITICAL-ERROR: Dependency File Not Found at '.$dependency.' Backup aborted.');
			criticalErrorOccurred($criticalErrors,$errors);						
			exit('CRITICAL-ERROR: Dependency File Not Found at '.$dependency.' Backup aborted.');
		}
	}


	/** 
	** Check to see if script is connected to internet
	**/
	if(isConnectedtoInternet()) {

		session_start();

		/**
		* Get Dates for Current Backup
		**/ 
		if (file_exists('config/last_backup_date.txt')) { 
			define('OLD_START_DATE',file_get_contents('config/last_backup_date.txt')); 
		} 
		else { 
			array_push($criticalErrors,'CRITICAL-ERROR: Dependency File Not Found at config/last_backup_date.txt. Backup aborted.');
			criticalErrorOccurred($criticalErrors,$errors);				
			exit('CRITICAL-ERROR: Dependency File Not Found at config/last_backup_date.txt. Backup aborted'); 
		}

		/**
		* Define Output Path
		**/
		define('OUTPUT_PATH','backups/'); 

		/**
		* Email Report Settingss
		**/
		define('EMAIL_RECEPIENT','rabbanii@carleton.edu');

		/**
		* This is where the check can be made.
		**/
		if (count($argv) == 1) {
			$newDates = getNextMonth();
			define('START_DATE',$newDates[0]); /* YYYY-MM-DD */
			define('END_DATE',$newDates[1]); /* YYYY-MM-DD */
		} else {
			define('START_DATE',$argv[1]);
			define('END_DATE',$argv[2]);
		}	

		/** 
		* Check to see if backup dates are viable i.e. not set to the future
		**/
		if (backupIsViable()) {
			
			/** 
			* Configure Dates for Backup
			**/
			if (isset($newDates)) { file_put_contents('config/last_backup_date.txt',$newDates[0]); }

			/** 
			* Setup Google Analytics Service Object for Communication with the GA API
			**/
			$ANALYTICS_SERVICE = createAnalyticsService();

			/**
			* Create Backup Directory
			**/
			$backupDirectory = createBackupDirectory();

			/**
			* Run Google Analytics Backup
			**/
			runBackup($ANALYTICS_SERVICE,$backupDirectory,$QUERY_ARRAY);

			/**
			* Create Report File
			**/
			$reportPath = generateReport($backupDirectory);

			/**
			* Finish Backup with completion email and completion message
			**/
			if (count($errors) > 0) { 
				
				/** Generate errorlog **/
				$logPath = generateErrorLog($backupDirectory,$errors);
				
				/** Send Email **/
				$emailMsg = 'GA Backup created with some errors. Please read the attached error-log. This is an automatically generated email.';
				$emailSubject = 'GA Monthly Backup';
				emailReport(array($logPath=>'error-log.txt',$reportPath=>'report.txt'),$emailSubject,$emailMsg);
				
				/** Print Completion Message File **/
				print 'Backup created with some errors for the period '.START_DATE.' to '.END_DATE.".\n";
			
			} 
			else {
				/** Send Email **/
				$emailMsg = 'GA Backup successfully created for the period '.START_DATE.' to '.END_DATE.
							".\n".'Please read the attached report. This is an automatically generated email.';
				$emailSubject = 'GA Monthly Backup';
				emailReport(array($reportPath=>'report.txt'),$emailSubject,$emailMsg);
				/** Print Completion Message File **/
				print 'Backup successfully created for the period '.START_DATE.' to '.END_DATE.".\n";
			}

		} else {
			array_push($criticalErrors,'CRITICAL-ERROR: Backup cannot be created for future dates. Backup Aborted.');	
			criticalErrorOccurred($criticalErrors,$errors);				
			exit('CRITICAL-ERROR: Backup cannot be created for future dates. Backup aborted.');
		}

	} else { 
			array_push($criticalErrors,'CRITICAL-ERROR: No Internet Connection. Backup Aborted.');	
			criticalErrorOccurred($criticalErrors,$errors);				
			exit('CRITICAL-ERROR: No Internet Connection. Backup aborted.');
	}

	/**
	* End Execution Timer
	**/
	$time_end = microtime_float();
	$execution_time = $time_end - $time_start;
	print 'Total Execution Time:'.$execution_time.' Seconds';	

} else { 
	print 'Script cannot be executed through browser';
}

/**
* OPtiMIZED 
* @return
**/
function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

/**
* COUOLD BE OPTIMIZED MAYBE
* @return 
**/
function backupIsViable() { 
	$current_end_date = split('-', date('Y-m-d'));
	$current_month = (int)$current_end_date[1];
	$current_year = (int)$current_end_date[0];
	$current_day = (int)$current_end_date[2];

	$backup_end_date = split('-', END_DATE);
	$backup_month =  (int)$backup_end_date[1];
	$backup_year = (int)$backup_end_date[0];
	$backup_month_numdays = cal_days_in_month(CAL_GREGORIAN,(int)$backup_end_date[1],(int)$backup_end_date[0]);

	if ($backup_year == $current_year){
		if ($backup_month == $current_month){ if($backup_month_numdays > $current_day){ return false; }} 	
		else if ($backup_month > $current_month) { return false; }
	}
	else if ($backup_year > $current_year){ return false; }
	return true;
}


/**
* @return boolean | returns true if connected to the internet, false if not 
**/
function isConnectedtoInternet() {
	$connected = @fsockopen("www.carleton.edu", 80); 
    if ($connected){
        fclose($connected);
        return true;
    }else{
        return false;
    }
}

/**
* @return object | $service | Google Analytics Service Object used to run queries 
**/
function createAnalyticsService() {
	/** 
	* Create and Authenticate Google Analytics Service Object
	**/
	$client = new Google_Client();
	$client -> setApplicationName(GOOGLE_API_APP_NAME);

	/**
	* Makes sure Private Key File exists and is readable. If you get an error, check path in apiConfig.php
	**/
	if(!file_exists(GOOGLE_API_PRIVATE_KEY_FILE)) { 
		array_push($GLOBALS['criticalErrors'],"CRITICAL-ERROR: Unable to find GOOGLE_API_PRIVATE_KEY_FILE p12 file at ".GOOGLE_API_PRIVATE_KEY_FILE);
		criticalErrorOccurred($criticalErrors,$errors);				
		exit("CRITICAL-ERROR: Unable to find GOOGLE_API_PRIVATE_KEY_FILE p12 file at ".GOOGLE_API_PRIVATE_KEY_FILE.' Backup aborted.');
	}
	elseif(!is_readable(GOOGLE_API_PRIVATE_KEY_FILE)) {
		array_push($GLOBALS['criticalErrors'],"CRITICAL-ERROR: Unable to read GOOGLE_API_PRIVATE_KEY_FILE p12 file at ".GOOGLE_API_PRIVATE_KEY_FILE);		
		criticalErrorOccurred($criticalErrors,$errors);				
		exit("CRITICAL-ERROR: Unable to read GOOGLE_API_PRIVATE_KEY_FILE p12 file at ".GOOGLE_API_PRIVATE_KEY_FILE.' Backup aborted.');
	}

	$client -> setAssertionCredentials(
	new Google_AssertionCredentials(
				GOOGLE_API_SERVICE_EMAIL, 
			array('https://www.googleapis.com/auth/analytics.readonly'),
			file_get_contents(GOOGLE_API_PRIVATE_KEY_FILE)
		)
	);

	$client -> setClientId(GOOGLE_API_SERVICE_CLIENT_ID);
	$client -> setUseObjects(true);
	$client->setUseObjects(true);
	$service = new Google_AnalyticsService($client);

	return $service;
}

/**
* @return string | $backupDirectory | path to the directory where backup files and directories will be created
**/
function createBackupDirectory() {
	/** 
	* Create Backup Directory
	**/
	$backupDirectory = OUTPUT_PATH.START_DATE.'_to_'.END_DATE;	
	if (!file_exists($backupDirectory)) {
		mkdir($backupDirectory);
		chmod($backupDirectory, 0775);
	} else { 
		array_push($GLOBALS['errors'],'ERROR: Directory already exists at '.$backupDirectory);
	}
	return $backupDirectory;
}

/**
* NOT OPTIMIZED
* @var constant string | OLD_START_DATE | takes date from confin/last_backup_date.txt 
* modifies the date by pushing it to the next month
* @return array | contains new starting date and ending date for backup
**/
function getNextMonth() {
	$startSplit = explode('-',OLD_START_DATE);
	$prevYear = $startSplit[0];
	$prevMonth = $startSplit[1];

	if ($prevMonth!='12') { 
		$nextYear = $prevYear;	
		$nextMonth = (int)$prevMonth+1;
		if ($nextMonth<10) {
			$nextMonth = '0'.(string)$nextMonth;
		} else {(string)$nextMonth;}
	}
	else { 
		$nextYear = (string)(int)$prevYear+1;
		$nextMonth = '01';
	}
	$days = cal_days_in_month(CAL_GREGORIAN,(int)$nextMonth,(int)$nextYear);
	$new_startDate = $nextYear.'-'.$nextMonth.'-01';
	$new_endDate = $nextYear.'-'.$nextMonth.'-'.(string)$days; 
	return array($new_startDate,$new_endDate);
}

/**
* NOT OPTIMIZED
* @param object | $service | Google Analytics Service Object used to run queries 
* @return array | $CREDENTIALS_ARRAY | associative array Account Ids, Property Ids and Profile IdsS
**/
function runBackup($service,$backupDirectory,$queryArray) {

	try { 
  		$accounts = $service->management_accounts->listManagementAccounts();
	} catch (Exception $e) {
		array_push($GLOBALS['criticalErrors'],'CRITICAL-ERROR: Analytics API Access Denied. Check config/apiConfig.php. Backup aborted.');
		criticalErrorOccurred($criticalErrors,$errors);				
		exit('CRITICAL-ERROR: Analytics API Access Denied. Check config/apiConfig.php. Backup aborted.');
	}
	/** Creates an Array of Account Ids **/
	if (count($accounts->getItems()) > 0) {
		$items = $accounts->getItems();
		for($i = 0; $i < count($items); $i++) {

			/** Getting Account Id and Pushing to Array **/	
			$accountId = $items[$i]->getId();  

			/** Creating Account Directory **/
			$accountDirectory = $backupDirectory.'/'.getName($accountId,'account');
			if (!file_exists($accountDirectory)) {
				mkdir($accountDirectory);
				chmod($accountDirectory, 0775);
			} else { 
				array_push($GLOBALS['errors'],'ERROR: Directory already exists at '.$accountDirectory);
			}

			/** Get Properties for Account ID **/
	    	$webproperties = $service->management_webproperties->listManagementWebproperties($accountId);
			$webproperties = $webproperties->getItems(); /** Array of Web Property Objects **/
			if (count($webproperties) > 0) {
				for ($j = 0; $j < count($webproperties); $j++) {
					$propertyId = $webproperties[$j]->getId();

					/** Create Property Directory **/
					$propertyDirectory = $accountDirectory.'/'.getName($propertyId,'property'); 	
					if (!file_exists($propertyDirectory)) {
						mkdir($propertyDirectory);
						chmod($propertyDirectory, 0775);
					} else { 
						array_push($GLOBALS['errors'],'ERROR: Directory already exists at '.$propertyDirectory);
					}

					/** Get Profiles for Property ID **/
					$profiles = $service->management_profiles->listManagementProfiles($accountId, $propertyId);
					$profiles = $profiles->getItems(); /** Array of Profile Objects **/
					
					for($k = 0; $k < count($profiles); $k++) {
						$profileId = $profiles[$k]->getId();

						if (!in_array($profileId, $GLOBALS['EXCLUDED_PROFILES'])) { 

							/** Create Profile Directory **/
							$profileDirectory = $propertyDirectory.'/'.getName($profileId,'profile');
							if (!file_exists($profileDirectory)) {
								mkdir($profileDirectory);
								chmod($profileDirectory, 0775);
							} else { 
								array_push($GLOBALS['errors'],'ERROR: Directory already exists at '.$profileDirectory);
							}

							/** Create Backup Files **/
							foreach ($queryArray as $query) {
								switch($query['query-type']) {
									/** Simple Reports **/
									case('single-csv-single-query'):
									$results = runQuery($service,$query,$profileId);
									writetoCSV($results,$profileDirectory,$query['query-name']);							
									break;
									/** College Metrics Report **/
									case('single-csv-multiple-queries'):
									$outputFile = $profileDirectory.'/'.$query['query-name'];
									createMultipleQuerySingleCSV($query,$service,$outputFile,$profileId);									
									break;
									/** Individual Conversion Reports **/
									case('multiple-csv-multiple-queries'):
									foreach($query as $q) {
										if (gettype($q) == 'array') {
											$results = runQuery($service,$q,$profileId);
											if ($results->getRows() > 0) {									
												if (isset($GLOBALS['CONVERSIONS_ARRAY'][$profileId]) AND gettype($GLOBALS['CONVERSIONS_ARRAY'][$profileId]) == 'array') { /** Property Id found in Conversions Array **/
													$goalName =  $q['goal-id'].'_'.$GLOBALS['CONVERSIONS_ARRAY'][$profileId][$q['goal-id']];
												} 
												else { 
													$goalName = $q['goal-id'].'_backup';
													array_push($GLOBALS['errors'],'ERROR: goal name not found for '.$q['goal-id'].' for the profile: '.getName($profileId,'profile'));												
												}
												writetoCSV($results,$profileDirectory,$goalName);	
											}						
										}
									}
									break;
								}
							}
						}
					}
				}
			}
		}
    }
}

/**
* OPtiMIZED
* @param
* @param
* @return
**/
function getName($id,$case) {
	switch($case) {	
		case 'account':			
			if(in_array($id, array_keys($GLOBALS['ANALYTICS_ACCOUNTS'])))
				return $GLOBALS['ANALYTICS_ACCOUNTS'][$id];
			else 
				/** ERROR LOGGED **/
				array_push($GLOBALS['errors'], 'ERROR: Name not found for GA Account-Id: '.$id);
				return $id;
			break;

		case 'property':			
			if(in_array($id, array_keys($GLOBALS['ANALYTICS_PROPERTIES'])))
				return $GLOBALS['ANALYTICS_PROPERTIES'][$id];
			else 
				array_push($GLOBALS['errors'], 'ERROR: Name not found for GA Property-Id: '.$id);
				return $id;
			break;

		case 'profile':			
			if(in_array($id, array_keys($GLOBALS['ANALYTICS_PROFILES'])))
				return $GLOBALS['ANALYTICS_PROFILES'][$id];
			else 
				array_push($GLOBALS['errors'], 'ERROR: Name not found for GA Profile-Id: '.$id);
				return $id;
			break;		
	}
}
 
/**
* OPTIMIZED
* @param object | $service | Google Analytics Service Object used to run queries 
* @param array | $query | Associative array passed on from config/queryConfig.php. It is a part of the the full $QUERY_ARRAY
* @return object | Google Analytics Profile Object used to extract profile information and query data
**/
function runQuery($service,$query,$profileId) {	
	$optParams = createOptionalParams($query);
	return $service->data_ga->get(
          'ga:'.$profileId, 
          START_DATE, 
          END_DATE, 
          $query['metrics'],
          $optParams
          );
}

/** 
* OPTIMIZED
* @param array | $query | Associative array passed on from config/queryConfig.php. It is a part of the the full $QUERY_ARRAY
* @return array | contains the pending query's dimension, filter, sort and maximum result values
**/
function createOptionalParams($query) {
	$optParams = array();
	if( isset($query['dimensions']) AND $query['dimensions']!='' )
		$optParams['dimensions'] = $query['dimensions'];
	if( isset($query['sort']) AND $query['sort']!='' )
		$optParams['sort'] = $query['sort'];
	if( isset($query['filters']) AND $query['filters']!='' )
		$optParams['filters'] = $query['filters'];
	$optParams['max-results'] = MAX_RESULTS;
	return $optParams;
}

/** 
* UNOPTIMIZED
* @param array | $queryArray | Associative array passed on from config/queryConfig.php. It is a part of the the full $QUERY_ARRAY
* @param object | $service | Google Analytics Service Object used to run queries 
* @param string | $filePath | path of the output file
**/
function createMultipleQuerySingleCSV($queryArray,$service,$filePath,$profileId) {	
	$fileName = $filePath.'.csv';
	if (!file_exists($fileName)) {
		$fp = fopen($fileName, 'w');				
		foreach ($queryArray as $query) {
			if (gettype($query)=='array') {
				$results = runQuery($service,$query,$profileId); 
				$data = $results->getRows();
				$columnHeaders = $results->getColumnHeaders();					
				if (count($data)>0) {
					for($i=0;$i<count($columnHeaders);$i++) {$columnHeaders[$i] = $columnHeaders[$i]->getName();}
					fputcsv($fp,$columnHeaders,',');
					foreach ($data as $field) { fputcsv($fp,$field,',');}
				}
			}		
		}
		fclose($fp);	
	}
}

/**
* OPTIMIZED
* @param GoogleAnalyticsProfileObject | $results | Used to extract profile information and query data
* @param string | $directory | Name of output directory
* @param string | $queryNam     	e | Name of output file
**/
function writeToCSV($results,$directory,$queryName) {	
	$data = $results->getRows();
	$columnHeaders = $results->getColumnHeaders();
	$fileName = $directory.'/'.$queryName.'.csv';
	if (!file_exists($fileName)) {
		$fp = fopen($fileName, 'w');				
		if (count($data)>0) {
			for($i=0;$i<count($columnHeaders);$i++) {$columnHeaders[$i] = $columnHeaders[$i]->getName();}
			fputcsv($fp,$columnHeaders,',');
			foreach ($data as $field) { fputcsv($fp,$field,',');}
		}
		fclose($fp);
	} else { 
		array_push($GLOBALS['errors'],'ERROR: Directory already exists at '.$fileName.' Did not overwrite file.');
	}
}

/** 
* @param array | $ANALYTICS_PROFILES | Associative array containing Google Analytics Profile Ids passed on from config/apiConfig.php
* @param string | $directory | Name of the output directory
**/
function generateReport($directory) {
	$outputPath = $directory.'/'.'report.txt';
	$output = 'Readme File for Universal Anayltics Backup on '.date('m-d-Y')."\n".
	'This backup was for the period '.START_DATE.' to '.END_DATE;
	file_put_contents($outputPath,$output);
	return $outputPath;
}

/**
* @param array  | $errorArray | stores all errors
* @param string | $error 	  | error to be logged
**/
function generateErrorLog($directory,$errorsArray) {
	$output = '';
	$outputPath = $directory.'/'.'error-log.txt';	
	foreach ($errorsArray as $error) {
		$output = $output.$error."\n";
	}
	file_put_contents($outputPath,$output);

	return $outputPath;
}

/**
* @param array  | $errorArray | stores all errors
* @param string | $error 	  | error to be logged
**/
function criticalErrorOccurred($criticalErrors,$errors) {
	$directory = createBackupDirectory();
	$logPath = generateErrorLog($directory,$errors);
	
	$logFile = fopen($logPath, 'a');
	foreach($criticalErrors as $error){ fwrite($logFile,$error); }
	fclose($logFile);
	
	$attachments = array(
		$logPath => 'error-log.txt',
	);
	$emailSubject = 'GA-Backup-Script: CRITICAL ERROR';
	$emailMsg = 'GA Monthly Backup Failed for the period '.START_DATE.' to '.END_DATE.'. Please review the attached error-log. 
	This is an automatically generated email';
	emailReport(array($logPath=>'error-log.txt'),$emailSubject,$emailMsg);
}

/** 
* @param | array | $attachments
*
**/
function emailReport($attachments,$subject,$message) {
	$to = EMAIL_RECEPIENT;
	$from = 'GA-Backup-Script';

	// Define any additional headers you may want to include
	$headers = array(
	);

	$status = mailAttachments($to, $from, $subject, $message, $attachments, $headers);
}

/** 
* @param | array | $attachments
* @param | array | $attachments
* @param | array | $attachments
* @param | array | $attachments
* @param | array | $attachments
* @param | array | $attachments
* @param | array | $attachments
**/
function mailAttachments($to, $from, $subject, $message, $attachments = array(), $headers = array(), $additional_parameters = '') {
	$headers['From'] = $from;

	// Define the boundray we're going to use to separate our data with.
	$mime_boundary = '==MIME_BOUNDARY_' . md5(time());

	// Define attachment-specific headers
	$headers['MIME-Version'] = '1.0';
	$headers['Content-Type'] = 'multipart/mixed; boundary="' . $mime_boundary . '"';

	// Convert the array of header data into a single string.
	$headers_string = '';
	foreach($headers as $header_name => $header_value) {
		if(!empty($headers_string)) {
			$headers_string .= "\r\n";
		}
		$headers_string .= $header_name . ': ' . $header_value;
	}

	// Message Body
	$message_string  = '--' . $mime_boundary;
	$message_string .= "\r\n";
	$message_string .= 'Content-Type: text/plain; charset="iso-8859-1"';
	$message_string .= "\r\n";
	$message_string .= 'Content-Transfer-Encoding: 7bit';
	$message_string .= "\r\n";
	$message_string .= "\r\n";
	$message_string .= $message;
	$message_string .= "\r\n";
	$message_string .= "\r\n";

	// Add attachments to message body
	foreach($attachments as $local_filename => $attachment_filename) {
		if(is_file($local_filename)) {
			$message_string .= '--' . $mime_boundary;
			$message_string .= "\r\n";
			$message_string .= 'Content-Type: application/octet-stream; name="' . $attachment_filename . '"';
			$message_string .= "\r\n";
			$message_string .= 'Content-Description: ' . $attachment_filename;
			$message_string .= "\r\n";

			$fp = @fopen($local_filename, 'rb'); // Create pointer to file
			$file_size = filesize($local_filename); // Read size of file
			$data = @fread($fp, $file_size); // Read file contents
			$data = chunk_split(base64_encode($data)); // Encode file contents for plain text sending

			$message_string .= 'Content-Disposition: attachment; filename="' . $attachment_filename . '"; size=' . $file_size.  ';';
			$message_string .= "\r\n";
			$message_string .= 'Content-Transfer-Encoding: base64';
			$message_string .= "\r\n\r\n";
			$message_string .= $data;
			$message_string .= "\r\n\r\n";
		}
	}

	// Signal end of message
	$message_string .= '--' . $mime_boundary . '--';

	// Send the e-mail.
	return mail($to, $subject, $message_string, $headers_string, $additional_parameters);
}

?>