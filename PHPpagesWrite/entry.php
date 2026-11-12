<?php 

// control things - these things will be initialized when the page loads - some of them
$jrnl = $_REQUEST["jrnlId"]; 
$jrnlMode = $_REQUEST["jrnlMode"]; 
$autoAcc = $_REQUEST["autoAcc"];
if($autoAcc == 0)
  $autoAcc = NULL; // just in case - incase there are mistakes with some other functions.
$flag = (int) $_POST["transType"]; //  use 7, absolutely no reason.

// data
$accList = NULL; // list of all accounts
$jrnlList = NULL; // list of all journals

// Input data
$description = $_POST["desc"];

if($_POST["debit"] == "theselect")
  $debit = NULL;
else
  $debit = (int) $_POST["debit"];

if($_POST["credit"] == "theselect")
  $credit = NULL;
else
  $credit = (int) $_POST["credit"];

$amount = $_POST["cAmount"];
$docId = $_POST["docId"];

// that date thing
$year = date("Y");
$tMonth = date("F");
$nMonth = date("n");
$day = date("j");
$month = $nMonth;

$crntBln = 0; // we need this to close accounts properly.
$titular = NULL; // this is just a marker of some sort.

//etc.
$db = new SQLiteDatabase("acid.sqlite");

function getData(){ // use this function to grab all the data we need
  
  global $db, $accList;
  
  $qAccnt = "SELECT accId, accName, accCode FROM nAccounts ORDER BY accName";
  
  $result = $db->query($qAccnt);
  $accList = $result->fetchAll(SQLITE_ASSOC);
  

} // getData()

function updateBalance($accCredit, $accDebit){
  global $db, $month, $year, $crntBln;
  
  $debitAccBalance = NULL;
  $creditAccBalance = NULL;
  $creditBln = NULL;
  $debitBln = NULL;
  
  // get data from account we credit
  $result = $db->query("SELECT debit, credit, amount, transFlag FROM ledgers WHERE (debit = $accCredit OR credit = $accCredit) AND (year = $year AND month = $month)");
  $creditAccBalance = $result->fetchAll();
  
  // get data from account we debit
  $result = $db->query("SELECT debit, credit, amount, transFlag FROM ledgers WHERE (debit = $accDebit OR credit = $accDebit) AND (year = $year AND month = $month)");
  $debitAccBalance = $result->fetchAll();
  
  // get data from account we credit
  foreach($creditAccBalance as $row_0){
    
          if($row_0["debit"] == $row_0["credit"]){ // Get balance
            switch($row_0["transFlag"]){
              case 1:
                getNotherBalance($row_0["amount"]);
                break;
              case 2:
                getNotherBalance(-$row_0["amount"]);
                break;
              case 3:
                getNotherBalance(-$row_0["amount"]);
                break;
              case 4:
                getNotherBalance($row_0["amount"]);
                break;
              }
            }
          else{
            if($row_0["debit"] == $accCredit){
              getNotherBalance($row_0["amount"]);
              }
            else{
              getNotherBalance(-$row_0["amount"]);
              }
           } // Get balance
    }
    $creditBln = $crntBln;
    $crntBln = 0; // reset.
    
    foreach($debitAccBalance as $row_1){
    
          if($row_1["debit"] == $row_1["credit"]){ // Get balance
            switch($row_1["transFlag"]){
              case 1:
                getNotherBalance($row_1["amount"]);
                break;
              case 2:
                getNotherBalance(-$row_1["amount"]);
                break;
              case 3:
                getNotherBalance(-$row_1["amount"]);
                break;
              case 4:
                getNotherBalance($row_1["amount"]);
                break;
              }
            }
          else{
            if($row_1["debit"] == $accDebit){
              getNotherBalance($row_1["amount"]);
              }
            else{
              getNotherBalance(-$row_1["amount"]);
              }
           } // Get balance
    }
    $debitBln = $crntBln;
    $crntBln = 0; // reset.
    
    // Now update the balance
    
    // the credited account first
    $db->query("UPDATE nAccounts SET accBalance = $creditBln WHERE accId == $accCredit");
    // the debited account first
    $db->query("UPDATE nAccounts SET accBalance = $debitBln WHERE accId == $accDebit");
  
  }

