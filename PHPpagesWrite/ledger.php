<?php

/*
  WE MIGHT NEED .. Yeah, some error checking.. i.e. don't execute anything unless we have enough variable to work with .. Damn or else we'll end up with
  errors.
  HEYYYY YO!! TURD.. THE SQL expressions need to be re-written, SORT needs to be added
  ANND where BOTH credit AND debit IS NOT NULL // nope they can't be NULL.
/*

/*
Global variables, we need a few
*/

//data
  $accId = $_GET["account"]; // this can't be null
  $thisAccName = NULL; //the accounts friendly name
  $accYear = (int) $_GET["year"]; // this can't be null
  $accMonth = (int) $_GET["month"]; // can't be null
  $accNCode = NULL; // that 4 letters code
  
  $ldgrEntr; // - the ledgers entries
  $accNameCode; // - this one for that thing we need translate code to name
  $accInfo; // info about the account, from DB.
  $otherMonths; // ledger for other months - We just pull the month not other stuff
  
  $db = new SQLiteDatabase("acid.sqlite"); // the db
  
  $year = date("Y");
  $month = date("n");
  $day = date("j");
  
// -- 

  $crntBln = 0; // Current balance
  $titular = NULL; // this is just a marker of some sort.

  function getData(){
  
  global $accId, $ldgrEntr,  $accNameCode, $accNCode, $otherMonths, $accYear, $accMonth, $db; 
  
$qEntries = "SELECT transId, year, month, day, debit, credit, amount, jnlGroup, transFlag FROM ledgers WHERE (debit = $accId OR credit = $accId) AND (year= $accYear AND month = $accMonth)"; // Ok, this has to be the .. well, watta watta .. Eh, ok the entries in the ledger
  // $qNames1 = "SELECT debit FROM ledger WHERE (year= $year AND month = $month AND credit = $accCode)";
  // $qNames2 = "SELECT credit FROM ledger WHERE (year= $year AND month = $month AND debit = $accCode)";
  $qNames = "SELECT accId, accName, accCode FROM nAccounts"; // grab all the account names, etc. 
  $qAccNCode = "SELECT accCode FROM nAccounts WHERE accId = $accId"; // no idea what this is for. - We might not need this. .. Might need this ..
  $qOtherMonth = "SELECT DISTINCT month FROM ledgers WHERE year = $accYear AND (debit = $accId OR credit = $accId)";
  
  // retrieve and store all ledger entries
  $result = $db->query($qEntries); 
  $ldgrEntr = $result->fetchAll(SQLITE_ASSOC);
  
  // retrieve account names and code so we can translate code to name 
  $result = $db->query($qNames);
  $accNameCode = $result->fetchAll(SQLITE_ASSOC);
  
  $accNCode = $db->singleQuery($qAccNCode); 
  
  $result = $db->query($qOtherMonth);
  $otherMonths = $result->fetchAll(SQLITE_ASSOC);

}

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
  
