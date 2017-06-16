<?php

/*******************************************************************************
 * CONSTANTS
 */	

define("EXPECTED_FIELDS", 19);

define("VALID_ORGS", 
	"AGO,BBC,BBC,BLU,BNH,BRM,BTP,CJB,CLK,CMS,COO,CPS,CSY,DGF,DOH,DRA,ECE,EMA,EPC,FAM,FCR,FFA,FPR," . 
	"FLA,FLL,FLP,FSF,FTT,HMI,HOL,HOM,HSE,HWP,IPC,ING,ITV,JWR,LCA,LCL,LCL,LCS,LDC,LFC,LHA,LHC,LJT," . 
	"LLS,LMC,LMO,LPC,LVH,MFL,MHA,MOJ,MPA,MSP,NCC,NFF,NGN,NSW,PCC,PRE,PSA,RCX,SCC,SCH,SDH,SFR,SHC," . 
	"SHS,SJA,SPA,SPP,STH,SWF,SYC,TAC,THC,TNA,TPF,TRH,TSO,TTA,WMP,WYC,YAS,SYP,PLM,SFA");

class DisclosedRecord
{
	
/*******************************************************************************
 * PROTECTED VARIABLES
 */	
	protected $av_formats = array("audio racal tape","audio tapes", "cassette tape","vhs tapes","video","audio","audio - cassette","audio cassette","audio-cassette","audio tapes",
			"cassette audio tape","racal tape","video - umatic","video - vhs","video cassette (vhs)" );
	
	protected $photo_formats = array ("transparency","photographic negatives","negative 35mm","photographs","photograph","photographic negative","photographic slide" );
	protected $standard_formats = array( "computer tape","floppy disk","coins","item", "paper","photocopy","paper;photocopy","paper and objects","paper;photograph" );
	protected $record;				
	protected $corruptRecord = false;
	protected $validRecord = true;
	protected $errorMessage = "";
	protected $warningMessage = "";
	protected $informationMessage = "";
	protected $ocrText;
	protected $ocrpath = "";
	protected $pdfpath = "";
	protected $solrpath = "";
	protected $victims;
	protected $persons;
	protected $personssurname;
	protected $corporatebodies;
	protected $db;
	protected $validOrgs;
	
/*******************************************************************************
 * CONSTRUCTOR
 */	
	
    function __construct($pocr, $ppdf, $psolr, $pvictims, $pcorporatebodies, $ppersons, $ppersonsurnames, $pdb, $orgs = null) 
    {
		$this->ocrpath = $pocr;
		$this->pdfpath = $ppdf;
		$this->solrpath = $psolr;
		
		if ($orgs==null)
			$this->validOrgs = VALID_ORGS;
		else
			$this->validOrgs = $orgs;
		
//		var_dump($this->validOrgs);
//		exit();
		

		$this->db = $pdb;
		
		$this->victims = $pvictims;
		$this->persons = $ppersons;
		$this->corporatebodies = $pcorporatebodies;
		$this->personsurnames = $ppersonsurnames;
	
    	$this->record = array(
			"begin_doc_id"=>"",
			"owning_organisation"=>"",
			"archive_ref_id"=>"",
			"series_title"=>"",
			"series_sub_title"=>"",
			"short_title"=>"",
			"description"=>"",
			"date_start"=>"",
			"date_end"=>"",
			"research_significance"=>"",
			"catalogue_level"=>"",
			"format"=>"",
			"out_of_scope_reason"=>"",
			"duplicate"=>"",
			"passed_panel_review"=>"",
			"owners_qa_by"=>"",
			"ready_for_panel"=>"",
			"for_public_disclosure"=>"",
			"date_start_scope"=>"",
			"date_end_scope"=>"",
			"ap_corporate_body"=>"",
			"ap_person"=>"",
			"ap_victim_name"=>"",
			"related_material"=>"",
    		"originalformat"=>"",
    		"formatted_outofscope"=>"",
    	
    	//KH: Added new date handling functionality
			"date_start_from"=>"",    	
			"date_start_to"=>"",    	
			"date_end_from"=>"",    	
			"date_end_to"=>"",    	
			"date_start_accuracy"=>"none",
			"date_end_accuracy"=>"none",    	    	
			"redactedreason"=>""
			
    	);
        $this->validRecord = true;
    }
    
/*******************************************************************************
 * PUBLIC METHODS
 */	
	
    public function SaveRecord()
    {
    	$sql = "INSERT INTO disclosed_material ";
    	$fieldname = "";
    	$fieldvalue = "";
    	
    	foreach($this->record as $key => $value)
    	{
    		$fieldname .= $key . ", ";
    		$fieldvalue .= "'" . addslashes($value) . "', ";
    	}
    	
    	$fieldname = substr($fieldname, 0, strlen($fieldname)-2);
    	$fieldvalue = substr($fieldvalue, 0, strlen($fieldvalue)-2);
    	
    	$sql .= "(" . $fieldname . ") VALUES (" . $fieldvalue . ")";
    	
    	try 
    	{
    		$this->db->dbUpdate($sql);
    	}
    	catch(Exception $ex)
    	{
    		if (strpos($ex->getMessage(), "Duplicate entry")!==false)
    			$this->AddError("Duplicate record found in import file and not imported");
			else
			{
    			$this->AddError("FATAL SQL error on SaveRecord.  " . $ex->getMessage() . ".  " . $sql);
			}
    	}
    	
    }

    public function UpdateRecord()
    {
    	$sql = "UPDATE disclosed_material SET ";
    	$fieldname = "";
    	$fieldvalue = "";
    	
    	foreach($this->record as $key => $value)
    	{
    		if ($key != "begin_doc_id")
    		{
	    		$fieldname .= $key . ", ";
	    		$fieldvalue .= "'" . addslashes($value) . "', ";
	    		
	    		$sql .= $fieldname . " = " . $fieldvalue . ", ";
    		}
    	}
    	
    	$sql = substr($sql, 0, strlen($sql)-2);
    	$sql .= " WHERE begin_doc_id = '" . $this->record["begin_doc_id"] . "'";
    	
    	try 
    	{
    		$this->db->dbUpdate($sql);
    	}
    	catch(Exception $ex)
    	{
    		if (strpos($ex->getMessage(), "Duplicate entry")!==false)
    			$this->AddError("Duplicate record found in import file and not imported");
			else
			{
    			$this->AddError("FATAL SQL error on SaveRecord.  " . $ex->getMessage() . ".  " . $sql);
			}
    	}
    	
    }
    
    public function ProcessOCR()
    {
    	$ocr = "";
    	if (($this->record["out_of_scope_reason"]=="")&&($this->record["duplicate"]==""))
    	{
	    	$file = $this->ocrpath . $this->record["begin_doc_id"] . ".txt";
	    	$ocr = $this::ReadOCRFile($file);
	    	$ocrOriginal = $ocr;
			$ocr = strtolower($ocr);
			
	    	// If we have OCR text, check for references to victims, people and organisations and their variants
			if($ocr!=null)
			{
				$this::BuildVictimMetadata($ocr, $this->victims);
				$this::BuildMetadata($ocr, $this->corporatebodies, "ap_corporate_body");
				$this::BuildPersonMetadata($ocrOriginal, $this->persons, $this->personsurnames);
			}

    	}
    				
		// Now create the SOLR XML data file for use later on in building the index
		$this::CreateSOLRFile($ocr);
    }
    
