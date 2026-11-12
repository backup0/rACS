<?php 
  
  $ctrlAccId = NULL;
  
  if(strlen($_GET["ctrlId"]) != 0){
    $ctrlAccId = (int) $_GET["ctrlId"];
    }
  
  $crntBln = 0; // Current balance
  $titular = NULL; // this is just a marker of some sort.
  
  $year = date("Y");
  $month = date("n");
  $day = date("j");
  
  
  $db = new SQLiteDatabase("acid.sqlite"); //the db object
  
  
  // We try and retrieve the account name, code etc.
  if(!is_Null($ctrlAccId)){
    $query_0 = $db->query("SELECT * FROM cAccounts WHERE accId = $ctrlAccId");
    $result_0 = $query_0->fetchAll();
    
    if(sizeof($result_0) > 0){
      // And now we assign those to a variable so we ca use it again
      $ctrlAccName = $result_0[0]["accName"]." [".$result_0[0]["accCode"].":".$result_0[0]["accId"]."]";
      echo $ctrlAccName;
      }
    else{
      $ctrlAccName = "NA";
      }
    }
  
  
function getBalance($theBalance){ // calculate balance

  global $crntBln;
  
  $crntBln += $theBalance;
  $theBalance = $crntBln; 
  
  return $theBalance;
  
  } // getBalance()

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
      
      //
    
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
  
  global $db, $ctrlAccId, $year, $month;
  
  $currentBalance = 0; // we need this.
  
  // Now retrieve all the accounts
  
  $query = $db->query("SELECT * FROM nAccounts WHERE ctrlAcc = $ctrlAccId ORDER BY accName");
  $result = $query->fetchAll();
  echo "<table border=1>";
  echo "<tr>";
  echo "<td class=\"titleDetail\">Account</td>";
  echo "<td class=\"titleDebit\">Debit</td>";
  echo "<td class=\"titleCredit\">Credit</td>";
  echo "<td class=\"titleBalance\" colspan=\"2\">Balance</td>";
  
  foreach($result as $row){
    
    echo "<tr>";
    echo "<td class=\"detailLedgerOdd\">";
    echo "<a href=\"ledger.php?account={$row["accId"]}&year=$year&month=$month\">";
    echo "{$row["accName"]} [{$row["accCode"]}:{$row["accId"]}]</a></a>";
    
    //Ok this is where we display the debit and credit so yeah.
    if($row["accBalance"] > 0){ // debit balance
      echo "<td class=\"debitLedgerEven\">".number_format($row["accBalance"], 2)."</td><td class=\"creditLedgerEven\">&nbsp;</td>";
      $currentBalance += $row["accBalance"];
      }
    elseif($row["accBalance"] < 0){ // credit balance
      echo "<td class=\"debitLedgerEven\">&nbsp;</td><td class=\"creditLedgerEven\">".number_format(-$row["accBalance"], 2)."</td>";
      $currentBalance += $row["accBalance"];
      }
    else{ // 0 balance - Well .. Too lazy to write another page where accounts with 0 balance is dispalyed.
      echo "<td class=\"debitLedgerEven\">0</td><td class=\"creditLedgerEven\">0</td>";
      }
    
    if($currentBalance > 0){
      echo "<td class = \"balanceLedgerOdd\">".number_format($currentBalance, 2)."</td>";
      echo "<td class = \"dcLedgerOdd\">Debit</td>";
      }
    elseif($currentBalance < 0){
      echo "<td class = \"balanceLedgerOdd\">".number_format(-$currentBalance, 2)."</td>";
      echo "<td class = \"dcLedgerOdd\">Credit</td>";
      }
    else{
      echo "<td class = \"balanceLedgerOdd\">".number_format(0, 2)."</td>";
      echo "<td class = \"dcLedgerOdd\">-</td>";
      }
    
    echo "</tr>";
  
    } 
  echo "</table>";

  }


closeAccounts();

?>

<!-- HTML elements here -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<head>
  <title>(RAcS) View <?php echo $ctrlAccName." account -".$titular?></title>
  <link rel="stylesheet" type="text/css" href="style/ledger.css">
</head>

<body>

<?php

require("header.html");
showEntries();
?>