function fmtDate($theDate){ // so date can be properly formatted i.e. 1st 2nd etc.
  
  switch($theDate){
    
    case 1:
      $theDate = $theDate."<sup>st</sup>";
      break;
    case 2:
      $theDate = $theDate."<sup>nd</sup>";
      break;
    case 3:
      $theDate = $theDate."<sup>rd</sup>";
      break;
    case 21:
      $theDate = $theDate."<sup>st</sup>";
      break;
    case 22:
      $theDate = $theDate."<sup>nd</sup>";
      break;
    case 23:
      $theDate = $theDate."<sup>rd</sup>";
      break;
    case 31:
      $theDate = $theDate."<sup>st</sup>";
      break;
    default:
      $theDate = $theDate."<sup>th</sup>";
      break;  
    } // switch
  
  return $theDate;
  
} // fmtDate()

function getNotherBalance($theBalance){ // calculate balance -- We need this of course
  
  global $crntBln;
  
  $crntBln += $theBalance;
  $theBalance = $crntBln; 
  
  return $theBalance;
  
} // getNotherBalance()

// Function, close account here.

function caGetLastDay($month, $year){ // we need to know the last day for soem month
        
        switch($month){
          
          case 2: // february
            if(date("L", strtotime("$year-01-01"))){ // if it's a leap year
              $cfDay = 29;
              }
            else{ // if it's not.
              $cfDay = 28;
              }
            break;
          case 4:
            $cfDay = 30;
            break;
          case 6:
            $cfDay = 30;
            break;
          case 9:
            $cfDay = 30;
            break;
          case 11:
            $cfDay = 30;     
            break;
          default: // other months, well 31 days  
            $cfDay = 31;        
            break;
                    
          }  
    return $cfDay;

  } // caGetLastDay($month, $year)

