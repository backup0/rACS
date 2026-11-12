<?php
  
  // This part is fairly simple

  $entry = $_POST["makeEntry"];
  $jnlName = $_POST["name"];
  if(is_Null($jnlName) || strlen($jnlName) < 5){
    $entry = 0; 
    }
    
  $jnlMode = (int) $_POST["mode"]; 
  $autoAcc = (int) $_POST["autoAcc"];
  
  // the date thing
  $year = date("Y");
  $month = date("n");
  $day = date("j");
  
  $accList; // yes the account list
  
  $db = new SQLiteDatabase("acid.sqlite");
  
  $result = $db->query("SELECT accId, accName, accCode FROM nAccounts ORDER BY accName"); // this is for that auto entry thing.
  $accList = $result->fetchAll(SQLITE_ASSOC);

  $crntBln = 0; // we need this to close accounts properly.
  $titular = NULL; // this is just a marker of some sort.

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
  
  global $db, $year, $month, $crntBln,  $day;
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

function processInput(){ // store all the data in the DB
  global $jnlName, $jnlMode, $autoAcc, $year, $month, $day, $db;
  
  $qEntry = "INSERT INTO journals(jrnlName, year, month, day, jrnlMode, autoEntr) VALUES (\"$jnlName\", $year, $month, $day, $jnlMode, $autoAcc)";
  
  $result = $db->query($qEntry);
  
  if($result){ // Ok, obviously we have to make this thing looks better - Yes, of course.
    echo "One new journal created.";
    }
  else{
    echo "Creation of new journal failed.";
    }  
  
  // Reset the entire thing .. and well show the form, so use can create notehr journal.
  $jnlName = NULL;
  $jnlMode = NULL;
  $autoAcc = NULL;
  echo "<table><tr><td class=\"success\"><p>One (1) journal was created successfully. <br>You may create another one if you like to.</p></td></tr></table>";
  echo "<table><tr><td class=\"title\">Create a new journal</td></tr>";
  echo "<tr><td>";
  noName(); // call that function .. the show form function.
  echo "</td></tr></table>";
  }

function noName(){ // if data isn't entered in fully, show all the form
  global $jnlMode, $autoAcc, $jnlName;
  
  echo "<form method=\"POST\" action=\"createjournal.php\">";
  
  echo "<p><b>Journal name</b><br> <input type=\"text\" size=\"50\" maxlength=\"50\" name=\"name\" value=\"$jnlName\"></p>";
  
  switch($jnlMode){
    case 1: // normal journal .. we need no auto account here
      echo "<input type=\"hidden\" name=\"mode\" value=\"$jnlMode\">";
      echo "<input type=\"hidden\" name=\"autoAcc\" value=\"$autoAcc\">"; // well .. I don't know about this, I think to avoid errors
      break;
    case 2:
      if($autoAcc != 0){
        echo "<p>Debit ".getAccName($autoAcc)." automatically. </p>";
        echo "<input type=\"hidden\" name=\"mode\" value=\"$jnlMode\">";
        echo "<input type=\"hidden\" name=\"autoAcc\" value=\"$autoAcc\">";
        }
      else{
        echo "<p>Debit ";
        echo "<select name=\"autoAcc\">";
        echo "<option>Select an account</option>";
        showAccounts();
        echo "</select>";
        echo " account automatically.</p>";
        echo "<input type=\"hidden\" name=\"mode\" value=\"$jnlMode\">";
        }
      break;
    case 3: 
      if($autoAcc != 0){
        echo "<p>Credit ".getAccName($autoAcc)." account automatically. </p>";
        echo "<input type=\"hidden\" name=\"mode\" value=\"$jnlMode\">";
        echo "<input type=\"hidden\" name=\"autoAcc\" value=\"$autoAcc\">";
        }
      else{
        echo "<p>Credit ";
        echo "<select name=\"autoAcc\">";
        echo "<option>Select an account</option>";
        showAccounts();
        echo "</select>";
        echo " account automatically.</p>";
        echo "<input type=\"hidden\" name=\"mode\" value=\"$jnlMode\">";
        }
      break;
    default:
      echo "<p>";
      echo "<input type=\"radio\" name=\"mode\" value=\"1\" checked> Regular journal <br>";
      echo "<input type=\"radio\" name=\"mode\" value=\"2\"> Debit ";
      echo "<input type=\"radio\" name=\"mode\" value=\"3\"> Credit ";
      echo "<select name=\"autoAcc\">";
      echo "<option>Select an account</option>";
      showAccounts();
      echo "</select>";
      echo " automatically.";
      echo "</p>";
      break;
  
  }
  
  echo "<input type=\"hidden\" name=\"makeEntry\" value=\"1\">";
  echo "<input type=\"submit\"></form>";   
  
  }
  
function showAccounts(){ // show account list this one's for the select
  global $db, $accList;
  
  foreach($accList as $row){
    echo "<option value=\"{$row["accId"]}\">{$row["accName"]}[{$row["accCode"]}:{$row["accId"]}]</option>";
    }
  
  }

function getAccName($theAcc){ // translate acount code to name
  
  global $db, $accList;
  
  foreach($accList as $row){
    
    if($theAcc == $row["accId"]){
      $theAcc = $row["accName"]." [".$row["accCode"].":".$row["accId"]."]";
      break;
      }
    
    }
  return $theAcc;
  } // getAccName()

closeAccounts();
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<html>

  <head>
    <title>(RAcS) Create a new journal - <?php echo $titular; ?></title>
    <link rel="stylesheet" type="text/css" href="style/entry.css">
  </head>
  
  <body>

<?php

require("header.html");

if($entry == 0 || ($jnlMode != 1 && $autoAcc == 0) || $jnlMode == 0){ // Make sure we have enough information, if we don't show the form.
  echo "<table><tr><td class=\"title\">Create a new journal</td></tr>";
  echo "<tr><td>";
  noName();
  echo "</td></tr></table>";
  }
else{
  processInput();
  }


?>
