<?php

/**
 * Universal Analytics Backup Script
 * @author Ibrahim Rabbani
 *
 * Use Google Analytics API to create a monthly backup for the selected account using CSV files
 * Backs up data for all accounts, properties and profiles associated with a particular Google Account
 *
 * Queries can be configured at 'config/queryConfig.php'
 * API can be configured at 'config/apiConfig.php'
 * 
 * Script details are logged in 'backups/MONTHLY_BACKUP_NAME/readme.txt'
 * 
 */
	class AnalyticsArchiver {

		/**
		 * Class Variables. Shouldn't have to change these
		 **/
		public $ERRORS_ARRAY;
		public $CRITICAL_ERROR;
		private $UAObject;
		private $QUERY_ARRAY;
		private $backupDirectory;
		/**
		 * @todo add CSVExport once finished writing module
		 **/
		public $DEPENDENCY_ARRAY;

		/**
		 * Public constructor for the class
		 * This is where all of the action happens
		 **/
		public function AnalyticsArchiver() {

			/** 
			* Configuration Options
			* These are alterable
			* @todo implement SQL vs CSV backup configuration option
			**/
			define('EMAIL_ID','rabbanii@carleton.edu');
			define('OUTPUT_PATH','backups/');			
		
			$this->DEPENDENCY_ARRAY = array(
										'config/queryConfig.php',
										'Analytics.class.php',
										'SQLExport.class.php',
										'config/last_backup_date.txt'
									);
			$this->checkDependencies();	
			$this->ERRORS_ARRAY = array();
			$this->CRITICAL_ERROR = '';

			/**
			* Ensure script can only be run through the command-line
			**/
			if (php_sapi_name() == 'cli') {	
				/**
				* Start Execution Timer
				**/
				$time_start = microtime_float();				
				/** 
				** Check to see if script is connected to internet
				**/
				if($this->isConnectedtoInternet()) {		
					/**
					* Retrieve dates from config/last_backup_date.txt
					**/ 
					define('OLD_START_DATE',file_get_contents('config/last_backup_date.txt')); 
					/**
					 * Generate dates for new backup
					 * @todo change from a monthly increment to a configurable increment
					 * NOTE: modified from original (removed checks)
					 **/
					$newDates = $this->getNextMonth();
					define('START_DATE',$newDates[0]); /* YYYY-MM-DD */
					define('END_DATE',$newDates[1]); /* YYYY-MM-DD */
					/** 
					* Make sure you're not backing up for dates that are in the future
					**/
					if ($this->backupIsViable()) {
						/** 
						* Write new dates out to the file config/last_backup_dates.txt
						* @todo this should happen at the end of the script. Move later
						**/
						if (isset($newDates))
							file_put_contents('config/last_backup_date.txt',$newDates[0]);
						/**
						* Create Backup Directory
						**/
						$this->backupDirectory = $this->createBackupDirectory();
						/** 
						* Create UA Object and get Results
						* @todo document the format of the results (nested array structure)
						**/
						// $UAObject = new Analytics(START_DATE,END_DATE,$GLOBALS['QUERY_ARRAY']);
						// $UAResults = $UAObject->getResults();
						// var_dump($UAResults);
						/** 
						* Write Results to MySQL
						* @todo change SQLExport constructor so this isn't done in one swoop
						* @todo clean up SQLExport and Optimize
						**/
						// $SQLObject = new SQLExport($UAResults,$GLOBALS['QUERY_ARRAY'],START_DATE,END_DATE);						
						/**
						* Create Report File
						**/
						$reportPath = $this->generateReport();
						/**
						* Check for Errors
						* @todo ideally it should generate an error log regardless with different info
						* @todo the else should also do something more sophisticated than just echo
						**/
						if (count($this->ERRORS_ARRAY) > 0) 
							$logPath = $this->generateErrorLog();
						else 
							echo 'Backup Complete with No Errors';
						/** 
						* Send Email about backup 
						* @todo the emailReport and attachment functions are black boxes. Figure out
						* @todo credit stackoverflow code
						* @todo add more useful information to the actual emails
						**/
						$emailMsg = 'GA Backup created with some errors. Please read the attached error-log. This is an automatically generated email.';
						$emailSubject = 'GA Monthly Backup';
						$this->emailReport(array($logPath=>'error-log.txt',$reportPath=>'report.txt'),$emailSubject,$emailMsg);
						/**
						* End Execution Timer
						* @todo change the placement of this and add it to the report/log
						**/
						$time_end = microtime_float();
						$execution_time = $time_end - $time_start;
						print 'Total Execution Time:'.$execution_time.' Seconds';	
					/**
					* If backup is note viable
					**/
					} else {
						$this->CRITICAL_ERROR = 'Backup cannot be created for future dates.'
						$this->criticalErrorOccured();
					}
				/**
				 * If not connected to the internet
				 **/
				} else {
					$this->CRITICAL_ERROR = 'Backup cannot be created. Check Internet Connection';
					$this->criticalErrorOccured();
				}
			/**
			 * If script run from browser
			 **/
			} else {
				exit('Script Cannot be Executed Through Browser');
			}
		}



		/**
		 * Checks Dependencies of the class and loads them if present
		 * @throws criticalErrorOccured() if dependencies not found
		 * @todo add a check for SQL vs CSV in next version
		 **/
		public function checkDependencies() { 
			foreach($this->DEPENDENCY_ARRAY as $dependency) {
				if(file_exists($dependency)) { 
					require_once($dependency); 
				} else { 
					$this->CRITICAL_ERROR = 'CRITICAL-ERROR: Dependency File Not Found at '.$dependency.' Backup aborted.';
					$this->criticalErrorOccured();
				}
			}
		}
		/**
		 * Exits script if a critical error occurs
		 * Writes a report, a log and generates an email to the configured email address
		 * @todo write report, log and email
		**/
		public function criticalErrorOccured() {
			#writeLogs($OUTPUT_PATH);
			#writeReport($OUTPUT_PATH);
			exit("BACKUP WAS ABORTED. CRITICAL ERROR OCCURED: ".$this->CRITICAL_ERROR);
		}
		/**
		 * Calculates total execution time for script
		 * @return time taken to execute in seconds
		 **/
		function microtime_float() {
		    list($usec, $sec) = explode(" ", microtime());
		    return ((float)$usec + (float)$sec);
		}		
		/**
		* @return boolean | returns true if connected to the internet, false if not 
		* @todo ping google analytics server instead of carleton.edu	
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
		 * Uses the last backup date to get the correct start and end dates for next month
		 * @return array containing a start and end date for the new backup
		 * @todo change increment from monthly to custom date range
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
		* Checks to see if backup dates are not in the future
		* @return true if dates are correct, false if not
		* @todo optimize function
		* @todo make it compatible with dynamic date ranges
		**/
		function backupIsViable() { 
			date_default_timezone_set('America/Chicago');
			$current_end_date = split('-', date('Y-m-d'));
			// $current_end_date = split('-', date('Y-m-d'));
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
		* @return path to the directory where backup files and directories will be created
		**/
		function createBackupDirectory() {
			$backupDirectory = OUTPUT_PATH.START_DATE.'_to_'.END_DATE;	
			if (!file_exists($backupDirectory)) {
				mkdir($backupDirectory);
				chmod($backupDirectory, 0775);
			} else { 
				$this->CRITICAL_ERROR = 'Directory already exists at '.$backupDirectory;
				$this->criticalErrorOccured();
			}
			return $backupDirectory;
		}
		/** 
		* @return the path to the report (used for email attachments)
		* @todo add more information that is useful to the report
		* @todo add try catch block to file_put_contents with errors
		**/
		function generateReport() {
			$outputPath = $this->backupDirectory.'/'.'report.txt';
			$output = 'Readme File for Universal Anayltics Backup on '.date('m-d-Y')."\n".
			'This backup was for the period '.START_DATE.' to '.END_DATE;
			file_put_contents($outputPath,$output);
			return $outputPath;
		}
		/** 
		* @return the path to the errorlog (used for email attachments)
		* @todo add more information that is useful to the error log (like timestamps)
		* @todo add try catch block to file_put_contents with errors
		**/		
		function generateErrorLog() {
			$output = '';
			$outputPath = $this->backupDirectory.'/'.'error-log.txt';	
			foreach ($this->ERRORS_ARRAY as $error) {
				$output = $output.$error."\n";
			}
			file_put_contents($outputPath,$output);
			return $outputPath;
		}
		/**
		* Sends email to the configured email address with error logs and report
		**/
		function emailReport($attachments,$subject,$message) {
			$to = EMAIL_ID;
			$from = 'GA-Backup-Script';

			// Define any additional headers you may want to include
			$headers = array(
			);

			$status = $this->mailAttachments($to, $from, $subject, $message, $attachments, $headers);
		}
		/**
		* Method used to add attachments to the email
		* @todo credit stackoverflow post
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
	}

	$Archiver = new AnalyticsArchiver();
?>