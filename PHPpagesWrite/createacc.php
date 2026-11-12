<?php

// that date thing

$tranType = $_POST["makeEntry"];
$inAccName = $_POST["accName"];
$inAccCode = $_POST["accCode"];
$inAccType = $_POST["type"];

if($tranType == 1){
  $reRun = TRUE;
  }
else{
  $reRun = FALSE;
  }

$inYear = date("Y");
$inMonth = date("n");
$inDay = date("j");

$year = $inYear;
$month = $inMonth;
$day = $inDay;
$crntBln = 0;
$titular = NULL;

// check and make sure everything's in order.

if(is_Null($inAccName) || strlen($inAccName) < 3){
  $tranType = NULL;
  }
if(is_Null($inAccCode) || strlen($inAccCode) < 4){
  $tranType = NULL;
  }
if(is_Null($inAccType) || empty($inAccType)){
  $tranType = NULL;
  }


//the database object
$db = new SQLiteDatabase("acid.sqlite"); // change this in the final version

function getBalance($theBalance){ // calculate balance -- We need this of course
  
  global $crntBln;
  
  $crntBln += $theBalance;
  $theBalance = $crntBln; 
  
  return $theBalance;
  
} // getBalance()

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

function showForm(){
  
  global $inAccName, $inAccCode, $inAccType, $reRun;
  $inAccCode = strtoupper($inAccCode);
  
  echo "<tr><td>";
  echo "<p>";
  
  if(!strlen($inAccName) < 3){
    echo "<p>";
    echo "<b>Account name</b><br>";
    echo "<input type =\"text\" name=\"accName\" size=\"50\" maxlength=\"50\" value=$inAccName><br>";
    echo "*50 characters maximum";
    echo "</p>";
    }
  else{
    echo "<p>";
    echo "<b>Account name</b><br>";
    echo "<input type =\"text\" name=\"accName\" size=\"50\" maxlength=\"50\" value=$inAccName><br>";
    if($reRun == TRUE){
     echo "<span class=\"inc\">*Account name has to be more than 3 characters, <br>and no more than 50.</spam>";
     }
    else{
      echo "*50 characters maximum";
      }
    echo "</p>";
    }
  
  if(strlen($inAccCode) == 4){
    echo "<p><b>Account code</b><br>";
    echo "<input type=\"text\" name=\"accCode\" size=\"8\" maxlength=\"4\" value=$inAccCode><br>*4 characters";
    echo "</p>";
    }
  else{
    echo "<p><b>Account code</b><br>";
    echo "<input type=\"text\" name=\"accCode\" size=\"8\" maxlength=\"4\" value=$inAccCode><br>";
    if($reRun == TRUE){
      echo "<span class=\"inc\">*Account code has to be exactly 4 characters.</span>";
      }
    else{
      echo "*4 characters";
      }
    echo "</p>";    
    }
  
  if($inAccType == 2){
    echo "<p><b>Account type</b><br>";
    echo "<input type=\"radio\" name=\"type\" value=\"1\">Normal account<br>";
    echo "<input type=\"radio\" name=\"type\" value=\"2\" checked>Control account";
    echo "</p>";
    }
  else{
    echo "<p><b>Account type</b><br>";
    echo "<input type=\"radio\" name=\"type\" value=\"1\" checked>Normal account<br>";
    echo "<input type=\"radio\" name=\"type\" value=\"2\">Control account";
    echo "</p>";
    }
  
  echo "<input type=\"hidden\" name=\"makeEntry\" value=\"1\">";
  echo "<p><input type=\"submit\" value=\"Submit\"></p>";
  echo "</td></tr>";  
  }

function processForm(){
  global $inAccName, $inAccCode, $inAccType, $inYear, $inMonth, $inDay, $db, $reRun;
  
  $inAccCode = strtoupper($inAccCode);
  
  if($inAccType == 1){
    $db->query("INSERT INTO nAccounts(accName, accCode, year, month, day, accBalance) VALUES(\"$inAccName\", \"$inAccCode\", $inYear, $inMonth, $inDay, 0)");
    }
  elseif($inAccType == 2){
    $db->query("INSERT INTO cAccounts(accName, accCode, year, month, day) VALUES(\"$inAccName\", \"$inAccCode\", $inYear, $inMonth, $inDay)");
    }
  
  $inAccName = NULL;
  $inAccCode = NULL;
  $inAccType = 1;
  $reRun = FALSE;
  
  echo "<table><tr><td class=\"success\"><p>One (1) new account was successfully created. Now you may add more.</p></td></tr></table>";
  echo "<form method=\"POST\" action=\"createacc.php\">";
  echo "<table>";
  showForm();
  echo "</table>";
  echo "</form>";
  
  }
  
  closeAccounts();

?>

<!-- HTML elements here -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<head>
  <title>(RAcS) Create a new account <?php echo " -".$titular?></title>
  <link rel="stylesheet" type="text/css" href="style/createacc.css">
</head>

<body>

<?php

require("header.html");

if((int)$tranType == 1 ){
  processForm();
}

else{
  echo "<form method=\"POST\" action=\"createacc.php\">";
  echo "<table>";
  echo "<tr><td class=\"title\">Create a new account </td></tr>";
  showForm();
  echo "</table>";
  echo "</form>";

}

?>

</body>
</html>