function closeAccounts(){
  
  global $db, $year, $month, $crntBln, $day;
  $crntYear = $year; // this is now
  $crntMonth = $month; // this is now
  
  $result_0 = $db->query("SELECT year, month FROM lastClosed WHERE day = 49"); // We need to know when the accounts were last closed
  $rowLC = $result_0->fetchAll(); // fetch
  
  if(sizeof($rowLC) > 0) { // if info on when the accounts were last closed exist run this block
    
    // first we retrieve the journal id.
    $qQuery = $db->query("SELECT jrnlId FROM journals WHERE jrnlName LIKE 'CLOSER'");
    $jnlLCRow = $qQuery->fetchAll();
    $LCJrnl = $jnlLCRow[0]["jrnlId"];
    
    // Now we figure out when the accounts were last closed. 
    $lastClosedYear = $rowLC[0]["year"];
    $lastClosedMonth= $rowLC[0]["month"];
    
    // now we determine if we need to close account
    if(($crntYear != $lastClosedYear) || ($crntMonth != $lastClosedMonth)) {// close account block
      
      
      // Now we need to loop through all the accounts so first we need list of accounts
      $qAccList = $db->query("SELECT DISTINCT accId FROM nAccounts"); 
      $allAcc = $qAccList->fetchAll(SQLITE_ASSOC); // this will give use list of all account
      
      foreach($allAcc as $allAccRow){ // account foreach
        
        // now set account id, so we can save some typing time, and space
        $crntAccId = $allAccRow["accId"];
        
        // Now we need to retrieve the ledger entry for each account
        $ldgr = "SELECT * FROM ledgers WHERE (debit = $crntAccId OR credit = $crntAccId) AND (year = $lastClosedYear AND month = $lastClosedMonth)";
        $qLdgr = $db->query($ldgr);
        $ldgrRows = $qLdgr->fetchAll(SQLITE_ASSOC);
        
        // Now that we have that it's time to calculate the balance, it isn't easy, we need another foreach
        foreach($ldgrRows as $ldgrRow){ // ledger foreach 
          
          // yeah calculate the balance
          // first figure out if debit equals credit, these are special entries
          if($ldgrRow["debit"] == $ldgrRow["credit"]){ // debit equals credit block
            
            // now switch
            switch($ldgrRow["transFlag"]){ //switch
              case 2:
                getNotherBalance(-$ldgrRow["amount"]);
                break;
              case 4:
                getNotherBalance($ldgrRow["amount"]);
                break;
              } // switch
          
            } // debit equals credit block
          else{ // debit doesn't equal credit block
            
            if($ldgrRow["debit"] == $crntAccId){ // debit entry block
              getNotherBalance($ldgrRow["amount"]);
              } // debit entry block
            else{ // credit entry block
              getNotherBalance(-$ldgrRow["amount"]);
              } // debit entry block
            
          
            } // debit doesn't equal credit block
        
          } // ledger foreach
         
         // Ok, now it's time to actually close those account, we need 2 entries to the ledger.
         $qPt_1 = "INSERT INTO ledgers(year, month, day, debit, credit, amount, descr, jnlGroup, transFlag) ";
         
         if($crntBln > 0){ // we need to carry debit balance
          
          $qCarry_1 = "VALUES($lastClosedYear, $lastClosedMonth, ".caGetLastDay($lastClosedMonth, $lastClosedYear).",  $crntAccId,  $crntAccId,";
          $qCarry_2 = "  $crntBln, 'Balance c/d', $LCJrnl, 3)";
          $qCarry = $qPt_1.$qCarry_1.$qCarry_2;
          $qBf_1 = "VALUES($crntYear, $crntMonth, 1,  $crntAccId,  $crntAccId,";
          $qBf_2 = "  $crntBln, 'Balance b/f', $LCJrnl, 4)";
          $qBf = $qPt_1.$qBf_1.$qBf_2;
          $db->query($qBf);
          $db->query($qCarry);

         
          } // carry debit balance
         elseif($crntBln < 0){ // carry credit balance
          $qCarry_1 = "VALUES($lastClosedYear, $lastClosedMonth, ".caGetLastDay($lastClosedMonth, $lastClosedYear).",  $crntAccId,  $crntAccId,";
          $qCarry_2 = "  ".-($crntBln).", 'Balance c/d', $LCJrnl, 1)";
          $qCarry = $qPt_1.$qCarry_1.$qCarry_2;
          $qBf_1 = "VALUES($crntYear, $crntMonth, 1,  $crntAccId,  $crntAccId,";
          $qBf_2 = "  ".-$crntBln.", 'Balance b/f', $LCJrnl, 2)";
          $qBf = $qPt_1.$qBf_1.$qBf_2;
          $db->query($qBf);
          $db->query($qCarry);

          } // carry credit balance
        else{
          
          } 
                  

         // Now update teh accoutn balance
         $qUpdate = "UPDATE nAccounts SET accBalance = $crntBln WHERE accId = $crntAccId";
         $db->query($qUpdate);
         
         $crntBln = 0;     
        
        } // account foreach
      // Now update the last time we close account
      $setLastClosed = "UPDATE lastClosed SET year = $crntYear, month = $crntMonth WHERE day = 49";
      $db->query($setLastClosed);

          
      }// close account block
      
    else{ // don't close account
      

    
      } // don't close account
  
    } // last close yes - block
    
  else{ // if info on when the accounts were last closed doesnt exist run this block
    
    $qAccList2 = $db->query("SELECT DISTINCT accId FROM nAccounts"); 
    $allAcc2 = $qAccList2->fetchAll(SQLITE_ASSOC); // this will give use list of all account
    
    if(sizeof($allAcc2) > 0){ // if no info on lastclosed exist but there are accounts run this block
      
      
      $qRy = $db->query("SELECT * FROM journals WHERE jrnlName LIKE 'CLOSER'");
      $jnlLs = $qRy->fetchAll();
      
      if(sizeof($jnlLs) == 0){
        $db->query("INSERT INTO journals(jrnlName, year, month, day, jrnlMode, autoEntr) VALUES('CLOSER', $year, $month, $day, 1, 0)");
          }
    
      } // if no info on lastclosed exist but there are accounts run this block
    else{
      
      }
    } // no last closed block
  
  
  } // closeAccounts()

function coldCall(){ // Nah

  echo "<table><tr>";
  echo "<td> Something's wrong. Please go back to index page.</td>";
  echo "</tr>/table>";

}

function showMode_0(){
  
  global $accList;
  
  
  echo "<tr> <td>Debit</td> <td><select name=\"debit\">";
  echo "<option value=\"theselect\">Select an account</option>";
    
  foreach($accList as $row){
  
    echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";
  
  } // foreach
  
  echo "</select></td></tr>";
  
  echo "<tr> <td>Credit</td> <td><select name=\"credit\">";
  
  echo "<option value=\"theselect\">Select an account</option>";
  
  foreach($accList as $row){
  
    echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";
  
  } // foreach
  
  echo "</select></td></tr>";
  
  

} // showMode_0()

