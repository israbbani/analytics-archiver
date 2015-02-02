<?php

/**
 * LATEST UPDATE 01/08/15
 * Not working when all reports run together
*/

/**
 * Goals for Today:
 * Run for All Tables
 * Takes in Backup Dates
 * Clean up constructor
 **/

/**
* Create error handling 
* Creates a log file (in the first instance)
* Keeps writing to it and sends out an email
*/

class SQLExport {

	/**
	 * Class Properties
	 **/
	private $dependencies;
	private $sqlObject;
	private $UAResults;
	private $query_array;
	private $UAIds;
	private $start_date;
	private $end_date;

	/**
	 * Class Constructor
	 * @param UAObject Universal Analytics object containing results 
	 **/
	public function SQLExport($analyticsObject,$query_array,$startDate,$endDate) {
		$this->dependencies = array(
			'mysql/db_settings.php',
			'config/queryConfig.php',
			'config/apiConfig.php'
			);
		// This is the QUERRY_ARRAY from config/queryConfig.php
		$this->query_array = $query_array;
		// check to see if all dependencies have loaded
		$this->checkDependencies();	
		// initializes sqlObject
		$this->connectToSql();
		$this->start_date = $startDate;
		$this->end_date = $endDate;
		// Set UA Object from Constructor Param
		// mysqli_query($this->sqlObject, "INSERT INTO backup_list (backup_id,backup_start,backup_end) VALUES (1,'2014-01-01','2014-01-31')");
		$this->UAResults = $analyticsObject;
		$this->UAIds = $this->getAllUAIds();
		$this->MySQLTransaction();
		// echo var_dump($this->UAIds);	
		// $staticParams = $this->generateStaticQueryParams(1,$this->UAIds[1]);
		// $data = $this->UAResults[$this->UAIds[1][0]][$this->UAIds[1][1]][$this->UAIds[1][2]][$this->UAIds[1][3]][0]->getRows();
		// echo $data;
		// for($i=0;$i<10;$i++) {
			// $this->addRow($staticParams,$data[$i]);
			// mysqli_query($this->sqlObject,$this->addRow($staticParams,$data[$i]));
		// }
	}