	public function Validate()
	{
		$this::ValidateBarcodeID();
		$this::ValidateOwningOrg();
		$this::ValidateSeries();
		$this::ValidateSubSeries();
		$this::ValidateShortTitle();
		$this::ValidateDescription();
		$this::ValidateDate("date_start", false);
		$this::ValidateDate("date_end", false);
		$this::ValidateSignificance();
		$this::ValidateCatalogueLevel();
		$this::ValidateFormat();
		$this::ValidateOOS();
		$this::ValidateDuplicate();
		$this::ValidateRedactedReason();
		$this::ValidateMediaFile();

		return $this->validRecord;
	}
	
	public function LoadRecord($fields)
	{
		$this->record["begin_doc_id"]=$fields[0];
		
		// Correct number of fields?
		if (count($fields)!=EXPECTED_FIELDS)
		{
			$this->corruptRecord = true;
			$this->AddError("Wrong number of fields (contains ".count($fields)." instead of " . EXPECTED_FIELDS . ")");
		}
		else 
		{
			//$this->record["begin_doc_id"]=$fields[0];
			$this->record["owning_organisation"]=$fields[3];
			$this->record["archive_ref_id"]=$fields[2];
			$this->record["series_title"]=$fields[4];
			$this->record["series_sub_title"]=$fields[5];
			$this->record["short_title"]=$fields[1];
			$this->record["description"]=$fields[6];
			$this->record["date_start"]=$fields[7];
			$this->record["date_end"]=$fields[8];
			$this->record["research_significance"]=$fields[9];
			$this->record["catalogue_level"]=$fields[16];
			$this->record["format"]=$fields[17];
			$this->record["out_of_scope_reason"]=$fields[10];
			$this->record["duplicate"]=$fields[11];
			$this->record["passed_panel_review"]=$fields[12];
			$this->record["owners_qa_by"]=$fields[13];
			$this->record["ready_for_panel"]=$fields[14];
			$this->record["for_public_disclosure"]=$fields[15];
			$this->record["related_material"] = $fields[18];

			$this->record["date_start_scope"]="";
			$this->record["date_end_scope"]="";
			
			$this->record["ap_corporate_body"]=";";
			$this->record["ap_person"]=";";
			$this->record["ap_victim_name"]=";";

			$this->record["series_lookup"] = null;
			$this->record["sub_series_lookup"] = null;
		}
		
		//Remove any tail [NEWLINE] and replace midstring ones with \r\n
		foreach($this->record as $key => $value)
		{
			$this->record[$key] = trim($this->record[$key]);
			$this->record[$key] = $this::ProcessNewline($this->record[$key], $key);
			$this->record[$key] = convert_smartquotes($this->record[$key]);
		}
	}
	

/*******************************************************************************
 * FIELD VALIDATION FUNCTIONS
 */	
	
    protected function ValidateBarcodeID()
    {
    	// CPS			- 3 chars
    	// 00000185		- 8 digits 
    	// 0001			- 4 digits
    	$tfield = $this->record["begin_doc_id"];

		
    	$orgCode = substr($tfield, 0, 3);
    	if (strpos($this->validOrgs, $orgCode)===false)
    		$this->AddError("Organisation code is not recognised as valid [" . $orgCode . "]");
    	
    	if (strlen($tfield)!=15)
	    	$this->AddError("Invalid document ID [" . $tfield . "] (length is " . strlen($tfield) . " chars)");
	    else 
	    {
	    	$id = substr($tfield, 3, 8);
	    	$ext = substr($tfield, 11, 4);
	    	
	    	if ($ext!=="0001")
	    		$this->AddError("Invalid extension (should be \"0001\") [" . $ext . "]");
	    		
    	    if (!ctype_digit($id))
	    		$this->AddError("Document is not recognised as a number [" . $id . "]");
	    		
	    }
	    	
    }
    
    protected function ValidateOwningOrg()
    {
    	$orgName = $this->record["owning_organisation"];
		try 
		{
			$sql = "SELECT owning_organisation FROM organisations WHERE lextranet_title = '" . addslashes($orgName) . "' LIMIT 0,1";
			$res = $this->db->dbFetch($sql, FALSE);
			if (isset($res["owning_organisation"]))
			{
				if ($res["owning_organisation"]!=$orgName)
				{
					$this->record["owning_organisation"] = $res["owning_organisation"];
					$this->AddInformation("Lextranet org name [".$orgName."] does not match the look up list so replacing with lookup name [".$res["owning_organisation"]."]");
				}
			}
			else 
			{
				$this->AddError("Lextranet org name [".$orgName."] not found in look up list");
			}
		}
		catch(Exception $ex)
		{
			$this->AddError("Error on SQL in ValidateOwningOrg.  " . $ex->getMessage() . ".  " . $sql);
		}
    	
    }
    
	protected function ValidateShortTitle()
	{
		$tfield = $this->record["short_title"];
		if (empty($tfield))
			$this->AddError("No short title has been specified");
		else
		{
			// tidy the field
			
			$tfield = str_replace("\r\n", "", $tfield);
			
			if ($tfield != $this->record["short_title"])
			{
				$this->record["short_title"] = $tfield;
				$this->AddWarning("Manipulated short_title to remove invalid chars");
			}				
			
			// Max length is 150 but taking into account [newline] will allow upto 
			if (strlen($this->record["short_title"])>170)	
			{
				$this->AddError("Short title is greater than 150 chars so rejecting record (actual length: " . strlen($this->record["short_title"]) . ")");
			}
		}
	}
	
