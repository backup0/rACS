<?php

$jnlId = $_GET["jrnl"];
$vYear = $_GET["year"];
$vMonth = $_GET["mth"];

$db = new SQLiteDatabase("acid.sqlite");
$accNameCode = NULL; // we need a function to retrieve the data.
$jnlEntries = NULL;

$crntBln = 0; // we need this to close accounts properly.
$titular = 0; // this is just a marker of some sort.

// the date thing
$year = date("Y");
$month = date("n");
$day = date("j");

function getData(){
  
  global $db, $jnlEntries, $jnlId, $vYear, $vMonth, $accNameCode ;
  
  $result = $db->query("SELECT accId, accName, accCode FROM nAccounts"); // grab list of all accounts
  $accNameCode = $result->fetchAll(SQLITE_ASSOC);
  
  $result = $db->query("SELECT * FROM ledgers WHERE jnlGroup = $jnlId AND year = $vYear AND month = $vMonth"); // retrieve all entries for this journal.
  $jnlEntries = $result->fetchAll(SQLITE_ASSOC);
  }//getData()

function getBalance($theBalance){ // calculate balance -- We need this of course
  
  global $crntBln;
  
  $crntBln += $theBalance;
  $theBalance = $crntBln; 
  
  return $theBalance;
  
} // getBalance()

// Close acount function
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
                getBalance(-$ldgrRow["amount"]);
                break;
              case 4:
                getBalance($ldgrRow["amount"]);
                break;
              } // switch
          
            } // debit equals credit block
          else{ // debit doesn't equal credit block
            
            if($ldgrRow["debit"] == $crntAccId){ // debit entry block
              getBalance($ldgrRow["amount"]);
              } // debit entry block
            else{ // credit entry block
              getBalance(-$ldgrRow["amount"]);
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
          // 
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
      
      // soemthing
    
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
      // 
      }
    } // no last closed block
  
  
  } // closeAccounts()

function showEntries(){
  
  global $jnlEntries;
  
  $today = NULL; // Need this for display, different day different color.
  $oddEven = "Odd";
  $oddFirstRun = TRUE;
  $evenFirstRun = TRUE;
  
  echo "<table cellspacing=\"0\">";
  
  foreach($jnlEntries as $row){
    
    if(is_Null($today)){ // initialize the thing
      $today = $row["day"];
      }
    
    if($row["day"] != $today){
      
      $oddFirstRun = TRUE;
      
      if($evenFirstRun = TRUE){
        if($oddEven == "Odd"){
          $oddEven = "Even";
          }
        else{
          $oddEven = "Odd";
          }
        
          echo "<tr><td class=\"title\">".getMonth($row["month"])." ".fmtDate($row["day"]).", {$row["year"]}</td></tr>";
          echo "<tr><td class=\"cont$oddEven"."Tran\">Transaction id : {$row["transId"]}</td></tr>";
          echo "<tr><td class=\"cont$oddEven"."Descr\"><b>Description :</b></b><p>{$row["descr"]}</p></td></tr>";
          echo "<tr><td class=\"cont$oddEven\"><b>Document id :</b> <i>{$row["docId"]}</i></td></tr>";
          echo "<tr><td class=\"cont$oddEven\"><b>Debit :</b> <i>".getAccName($row["debit"])."</i></td></tr>";
          echo "<tr><td class=\"cont$oddEven\"><b>Credit :</b> <i>".getAccName($row["credit"])."</i></td></tr>";
          echo "<tr><td class=\"cont$oddEven"."Amount\"><b>Amount :</b> <i>".number_format($row["amount"], 2)."</i></td></tr>";
          echo "<tr><td class=\"cont$oddEven\">&nbsp</td></tr>";
          $oddFirstRun = FALSE;
        }
      else{
        echo "<tr><td class=\"cont$oddEven"."Tran\">Transaction id : {$row["transId"]}</td></tr>";
        echo "<tr><td class=\"cont$oddEven"."Descr\"><b>Description :</b><p>{$row["descr"]}</p></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Document id :</b> <i>{$row["docId"]}</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Debit :</b> <i>".getAccName($row["debit"])."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Credit :</b> <i>".getAccName($row["credit"])."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven"."Amount\"><b>Amount :</b> <i>".number_format($row["amount"], 2)."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\">&nbsp</td></tr>";
        }
      
      }
      
    else{
      
      $evenFirstRun = TRUE;
      
      if($oddFirstRun == TRUE){
        echo "<tr><td class=\"title\">".getMonth($row["month"])." ".fmtDate($row["day"]).", {$row["year"]}</td></tr>";
        echo "<tr><td class=\"cont$oddEven"."Tran\">Transaction id : {$row["transId"]}</td></tr>";
        echo "<tr><td class=\"cont$oddEven"."Descr\"><b>Description :</b><p>{$row["descr"]}</p></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Document id :</b> <i>{$row["docId"]}</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Debit :</b> <i>".getAccName($row["debit"])."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Credit :</b> <i>".getAccName($row["credit"])."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven"."Amount\"><b>Amount :</b> <i>".number_format($row["amount"], 2)."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\">&nbsp</td></tr>";
        
        $oddFirstRun = FALSE;
        }
      else{
        echo "<tr><td class=\"cont$oddEven"."Tran\">Transaction id : {$row["transId"]}</td></tr>";
        echo "<tr><td class=\"cont$oddEven"."Descr\"><b>Description :</b><p>{$row["descr"]}</p></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Document id :</b> <i>{$row["docId"]}</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Debit :</b> <i>".getAccName($row["debit"])."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\"><b>Credit :</b> <i>".getAccName($row["credit"])."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven"."Amount\"><b>Amount :</b> <i>".number_format($row["amount"], 2)."</i></td></tr>";
        echo "<tr><td class=\"cont$oddEven\">&nbsp</td></tr>";
        }
    
      }
    
    }
    
    echo "</table>";

  } // showEntries

