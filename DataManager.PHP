<?php

date_default_timezone_set("America/New_York");
header('Access-Control-Allow-Origin: *');

$servername = "localhost";
$username = "tailored_master";
$password = "Tailored1";
$dbname = "tailored_jobboard";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}

$func = $_POST["function"];
$data = $_POST["data"];

switch($func)
{
	case "NewJob":
	insertIntoJobs($conn, $data);
	break;
	case "CreateJobsTable":
	createJobsTable($conn, $data);
	break;
	case "SyncJobs":
	cloneGoogleSheetToJobsTable($conn, $data);
	break;
	case "SyncCandidatesInConsideration":
	cloneSheetsToNewCandidatesInConsideration($conn, $data);
	break;
	case "SyncCandidates":
	syncCandidates($conn, $data);
	break;
	case "ModifyJob":
	echo modifyJob($conn, $data, $_POST["originalReq"]);
	break;
	case "PlaceOnHold":
	echo placeJobOnHold($conn, $data);
	break;
	case "CloseJob":
	echo closeJob($conn, $data);
	break;
	case "RetrieveHeadersAndValues":
	$headers = retrieveHeaders($conn);
	$values = retrieveValues($conn, $data, $headers);
	echo $headers . "<elementSplit>" . $values;
	break;
	case "Test":
	echo retrieveBasedOnPriority($conn, $data);
	break;
	case "Search":
	echo searchFunction($conn, $_POST["search"], $_POST["column"], $_POST["priorities"], $_POST["order"], $_POST["ascending"]);
	break;
	case "CandidateSearch":
	echo searchForCandidate($conn, $_POST["search"], $_POST["priorities"], $_POST["order"]);
	break;
	case "DoEdit":
	echo doEdit($conn, $data, $_POST["req"]);
	break;
	case "GetCandidates":
	echo getCandidates($conn, $data);
	break;
	case "SubmitCandidate":
	echo submitCandidate($conn, $data, $_POST["req"], $_POST["candidate"]);
	break;
	case "SortedGet":
	echo sortedGet($conn, $_POST["search"], $_POST["column"], $_POST["order"]);
	break;
	case "SortedGetNew":
	echo sortedGetNew($conn, $_POST["search"], $_POST["column"], $_POST["order"]);
	break;
	case "AddCandidate":
	echo addCandidate($conn, $data, $_POST["candidateQuery"]);
	break;
	case "GetDetails":
	echo getDetails($conn, $data);
	break;
	case "RemoveCandidate":
	removeCandidate($conn, $_POST["id"], $data);
	break;
	case "UpdateCandidateStatus":
	echo updateCandidateStatus($conn, $_POST["id"], $data, $_POST["notes"], $_POST["recruiter"]);
	break;
	case "GetFormattedDetails":
	echo getFormattedDetails($conn, $data);
	break;
	case "GetScreenData":
	echo getScreenData($conn, $data);
	break;
}

function getScreenData($conn, $data)
{
$result = $conn->query("SELECT * FROM Jobs WHERE Type_of_Service='" . $data . "'");
$output = "";
while ($row = $result->fetch_assoc()) {
foreach($row as $element)
{
$output .= $element . "<dataElement>";
}
$output .= "<rowElement>";
}
return $output;
}