	protected function ValidateDate($dateField, $required)
	{
		
		
		
		$date_accuracy = "exact";
		
		//echo "\r\n\r\nprocessing date: " . $this->record[$dateField] . "\r\n";
		// IF date is required - must be valid
		if ($required)
		{
		    if(!preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?)$/', $this->record[$dateField], $matches))
		    {
		    	$date_accuracy = "none";
		    	$this->AddError("Invalid date for field [" . $dateField . "], found [" . $this->record[$dateField] . "]");
		    }
	    	elseif (!checkdate($matches[2], $matches[3], $matches[1]))
	    	{
	    		if (($matches[2]=="00")||($matches[3]=="00"))
	    		{
	    			$this->AddInformation("Date range indicates a range (month and/or year) [" . $dateField . "], found [" . $this->record[$dateField] . "]");
	    		}
	    		else 
	    		{
	    			$this->AddWarning("Date range invalid [" . $dateField . "], found [ARR: " . implode("/", $matches) . " / Field: " . $this->record[$dateField] . "]");
	    		}
	    			    		
	    		//KH: Additional fields
	    		if (($matches[2]=="00")&&($matches[3]=="00"))
	    			$date_accuracy = "year";	
	    		else 
	    			$date_accuracy = "month";
	    			
    		}
    		else
    		{
    			
    		}
    		
    		$this::DateToTS($dateField);
		}
		else		//ELSE not required, but if there is one, make sure it's formatted correctly
		{
			if ($this->record[$dateField]!="")
			{
			    if(!preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?)$/', $this->record[$dateField], $matches))
			    {
			    	$this->AddError("Invalid date for field [" . $dateField . "], found [" . $this->record[$dateField] . "]");
			    }
			    elseif (!checkdate($matches[2], $matches[3], $matches[1]))
		    	{
		    		if (($matches[2]=="00")||($matches[3]=="00"))
		    		{
		    			$this->AddInformation("Date range indicates a range (month and/or year) [" . $dateField . "], found [" . $this->record[$dateField] . "]");

		    			//KH: Additional fields
			    		if (($matches[2]=="00")&&($matches[3]=="00"))
			    			$date_accuracy = "year";	
			    		else 
			    			$date_accuracy = "month";
		    		}
		    		else 
		    		{
			    		$this->AddWarning("Date range invalid [" . $dateField . "], found [" . $this->record[$dateField] . "]");
		    		}
	    		}
	    		
	    		$this->record[$dateField . "_accuracy"] = $date_accuracy;
	    		
	    		$this::StoreDateRegion($dateField);
	    		$this::DateToTS($dateField);
	    		
			}
		}
		//return $date_accuracy;
	}
	
	protected function ValidateDescription()
	{
		if ($this->record["description"]=="0")
			$this->record["description"]="";
			
//		if (($this->record["research_significance"]=="1") &&
//			(empty($this->record["description"])))
//		{
//			$this->AddWarning("No description found for material marked as significant");
//		}		
	}

	protected function ValidateSeries()
	{
// A.S. Added 08/08/2012 following discussion with Will
		if ((empty($this->record["series_title"])) && (empty($this->record["series_sub_title"])))
		{
			$this->record["series_title"] = "All other documents";
			$this->record["series_sub_title"] = "All other documents";
		}
		
		if (!empty($this->record["series_title"]))
		{					
			try 
			{
				$this->record["series_title"] = $this::ConvertToASCII($this->record["series_title"], "series_title");
				
				$sql = "SELECT id FROM serieslookup WHERE series_title = '" . addslashes($this->record["series_title"]) . "' AND owning_organisation = '" . addslashes($this->record["owning_organisation"]) . "' AND series_sub_title = '' LIMIT 0,1";
				$check_series = $this->db->dbFetch($sql, FALSE);
									
				if (empty($check_series)) // add to series lookup
				{
					$newurl = $this::nameConvert($this->record["series_title"]);


					// The following section of code is a copy of that in ValidateSubSeries
					
					$sql = "SELECT COUNT(id) AS num FROM serieslookup WHERE owning_organisation = '" . addslashes($this->record["owning_organisation"]) . "' AND url_title = '".addslashes($newurl)."' LIMIT 0,1";
					$check_number = $this->db->dbFetch($sql, FALSE);

					// check if url exists, if so replace with another one.
					$insertok = false;
					$append = false;
				
					$oUrl = $newurl;
					$counter = 0;
				
					while(!$insertok)
					{
						$sql = "SELECT COUNT(*) AS num FROM serieslookup WHERE url_title = '" . addslashes($newurl) . "'";
						$check_number = $this->db->dbFetch($sql, FALSE);
						if (isset($check_number["num"]))
						{
							if ($check_number["num"]=="1")
							{
								//$newurl .= "1";
								$counter++;
								$newurl = $oUrl . $counter;
								$append = true;
							}
							else 
							{
								$insertok = true;
							}
						}
					}

					if ($append)
						$this->AddInformation("Found duplicate URL for series so appending");

						
					$sql = "INSERT INTO `serieslookup` (`series_title`, `series_sub_title`, `owning_organisation`, `url_title`) VALUES ('" . addslashes($this->record["series_title"]) . "', '', '" . addslashes($this->record["owning_organisation"]) . "', '".$newurl."');";
					
					if ($this->db->dbUpdate($sql))
					{
						$this->AddInformation("Inserted new series of " . $this->record["series_title"]);
						$series_lookup = mysql_insert_id();
					} 
					
				}
				else
				{
					$this->record["series_lookup"] = $check_series['id'];
				}
			}
			catch(Exception $ex)
			{
				$this->AddError("FATAL ERROR in ValidateSeries.  " . $ex->getMessage() . "Tried executing ". $sql);
			}
			
		}
		else				
		{
			$this->AddInformation("No series title found.");
		}		
	}

	protected function ValidateSubSeries()
	{
		if (empty($this->record["series_sub_title"]))
		{
			$this->record["series_sub_title"] = "All other documents";
		}
		
		if (empty($this->record["series_title"]))
		{
			$this->AddError("Document assigned a sub-series but no series.");
			return;
		}
		
		
		try 
		{
			
			$this->record["series_sub_title"] = $this::ConvertToASCII($this->record["series_sub_title"], "series_sub_title");
			
			$sql = "SELECT id FROM serieslookup WHERE " . 
					" series_title = '" . addslashes($this->record["series_title"]) . "' AND " . 
					" owning_organisation = '" . addslashes($this->record["owning_organisation"]) . "' AND " . 
					" series_sub_title = '" . addslashes($this->record["series_sub_title"]) . "' LIMIT 0,1";						
			
			$check_series = $this->db->dbFetch($sql, FALSE);
			
			if (empty($check_series)) // add to series lookup
			{
				
				//var_dump($sql, $check_series);
				//exit();
				
				
				// check if the series lookup already exists for this org
				$newurl = $this::nameConvert($this->record["series_sub_title"]);
				
				$sql = "SELECT COUNT(id) AS num FROM serieslookup WHERE owning_organisation = '" . addslashes($this->record["owning_organisation"]) . "' AND url_title = '".addslashes($newurl)."' LIMIT 0,1";
				$check_number = $this->db->dbFetch($sql, FALSE);

				// check if url exists, if so replace with another one.
				$insertok = false;
				$append = false;
				
				$oUrl = $newurl;
				$counter = 0;
				
				while(!$insertok)
				{
					$sql = "SELECT COUNT(*) AS num FROM serieslookup WHERE url_title = '" . addslashes($newurl) . "'";
					$check_number = $this->db->dbFetch($sql, FALSE);
					if (isset($check_number["num"]))
					{
						if ($check_number["num"]=="1")
						{
							//$newurl .= "1";
							$counter++;
							$newurl = $oUrl . $counter;
							$append = true;
						}
						else 
						{
							$insertok = true;
						}
					}
				}

				if ($append)
					$this->AddInformation("Found duplicate URL for sub series so appending");
				
				
				
				if (!empty($this->record["series_title"]))
					$sql = "INSERT INTO `serieslookup` (`series_title`, `series_sub_title`, `owning_organisation`, `url_title`) VALUES ('" . addslashes($this->record["series_title"]) . "', '" . addslashes($this->record["series_sub_title"]) . "', '" . addslashes($this->record["owning_organisation"]) . "', '".$newurl."');";
				else
					$sql = "INSERT INTO `serieslookup` (`series_title`, `series_sub_title`, `owning_organisation`, `url_title`) VALUES ('All other documents', '" . addslashes($this->record["series_sub_title"]) . "', '" . addslashes($this->record["owning_organisation"]) . "', '".$newurl."');";
					
				if ($this->db->dbUpdate($sql))
				{
					$this->AddInformation("Inserted new subseries of " . $this->record["series_sub_title"]);
					$sub_series_lookup = mysql_insert_id();
				}
				
				
			}
			else // already found
			{
				$this->record["sub_series_lookup"] = $check_series['id'];
			}
		}
		catch (Exception $ex)
		{
			$this->AddError("FATAL ERROR in ValidateSubSeries.  " . $ex->getMessage() . "Tried executing ". $sql);
		}
	}
	protected function ValidateSignificance()
	{
		global $db;
		$sql = 	"SELECT chapterref FROM materialreferenced_import WHERE begin_doc_id = '" . $this->record['begin_doc_id'] . "'";
		$ref_check = $db->dbFetch($sql, FALSE);
		
		$chapter = "";
		if($ref_check!==FALSE)
		{
			foreach($ref_check as $chapter_reference)
				$chapter .= ";".$chapter_reference.";";

			$this->record["research_significance"]=$chapter;
		}				
	}
	