function showMode_1(){
  
  global $accList, $autoAcc, $jrnlMode;
  
  switch($jrnlMode){
    
    case 2:
         
      echo "<tr>";
      echo "<td>Credit</td>";
      echo "<td>";
      echo "<select name=\"credit\">";
      
      echo "<option value=\"theselect\">Select an account</option>";
      
      foreach($accList as $row){
        
        if($autoAcc == $row["accId"]) // break the loop if the two accounts are the same. 
          continue;
          
        echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";
            
        }
      
      echo "</select><input type=\"hidden\" name=\"debit\" value=\"$autoAcc\">";
      echo "</td>";
      echo "</tr>";
      echo "<tr><td colspan=\"2\">".getAccName($autoAcc)." account will be debited automatically.</td></tr>";
      break;
    
    case 3:
          
      echo "<tr>";
      echo "<td>Debit</td>";
      echo "<td>";
      echo "<select name=\"debit\">";
      
      echo "<option value=\"theselect\">Select an account</option>";
      
      foreach($accList as $row){
        
        if($autoAcc == $row["accId"]) // break the loop if the two accounts are the same. 
          continue;
          
        echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";
            
        }
      
      echo "</select><input type=\"hidden\" name=\"credit\" value=\"$autoAcc\">";
      echo "</td>";
      echo "</tr>";
      echo "<tr><td colspan=\"2\">".getAccName($autoAcc)." account will be credited automatically.</td></tr>";    
      break;
    
    default:
      echo "This is not supposed to happen, ever."; 
      break;
  
    }
  
}

function showFormTop(){
  
    global $year, $tMonth, $day;

    echo "<form name=\"mkTransaction\" method=\"POST\" action=\"entry.php\">";
    echo "<table cellspacing=\"2\">";
    echo "<tr><td style=\"width:100px\">Description</td><td style=\"text-align:right\"><i>$tMonth ". fmtDate($day).", $year</i></td></tr>";
    echo "<tr><td colspan=\"2\"><textarea name=\"desc\" rows=\"10\" style=\"width:100%\"></textarea></td></tr>";
    echo "<tr><td>Document id.</td><td><input type=\"text\" name=\"docId\"></td></tr>";
}

function showFormBottom(){

    global $jrnl, $jrnlMode; 
    
    echo "<tr><td>Amount</td><td><input type=\"text\" name=\"cAmount\" style=\"text-align:right\"></td></tr>";
    echo "<tr><td>&nbsp;</td><td><input type=\"submit\" name=\"sbmtBtn\"></td></tr>";
    echo "</table>";
    echo "<input type=\"hidden\" name=\"transType\" value=\"7\">";
    echo "<input type=\"hidden\" name=\"jrnlId\" value=\"$jrnl\">";
    echo "<input type=\"hidden\" name=\"jrnlMode\" value=\"$jrnlMode\">";
    echo "</form>";

}

function getAccName($theCode){
  
  global $accList;
  $theName = NULL;
  
  foreach($accList as $row){
    
    if($row["accId"] == $theCode){
         $theName = $row["accName"]."[{$row["accCode"]}:{$row["accId"]}]";
         break;
         }                       
      }
      
  return $theName; 

} //getAccName($accCode)

function getBalance($theAcc){ // we need to know what account - which account 
  
  global $db, $year, $nMonth;

  $result = $db->query("SELECT * FROM ledgers WHERE (year = $year AND month = $nMonth AND (debit = $theAcc OR credit = $theAcc))");

  $row = $result->fetchAll();

  $balance = 0;

  foreach($row as $rows){

    if($rows["debit"] == $rows["credit"]){ // ok, this is either CD or BF.
    
      switch($rows["tranFlag"]){
    
      case 0:
        $balance += $rows["amount"];
        break;
      case 1:
        $balance += -($rows["amount"]);
        break;
      case 2:
        $balance += -($rows["amount"]);
        break;
      case 3:
        $balance += $rows["amount"];
        break;
        }
      }

  
  else // this is normal entry
    
    if($rows["debit"] == $theAcc){
      
      $balance += $rows["amount"];
      }
  
  
    else{    
      $balance += -($rows["amount"]);
      }
      
}
  
  return $balance;


}

