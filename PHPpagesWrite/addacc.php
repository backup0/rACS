<?php

  $ctrlAccId = $_REQUEST["ctrlId"]; // this is the id for that control account.
  $ctrlAccList = NULL;
  $accToAdd = NULL;
  
  if(strlen($ctrlAccId) > 0){
    $ctrlAccId = (int) $ctrlAccId;
    }
  else{
    $ctrlAccId = NULL;
    }
  
  if(!strcmp($_POST["acc"], "NA") || is_Null($_POST["acc"])){
    $accToAdd = NULL;
    }
  else{
    $accToAdd = (int) $_POST["acc"];
    }

  $crntBln = 0; // Current balance
  $titular = NULL; // this is just a marker of some sort.
  
  $year = date("Y");
  $month = date("n");
  $day = date("j");
  
  
  $db = new SQLiteDatabase("acid.sqlite"); //the db object
  
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
  
  global $db, $ctrlAccId;
  
  $qAccList = $db->query("SELECT * FROM nAccounts");
  $result = $qAccList->fetchAll(); 
  
  $qCtrList = $db->query("SELECT * FROM cAccounts WHERE accId = $ctrlAccId");
  $result_2 = $qCtrList->fetchAll();
    
  echo "<table>";
  echo "<tr><td>Add an account as a member of <b>{$result_2[0]["accName"]} [{$result_2[0]["accCode"]}:{$result_2[0]["accId"]}]</b> control account.</tr>";
  echo "<tr><td>";
  echo "<form method=\"POST\" action=\"addacc.php\">";
  echo "<p><select name=\"acc\"><option value=\"NA\">Select an account</option>";
  
  foreach($result as $row){
    
    echo "<option value=\"{$row["accId"]}\">";
    echo "{$row["accName"]} [{$row["accCode"]}:{$row["accId"]}]";
    echo "</option>";
  }
  
  echo "</select><br><br><input type=\"submit\"></p>";
  echo "<input type=\"hidden\" name=\"ctrlId\" value=\"$ctrlAccId\">";
  echo "</form>";
  echo "</td></tr></table>";
  
  }

function processInput(){
  global $db, $ctrlAccId, $accToAdd;
     
    if(!is_Null($accToAdd)){ // it's not null we process the input
      
      $qCtrList = $db->query("SELECT * FROM cAccounts WHERE accId = $ctrlAccId");
      $result_2 = $qCtrList->fetchAll();
      
      $qTheQuery = $db->query("UPDATE nAccounts SET ctrlAcc = $ctrlAccId WHERE accId = $accToAdd");
      
      if($qTheQuery){
        echo "<table><tr><td class=\"success\">";
        echo "<p>One account added as a member of {$result_2[0]["accName"]} [{$result_2[0]["accCode"]}:{$result_2[0]["accId"]}] control account.</p>";
        echo "</td></tr>";
        }
        
    showForm();
    }
  else{
      showForm();      
    }
  

  } // process input

closeAccounts();

?>

<!-- HTML elements here -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<head>
  <title>(RAcS) Add an account to a control account <?php echo " -".$titular?></title>
  <link rel="stylesheet" type="text/css" href="style/addacc.css">
</head>

<body>


<?php
require("header.html");

if(!is_Null($ctrlAccId)){
  processInput();
  }
else {
  echo "<table><tr><td>No account is specified.</td></tr></table>";
  }
?>