	protected function ValidateCatalogueLevel()
	{
		/*****  FOR IMPORTING ORGS ******/
		/*
		$sql = "SELECT id FROM organisations WHERE owning_organisation = '" . addslashes($this->record['owning_organisation']) . "' LIMIT 0,1";
		try 
		{
			$organisation_check = $db->dbFetch($sql, FALSE);
			
			$dir_name = $this::nameConvert($this->record['owning_organisation']); // create url-friendly name
			$non_contributing = ($this->record['catalogue_level'] == 'Organisation') ? 0 : 1;
			
			if (empty($organisation_check)) // insert
			{
				$sql = "INSERT INTO organisations ( id, owning_organisation, short_title, description, dir_name, lextranet_title, non_contributing ) VALUES ( NULL, '" . $this->record['owning_organisation'] . "', '" . $this->record['short_title'] . "', '" . $this->record['description'] . "', '" . addslashes($dir_name) . "', '" . $this->record['owning_organisation'] . "', '" . $non_contributing . "' )";	
				$update_type = "INSERT";
				$insertcount++;
			}
			else // update
			{
				$sql = "UPDATE organisations SET owning_organisation = '" . addslashes($this->record['owning_organisation']) . "', short_title = '" . addslashes($this->record['short_title']) . "', description = '" . addslashes($this->record['description']) . "', dir_name = '" . addslashes($dir_name) . "', lextranet_title = '" . addslashes($this->record['owning_organisation']) . "', non_contributing = '" . $non_contributing . "' WHERE id = '" . $organisation_check['id'] . "' LIMIT 1";
				$update_type = "UPDATE";
				$updatecount++;
			}
			
			if ($db->dbUpdate($sql))
			{
				$this->AddWarning("Added new organisation [" . $this->record["owning_organisation"] . "]");
			}
			
		}
		catch(Exception $ex)
		{
			$this->AddError("FATAL SQL: Error occurred in ValidateCatalogueLevel.  " . $ex->getMessage() . ".  " . $sql);
		}
		*/
	}
	
	protected function ValidateFormat()
	{
		$this->record["originalformat"] = $this->record["format"];
		
		$oldFormat = strtolower($this->record["format"]);
		$newFormat = "";
		
		if (in_array($oldFormat, $this->standard_formats))
			$newFormat = "Document";
		elseif (in_array($oldFormat, $this->photo_formats))
			$newFormat = "Photograph";
		elseif (in_array($oldFormat, $this->av_formats))
			$newFormat = "AV";
			
		if ($newFormat=="")
		{
			// Look to see if PDF exists and set to 
			if (file_exists($this->pdfpath . $this->record["begin_doc_id"] . ".pdf"))
			{
				$this->AddWarning("Unrecognised media format of [" . $oldFormat . "], PDF found so defaulting to Document");			
			}
			else 
			{
				//Already report if inscope and no doc exists so no need to report again.  Record would be rejected anyway.
//				if ((empty($this->record["out_of_scope_reason"]))&&(empty($this->record["duplicate"])))
//					$this->AddError("Unrecognised media format of [" . $oldFormat . "], defaulting to Document though no PDF exists");
//				else 
//				{
					$this->AddWarning("Unrecognised media format of [" . $oldFormat . "], defaulting to Document though out of scope");
//				}
			}
			$newFormat = "Document";
		}
		$this->record["format"] = $newFormat;
	}
	
	//KH: Change as we need to create the formatted version for the catalogue listing now.  If get a bit more time
	//    need to make this dynamic from the DB table but not going to make this weeks deployment.
	protected function ValidateOOS()
	{
		if (!empty($this->record["out_of_scope_reason"]))
		{
			$foos = "";
			$t = strtolower($this->record["out_of_scope_reason"]);
			
			if (strpos($t, 'duplicate')!==false)
				$foos = "Duplicate - other copy available";
			elseif (strpos($t, 'no information held')===0)
				$foos = "No information held";
			elseif (strpos($t, 'not disclosed to panel')===0)
				$foos = "Not disclosed to Panel";
			else 
			{
				$this->AddWarning("Formatted out of scope reason has been defaulted to \"Other - seen by Panel\".  Original OOS was [" . $t . "].");
				$foos = "Other - seen by Panel";
			}	
			$this->record["formatted_outofscope"] = $foos;
		}				
	}
	//KH: End of change
	
	protected function ValidateDuplicate()
	{
		$tfield = strtolower($this->record["duplicate"]);
		
		if ($tfield!="yes" && $tfield!="no" && $tfield!="yes (duplicate)" && $tfield!="no (master)" && $tfield!="" && $tfield!="yes (copy)")
		{
			if ($this->record["out_of_scope_reason"]=="")
			{
				$this->AddWarning("Duplicate field does not contain the word \"yes\" or \"no\" and is not blank [" . $this->record["duplicate"] . "].  No \"Out of scope reason\" defined so blanking and including.");
			}
			elseif (($this->record["out_of_scope_reason"]!="")&&(strpos(strtolower($this->record["out_of_scope_reason"]), "duplicate")===false))
			{
				$this->AddWarning("Duplicate field does not contain the word \"yes\" or \"no\" and is not blank [" . $this->record["duplicate"] . "].  Out of scope has value but no duplicate reference. Blanking duplicate field even though document is out-of-scope.");
			}
			else 
			{
				$this->AddWarning("Duplicate field does not contain the word \"yes\" or \"no\" and is not blank [" . $this->record["duplicate"] . "].  Out of scope refers to duplicate so leaving as is.");
			}
		}
		
		// If not yes then default to blank
		if (substr($tfield, 0, 3) == "yes")
			$this->record["duplicate"] = "Yes";
		else
			$this->record["duplicate"] = "";					
	}
	
