<?php

$crntBln = 0; // we need this to close accounts properly.
$titular = NULL; // this is just a marker of some sort.

// the date thing
$year = date("Y");
$month = date("n");
$day = date("j");

$creditBlnAcc = NULL;
$debitBlnAcc = NULL;

$creditSize = NULL;
$debitSize = NULL;

$db = new SQLiteDatabase("acid.sqlite");

function getBalance($theBalance){ // calculate balance -- We need this of course
  
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
      
      echo "Accounts have been closed no action was taken";
    
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


function getCreditAcc(){
  global $creditBlnAcc, $db, $creditSize;
  
  $result = $db->query("SELECT * FROM nAccounts WHERE accBalance < 0");
  $creditBlnAcc = $result->fetchAll();
  
  $creditSize = sizeof($creditBlnAcc);
  
  }
function getDebitAcc(){
  global $debitBlnAcc, $db, $debitSize;
  
  $result = $db->query("SELECT * FROM nAccounts WHERE accBalance > 0");
  $debitBlnAcc = $result->fetchAll();
  
  $debitSize = sizeof($debitBlnAcc);
  }

function showTrialBalance(){
  
  global $creditSize, $debitSize, $creditBlnAcc, $debitBlnAcc, $year, $month;
  
  $biggest = NULL; // 0 - same size, 1 credit is bigger, 2 debit si bigger
  $limit;
  $upLimit;
  $creditBalance = 0;
  $debitBalance = 0;
  
  //We need to know which one is bigger
  if($debitSize > $creditSize){ // debit is bigger
    $biggest = 1;
    $upLimit = $debitSize;
    $limit = $creditSize;
    }
  elseif($creditSize > $debitSize){ // credit is bigger
    $biggest = 2;
    $upLimit = $creditSize;
    $limit = $debitSize;
    }
  else{ // the same
    $biggest = 0;
    // doesn't really matter
    $upLimit = $creditSize;
    $limit = $debitSize;
    }
    
  for($i=0; $i < $upLimit; $i++){
    
    if($i >= $limit && $biggest != 0){ // Reach the limit
      
      if($biggest == 1){ // Debit is bigger
        
        echo "<tr>";
        echo "<td class=\"cont\"><a href=\"ledger.php?account=".$debitBlnAcc[$i]["accId"]."&year=$year&month=$month\">".$debitBlnAcc[$i]["accName"]." [".$debitBlnAcc[$i]["accCode"].":".$debitBlnAcc[$i]["accId"]."]"."</a></td>";
        echo "<td class=\"balance\">".number_format($debitBlnAcc[$i]["accBalance"], 2)."</td>";
        echo "<td class=\"cont\">&nbsp;</td>";
        echo "<td class=\"balance\">&nbsp;</td>";
        echo "</tr>";
        $debitBalance += $debitBlnAcc[$i]["accBalance"];
        
        }
      else{ // credit is bigger
      
        echo "<tr>";
        echo "<td class=\"cont\">&nbsp;</td>";
        echo "<td class=\"balance\">&nbsp;</td>";
        echo "<td class=\"cont\"><a href=\"ledger.php?account=".$creditBlnAcc[$i]["accId"]."&year=$year&month=$month\">".$creditBlnAcc[$i]["accName"]." [".$creditBlnAcc[$i]["accCode"].":".$creditBlnAcc[$i]["accId"]."]"."</a></td>";
        echo "<td class=\"balance\">".number_format(-($creditBlnAcc[$i]["accBalance"]), 2)."</td>";
        echo "</tr>";
        $creditBalance += $creditBlnAcc[$i]["accBalance"];
      
        }
    
      }
    else{
      
      echo "<tr>";
      echo "<td class=\"cont\"><a href=\"ledger.php?account=".$debitBlnAcc[$i]["accId"]."&year=$year&month=$month\">".$debitBlnAcc[$i]["accName"]." [".$debitBlnAcc[$i]["accCode"].":".$debitBlnAcc[$i]["accId"]."]"."</a></td>";
      echo "<td class=\"balance\">".number_format($debitBlnAcc[$i]["accBalance"], 2)."</td>";
      echo "<td class=\"cont\"><a href=\"ledger.php?account=".$creditBlnAcc[$i]["accId"]."&year=$year&month=$month\">".$creditBlnAcc[$i]["accName"]." [".$creditBlnAcc[$i]["accCode"].":".$creditBlnAcc[$i]["accId"]."]"."</a></td>";
      echo "<td class=\"balance\">".number_format(-($creditBlnAcc[$i]["accBalance"]), 2)."</td>";
      echo "</tr>";
      $debitBalance += $debitBlnAcc[$i]["accBalance"];
      $creditBalance += $creditBlnAcc[$i]["accBalance"];
      
      }
    
    }
    
    echo "<tr>";
    echo "<td class=\"total\">Total &nbsp;&nbsp;</td><td class=\"totalBalance\">".number_format($debitBalance, 2)."</td>";   
    echo "<td class=\"total\">Total &nbsp;&nbsp;</td><td  class=\"totalBalance\">".number_format(-($creditBalance), 2)."</td>";
    echo "</tr>";
    
  
  
  }

closeAccounts();

getCreditAcc();
getDebitAcc();

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<html>

  <head>
    <title>(RAcS) Trial balance - <?php echo $titular; ?></title>
    <link rel="stylesheet" type="text/css" href="style/trialbalance.css">
  </head>
  
  <body>
  
<?php 

require("header.html");
echo "<table cellspacing = \"0\">";
echo "<tr><td class=\"title\" colspan = \"2\">Debit</td><td class=\"title\" colspan = \"2\">Credit</td></tr>";
showTrialBalance();
echo "</table>";

?>