function processInput(){ // process input - We just have to assume that some info is correct
  
  global $description, $debit, $credit, $amount, $docId, $accList, $jrnl, $flag;
  global $year, $tMonth, $nMonth, $day, $db, $jrnlMode, $crntBln;;
  
  // just to be safe - until I can figure stuff out.
  $qYear = (int) $year; 
  $qMonth = (int) $nMonth;
  $qDay = (int) $day;
  $qDebit = (int) $debit;
  $qCredit = (int) $credit;
  $qAmount = (int) $amount;
  $qJrnl = (int) $jrnl;
  
  // make sure that ever single field is filled.. Except document id.
  
  if(!(is_Null($description) || strlen($description) < 3) && !is_Null($debit) && !is_Null($credit) && !(is_Null($amount) || strlen($amount == 0))){ 
    
    $qInsert_1 = "INSERT INTO ledgers(year, month, day, debit, credit, amount, descr, jnlGroup, docId, transFlag) ";
    $qInsert_2 = "VALUES ($qYear, $qMonth, $qDay, $qDebit, $qCredit, $qAmount, \"$description\", $qJrnl, '$docId', 0)";
    $qInsert = $qInsert_1.$qInsert_2;
    
    // before we execute the query we need to make sure the debit and credit field is properly inserted
    
    if($qDebit == $qCredit){ // can't debit and credit the same account at the same time .. It's wrong.
      
      
      echo "<form name=\"submission\" method=\"POST\" action=\"entry.php\">";
      echo "<table cellspacing=\"2\">";
      echo "<tr><td colspan=\"2\"> SORRY! Can't debit and credit the same account at the same time.</td></tr>";
      echo "<tr>";
      echo "<td>Debit</td><td><select name=\"debit\"><option value=\"theselect\">Select an account</option>";
      
      foreach($accList as $row){
        echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";
        }
      
      echo "</select></td></tr>";
      
      echo "<tr>";
      echo "<td>Credit</td><td><select name=\"credit\"><option value=\"theselect\">Select an account</option>";
    
      foreach($accList as $row){
        echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";
        }
        
      echo "</select></td></tr>";
    
      echo "</table>";
      echo "<input type=\"hidden\" name=\"desc\" value=\"$description\">";
      echo "<input type=\"hidden\" name=\"cAmount\" value=\"$amount\">";
      echo "<input type=\"hidden\" name=\"docId\" value=\"$docId\">";
      echo "<input type=\"hidden\" name=\"jrnlId\" value=\"$jrnl\">";
      echo "<input type=\"hidden\" name=\"transType\" value=\"7\">";
      echo "<input type=\"hidden\" name=\"jrnlMode\" value=\"$jrnlMode\">";
      echo "<input type=\"submit\">";
      echo "</form>";
      
      }
    else{
    
      echo "Well all right -- all entered correctly KUDOS!";
      
      if($db->query($qInsert)){ // ok .. this one. When we store the data
        // before that we still need to update the current balance . Yeah.
        echo $crntBln;
        updateBalance($qCredit, $qDebit);        
        echo "<table><tr><td class=\"success\"><p class=\"smellOfSuccess\">One (1) entry was successfully stored.</p></td></tr></table>";
        showFormTop();
        if($jrnlMode == 1){
            showMode_0();
          }
        else{
            showMode_1();
          }
        showFormBottom();        
        }
      else{
        echo "<table><tr><td class=\"success\"><p class=\"smellOfSuccess\">Failed attempt to store one (1) entry.</p></td></tr></table>";
        }
  
    }
  }
    
  else{ // something is empty .. Something is amiss .. We need to reconstruct the form .. Oh yeah.
    
    echo"<form name=\"mkTransaction\" method=\"POST\" action=\"entry.php\">";
    echo "<table cellspacing=\"2\">";
    
    if(is_Null($description) || strlen($description) < 3){ // if the description is empty or too short
      
      echo "<tr><td style=\"width:100px\">Description</td><td style=\"text-align:right\"><i>$tMonth ". fmtDate($day).", $year</i></td></tr>";
      echo "<tr><td colspan=\"2\"><textarea name=\"desc\" rows=\"10\" style=\"width:100%\">$description</textarea></td></tr>";
      }
    else{
      echo "<tr><td colspan=\"2\"><p>Description</p><p class=\"dispDescr\">$description<input type=\"hidden\" name=\"desc\" value=\"$description\"></p></td></tr>";
      }
    
    echo "<tr><td>Document id.</td><td><input type=\"text\" name=\"docId\" value=\"$docId\"></td></tr>";
    
    if(is_Null($debit)){ // if the debit field is null
      
      echo "<tr><td>Debit</td><td><select name=\"debit\"><option value=\"theselect\">Select an account</option>";
      
      foreach($accList as $row){
          
          if($row["accId"] == $credit)
            continue;
          
          echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";          
        }
        
        echo "</select></td></tr>";
      
      // we need to redisplay -- hence the need for get data
      }
      
    else{ //if the thing is not empty
      
      echo "<tr><td>Debit</td><td><span class=\"dispDebit\">".getAccName($debit)."</span><br><input type=\"hidden\" name=\"debit\" value=\"$debit\"></td></tr>";
          
      }
    
    if(is_Null($credit)){ // if the credit field is null
      
      echo "<tr><td>Credit</td><td><select name=\"credit\"><option value=\"theselect\">Select an account</option>";
      
      foreach($accList as $row){
          
          if($row["accId"] == $debit)
            continue;
          
          echo "<option value=\"{$row["accId"]}\">".getAccName($row["accId"])."</option>";          
        }
        
        echo "</select></td></tr>";
      // we need to redisplay -- hence the need for get data
      }
      
    else{ //if the thing is not empty
      
      echo "<tr><td>Credit</td><td><span class=\"dispCredit\">".getAccName($credit)."</span><br><input type=\"hidden\" name=\"credit\" value=\"$credit\"></td></tr>";
          
      }
    
    if(is_Null($amount) || strlen($amount)<= 0){ // if the amount field is empty.
      
      echo "<tr><td>Amount:</td><td><input type=\"text\" name=\"cAmount\" style=\"text-align:right\"></td></tr>";
      }
      
    else{
      
      if(is_Numeric($amount))  // Check if it's numeric
        echo "<tr><td>Amount</td><td><span class=\"dispAmount\">$amount</span><br><input type=\"hidden\" name=\"cAmount\" value=\"$amount\"></td></tr>";
      else
        echo "<tr><td>Amount:</td><td><input type=\"text\" name=\"cAmount\" style=\"text-align:right\"></td></tr>";
        
      }
     
     echo "<tr><td>&nbsp</td><td><input type=\"submit\" name=\"sbmtBtn\"></td></tr>";
     echo "</table>";
     echo "<input type=\"hidden\" name=\"jrnlId\" value=\"$jrnl\">";
     echo "<input type=\"hidden\" name=\"autoAcc\" value=\"$autoAcc\"><input type=\"hidden\" name=\"transType\" value=\"7\">";
     echo "<input type=\"hidden\" name=\"jrnlMode\" value=\"$jrnlMode\">";
     echo "</form>";   
  
    }


} // processInput()