	protected function ValidateMediaFile()
	{
		if (($this->record["format"]=="Document")&&(($this->record["out_of_scope_reason"]=="")&&($this->record["duplicate"]=="")))
		{
			if (!file_exists($this->pdfpath . $this->record["begin_doc_id"] . ".pdf"))
			{	
				$this->AddError("Document PDF is not available");
			}
		}
	}

	protected function ValidateRedactedReason()
	{
		global $db;
		$sql = "SELECT redacted_value FROM redaction_import WHERE begin_doc_id = '" . $this->record['begin_doc_id'] . "'";
		$redaction_check = $db->dbFetch($sql, FALSE);
		
		if($redaction_check!==FALSE)
		{
			$reasons = explode(";", $redaction_check['redacted_value']);
			
			$opta = false;
			$optb = false;
			
			foreach($reasons as $reason)
			{
				if (substr($reason, 0, 1)=="1")
					$opta=true;
				if (substr($reason, 0, 1)!="1")
					$optb=true;					
			}
			
			if ($opta && $optb)
				$redacted_summary=3;
			elseif (!$opta && $optb)
				$redacted_summary=2;
			elseif ($opta && !$optb)
				$redacted_summary = 1;
			else 
				$redacted_summary = 0;

			$this->record["redactedreason"]=$redacted_summary;
		}		
	}
	
	
/*******************************************************************************
 * PROPERTIES FOR OBJECT
 */
	
	public function IsValid()
	{
		return $this->validRecord;
	}

	public function IsCorrupt()
	{
		return $this->corruptRecord;
	}
		
	
/*******************************************************************************
 * MISC FUNCTIONS
 */

	
	protected function GetSOLRBoost()
	{
		$boost = false;
		global $db;
		if ($this->record['research_significance']!="")
			$boost = true;
		
		if (!$boost)
		{
			$sql = "SELECT distinct BeginDocId FROM DocumentsForBoost WHERE BeginDocId = '" . $this->record['begin_doc_id'] . "' limit 1";
			$significantDoc = $db->dbFetch($sql, FALSE);
			if (!$significantDoc)
				$boost = true;
		}

		if ($boost)
			return 100;

		return 3;
	}

	protected function CreateSOLRFile($ocr)
    {
		$filename = $this->solrpath . $this->record["begin_doc_id"] . ".xml";
    	
    	
    	if ($this->record['ap_victim_name']!="")
			$v = $this::GetLookupDataExpanded($this->record['ap_victim_name'], 1);
		if ($this->record['ap_person']!="")
			$p = $this::GetLookupDataExpanded($this->record['ap_person'], 3);
		if ($this->record['ap_corporate_body']!="")
			$cb = $this::GetLookupDataExpanded($this->record['ap_corporate_body'], 2);
		
		if (!is_long($this->record['date_start']))
		{
			$docDate = "";
			$this->AddInformation("Unable to create start date for SOLR XML file [".$this->record['date_start']."]");
		}
		else
			$docDate = date("Y-m-d", $this->record['date_start']) . "T" . date("H:i:s", $this->record['date_start']) .  "Z";

		$endDate = "";
		if (empty($this->record['date_end']))
		{
			// Do nothing now, this actually is valid
		}
		elseif (!is_long($this->record['date_end']))
		{
			$endDate = "";
			$this->AddInformation("Unable to create end date for SOLR XML file [".$this->record['date_end']."]");
		}
		else
			$endDate = date("Y-m-d", $this->record['date_end']) . "T" . date("H:i:s", $this->record['date_end']) .  "Z";
		
		$media = $this->record['format'];


		$victim_group = "";
		foreach($v as $vitem)
			$victim_group .= "<field name=\"hip_victim\">".$this::StripUTF8($vitem)."</field>\r\n";
		$person_group = "";
		foreach($p as $vitem)
			$person_group .= "<field name=\"hip_person\">".$this::StripUTF8($vitem)."</field>\r\n";
		$corporate_group = "";
		foreach($cb as $vitem)
			$corporate_group .= "<field name=\"hip_corporate\">".$this::StripUTF8($vitem)."</field>\r\n";
				
			
		//KH Boost code
		$boostValue = $this->GetSOLRBoost();
			
			
		$curl = "<add>\r\n"
				. "<doc boost=\"".$boostValue."\">\r\n"
				. "<field name=\"hip_uid\">" . $this->record['begin_doc_id'] . "</field>\r\n";
		$curl .= "<field name=\"hip_location\">" . REPO_SERVER . $this->record['begin_doc_id'] . ".html</field>\r\n";					
		$curl .= "<field name=\"hip_series_title\">" . htmlspecialchars($this::StripUTF8($this->record['series_title'])) . "</field>\r\n"
				. "<field name=\"hip_title\">" . htmlspecialchars($this::StripUTF8($this->record['short_title'])) . "</field>\r\n"
				. "<field name=\"hip_format\">" . $media . "</field>\r\n"
				. "<field name=\"hip_description\">" . htmlspecialchars($this::StripUTF8($this->record['description'])) . "</field>\r\n"
				. "<field name=\"hip_series_subtitle\">" . htmlspecialchars($this::StripUTF8($this->record['series_sub_title'])) . "</field>\r\n";
				
		if (!empty($docDate))
		{
			$curl .= "<field name=\"hip_date\">" . $docDate . "</field>\r\n";
			$startSearchDate = date("Y-m-d", $this->record['date_start_from']) . "T" . date("H:i:s", $this->record['date_start_from']) .  "Z";			
			$curl .= "<field name=\"hip_search_start\">" . $startSearchDate . "</field>\r\n";			
		}
		
		$curl .=  $victim_group 
				. $person_group 
				. $corporate_group 
				
				. "<field name=\"hip_contrib_org\">".htmlspecialchars($this::StripUTF8($this->record['owning_organisation']))."</field>\r\n"	
				. "<field name=\"hip_chapter\">" . htmlspecialchars($this::StripUTF8($this->record['research_significance']))."</field>\r\n"
				. "<field name=\"hip_archive_ref\">".htmlspecialchars($this::StripUTF8($this->record['archive_ref_id']))."</field>\r\n"
				. "<field name=\"hip_outofscope_reason\">".htmlspecialchars($this::StripUTF8($this->record['formatted_outofscope']))."</field>\r\n"
				. "<field name=\"hip_report\">false</field>\r\n";
								
		if (!empty($endDate))
		{
			$curl .=  "<field name=\"hip_enddate\">".$endDate."</field>\r\n";
			$endSearchDate = date("Y-m-d", $this->record['date_end_to']) . "T" . date("H:i:s", $this->record['date_end_to']) .  "Z";
			$curl .= "<field name=\"hip_search_end\">" . $endSearchDate . "</field>\r\n";
		}
		else 
		{
			if (!empty($docDate))
			{
				try
				{
					$endSearchDate = date("Y-m-d", $this->record['date_start_to']) . "T" . date("H:i:s", $this->record['date_start_to']) .  "Z";
					$curl .= "<field name=\"hip_search_end\">" . $endSearchDate . "</field>\r\n";
				}
				catch(Exception $ex)
				{
					$this->AddError("Invalid date encountered in SOLR XML file creation for [endSearchDate]");
				}
			}
		}
		if ($this->record['redactedreason']!=0)
				$curl .=  "<field name=\"hip_redacted\">".$this->record['redactedreason']."</field>\r\n";
					
			if ($ocr!="") 
			{
				$curl .= "<field name=\"hip_content\">\r\n";
				$ocr = $this::StripUTF8($ocr);
				$ocr = convert_ascii($ocr);
				$ocr = htmlspecialchars($ocr);
				$curl .= $ocr . "</field>\r\n";
			}	
			$curl .= "</doc>\r\n</add>\r\n";		
		
			$savefile = fopen($filename, "w");
			fwrite($savefile, $curl);
			fclose($savefile);
	    	
    }
	