function showLedger(){
  
  global $ldgrEntr, $accId, $accMonth, $accYear;
  $theDay = NULL;
  $oddEven = "LedgerOdd";
  $dBalance = NULL;
  
  foreach($ldgrEntr as $row){
 
    echo "<tr>";
  
    if($row["day"] != $theDay){ // Where we print the first column .. the date
      
      if($oddEven == "LedgerOdd"){
          $oddEven = "LedgerEven";
        }
      else{
        $oddEven = "LedgerOdd";
        }
        
      echo "<td class = \"date$oddEven\">".fmtDate($row["day"])."</td>";
      $theDay = $row["day"];
          
            
      } // Where we print the first column .. the date; if the two don't agree we print date
      
    else{ // we don't print date
    
      echo "<td class = \"date$oddEven\">&nbsp;</td>";
    
      } // we don't print date
      
    if($row["debit"] == $row["credit"]){ // this is for that other thing .. Well, close and open.
    
      switch($row["transFlag"]){ // We figure out what kind of transaction this one is.
      
        case 1: // Carried down - Debit . the entry's on debit side and carried down to credit side
          
          echo "<td class = \"detail$oddEven\"> Balance c/d</td>";
          echo "<td class = \"debit$oddEven\">".number_format($row["amount"], 2)."</td>";
          echo "<td class = \"credit$oddEven\"> &nbsp; </td>";
          $dBalance = getBalance($row["amount"]); // We retrieve the balance
          break;
          
        case 2: // Brought forward - Credit
        
          echo "<td class = \"detail$oddEven\"> Balance b/f</td>";
          echo "<td class = \"debit$oddEven\"> &nbsp; </td>";
          echo "<td class = \"credit$oddEven\">".number_format($row["amount"], 2)."</td>";
          $dBalance = getBalance(-$row["amount"]); // We retrieve the balance
          break;
          
        case 3: // Carried down - Credit . the entry's on credit side and carried down to debit side
          
          echo "<td class = \"detail$oddEven\"> Balance c/d</td>";
          echo "<td class = \"debit$oddEven\"> &nbsp; </td>";
          echo "<td class = \"credit$oddEven\">".number_format($row["amount"], 2)."</td>";
          $dBalance = getBalance(-$row["amount"]); // We retrieve the balance
          break;
          
        case 4: // Brought forward - Debit
          
          echo "<td class = \"detail$oddEven\"> Balance b/f</td>";
          echo "<td class = \"debit$oddEven\">".number_format($row["amount"], 2)."</td>";
          echo "<td class = \"credit$oddEven\"> &nbsp; </td>";
          $dBalance = getBalance($row["amount"]); // We retrieve the balance
          break;
                
        } // switch
    
      }
    
    else{ // regular entries.
     
      if($row["debit"] == $accId){ // if it's a debit entry 
        
        echo "<td class = \"detail$oddEven\"><a href=\"ledger.php?account={$row["credit"]}&month=$accMonth&year=$accYear\">".getAccName($row["credit"])."</a></td>";
        echo "<td class = \"debit$oddEven\">".number_format($row["amount"], 2)."</td>";
        echo "<td class = \"credit$oddEven\"> &nbsp; </td>";
         $dBalance = getBalance($row["amount"]); // We retrieve the balance
        
          } // if it's a debit entry 
          
      else{ // if it's a credit entry
        
        echo "<td class = \"detail$oddEven\"><a href=\"ledger.php?account={$row["debit"]}&month=$accMonth&year=$accYear\">".getAccName($row["debit"])."</a></td>";
        echo "<td class = \"debit$oddEven\"> &nbsp;</td>";
        echo "<td class = \"credit$oddEven\">".number_format($row["amount"], 2)."</td>";
        $dBalance = getBalance(-($row["amount"])); // we retrieve the balance
        
          } // if it's a credit entry
        }
     
    // Print balance
    
    if($dBalance == 0){ // 0 balance
    
      echo "<td class = \"balance$oddEven\">".number_format($dBalance, 2)."</td><td class = \"dc$oddEven\">-</td>";
      
      }
    elseif($dBalance > 0){ // debit balance
      echo "<td class = \"balance$oddEven\">".number_format($dBalance, 2)."</td><td class = \"dc$oddEven\">Debit</td>";
      }
    else{ // credit balance
      
      echo "<td class = \"balance$oddEven\">".number_format(-($dBalance), 2)."</td><td class = \"dc$oddEven\">Credit</td>";
      
    }
    
    
    echo "</tr>";
        
      
    } // foreach
  
} // showLedger()

function getAccName($theCode){ // the function translates account code to name 
  
  global $accNameCode;
  $theName = NULL;
  
  foreach($accNameCode as $row){
    
    if($row["accId"] == $theCode){
      $theName = $row["accName"];
      }
    
    }
    
    if($theName == NULL){
      $theName = "ERROR - The account doesn't exist";
      }
    
    return $theName;  


} // getAccName

function getBalance($theBalance){ // calculate balance

  global $crntBln;
  
  $crntBln += $theBalance;
  $theBalance = $crntBln; 
  
  return $theBalance;
  
} // getBalance()

function fmtDate($theDate){ // so date can be properly formatted i.e. 1st 2nd etc.
  
  switch($theDate){
    
    case 0:
      $theDate = NULL;
      break;
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
        $theMonth = "What are you crazy, there's no the 13th month";
        break;
      } // switch
    
    return $theMonth;
} // getMonth()

function getOtherMonths(){
    
    global $otherMonths, $month;
    
    echo "<tr>";
    echo "<td colspan=\"7\" style=\"text-align:center\">";
   
    if($otherMonths != NULL){
    
     foreach($otherMonths as $row){
      
        if($row["month"] != $month){
            echo getMonth($row["month"]);
          }
        else{
           echo "+".getMonth($row["month"])."+";
          }
      
       }// foreach
    
      }
      
    else{
        
       echo "No entries for other months";
        }
    
    echo "</td>";
    echo "</tr>";

} // getOtherMonths;

closeAccounts();
getData();
$thisAccName = getAccName($accId);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<html>

  <head>
    <title>(RAcS) <?php echo $thisAccName."[".$accNCode.":".$accId."]"." "."- ".$titular; ?></title>
    <link rel="stylesheet" type="text/css" href="style/ledger.css">
  </head>
  
  <body>

<?php 

require("header.html");

if(sizeof($ldgrEntr) > 0){ // Ok, we can dispaly stuff
  
  echo "<table>";
  echo "<tr><td>Entries for <span class=\"accNam\">$thisAccName [$accNCode:$accId]</span> account for the month of <i>".getMonth($accMonth).", $accYear</i>.</td></tr>";
  echo "</table>";
  
  echo "<table>";
  echo "<tr>";
  echo "<td class=\"titleDate\">Date</td>";
  echo "<td class=\"titleDetail\">Detail</td>";
  echo "<td class=\"titleDebit\">Debit</td>";
  echo "<td class=\"titleCredit\">Credit</td>";
  echo "<td class=\"titleBalance\" colspan=\"2\">Balance</td>";  
  echo "</tr>";
  showLedger();
  //getOtherMonths();
  echo "</table>";

  }
else{ // empty
?>

    <table>
      <tr><td>No entries exist.</td></tr>
    </table>
  <?php } ?>
</body>
</html>