function getFormattedDetails($conn, $req)
{
	$result = $conn->query("SELECT * FROM CandidatesInConsideration WHERE Req_Number='" . $req . "'");
	$inconsideration = 0;
	$removed = 0;
	$submissions = 0;
	$offers = 0;
	while ($row = $result->fetch_assoc()) {
                $status = $row['Status'];
		if ($status == "Removed")
		{
			$removed++;
		}
		else if ($status == "Offer Accepted")
		{
			$offers++;
			$inconsideration--;
		}
		else if ($status == "Submitted" || $status == "Interview Requested" || $status == "Interview Confirmed" || $status == "Offer Requested")
		{
			$inconsideration++;
		}
		
                if ($status != "Pending")
		{
			$submissions++;
		}
		if ($inconsideration < 0)
		{
			$inconsideration = 0;
		}
	}
	return $removed . ";" . $inconsideration . ";" . $submissions . ";" . $offers;
}
function removeCandidate($conn, $id, $data)
{
	if ($conn->query("DELETE FROM CandidatesInConsideration WHERE ID='" . $id. "'") === FALSE) {
		echo "Error: " . "DELETE FAILED" . "<br>" . $conn->error;
	}
	if ($conn->query("INSERT INTO CandidatesRemoved " . $data) === FALSE) {
		echo "Error: " . "DELETE FAILED" . "<br>" . $conn->error;
	}
}
function getConsiderationDetails($conn, $req)
{
	$result = $conn->query("SELECT * FROM CandidatesInConsideration WHERE Req_Number='" . $req . "'");
	$inconsideration = 0;
	$removed = 0;
	$submissions = 0;
	$offers = 0;
	while ($row = $result->fetch_assoc()) {
                $status = $row['Status'];
		if ($status == "Removed")
		{
			$removed++;
		}
		else if ($status == "Offer Accepted")
		{
			$offers++;
			$inconsideration--;
		}
		else if ($status == "Submitted" || $status == "Interview Requested" || $status == "Interview Confirmed" || $status == "Offer Requested")
		{
			$inconsideration++;
		}
		
                if ($status != "Pending")
		{
			$submissions++;
		}
		if ($inconsideration < 0)
		{
			$inconsideration = 0;
		}
	}
	return $removed . ";" . $inconsideration . ";" . $submissions . ";" . $offers;
}
function updateCandidateStatus($conn, $id, $data, $notes, $recruiter)
{
	$addOpenings = 0;
	$result = $conn->query("SELECT Notes, Req_Number, Status FROM CandidatesInConsideration WHERE ID='" . $id. "'")->fetch_assoc();
	$notes = $notes . ', ' . $result['Notes'];
	$req = $result['Req_Number'];
	$oldstatus = $result['Status'];
	
/* if (($oldstatus == "Offer Accepted") && ($data != "Offer Accepted"))
{
$addOpenings = 1;
}
if (($data == "Offer Accepted") && ($oldstatus != "Offer Accepted"))
{
$addOpenings = -1;
} */

	$query = "UPDATE CandidatesInConsideration SET Status='" . $data . "', Notes='" . $notes . "' WHERE ID='" . $id. "'";

	if ($conn->query($query) === FALSE) {
		$errormessage = "Error: " . $query. "<br>" . $conn->error;
		return $errormessage . "|" . count($values) . "|";
	}
	else
	{
		$stamp = $conn->query("SELECT Stamp FROM CandidatesInConsideration WHERE ID='" . $id . "'")->fetch_assoc()['Stamp'];
		$log = $conn->query("SELECT Log FROM CandidatesInConsideration WHERE ID='" . $id . "'")->fetch_assoc()['Log'];
		$res = $conn->query("UPDATE CandidatesInConsideration SET Log='" . $stamp . " | " . $data . "; " . $recruiter . ", " . $log . "' WHERE ID='" . $id . "'"); 
		if ($res === FALSE)
		{
		return $errormessage = "Error: <br>" . $conn->error;	
		}
		else
		{
			updateJobConsiderationwithoutactive($conn, $req, $addOpenings);
			return (new \DateTime())->format('Y-m-d H:i:s');
		}
	}
}
function updateJobConsiderationwithoutactive($conn, $req, $addOpenings)
{
	$r= $conn->query("SELECT Openings, Priority FROM Jobs WHERE Req_Number='" . $req . "'");
	
	if (!$r)
	{
		 throw new Exception("SELECT error: " . $conn->error);
	}
	else
	{
		$result = $r->fetch_assoc();
	}
	$openings = $result['Openings'];
	$status = $result['Priority'];

	$openings = strval(intval($openings) + $addOpenings);

	$split = explode(";", getConsiderationDetails($conn, $req));

	$needed = (intval($openings) * 2) - intval($split[1]);

	if ($needed <= 0)
	{
		$needed = 0;
		$status = "Covered";
	}

	$ur = $conn->query("UPDATE Jobs SET Openings='" . $openings . "', Priority='" . $status . "', Candidates_in_Consideration='" . $split[1] . "', Submissions='" . $split[2] . "', Candidates_Rejected='" . $split[0] . "', Number_of_submissions_needed='" . $needed . "' WHERE Req_Number='" . $req . "'" );
	
	if (!$ur)
	{

		throw new Exception("UPDATE error: " . $conn->error);
	}

}
function updateJobConsideration($conn, $req, $addOpenings)
{
	$r= $conn->query("SELECT Openings, Priority FROM Jobs WHERE Req_Number='" . $req . "'");
	
	if (!$r)
	{
		 throw new Exception("SELECT error: " . $conn->error);
	}
	else
	{
		$result = $r->fetch_assoc();
	}
	$openings = $result['Openings'];
	$status = $result['Priority'];

	$openings = strval(intval($openings) + $addOpenings);

	$split = explode(";", getConsiderationDetails($conn, $req));

	$needed = (intval($openings) * 2) - intval($split[1]);

	if ($needed <= 0)
	{
		$needed = 0;
		$status = "Covered";
	}
	else
	{
		if ($status != "On Hold")
		{
			$status = "Active";
		}
	}


	$ur = $conn->query("UPDATE Jobs SET Openings='" . $openings . "', Priority='" . $status . "', Candidates_in_Consideration='" . $split[1] . "', Submissions='" . $split[2] . "', Candidates_Rejected='" . $split[0] . "', Number_of_submissions_needed='" . $needed . "' WHERE Req_Number='" . $req . "'" );
	
	if (!$ur)
	{

		throw new Exception("UPDATE error: " . $conn->error);
	}

}
function cloneSheetsToNewCandidatesInConsideration($conn, $data)
{
	echo "begin clone of candidates\n";
	createCandidates($conn);
	$rows = explode("<rowBreak>", $data);
	foreach ($rows as $element)
	{
		$split = explode("<dataElement>", $element);
		submitCandidate($conn, $split[0], $split[1], $split[2], "Consideration");
	}

}
function createCandidatesTable($conn)
{
	echo "begin CreateCandidatesTable\n";
	
	if ($conn->query("DROP TABLE IF EXISTS Candidates") === FALSE) {
		echo "Error: " . "DROP TABLE" . "<br>" . $conn->error;
	}
	
	$sql = "CREATE TABLE Candidates (";
	
	$sql .= "Candidate_ID VARCHAR(255), Name VARCHAR(255) NOT NULL, Notes TEXT NOT NULL, Stamp TIMESTAMP, PRIMARY KEY (Candidate_ID)";

	$sql .= ")";

	if ($conn->query($sql) === FALSE) {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
}
function syncCandidates($conn, $data)
{
	createCandidatesTable($conn);
	$rows = explode("<rowBreak>", $data);
	
	foreach ($rows as $element)
	{
		$split = explode("<dataElement>", $element);
		addCandidateToTable($conn, $split[0], $split[1], $split[2], "Candidate");
	}
}
function addCandidateToTable($conn, $data, $req, $candidate, $code)
{
	$query = "INSERT INTO Candidates " . $data;
	if ($conn->query($query) === FALSE) {
		echo "Error: " . $query. "<br>" . $conn->error;
		echo "|" . count($dataArray) . "|";
		if ($conn->query("INSERT INTO ErrorLog (Req, Candidate, Code) VALUES ('" . $req . "', '" . $candidate . "', '" . $code . "')") == FALSE)
		{
			echo $conn->error;
		}
	}
}
function getDetails($conn, $data)
{
	$sql = "SELECT * FROM CandidatesInConsideration WHERE ReqNumber='" . $data . "'";

	$result = $conn->query($sql);
	$output = "";
	$count = 0;
	while ($row = $result->fetch_assoc()) {
		$count++;
		
	}
echo "needed<mainBreak>" . $count . "<mainBreak>rejects<mainBreak>";
}
function addCandidate($conn, $data, $candidateQuery)
{
	$temp = explode("<dataElement>", $data);
	$split = explode("<breakPoint>", $temp[0]);
	$req = $temp[1];
	$candidate = $temp[2];
	$code = $temp[3];
	if ($conn->query($split[0] . $split[1] . $split[2]) === FALSE) {
		echo "Error: " . $data . "<br>" . $conn->error;
		if ($conn->query($split[0] . "('Writeup or Additional Info failed'), ('Writeup or Additional Info failed')" . $split[2]) === FALSE) {
			echo "Error: " . $data . "<br>" . $conn->error;
			if ($conn->query("INSERT INTO ErrorLog (Req, Candidate, Code) VALUES ('" . $req . "', '" . $candidate . "', '" . $code . "')") == FALSE)
			{
				echo $conn->error;
			}
		}

	}
	
	$temp = explode("<dataElement>", $candidateQuery);
	$req = $temp[1];
	$candidate = $temp[2];
	$code = $temp[3];
	if ($conn->query($temp[0]) === FALSE) {
		echo "Error: " . $data . "<br>" . $conn->error;
		
			if ($conn->query("INSERT INTO ErrorLog (Req, Candidate, Code) VALUES ('" . $req . "', '" . $candidate . "', '" . $code . "')") == FALSE)
			{
				echo $conn->error;
			}
		

	}

}
function sortedGet($conn, $data, $sort)
{
	$sql = "SELECT * FROM Jobs WHERE " . $data . " ORDER BY " . $sort . " ASC";
	$result = $conn->query($sql);
	$output = "";
	while ($row = $result->fetch_assoc()) {
		foreach($row as $element)
		{
			$output .= $element . "<dataElement>";
		}
		$output .= "<rowElement>";
	}
	return $output;
}
function submitCandidate($conn, $data, $req, $candidate, $code)
{
	$split = explode("<breakPoint>", $data);
	
	$query = "INSERT INTO CandidatesInConsideration " . $split[0] . $split[1] . $split[2];
	if ($conn->query($query) === FALSE) {
		echo "Error: " . $query. "<br>" . $conn->error;
		echo "|" . count($dataArray) . "|";
		if ($conn->query("INSERT INTO CandidatesInConsideration " . $split[0] . "'Writeup or Additional Info failed', 'Writeup or Additional Info failed'" . $split[2]) === FALSE)
		{
			$conn->query("INSERT INTO ErrorLog (Req, Candidate, Code) VALUES (('" . $req . "'), ('" . $candidate . "'), ('" . $code . "'))");
		}
	}
	updateJobConsideration($conn, $req, 0);
	
}
function cloneGoogleSheetToCandidatesTable($conn, $data)
{
	echo "begin clone of candidates\n";
	createCandidates($conn);
	$rows = explode("<rowBreak>", $data);
	foreach ($rows as $element)
	{
		insertIntoCandidates($conn, $element);
	}

}
function insertIntoCandidates($conn, $element)
{
	$query = "INSERT INTO CandidatesInConsideration VALUES (";
	$dataArray = explode("<dataElement>", $element); 
	
	foreach($dataArray as $dataElement)
	{
		$value = str_replace(array("'","(",")"), "", $dataElement);
		$query .= "('" . (string)$value . "'),";
	}
	$query = rtrim($query, ",");
	
	$query .= ")";
	if ($conn->query($query) === FALSE) {
		echo "Error: " . $query. "<br>" . $conn->error;
		echo "|" . count($dataArray) . "|";
	}
}
function getCandidates($conn, $data)
{
	
	$output = "";
	$sql = "SELECT * FROM CandidatesInConsideration WHERE Req_Number='" . $data . "'";

	$result = $conn->query($sql);
	
	while ($row = $result->fetch_assoc()) {
		$output .= getCandidateName($conn, $row['Candidate_ID']) . "<dataElement>";
		foreach($row as $element)
		{
			$output .= $element . "<dataElement>";
		}
		$output .= "<rowElement>";
	}
	return $output;
}
function getCandidateName($conn, $data)
{

	$result2 = $conn->query("SELECT Name FROM Candidates WHERE Candidate_ID='" . $data. "'");
	if ($result2->num_rows > 0) {
    		   return $result2->fetch_assoc()['Name'];	
	}
	 else {
		return "failed name retrieval";   
	}
}

function createCandidates($conn)
{
	echo "begin CreateCandidatesTable\n";
	
	if ($conn->query("DROP TABLE IF EXISTS CandidatesInConsideration") === FALSE) {
		echo "Error: " . "DROP TABLE" . "<br>" . $conn->error;
	}
	
	$sql = "CREATE TABLE CandidatesInConsideration (";
	
	$sql .= "ID int(11) NOT NULL AUTO_INCREMENT, Candidate_ID VARCHAR(255) NOT NULL, Req_Number VARCHAR(255), Status VARCHAR(255) NOT NULL, Recruiter VARCHAR(255), Pay_Rate FLOAT, Writeup TEXT, AdditionalInfo TEXT, Submission_Date DATE, Notes TEXT, Log TEXT NOT NULL, Stamp TIMESTAMP, PRIMARY KEY (ID)";

	$sql .= ")";

	if ($conn->query($sql) === FALSE) {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
}
function searchFunction($conn, $search, $column, $priorities, $sortby, $ascend)
{

	if ($column == "Candidate")
	{
		return searchForCandidate($conn, $search, $priorities, $sortby);
	}
	if ($sortby != "")
	{
		switch($sortby)
		{
			case "Req_Number":
			case "Candidates_In_Consideration":
			$sortby = " ORDER BY ABS(" . $sortby . ") " . $ascend;
			break;
			case "Pay_Rate":
			case "Bill_Rate":
			$sortby = " ORDER BY CAST(" . $sortby . " as decimal(38,10)) " . $ascend;
			break;
			default:
			$sortby = " ORDER BY " . $sortby . " " . $ascend;
			break;
		}
	}
	if (($priorities != "") && ($search != ""))
	{
	$sql = "SELECT * FROM Jobs WHERE (" . $priorities . ") AND " . $column . " LIKE '%" . $search . "%'" . $sortby;
	}
	else if (($priorities == "") && ($search != ""))
	{
	$sql = "SELECT * FROM Jobs WHERE " . $column . " LIKE '%" . $search . "%'" . $sortby;
	}
	else if (($priorities != "") && ($search == ""))
	{
	$sql = "SELECT * FROM Jobs WHERE (" . $priorities . ") " . $sortby;
	}
	else
	{
	$sql = "SELECT * FROM Jobs " . $sortby;
	}
	$result = $conn->query($sql);
	$output = "";
	while ($row = $result->fetch_assoc()) {
		//updateJobConsideration($conn, $row['Req_Number'], 0);

		$output .= $row['Req_Number'] . "<dataElement>" . $row['Client'] . "<dataElement>" . $row['Job_Title'] . "<dataElement>" . $row['Type_of_Service'] . "<dataElement>" . $row['Priority'] . "<dataElement>" . $row['Location'] . "<dataElement>" . $row['Pay_Rate'] . "<dataElement>" . $row['Bill_Rate'] . "<dataElement>" . $row['Open_Date'] . "<dataElement>" . $row['Submittal_Deadline'] . "<dataElement>" . $row['Openings'] . "<dataElement>" . $row['Candidates_in_Consideration'] . "<dataElement>" . $row['Submissions'] . "<dataElement>" . $row['Candidates_Rejected'] . "<dataElement>" . $row['Number_of_submissions_needed'] . "<dataElement>" . $row['Description'] . "<dataElement>" . $row['Notes'] . "<dataElement>" . $row['Start_Date'] . "<dataElement>" . $row['End_Date'] . "<dataElement>" . $row['Hiring_Manager'] . "<dataElement>" . $row['Address'] . "<dataElement>" . $row['Override'] . "<dataElement>" . $row['Stamp'];
		
		
		$output .= "<rowElement>";
	}
	return $output;
}
function sortedGetNew($conn, $search, $column, $sortby)
{
	if ($sortby != "")
	{
		$sortby = " ORDER BY " . $sortby . " ASC";
	}
	if ($search == "")
	{
		$sql = "SELECT * FROM Jobs " . $sortby;
	}
	else
	{
		$sql = "SELECT * FROM Jobs WHERE " . $column . " LIKE '%" . $search . "%'" . $sortby;
	}
	$result = $conn->query($sql);
	$output = "";
	while ($row = $result->fetch_assoc()) {
		$output .= $row['Req_Number'] . "<dataElement>" . $row['Client'] . "<dataElement>" . $row['Job_Title'] . "<dataElement>" . $row['Type_of_Service'] . "<dataElement>" . $row['Priority'] . "<dataElement>" . $row['Location'] . "<dataElement>" . $row['Pay_Rate'] . "<dataElement>" . $row['Bill_Rate'] . "<dataElement>" . $row['Open_Date'] . "<dataElement>" . $row['Submittal_Deadline'] . "<dataElement>" . explode(";", getConsiderationDetails($conn, $row['Req_Number']))[3] . "<dataElement>" . $row['Candidates_in_Consideration'] . "<dataElement>" . $row['Submissions'] . "<dataElement>" . $row['Candidates_Rejected'] . "<dataElement>" . $row['Number_of_submissions_needed'] . "<dataElement>" . $row['Description'] . "<dataElement>" . $row['Notes'] . "<dataElement>" . $row['Start_Date'] . "<dataElement>" . $row['End_Date'] . "<dataElement>" . $row['Hiring_Manager'] . "<dataElement>" . $row['Address'] . "<dataElement>" . $row['Override'] . "<dataElement>" . $row['Stamp'];
		
		
		$output .= "<rowElement>";
	}
	return $output;
}
function searchForCandidate($conn, $search, $priorities, $sortby)
{
	$output = "";
	$candidates = $conn->query("SELECT * FROM Candidates WHERE Name LIKE '%" . $search . "%'");
	while($cands = $candidates->fetch_assoc())
	{
		$sql = "SELECT * FROM CandidatesInConsideration WHERE Candidate_ID='" . $cands['Candidate_ID'] . "'";
		$result = $conn->query($sql);
		while ($row = $result->fetch_assoc()) {
			$output .= getReqResult($conn, $priorities, $row['Req_Number']);
		}
	}
	return $output;
}

function getReqResult($conn, $priorities, $req)
{
	if ($priorities != "")
	{
	$sql = "SELECT * FROM Jobs WHERE (" . $priorities . ") AND (Req_Number='" . $req . "')";
	}
	else
	{
	$sql = "SELECT * FROM Jobs WHERE Req_Number='" . $req . "'";
	}
	$result = $conn->query($sql);
	$output = "";
	while ($row = $result->fetch_assoc()) {
		$output .= $row['Req_Number'] . "<dataElement>" . $row['Client'] . "<dataElement>" . $row['Job_Title'] . "<dataElement>" . $row['Type_of_Service'] . "<dataElement>" . $row['Priority'] . "<dataElement>" . $row['Location'] . "<dataElement>" . $row['Pay_Rate'] . "<dataElement>" . $row['Bill_Rate'] . "<dataElement>" . $row['Open_Date'] . "<dataElement>" . $row['Submittal_Deadline'] . "<dataElement>" . $row['Openings'] . "<dataElement>" . $row['Candidates_in_Consideration'] . "<dataElement>" . $row['Submissions'] . "<dataElement>" . $row['Candidates_Rejected'] . "<dataElement>" . $row['Number_of_submissions_needed'] . "<dataElement>" . $row['Description'] . "<dataElement>" . $row['Notes'] . "<dataElement>" . $row['Start_Date'] . "<dataElement>" . $row['End_Date'] . "<dataElement>" . $row['Hiring_Manager'] . "<dataElement>" . $row['Address'] . "<dataElement>" . $row['Override'] . "<dataElement>" . $row['Stamp'];
		
		
		$output .= "<rowElement>";
	}
	return $output;
}

function placeJobOnHold($conn, $data)
{
	$query = "UPDATE Jobs SET Priority='On Hold' WHERE Req_Number='" . $data . "'";

	if ($conn->query($query) === FALSE) {
		$errormessage = "Error: " . $query. "<br>" . $conn->error;
		return $errormessage . "|" . count($values) . "|";
	}
	else
	{
		return "working onHold";
	}
}
function closeJob($conn, $data)
{
	$query = "UPDATE Jobs SET Priority='Closed' WHERE Req_Number='" . $data . "'";

	if ($conn->query($query) === FALSE) {
		$errormessage = "Error: " . $query. "<br>" . $conn->error;
		return $errormessage . "|" . count($values) . "|";
	}
	else
	{
		return "working closed";
	}
}
function retrieveHeaders($conn)
{
	$sql = "SHOW COLUMNS FROM Jobs";
	$result = $conn->query($sql);
	$data = "";
	while ($row = $result->fetch_assoc()) {
		$data .= $row['Field'] . "<dataElement>";
	}
	return $data;
}
function retrieveValues($conn, $data, $headers)
{
	$headerArray = explode("<dataElement>", $headers);
	$columns = str_replace("<dataElement>", ",", $headers);
	$columns = rtrim($columns, ",");
	$sql = "SELECT " . $columns . " FROM Jobs WHERE Req_Number='" . $data . "'";
	$result = $conn->query($sql);
	$output = "";
	$row = $result->fetch_assoc();
	foreach ($headerArray as $index)
	{
		if ($index == 'Candidates_in_Consideration') {
		
			$output .= explode(";", getFormattedDetails($conn, $req))[1];
		}
		else {
			$output .= $row[$index] . "<dataElement>";
		}
	}
	return $output;
}
function retrieveBasedOnPriority($conn, $priorities)
{
	$sql = "SELECT * FROM Jobs WHERE " . $priorities;
	$result = $conn->query($sql);
	$output = "";

	while ($row = $result->fetch_assoc()) {
		//updateJobConsideration($conn, $row['Req_Number'], 0);
		$output .= $row['Req_Number'] . "<dataElement>" . $row['Client'] . "<dataElement>" . $row['Job_Title'] . "<dataElement>" . $row['Type_of_Service'] . "<dataElement>" . $row['Priority'] . "<dataElement>" . $row['Location'] . "<dataElement>" . $row['Pay_Rate'] . "<dataElement>" . $row['Bill_Rate'] . "<dataElement>" . $row['Open_Date'] . "<dataElement>" . $row['Submittal_Deadline'] . "<dataElement>" . $row['Openings'] . "<dataElement>" . $row['Candidates_in_Consideration'] . "<dataElement>" . $row['Submissions'] . "<dataElement>" . $row['Candidates_Rejected'] . "<dataElement>" . $row['Number_of_submissions_needed'] . "<dataElement>" . $row['Description'] . "<dataElement>" . $row['Notes'] . "<dataElement>" . $row['Start_Date'] . "<dataElement>" . $row['End_Date'] . "<dataElement>" . $row['Hiring_Manager'] . "<dataElement>" . $row['Address'] . "<dataElement>" . $row['Override'] . "<dataElement>" . $row['Stamp'];
		
		
		
		//foreach($row as $element)
		//{
		//	$output .= $element . "<dataElement>";
		//}
		$output .= "<rowElement>";
	}
	return $output;
}
function createJobsTable($conn, $data)
{
	echo "begin CreateTable\n";
	$dataArray = explode("<dataElement>", $data); 
	
	if ($conn->query("DROP TABLE IF EXISTS Jobs") === FALSE) {
		echo "Error: " . "DROP TABLE" . "<br>" . $conn->error;
	}
	
	$sql = "CREATE TABLE Jobs (";
	
	foreach ($dataArray as $element)
	{
		$value = str_replace(" ", "_", $element);
		$value = str_replace("#", "Number", $value);
		if ($value == 'Req_Number') {
			$sql .= (string)$value . " VARCHAR(255) NOT NULL,";
		}
		else if ($value == 'Bill_Rate') {
			$sql .= (string)$value . " FLOAT NOT NULL,";
		}
		else if ($value == 'Pay_Rate') {
			$sql .= (string)$value . " FLOAT NOT NULL,";
		}
		else {
			$sql .= (string)$value . " TEXT NOT NULL,";
		}
	}	
	$sql .= "Stamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (Req_Number)";
	$sql .= ")";

	if ($conn->query($sql) === FALSE) {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
	else {
		echo "success";
	}

	$conn->close();
	
}

function doEdit($conn, $data, $req)
{
	$query = "UPDATE Jobs SET " . $data . " WHERE Req_Number='" . $req . "'";

	if ($conn->query($query) === FALSE) {
		$errormessage = "Error: " . $query. "<br>" . $conn->error;
		return $errormessage . "|" . count($values) . "|";
	}
	else
	{
		return "working doEdit";
	}

}
function modifyJob($conn, $data, $req)
{
	$values = explode("<dataElement>", substr($data, 0, -13));

	if ($conn->query("DELETE FROM Jobs WHERE Req_Number='" . $req . "'") === FALSE) {
		return "Error: " . "DELETE FAILED" . "<br>" . $conn->error;
	}

	$query = "INSERT INTO Jobs VALUES (";
	
	foreach($values as $element)
	{
		$value = str_replace(array("'","(",")"), "", $element);
		$query .= "('" . (string)$value . "'),";
	}
	$query = rtrim($query, ",");
	
	$query .= ")";
	//return $query;
	if ($conn->query($query) === FALSE) {
		$errormessage = "Error: " . $query. "<br>" . $conn->error;
		return $errormessage . "|" . count($values) . "|";
	}
	else
	{
		return "working modifyJob";
	}
}

function cloneGoogleSheetToJobsTable($conn, $data)
{
	echo "begin clone of jobs\n";
	$rows = explode("<rowBreak>", $data);
	foreach ($rows as $element)
	{
		insertIntoJobs($conn, $element);
	}
	
}

function generateJobsReport()
{

}

function dataToArray()
{
	global $data;
	$dataArray = explode("<dataElement>", $data);
	return $dataArray;
}
function insertIntoJobs($conn, $data)
{
	$query = "INSERT INTO Jobs VALUES (";
	$dataArray = explode("<dataElement>", $data); 
	
	foreach($dataArray as $element)
	{
		$value = str_replace(array("'","(",")","\"","\n","\t"), "", $element);
		$query .= "('" . (string)$value . "'),";
	}
	//$query = rtrim($query, ",");
	
	$query .= "(NOW()))";
	
	if ($conn->query($query) === FALSE) {
		echo "Error: " . $query. "<br>" . $conn->error;
		echo "|" . count($dataArray) . "|";
	}
}

?>