	protected function GetLookupDataExpanded($ids, $type)
	{
		
		/** KH: Change to store integer rather than text
		$ids = $this::RemoveTrailingComma(str_replace(";", ",", $ids));

		if ($ids == "")
			return array();
			
		$result = array();
		$sql = "SELECT * FROM " . AUTOPOPULATE_LOOKUP_TABLE . " WHERE type = " . $type . " and id in (".$ids.")";
		$lookup = $this->db->dbFetch($sql);
		foreach($lookup as $row)
		{
			$result[] = htmlspecialchars($row['id']);
		}	
		return $result;
		*/
		$values = explode(";", $ids);
		$ids = "";
		foreach($values as $entry)
		{
			if ($entry!="")
			{
				$t = $entry;
				if (strpos($t, "(")!==false)
					$t = substr($entry, 0, strpos($entry, "("));
				
				$ids .= $t . ",";
				//var_dump("START", $entry, $t, $ids);
			}
		}
		$ids = $this::RemoveTrailingComma($ids);
		return explode(",", $ids);	
	}
	
    protected function BuildMetadata($text, $lookup, $fieldName)
    {
    	foreach($lookup as $value => $vid)
		{
			if (($value!="") && ($value!=null))
			{
				if (strpos($text, $value)!==FALSE)
				{
					if (preg_match("/[^0-9a-zA-Z]".$value."[^0-9a-zA-Z]/",$text))
					{
						//If not already there, add it.
						if (strpos($this->record[$fieldName], ";".$vid.";")===FALSE)
						{
							$this->record[$fieldName] .= $vid . ";";
						}
					}
				}

				if (stripos($this->record['short_title'], $value)!==FALSE)
				{
					if (preg_match("/[^0-9a-zA-Z]".$value."[^0-9a-zA-Z]/",strtolower($this->record['short_title'])))
					{
						//If not already there, add it.
						if (strpos($this->record[$fieldName], ";".$vid.";")===FALSE)
						{
							$this->record[$fieldName] .= $vid . ";";
						}
					}
				}

				if (stripos($this->record['description'], $value)!==FALSE)
				{
					if (preg_match("/[^0-9a-zA-Z]".$value."[^0-9a-zA-Z]/",strtolower($this->record['description'])))
					{
						//If not already there, add it.
						if (strpos($this->record[$fieldName], ";".$vid.";")===FALSE)
						{
							$this->record[$fieldName] .= $vid . ";";
						}
					}
				}
				
//Removed case conversion				
//				if (strpos($text, strtolower($value))!==FALSE)
//				{
//					if (preg_match("/[^0-9a-zA-Z]".strtolower($value)."[^0-9a-zA-Z]/",$text))
//					{
//						//If not already there, add it.
//						if (strpos($this->record[$fieldName], ";".$vid.";")===FALSE)
//						{
//							$this->record[$fieldName] .= $vid . ";";
//						}
//					}
//				}
			}
			else 
			{
				//$this->AddError("Unhandled variant situation for " . $fieldName . " of " . $value . "\r\n");
			}
		}
    }

    function PerformMatch($textToSearch, $textToMatch)
	{
		$result = array();
		if (strpos($textToSearch, $textToMatch)!==FALSE)
		{
			$matches = array();
			preg_match_all("/[^0-9a-zA-Z]".$textToMatch."[^0-9a-zA-Z]/", $textToSearch, $matches);
	
			if (sizeof($matches[0])>0)
			{
				$result[$textToMatch] = sizeof($matches[0]);
				//var_dump($matches[0]);
			}
		}
		return $result;		
	}
    
    function BuildVictimMetadata($text, $lookup)
	{
		$id= -1;
		$oid = 0;
	
		
		foreach($lookup as $vid => $victim)
		{
			//var_dump($vid, $victim);
			//exit();
			
			$matchInTitle = false;
			$matchInDescription = false;
			
			$matchOnBody = false;
			$singleInitialSurname = false;
			$forenameSurname = false;
			$forenameMiddleSurname = false;
		
			$result = array();
			$value = $victim[0];
			//echo "Looking for references to: " . $value . "\r\n";
			foreach($victim as $variant)
			{
				if (($variant!="") && ($variant!=null))
				{
					$variant = strtolower($variant);
				
					$t = $this->PerformMatch($text, $variant);
					if (sizeof($t)>0)
					{
						//$matchedTerm = true;
						$result[] = $t;
					}	
					$t = $this->PerformMatch(strtolower($this->record["short_title"]), $variant);
					if (sizeof($t)>0)
					{
						$result[] = $t;
						$matchInTitle = true;
					}
					
					$t = $this->PerformMatch(strtolower($this->record["description"]), $variant);
					if (sizeof($t)>0)
					{
						$result[] = $t;
						$matchInDescription = true;
					}
				}
			}
			
			//A match was found so... 
			if (sizeof($result)>0)
			{				
				$matchedTerm = "";
				$total = 0;
				
				$bodyref = false;
				
				foreach($result as $t)
				{
					foreach($t as $term=>$val)
					{
						$matchedTerm .= $term . " (" . $val . "), ";
						$total += $val; 
						$termParts = explode(" ", $term);
						
						$containsInitial = false;
						$wordCount=0;
						foreach($termParts as $token)
						{
							if (strlen($token)<=2)
							{
								if (!is_numeric($token))
									$containsInitial = true;
							}
							else 
								$wordCount++;
						}
		
						if ($containsInitial && ($wordCount==1))
							$singleInitialSurname = true;
							
						if ($wordCount==2)
							$forenameSurname = true;
		
						if ($containsInitial && ($wordCount==2))
							$forenameMiddleSurname = true;
						
						if ($wordCount>2)
							$forenameMiddleSurname = true;
							
						if (strpos($term, "body")!==false)
							$bodyref=true;
					}
				}

				/*KH
				 * Score is the level of confidence on the match
				 * 1: Low (just initial surname)
				 * 2: Medium (forename surname)
				 * 3: High (match 3 parts of the variant (> 1 initial & surname), body ref, etc)
				 */
				$score = 0;
				if ($singleInitialSurname)
//				if ($singleInitialSurname||$forenameSurname)
					$score = 1;
				if ($forenameSurname)
//				if (($matchInTitle || $matchInDescription )&&($forenameSurname))
					$score = 2;
				if ($forenameMiddleSurname||$bodyref)
					$score = 3;
				
				//If not already there, add it.
				if ($score>0)
				{
					if (strpos($this->record["ap_victim_name"], ";".$vid."(")===FALSE)
					{
						$this->record["ap_victim_name"] .= $vid . "(" . $score . ");";
					}
				}				
			}
		}
	}