closeAccounts();
getData();

// Hey start here.
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<html>

  <head>
    <title>(RAcS) Make an entry - <?php echo $titular; ?></title>
    <link rel="stylesheet" type="text/css" href="style/entry.css">
  </head>
  
  <body>

<?php

require("header.html");

if(is_Null($jrnlId) || $jrnl == ""){ // if journal id is blank .. then it's cold call, it doesn't if other field are filled .. We need jrnl id.
  
  coldCall();

  }
  
else{ // Showtime

  if($flag == 7){ // flag tells us that we need to process input
    //process input .. if there are errors, the process input function will take care of 'em. See, when we process in[ut we don't need mode etc.
    
    processInput();
    
    }
  else{ // we have to show forms
    
    if($jrnlMode == 1){ // normal mode
      
        showFormTop();
        showMode_0();
        showFormBottom();
    
      }
    elseif($jrnlMode == 2 || $jrnlMode == 3){ // mode 3 or 3
    
      if(is_Null($autoAcc) || is_Null(getAccName($autoAcc))){ // if the otehr account is not supplied or it doesn't exist
          
        echo "No corresponding account is supplied, or the account supplied doesn't exist";
          
          }
      else{ // it's showtime!
        
        showFormTop();
        showMode_1();
        showFormBottom();
        }
    
      }
    else{ // error .. print error message and call coldCall..
      
      if(is_Null($jrnlMode)){
        echo "No journal mode is supplies";
        }
      else{
        echo "Invalid mode";
        }
      
      }
    
    }

  }




/*
  *
  * Ok this is what supposed to happen.
  * You 
  *   Send somebody to this page and supply journalI id, the mode and the account id(not necessary if the mode is 0) .. Yes, else I'm going to kick your face, OR
  *   Send somebody here without any info i.e. XXXXX/thisPageName.php
  *   
  * OR
  * 
  * This page send itself with required information to be stored in the database .. 
  * 
  * That's it.                    
*/

?>

</body>
</html>