function getAccName($theCode){ // the function translates account code to name 
  
  global $accNameCode;
  $theName = NULL;
  
  foreach($accNameCode as $row){
    
    if($row["accId"] == $theCode){
      $theName = $row["accName"]."[".$row["accCode"].":".$row["accId"]."]";
      }
    
    }
    
    if($theName == NULL){
      $theName = "ERROR - The account doesn't exist";
      }
    
    return $theName;  


} // getAccName

function fmtDate($theDate){ // so date can be properly formatted i.e. 1st 2nd etc.
  
  switch($theDate){
    
    case 0:
      $theDate = NULL;
      break;
    case 1:
      $theDate = $theDate."st";
      break;
    case 2:
      $theDate = $theDate."nd";
      break;
    case 3:
      $theDate = $theDate."rd";
      break;
    case 21:
      $theDate = $theDate."st";
      break;
    case 22:
      $theDate = $theDate."nd";
      break;
    case 23:
      $theDate = $theDate."rd";
      break;
    case 31:
      $theDate = $theDate."st";
      break;
    default:
      $theDate = $theDate."th";
      break;  
    } // switch
  
  return $theDate;
  
} // fmtDate()

function getMonth($theMonth){ // format month
    
    switch ($theMonth){
      
      case 1:
        $theMonth = "January";
        break;
      case 2:
        $theMonth = "February";
        break;
      case 3:
        $theMonth = "March";
        break;
      case 4:
        $theMonth = "April";
        break;
      case 5:
        $theMonth = "May";
        break;
      case 6:
        $theMonth = "June";
        break;
      case 7:
        $theMonth = "July";
        break;
      case 8:
        $theMonth = "August";
        break;
      case 9:
        $theMonth = "September";
        break;
      case 10:
        $theMonth = "October";
        break;
      case 11:
        $theMonth = "November";
        break;
      case 12:
        $theMonth = "December";
        break;
      default:
        $theMonth = "What are you crazy, there's no the 13th month, Fucktuary";
        break;
      } // switch
    
    return $theMonth;
} // getMonth()

closeAccounts();
?>

<!-- HTML elements here -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<head>
  <title>(RAcS)<?php echo " - ".$titular?></title>
  <link rel="stylesheet" type="text/css" href="style/viewjournal.css">
</head>

<body>

<?php
require("header.html");

if(is_Null($jnlId) || is_Null($vYear) || is_Null($vMonth)){
  echo "<table><tr><td>Opss! Error.</td></tr></table>";
  }
else{
  getData();
  
  if(sizeof($jnlEntries) > 0){
    showEntries();
  }
  else{
    echo "<table><tr><td>Opss! Sorry, nothing to display.</td></tr></table>";
  }
  }

?>