	function LookForPersonMatch($body, $variants)
	{
		$terms = explode(";", $variants);

		foreach($terms as $term)
		{
			//echo "checking term; " . $term . PHP_EOL;
			if ($term!="")
			{
				if (strpos($body, $term)!==FALSE)
                {
                    if (preg_match("/[^0-9a-zA-Z]" . str_replace("/", "\/", $term) . "[^0-9a-zA-Z]/", $body))
                    {
                        return true;
                    }
                }
			}
		}
		return false;
	}
	
	function BuildPersonMetadata($text, $lookups, $surnames)
	{
		$confidence = 0;
		
		foreach($surnames as $id=>$surname)
		{
            //Check to see if any of the surname variants is present
            $surnameVariants = explode(";",$surname);
            $surnameMatch = FALSE;
			
			foreach($surnameVariants as $surnameVariant)
            {
                if ((strpos($text, $surnameVariant)!==FALSE)||
                    (strpos($this->record["short_title"], $surnameVariant)!==FALSE)||
                    (strpos($this->record["description"], $surnameVariant)!==FALSE))
                {
                    $surnameMatch = TRUE;
					//exit;
                    break;
                }
            }
                    
			
					
			if ($surnameMatch)
			{
				//echo "Found occurrence of " . $surname . " (" . $id . ") so going to determine level of accuracy\r\n";

				if ($this->LookForPersonMatch($text . " " . $this->record["short_title"] . " " . $this->record["description"], $lookups[$id]["high"]))
					$confidence = 3;
				elseif ($this->LookForPersonMatch($text . " " . $this->record["short_title"] . " " . $this->record["description"], $lookups[$id]["medium"]))
					$confidence = 2;
				elseif ($this->LookForPersonMatch($text . " " . $this->record["short_title"] . " " . $this->record["description"], $lookups[$id]["low"]))
					$confidence = 1;
					
		        if ($confidence > 0)
		        {
		            if (strpos($this->record["ap_person"], ";".$id."(")===FALSE)
		            {
		                $this->record["ap_person"] .= $id . "(" . $confidence . ");";
		            }
		            //echo $this->record["begin_doc_id"] . " [" . $surname . "] Match confidence: " . $confidence . "\r\n";
		        }
		        else 
		        {
		        	//echo $this->record["begin_doc_id"] . " [" . $surname . "] No match confidence\r\n";
		        }
				//echo "matched surnameVariant: " . $surnameVariant . " and confidence of " . $confidence . PHP_EOL;
			}

		}

		//exit;
		
    }	

    protected function ReadOCRFile($file)
    {
    	if (!file_exists($file))
    	{
    		if ((empty($this->record["out_of_scope_reason"]))&&(empty($this->record["duplicate"])))
    		{
	    		$this->AddWarning("The OCR file is not available for this material");
    		}
    	}
		else 
		{
			$ocr = file_get_contents($file);
			
			$oldocr = " " . $ocr . " ";
			$ocr = preg_replace('!\s+!', ' ', $oldocr);			
			
			//return strtolower(convert_ascii($ocr));
			return convert_ascii($ocr);
		} 
		return null;    	
    }
	
    protected function AddInformation($text)
    {
    	$this->informationMessage .= $text . "|";
    }
	
    protected function AddWarning($text)
    {
    	$this->warningMessage .= $text . "|";
    }
    
    protected function AddError($text)
    {
    	$this->errorMessage .= $text . "|";
    	$this->validRecord = false;
    }
    
	public function VerboseOutput()
	{
		echo "Record ID: " . $this->record["begin_doc_id"] . "\r\n";
		echo "  This record is ";
		if ($this->validRecord)
			echo "valid";
		else 
			echo "not valid";
		echo "\r\n";
		
		if (!empty($this->informationMessage))
		{
			echo "  The following informational messages occurred:\r\n";
			$msg = explode("|", $this->informationMessage);
			foreach($msg as $part)
				if($part!="")
					echo "   - " . $part . "\r\n";
		}
		
		if (!empty($this->warningMessage))
		{
			echo "  The following warnings occurred:\r\n";
			$msg = explode("|", $this->warningMessage);
			foreach($msg as $part)
				if($part!="")
					echo "   - " . $part . "\r\n";
		}

		if (!empty($this->errorMessage))
		{
			echo "  The following errors occurred:\r\n";
			$msg = explode("|", $this->errorMessage);
			foreach($msg as $part)
				if($part!="")
					echo "   - " . $part . "\r\n";
		}
		

	}

	public function PersistLog()
	{
		$id = $this->record["begin_doc_id"];
		
		
		$msg = "This record is ";
		if ($this->validRecord)
			$msg .= "valid";
		else 
			$msg .= "not valid";
		$sql = "INSERT INTO logging (barcodeid,eventtype,eventtime,eventmessage) VALUES ('" . addslashes($id) . "', " . 
			"'Information', now(),'".addslashes($msg)."')";

		$this->db->dbUpdate($sql);
		
		if ($this->informationMessage!="")
		{		
			$msg = explode("|", $this->informationMessage);
			foreach($msg as $part)
				if($part!="")
				{
					$sql = "INSERT INTO logging (barcodeid,eventtype,eventtime,eventmessage) VALUES ('" . addslashes($id) . "', " . 
						"'Information', now(),'".addslashes($part)."')";
					$this->db->dbUpdate($sql);
				}
		}
		
		if ($this->warningMessage!="")
		{		
			$msg = explode("|", $this->warningMessage);
			foreach($msg as $part)
				if($part!="")
				{
					$sql = "INSERT INTO logging (barcodeid,eventtype,eventtime,eventmessage) VALUES ('" . addslashes($id) . "', " . 
						"'Warning', now(),'".addslashes($part)."')";
					$this->db->dbUpdate($sql);
				}
		}

		if ($this->errorMessage!="")
		{
			$msg = explode("|", $this->errorMessage);
			foreach($msg as $part)
				if($part!="")
				{
					$sql = "INSERT INTO logging (barcodeid,eventtype,eventtime,eventmessage) VALUES ('" . addslashes($id) . "', " . 
						"'Error', now(),'".addslashes($part)."')";
					$this->db->dbUpdate($sql);
				}
		}
		

	}
	
/*******************************************************************************
 * DATA CLEANSING
 */	
	
	protected function StripUTF8($string)
	{	
		$string = preg_replace('/[^(\x20-\x7F)]*/','', $string);
		return $string;	
	}
	
