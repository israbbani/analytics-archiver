<?php

	/**
	 * Immmediate Next Orders of Business:
	 * 
	 * + Logs Errors
	 * + Adds SQL Metadata
	 * + Emails Logs and Updates
	 * 
	 * 
	 **/
	class Analytics { 

	/**
	 * Private Variables
	 **/
	private $dependencies;
	private $analytics_service;
	private $profile_names;
	private $query_results;
	private $query_array;
	private $start_date;
	private $end_date;

	/**
	 * Public Constructor
	 **/
	public function Analytics($startDate, $endDate, $query_array) {
		
		$this->dependencies = array(
		'/usr/local/webapps/branches/ahnjo-apps/reason_package/google-api-php-client/src/Google_Client.php',
		'/usr/local/webapps/branches/ahnjo-apps/reason_package/google-api-php-client/src/contrib/Google_AnalyticsService.php',
		'config/queryConfig.php',
		'config/apiConfig.php'
		);

		$this->checkDependencies();
		$this->analytics_service = $this->createAnalyticsService();
		$this->start_date = $startDate;
		$this->end_date = $endDate;
		$this->query_array = $query_array;
		$this->query_results = array();
		$this->runBackup();
	}
	

	private function checkDependencies() {
		foreach($this->dependencies as $dependency) {
			if(file_exists($dependency)) { 
				require_once($dependency); 
			}
			else { 
				exit('CRITICAL-ERROR: Dependency File Not Found at '.$dependency.' Backup aborted.');
			}
		}
	}

	/**
	* @return object | $service | Google Analytics Service Object used to run queries 
	**/
	private function createAnalyticsService() {
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
		$service = new Google_AnalyticsService($client);

		return $service;
	}

	/**
	 * This generates the backup including the directories that need to be created for the backup.. 
	 **/
	public function runBackup() {

		try { 
	  		$accounts = $this->analytics_service->management_accounts->listManagementAccounts();
		} catch (Exception $e) {
			// array_push($GLOBALS['criticalErrors'],'CRITICAL-ERROR: Analytics API Access Denied. Check config/apiConfig.php. Backup aborted.');
			// criticalErrorOccurred($criticalErrors,$errors);				
			exit('CRITICAL-ERROR: Analytics API Access Denied. Check config/apiConfig.php. Backup aborted.');
		}
		/** Creates an Array of Account Ids **/
		if (count($accounts->getItems()) > 0) {
			$items = $accounts->getItems();
			for($i = 0; $i < count($items); $i++) {

				/** Getting Account Id and Pushing to Array **/	
				$accountId = $items[$i]->getId();  
				$this->query_results[$accountId] = array();

				/** Get Properties for Account ID **/
		    	$webproperties = $this->analytics_service->management_webproperties->listManagementWebproperties($accountId);
				$webproperties = $webproperties->getItems(); /** Arras`` of Web Property Objects **/
				if (count($webproperties) > 0) {
					for ($j = 0; $j < count($webproperties); $j++) {
						$propertyId = $webproperties[$j]->getId();
						$this->query_results[$accountId][$propertyId] = array();

						/** Get Profiles for Property ID **/
						$profiles = $this->analytics_service->management_profiles->listManagementProfiles($accountId, $propertyId);
						$profiles = $profiles->getItems(); /** Array of Profile Objects **/
						
						for($k = 0; $k < count($profiles); $k++) {
							$profileId = $profiles[$k]->getId();

							$this->query_results[$accountId][$propertyId][$profileId] = array();
							
							/** Create Backup Files **/
							foreach ($this->query_array as $query) {
								switch($query['query-type']) {
									
									/** Simple Reports **/
									case('single-csv-single-query'):
									$results = $this->runQuery($query,$profileId);
									$this->query_results[$accountId][$propertyId][$profileId][$query['query-name']] = array();
									array_push($this->query_results[$accountId][$propertyId][$profileId][$query['query-name']],$results);
									// writeToCSV($results,$profileDirectory,$query['query-name']);							
									break;
	
									/** College Metrics Report **/
									case('single-csv-multiple-queries'):
									$results = $this->runMultipleQuery($query,$profileId);
									$this->query_results[$accountId][$propertyId][$profileId][$query['query-name']] = array();
									array_push($this->query_results[$accountId][$propertyId][$profileId][$query['query-name']],$results);																			
									break;

									/** Individual Conversion Reports **/
									case('multiple-csv-multiple-queries'):
									foreach($query as $q) {
										if (gettype($q) == 'array') {
											$results = $this->runQuery($q,$profileId);
											if ($results->getRows() > 0) {									
												// if (isset($GLOBALS['CONVERSIONS_ARRAY'][$profileId]) AND gettype($GLOBALS['CONVERSIONS_ARRAY'][$profileId]) == 'array') { /** Property Id found in Conversions Array **/
													// $goalName =  $q['goal-id'].'_'.$GLOBALS['CONVERSIONS_ARRAY'][$profileId][$q['goal-id']];					
												// } 
												// else { 
													$goalName = $q['goal-id'];
													// echo $goalName;
												// }
													$this->query_results[$accountId][$propertyId][$profileId][$goalName] = array();													
													array_push($this->query_results[$accountId][$propertyId][$profileId][$goalName],$results);
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

	private function runQuery($query,$profileId) {	
		$optParams = $this->createOptionalParams($query);
		return $this->analytics_service->data_ga->get(
	          'ga:'.$profileId, 
	          $this->start_date,
	          $this->end_date,
	          $this->arrayToString($query['metrics']),
	          $optParams
	          );
	}

	private	function runMultipleQuery($queryArray,$profileId) {	
		$output = array();
		foreach ($queryArray as $query) {
			if (gettype($query)=='array') {
				$results = $this->runQuery($query,$profileId); 
				array_push($output,$results);				
				}
			}		
		return $output;
	}

	private function createOptionalParams($query) {
		// var_dump($query);
		$optParams = array();
		if( isset($query['dimensions']) AND $query['dimensions']!='' )
			$optParams['dimensions'] = $this->arrayToString($query['dimensions']);
		if( isset($query['sort']) AND $query['sort']!='' )
			$optParams['sort'] = $query['sort'];
		if( isset($query['filters']) AND $query['filters']!='' )
			$optParams['filters'] = $query['filters'];
		$optParams['max-results'] = MAX_RESULTS;
		return $optParams;
	}

	private function arrayToString($array) {
		$output = '';
		foreach ($array as $string) {
			$output = $output.$string.', ';
		}
		return substr($output,0,count($output)-3);
	}

	public function getResults() { 
		// var_dump($this->query_results);
		return $this->query_results; 
	}
}

?>