	/**
	 * Class Methods
	 **/
	private function connectToSql() {
		try {
			$this->sqlObject =mysqli_connect(MYSQL_HOST,MYSQL_USER,MYSQL_PASSWORD,MYSQL_DB_NAME);
			print "Connected<BR>";
		} catch (Exception $e) {
			// ERROR LOGGING NEEDED HERE
			echo 'Connection Error: '.$e;
		}
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
	 * Writes all results to MySQL Database
	 * Uses the UAIds array and the results array to iterate over the data and 
	 **/
	private function MySQLTransaction() { 
		
		try {
			// Turn Begin Transaction
		    $this->sqlObject->autocommit(FALSE);
		    // Create Entry in Metadata Table
		    $result = mysqli_query($this->sqlObject, "INSERT INTO backup_list(backup_start,backup_end) VALUES ('".$this->start_date."','".$this->end_date."')");
			if (!$result) {
		        	// $result->free();
		       	throw new Exception($this->sqlObject->error);
		   	}
		   	$backupIndex = $this->getBackupIndex();
		  	// Iterate over each UID: i.e. each backup query
		  	foreach ($this->UAIds as $backupQuery) {
		  		$staticParams = $this->generateStaticQueryParams($backupIndex,$backupQuery);
		  		// This is a special-case for college-communication-metrics
		  		if (!strcmp($backupQuery[3],'college_comm_metrics_report')) {
		  			if (!strcmp($this->UAResults[$backupQuery[0]][$backupQuery[1]][$backupQuery[2]][$backupQuery[3]][0][0]->profileInfo->profileName,'No filter')) {
			  			$data = array();
			  			foreach ($this->UAResults[$backupQuery[0]][$backupQuery[1]][$backupQuery[2]][$backupQuery[3]][0] as $college_comm_result) {
			  				$temp = $college_comm_result->getRows();
			  				array_push($data,end($temp[0]));
			  			}
			  			// echo $this->addRow($staticParams,$data);
			  			mysqli_query($this->sqlObject,$this->addRow($staticParams,$data));
			  		}
		  		} else {
					$data = $this->UAResults[$backupQuery[0]][$backupQuery[1]][$backupQuery[2]][$backupQuery[3]][0]->getRows();
					for($i=0;$i<sizeof($data);$i++) {
						// $this->addRow($staticParams,$data[$i]);
						mysqli_query($this->sqlObject,$this->addRow($staticParams,$data[$i]));
						// if (!strcmp($backupQuery[3],"mobiles_devices_report"))
							// echo $this->addRow($staticParams,$data[$i])."<BR>";
					}
				}
		  	}
		    // our SQL queries have been successful. commit them
		    // and go back to non-transaction mode.
		    $this->sqlObject->commit();
		    $this->sqlObject->autocommit(TRUE); // i.e., end transaction

		}
		catch ( Exception $e ) {
		    // before rolling back the transaction, you'd want
		    // to make sure that the exception was db-related
		    $this->sqlObject->rollback(); 
		    $this->sqlObject->autocommit(TRUE); // i.e., end transaction   
	       	throw new Exception($this->sqlObject->error);

		}
	}

	/**
	 * @return array of form: array(accountId profileId viewId queryName)
	 **/
	private function getAllUAIds() {
		$UAIds = array();
		foreach (array_keys($this->UAResults) as $account) {
			foreach (array_keys($this->UAResults[$account]) as $profile) {
				foreach (array_keys($this->UAResults[$account][$profile]) as $view) {
					foreach(array_keys($this->UAResults[$account][$profile][$view]) as $query) {
						array_push($UAIds,array($account,$profile,$view,$query));
					}
				}
			}
		}
		return $UAIds;
	}
	/**
	 * @return array with: backup-index accountId profileId viewId queryName 
	 **/
	private function  generateStaticQueryParams($backupIndex ,$UAId) {
		$staticParams = array(
			'id' => $backupIndex,
			'account_id' => $UAId[0],
			'profile_id' => $UAId[1],
			'view_id' => $UAId[2],
			'table_name' => $UAId[3],
			'backup_start' => $this->start_date,
			'backup_end' => $this->end_date
		);
		return $staticParams;
	}

	/**
	 * @return string with all the elements of the array
	 **/
	private function getOtherParams($array) {
		$output = "";
		for ($i = 0; $i < count($array); $i++) { 
			$output = $output.substr($array[$i],3).',';
		}
		$output = substr($output,0,strlen($output)-1);
		return $output;
	}

	/**
	 * Needs Static Params - DONE
	 * Needs Dimensions and Metrics - From Original QueryArray(Will pass as param)1
	 * Needs the actual data to be written from the results object
	 **/
	private function addRow($staticParams,$row) {
		$sqlQuery = "INSERT INTO ".$staticParams['table_name']."(id,account_id,profile_id,view_id,backup_start,backup_end,";
		// special case for college-comms-metrics
		$otherParams = '';
		if (!strcmp($staticParams['table_name'],'college_comm_metrics_report')) {
			$otherParams = "total_visits,homepage_visits,news_visits,athletics_visits,alumni_visits,admissions_visits";
		}
		// special case for specific goal conversion reports
		else if (strpos($staticParams['table_name'],'goal') !== False ) {
			foreach ($this->query_array['specific-conversions-report'] as $goal) {
				if (gettype($goal) == 'array') {
					if (!strcmp($goal['goal-id'],$staticParams['table_name'])) {
						$otherParams = $this->getOtherParams($goal['dimensions']).','.$this->getOtherParams($goal['metrics']);
					}
				}
			}
		}
		else {
			$otherParams = $this->getOtherParams($this->query_array[$staticParams['table_name']]['dimensions']).','.$this->getOtherParams($this->query_array[$staticParams['table_name']]['metrics']);
		}

		$sqlQuery = $sqlQuery.$otherParams.")"."VALUES (".$staticParams['id'].",".$staticParams['account_id'].",'".$staticParams['profile_id']."',".$staticParams['view_id'].",'".$staticParams['backup_start']."','".$staticParams['backup_end']."'";
		$columns = "";

		foreach ($row as $column) {
			$column = mysql_real_escape_string($column);
			if (ctype_digit($column)) { 
				$columns = $columns.",".$column; 
			} 
			else { 
				$temp = str_replace(".","",$column);
				if (ctype_digit($temp)) {
					$columns = $columns.",".$column; 
				} else {
					$columns = $columns.",'".$column."'"; 
				}
			}
		}
		$sqlQuery = $sqlQuery.$columns.")";
		return $sqlQuery;
	}	

	private function getBackupIndex() {
		$result = mysqli_query($this->sqlObject, "select max(backup_id) from backup_list");
		$row = mysqli_fetch_array($result);
		return $row['max(backup_id)'];	
	}

	public function writeOutput() {
		$data = $this->UAResults->getRows();
		$index = generateBackupIndex($this->sqlObject);
		$staticParams = generateStaticQueryParams('traffic_sources_report',$backupIndex,$startDate,$endDate);
	}
}