	protected function StoreDateRegion($fieldname)
	{
		$value = $this->record[$fieldname];
		if (($value!="0")&&(!empty($value))) 
		{
			$value = str_replace("-", "/", $value);			
			$date = explode("/", $value);
			
			$startdate = $date;
			$enddate = $date;
			
			if (count($date)==1)
				return;
			
			// if the month contains 00 then the scope is assumed to be year
			if ($date[1] == (string) "00")	// mm
			{
				$startdate[1] = 1;
				$startdate[2] = 1;
				$enddate[1] = 12;
				$enddate[2] = 31; 
			}
			// otherwise if day contain 00 this means the scope is month
			elseif ($date[2] == (string) "00")	// dd
			{
				$startdate[2] = 1;

				// 30 days have September, April, June and November :)
				if (($date[1]=="09")||($date[1]=="04")||($date[1]=="06")||($date[1]=="11"))
					$enddate[2] = 30;
				elseif ($date[1]=="02")
				{
					if (date('L', strtotime($date[0]."-01-01")))
						$enddate[2] = 29;
					else 	
						$enddate[2] = 28;
				}
				else
					$enddate[2] = 31; 					
			}			
			
			try 
			{
				if ((is_numeric($enddate[0]))&&(is_numeric($enddate[1]))&&(is_numeric($enddate[2])))
				{
					$returnstart = mktime(0, 0, 0, $startdate[1], $startdate[2], $startdate[0]);
					$returnend = mktime(23, 59, 59, $enddate[1], $enddate[2], $enddate[0]);
				}				
				else
				{
					$returnstart = null;
					$returnend = null;
				}
				
				
				if ($returnstart==null)
				{
					$this->AddWarning("Incorrect date format detected [".implode("/", $date) ."]");
				}
				$this->record[$fieldname."_from"] = $returnstart; // update timestamp
				$this->record[$fieldname."_to"] = $returnend; // update timestamp
				
			}
			catch(Exception $ex)
			{
				$this->AddError("Unhandled exception thrown in try/catch of StoreDateRegion: " . $ex->getMessage());
			}
			
		}
	}
	
	protected function DateToTS($fieldname)
	{
		$value = $this->record[$fieldname];
		if (!empty($value)) // if they're not empty, obviously
		{
			$value = str_replace("-", "/", $value);
			
			$date = explode("/", $value);
			
			if (count($date)==1)
				return;
			
			// if day or month contain 00 this means the scope is either month or year, but we'll set these to 1 otherwise the date conversion breaks
			if ($date[2] == (string) "00")
			{
				$date[2] = 1;
			}

			if ($date[1] == (string) "00")
			{
				$date[1] = 1;
			}
			
			try 
			{
				if ((is_numeric($date[0]))&&(is_numeric($date[1]))&&(is_numeric($date[2])))
				{
					$returntime = mktime(0, 0, 0, $date[1], $date[2], $date[0]);
				}				
				else
				{
					$returntime = null;
				}
				
				
				if ($returntime==null)
				{
					$this->AddError("Incorrect date format detected [".implode("/", $date) ."]");
				}
				$this->record[$fieldname] = $returntime; // update timestamp

			}
			catch(Exception $ex)
			{
				$this->AddError("Unhandled exception thrown in try/catch of DateToTS: " . $ex->getMessage());
			}
			
		}
	}
	
	protected function RemoveTrailingComma($oldStr)
	{
		$oldStr = trim($oldStr);
		$oldStr = trim($oldStr, "|");
		while (substr($oldStr, 0, 1)==",")
			$oldStr = trim(substr($oldStr, 1));
	
		while (substr($oldStr, strlen($oldStr)-1, 1)==",")
			$oldStr = trim(substr($oldStr, 0, -1));
		return $oldStr;
	}
	
	
	protected function ProcessNewline($field, $key)
	{
		$tfield = $field;
		
		// Remove from end
		while(endsWith($tfield, "[NEWLINE]"))
			$tfield = substr($tfield,0, count($tfield)-10);

		// Replace in text with <br/><br/>
		$tfield = str_replace("[NEWLINE]", "\r\n", $tfield);
		
		if ($tfield!=$field)
			$this->AddInformation("Detected [NEWLINE] in " . $key . " so removing tail and replacing with \\r\\n");
		
		return $tfield;
	}
	
	protected function ConvertToASCII($orig, $fieldName)
	{
		$text = $orig;
		/*
	    $text = preg_replace("/[�����]/u","a",$text);
	    $text = preg_replace("/[�����]/u","A",$text);
	    $text = preg_replace("/[����]/u","I",$text);
	    $text = preg_replace("/[����]/u","i",$text);
	    $text = preg_replace("/[����]/u","e",$text);
	    $text = preg_replace("/[����]/u","E",$text);
	    $text = preg_replace("/[������]/u","o",$text);
	    $text = preg_replace("/[�����]/u","O",$text);
	    $text = preg_replace("/[����]/u","u",$text);
	    $text = preg_replace("/[����]/u","U",$text);
	    $text = preg_replace("/[�����]/u","'",$text);
	    $text = preg_replace("/[�����]/u",'"',$text);
	    */
	    $text = str_replace("�","-",$text);
	    $text = str_replace(" "," ",$text);
	    $text = str_replace("�","c",$text);
	    $text = str_replace("�","C",$text);
	    $text = str_replace("�","n",$text);
	    $text = str_replace("�","N",$text);
	  
//	    foreach ($trans as $k => $v) {
//	        $text = str_replace($v, $k, $text);
//	    }
	 
	    $text = preg_replace('/[^(\x20-\x7F)]*/','', $text);
	 
	    $targets=array('\r\n','\n','\r','\t');
	    $results=array(" "," "," ","");
	    $text = str_replace($targets,$results,$text);
//	 	$text = $this::StripUTF8($text);
	    
		if ($text!=$orig)
			$this->AddWarning("Converted text in field " . $fieldName . " to ASCII");
			
		return $text;
	} 

	/**
	 * This converts to a URL friendly format
	 */
	protected function nameConvert($name)
	{
		$clean = $name;
		
		$no  = array( " ", ":", "'", "&", ".", ",", "/", ";", "(", ")", "[NEWLINE]", "[", "]", "�", "?" );
		$yes = array( "-", "",  "",  "",  "",  "",  "-", "",  "",  "",  "",          "",  "",  "",  ""  );
		
	    $clean = preg_replace('/[^(\x20-\x7F)]*/','', $clean);
		
		$clean = str_replace($no, $yes, trim(strtolower($clean)));
		$clean = iconv('ASCII', 'UTF-8//IGNORE', $clean);
//		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);  
		$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);  
		$clean = strtolower(trim($clean, '-'));  
		$clean = preg_replace("/[\/_|+ -]+/", "-", $clean); 
	 
		if (strlen($clean) > 100)
		{
			$clean = substr($clean, 0, 100);
		}
		
		return $clean;
	}
	
    /**
	 * Used for testing only!
	 */
	public function testSetDocID($docID)
	{
		$this->record["begin_doc_id"] = $docID;
	}

}
