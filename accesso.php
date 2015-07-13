<?php

	$web = 0 ;


	//------------------------------------------------------
	//
	// Include Files
	//
	//------------------------------------------------------ 

	require "random_code.php";
	require "twilio.php";

	$ApiVersion = "2010-04-01";
	$AccountSid = "<<TWILIO ACCOUNT SID>>";
	$AuthToken = "<<TWILIO ACCOUNT TOKEN>>";
	$client = new TwilioRestClient($AccountSid, $AuthToken);

	//------------------------------------------------------
	//
	// Set Variables
	//
	//------------------------------------------------------ 

	$db_host='localhost';  
	$db_name='<<DB NAME>>';  
	$db_user='<<DB USER>>';  
	$db_passwd='<<DB PASSWORD>>';

	if ($web){    	
		$pressbuzzer = 'BUZZER PRESS';
		$pressgarage = 'GARAGE PRESS';
		$body 		 =  htmlspecialchars($_GET["Body"]);
		$sender 	 =  "+".htmlspecialchars($_GET["Sender"]);				// TWilio sends number as +1XXXXXXXXXX
		$twinum 	 = '<<TWILIO NUMBER>>';		// testing
	}
	else{
		$pressbuzzer = '<<BUZZER GUID>>';
		$pressgarage = '<<GARAGE GUID>>';
	//	$pressbuzzer = 'Press Buzzer';
	//	$pressgarage = 'Toggle Garage';
		$body 		 = $_REQUEST['Body'];  // read message into string
		$sender		 = $_REQUEST['From'];
		$twinum 	 = '<<TWILIO NUMBER>>';	//	<----------------- release the hounds
		}

	$admin1 	= '<<ADMIN USER 1>>';
	$admin2 	= '<<ADMIN USER 2>>';
	$house  = '<<IFTTT NUMBER>>'; // <----- this is the one that actually opens the house!

	$selgar = 0 ;					// garage is selected
	$admin  = 0 ;					// user is admin
	$notnew = 0 ;					// user is not new to the system
	$toggle_garage = 0 ;
	$open_gate = 0 ;

	$currenttime = date( 'Y-m-d H:i:s', time() );	

	//------------------------------------------------------
	//
	// Database Connect and Read Table
	//
	//------------------------------------------------------ 
	
	$connect = mysql_connect($db_host, $db_user, $db_passwd) ;

	$link = mysql_select_db($db_name) ;
	if (!$link || !$connect){

		if ($web)
			web_response ('Admin',$twinum,'[Automated Home Database Connection Issues]');
		else 
			$response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array(
			"To"   => $voice,
			"From" => $twinum,
			"Body" => 'Automated Home Database Connection Issues'));
		
		die('Could not select database');
		}

	$all = array();
	$numbers = array();
	$sql = "SELECT * FROM visitors";
	$result = mysql_query($sql);
	while ($row = mysql_fetch_assoc($result))
		{		$all[] = $row;
		 		$numbers[] = $row['PhoneNum'];		}

	$n = sizeof($all);

 
	//------------------------------------------------------
	//
	// Look up the sender's code and access end time in the database and check them against the incoming code and the current time
	//
	//------------------------------------------------------ 

	if (in_array($sender,$numbers))
 		$sendernotnew = 1;
 	else
 		$sendernotnew = 0;	

	$key = searchhaystack($all,'PhoneNum',$sender); // $key = 2;

	if ($sendernotnew){
		$thisuserscode = $all[$key]['AccessCode'];
		$thisusersendtime = $all[$key]['EndAccess'];
		$thisusersname = $all[$key]['FirstName'];
		}
	else {
		$thisuserscode = null;
		$thisusersendtime = null;
		$thisusersname = 'friend';
	}

	if (stripos($body,$thisuserscode) !== false) $codechecksout =  1 ;
	else $codechecksout = 0;

	$temp0 = new DateTime($thisusersendtime);
	$temp1 = new DateTime($currenttime);

	if ( $temp1 < $temp0 ) $timechecksout = 1 ;
	else $timechecksout = 0;

	//------------------------------------------------------
	//
	// Analyze the Incoming Text Message
	//
	//------------------------------------------------------ 

	if (stripos($body,'garage') !== false) $selgar = 1 ; 									// the message contains the word 'garage'
	if ($sender == $admin1 || $sender == $admin2 || $sender == $voice )	$admin = 1 ;			// the message is from an admin	

	// From Admin => allow <10-consecutive-digit-number> <integer number of days>    

	if ($admin == 1 && stripos($body,'allow') !== false ){									// if the message is from an admin and contains the word 'allow'...
		$adminisadminning = 1;
//		print "<pre>";
//	 	echo "Input string: ".$body."</br>";	
	 	$newstring = str_replace('allow','',$body);											// remove the word 'allow'
//	 	echo "String after removing allow: ".$newstring."</br>";	
 		$newstring = str_replace(' ','',$newstring);										// then remove spaces.... 
// 	 	echo "String after removing spaces: ".$newstring."</br>";	
	 	$matchnum = preg_match('/\d{10}/u', $newstring, $matches);							// find the first 10 consecutive digits in the message 
 		$newusernum = $matches[0];															// assign it to the new user number
 		$newstring = str_replace($newusernum,'',$newstring);								// then remove the phone number
//	 	echo "String after removing number: ".$newstring."</br>";	
 		$matchname = preg_match('/[a-zA-Z]+/', $newstring, $catches);			  			// find the next string of consecutive letters (this is the name)
 		if(!empty($catches[0]))
 				$newusername = $catches[0];													// assign it to the new user name
		else 	$newusername = 'friend';
//	 	echo "Name: ".$newusername."</br>";	
 		$newstring = str_replace($newusername,'',$newstring);								// then remove the name
//	 	echo "String after removing name: ".$newstring."</br>";	
		$daysallowed = $newstring;						    								// remove the phone number
 		$good = ctype_digit($newstring) && $matchnum ;				// make sure what is left is a number
 		$newusernum = "+1".$newusernum;
 		$notnew = in_array($newusernum,$numbers);
// 		print "</pre>";
 		$new_code = random_text( $type = 'distinct', $length = 5 );
		$enddate = date( 'Y-m-d H:i:s', time() + (24*3600*$daysallowed) );			
 	
  	}

	//------------------------------------------------------
	//
	// Display Code
	//
	//------------------------------------------------------ 

	if ($web){

	print "<pre>";
	echo "Sender: ".$sender."</br>";
	echo "Body: ".$body."</br>";
	echo "Garage Selected: ".$selgar."</br>";
	echo "Admin: ".$admin."</br></br>";	

		if ($adminisadminning){
			echo "Match Number: ".$matchnum."</br>";
			echo "Match Name: ".$matchname."</br>";
			echo "New User Name: ".$newusername."</br>";	
			echo "New User Number: ".$newusernum."</br>";
			echo "Days Allowed: ".$daysallowed."</br>";
			echo "Checks out: ".$good."</br></br>";	
			echo "Not New User: ".$notnew."</br>";
		}
		else{
			echo "The key is :".$key."</br>";
			echo "This users name: ".$thisusersname."</br>";
			echo "This sender is new: ".($sendernotnew==0 ? 'True' : 'False')."</br>";
			echo "Sendernotnew val: ".$sendernotnew."</br>";
			echo "This users code: ".$thisuserscode."</br>";
			echo "This users time: ".$thisusersendtime."</br>";		
			echo "Code checks out: ".$codechecksout."</br>";
			echo "Date checks out: ".$timechecksout."</br>";
		}

	echo "Now: ".$currenttime."</br></br>";
	print "</pre>";

	} 


	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//
	// 						Open Garage
	//
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	if ( $selgar && ( ($admin && !$adminisadminning) || (!$admin && $codechecksout && $timechecksout) ) ){	// Garage is selected, Admin (who is not adminning) or non admin with the right code and the right time
	// TOGGLE THE GARAGE AND SEND CONFIRMAITON

		$mssgtohouse = $pressgarage;
		$mssgtosendr = "I've toggled the garage ".$thisusersname;
		$mssgtoadmin1 = $thisusersname." ".$sender." toggled the garage";

		if ($web){
						 web_response ('HOUSE', $twinum, $mssgtohouse);
						 web_response ('SENDR', $twinum, $mssgtosendr );
			if (!$admin) web_response ('admin1', $twinum, $mssgtoadmin1 );
		}
		else {
						 $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $house,   "From" => $twinum, "Body" => $mssgtohouse ));										
						 $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $sender,  "From" => $twinum, "Body" => $mssgtosendr ));
			if (!$admin) $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $admin1,   "From" => $twinum, "Body" => $mssgtoadmin1 ));

		}
		mysql_query("UPDATE visitors SET HasBeen ='1', LastUse = '$currenttime' where PhoneNum='$sender' ");
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//
	// 						Open Gate
	//
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	elseif ( !$selgar && ( ($admin && !$adminisadminning) || (!$admin && $codechecksout && $timechecksout) ) ){
	// OPEN THE GATE AND SEND CONFIRMATION

		$mssgtohouse = $pressbuzzer;
		$mssgtosendr = "Come on in ".$thisusersname;
		$mssgtoadmin1 = $thisusersname." ".$sender." buzzed the gate";	

		if ($web){
						 web_response ('HOUSE', $twinum, $mssgtohouse);
						 web_response ('SENDR', $twinum, $mssgtosendr );
			if (!$admin) web_response ('admin1', $twinum, $mssgtoadmin1 );
		}
		else {
						 $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $house,   "From" => $twinum, "Body" => $mssgtohouse ));										
						 $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $sender,  "From" => $twinum, "Body" => $mssgtosendr ));
			if (!$admin) $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $admin1,   "From" => $twinum, "Body" => $mssgtoadmin1 ));
		}
		mysql_query("UPDATE visitors SET HasBeen ='1', LastUse = '$currenttime' where PhoneNum='$sender' ");		
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//
	// 						Add Update New User - Run Admin Tasks
	//
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	elseif ($adminisadminning){
		if ($good){

			if (!$notnew){
				if($web) echo "ADDING";
				mysql_query("INSERT INTO visitors (FirstName, PhoneNum, StartAccess, EndAccess, AccessCode) VALUES ('$newusername','$newusernum','$currenttime', '$enddate', '$new_code') ");}
			else{
				if($web) echo "UPDATING";
				mysql_query("UPDATE visitors SET FirstName='$newusername', StartAccess = '$currenttime', EndAccess = '$enddate', AccessCode = '$new_code' where PhoneNum='$newusernum' ");}


 			// TELL A USER THEY HAVE NEW ACCESS
			if ($daysallowed == 1)  $mssgtonuser = "Hi ".$newusername.". I am admin1 and admin2's smart home. Access granted for 24 hours. Text ".$new_code." back to me to buzz the gate. Add the word 'garage' if you'd rather open that.";
			else      				$mssgtonuser = "Hi ".$newusername.". I am admin1 and admin2's smart home. Access granted for ".$daysallowed." days. Text ".$new_code." to me to open the gate. Add the word 'garage' if you'd rather open that.";

									$mssgtosendr = "Gave ".$newusername." ".$newusernum." ".$daysallowed." days";	

			if ($web){
				if ($daysallowed != 0) web_response ($newusername,$twinum,$mssgtonuser);
				 					   web_response ('SENDR',     $twinum,$mssgtosendr);
			}
			else {
				if ($daysallowed != 0) $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To" => $newusernum,  "From" => $twinum, "Body" => $mssgtonuser ));
									   $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To" => $sender,      "From" => $twinum, "Body" => $mssgtosendr ));	
			}
	}	
		
		 	// TELL AN ADMIN USER THEY HAVE MADE A FORMATTING ERROR
		else {
			$mssgtosendr = "That entry is not good. Format is: allow Name [ph number] [days]";
			if ($web) web_response ($sender,$twinum,$mssgtosendr);
			else 	  $response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $sender,  "From" => $twinum, "Body" => $mssgtosendr  ));
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//
	// 						HANDLE NON-ADMIN ERROR CASE 1 :- RIGHT TIME, WRONG CODE
	//
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	elseif ($sendernotnew && $timechecksout && !$codechecksout) {

		$mssgtosendr = "I'm sorry, did you forget your code ".$thisusersname."?";
		$mssgtoadmin1 = $thisusersname." ".$sender." is having code problems";	

  		if ($web){ 
  			web_response ($sender,$twinum,$mssgtosendr);
  			web_response ('admin1',$twinum,$mssgtoadmin1);
		}
		else{
			$response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $sender,  "From" => $twinum, "Body" => $mssgtosendr ));
			$response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $admin1,   "From" => $twinum, "Body" => $mssgtoadmin1 ));
		}
	}
	
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//
	// 						HANDLE NON-ADMIN ERROR CASE 2 : WRONG TIME
	//
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	elseif ($sendernotnew && !$timechecksout && $codechecksout) {

		$mssgtosendr = "I'm sorry ".$thisusersname.", it looks like your key has expired. :'(";
		$mssgtoadmin1 = $thisusersname." ".$sender." just tried to get in with an expired key";	

  		if ($web){ 
  			web_response ($sender,$twinum,$mssgtosendr);
  			web_response ('admin1',$twinum,$mssgtoadmin1);
		}
		else{
			$response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $sender,  "From" => $twinum, "Body" => $mssgtosendr ));
			$response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $admin1,   "From" => $twinum, "Body" => $mssgtoadmin1 ));
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//
	// 						NEW USER
	//
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	else {

		$mssgtosendr = "Hello! I am 85 Linda, a very 'smart' home. I can open my gate for you just as soon as I get permission from my occupants. Please hold on for 1 minute.";
		$mssgtoadmin1 = "Knock knock! ".$sender." is texting the Smart Home" ;	

  		if ($web){ 
  			web_response ($sender,$twinum,$mssgtosendr);
  			web_response ('admin1',$twinum,$mssgtoadmin1);
		}
		else{
			$response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $sender,  "From" => $twinum, "Body" => $mssgtosendr ));
			$response = $client->request("/$ApiVersion/Accounts/$AccountSid/SMS/Messages", "POST", array( "To"   => $admin1,   "From" => $twinum, "Body" => $mssgtoadmin1 ));
		}
	}



	if ($web){
		$all = array();
		$numbers = array();
		mysql_free_result($result);	
		$result = mysql_query($sql);
		while ($row = mysql_fetch_assoc($result))
			{		$all[] = $row;
			 		$numbers[] = $row['PhoneNum'];		}		
		print "<pre>";
		print_r(array_values($all));
		print "</pre>";	
	} 

?>
