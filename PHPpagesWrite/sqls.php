<html>
<body>
<?php

  $db=new SQLiteDatabase("acid.sqlite");
  
  $year = 2008;
  $month = 11;
  $day = 2;
  $debit = 1;
  $credit = 4;
  $amount = 100;
  
  //$shit = $db->query("SELECT * FROM ledgers WHERE debit = 1 AND credit = 1 ORDER BY transId");
  //$result = $shit->fetchAll(SQLITE_ASSOC);
  
  //$shit = $db->query("SELECT * FROM ledgers");
  //$result = $shit->fetchAll(SQLITE_ASSOC);
  
  //print_r($result);
  
  
  //print_r($result);
  $db->query("DELETE FROM nAccounts");
  $db->query("DELETE FROM cAccounts");
  $db->query("DELETE FROM ledgers");
  $db->query("DELETE FROM journals");
  $db->query("DELETE FROM lastClosed");
  
  //$db->query("INSERT INTO nAccounts(accName, accCode, year, month, day, accBalance) VALUES('Cash', 'CASH', 2008, 2, 6, 0)");
  //$db->query("INSERT INTO nAccounts(accName, accCode, year, month, day, accBalance) VALUES('Wealth', 'WLTH', 2008, 2, 16, 0)");
  //$db->query("INSERT INTO lastClosed(year, month, day) VALUES(2008, 2, 49)");
  //$db->query("INSERT INTO ledgers(year, month, day, debit, credit, amount, descr, jnlGroup, transFlag) VALUES(2008, 3, 8, 1, 1, 1100, 'fart', 1, 4)");
  //$db->query("INSERT INTO ledgers(year, month, day, debit, credit, amount, descr, jnlGroup, transFlag) VALUES(2008, 3, 9, 2, 1, 110, 'fart2', 1, 0)");
  //$db->query("INSERT INTO ledgers(year, month, day, debit, credit, amount, descr, jnlGroup, transFlag) VALUES(2008, 3, 14, 1, 2, 1500, 'fart4', 1, 0)");
  
  //$db->query("INSERT INTO ledgers(year, month, day, debit, credit, amount, jnlGroup, transFlag) VALUES($year, $month, $day, $debit, $credit, $amount, 2, 0)")
  
  //$db->query("CREATE TABLE lastClosed(id INTEGER AUTOINCREMENT PRIMARY KEY, year INT NOT NULL, month INT NOT NULL, day INT NOT NULL)");
  
  //$result = $db->query("CREATE TABLE settings(id INTEGER AUTOINCREMENT PRIMARY KEY, item varchar(50) NOT NULL, detail INT NOT NULL)");
  
  //if($result)
    //echo "OK"; 

  //$db->query("DROP TABLE ledgers");
  //$result = $db->query("CREATE TABLE ledgers(transId INTEGER AUTOINCREMENT PRIMARY KEY NOT NULL, year INT NOT NULL, month INT NOT NULL, day INT NOT NULL, debit INT NOT NULL, credit INT NOT NULL, amount NUMERIC NOT NULL, descr TEXT, jnlGroup INT NOT NULL, docId VARCHAR(20), transFlag INT NOT NULL)");


/*
  $result = $db->query("SELECT * FROM nAcc");
  $rowZ = $result->fetchAll(SQLITE_ASSOC);
  
  print_r($result);
  
  foreach($rowZ as $row){
    echo "<p>";
    print_r($row);
    echo "</p>";
  }
  */

  
  /*
  
$result = $db->query("INSERT INTO ledger(year, month, day, debit, credit, amount) VALUES(2009, 1, 1, 1, 10, 50000)");

$result = $db->query("INSERT INTO ledger(year, month, day, debit, credit, amount) VALUES(2009, 1, 2, 9, 1, 25000)");

$result = $db->query("INSERT INTO ledger(year, month, day, debit, credit, amount) VALUES(2009, 1, 4, 5, 1, 5000)");

$result = $db->query("INSERT INTO ledger(year, month, day, debit, credit, amount) VALUES(2009, 1, 4, 4, 1, 110)");

$result = $db->query("INSERT INTO ledger(year, month, day, debit, credit, amount) VALUES(2009, 1, 5, 9, 2, 10000)");

$result = $db->query("INSERT INTO ledger(year, month, day, debit, credit, amount) VALUES(2009, 1, 5, 7, 1, 220)");

$result = $db->query("INSERT INTO ledger(year, month, day, debit, credit, amount) VALUES(2009, 1, 5, 6, 1, 3200)");
  */   
   /*

  $result = $db->query("INSERT INTO accounts(accName, accCode, dateCreated) VALUES('Paycheck', 'PCHK', 20090102)");
  $result = $db->query("INSERT INTO accounts(accName, accCode, dateCreated) VALUES('AdSense Revenue', 'ADSR', 20090102)");
  $result = $db->query("INSERT INTO accounts(accName, accCode, dateCreated) VALUES('Sundry Expense', 'EXPN', 20090102)");
  $result = $db->query("INSERT INTO accounts(accName, accCode, dateCreated) VALUES('Stocks', 'STCK', 20090103)");
  $result = $db->query("INSERT INTO accounts(accName, accCode, dateCreated) VALUES('Entertainment equipment', 'ENTS', 20090104)");
  $result = $db->query("INSERT INTO accounts(accName, accCode, dateCreated) VALUES('Porn', 'PORN', 20090104)");
  $result = $db->query("INSERT INTO accounts(accName, accCode, dateCreated) VALUES('Dividend', 'DVDN', 20090104)");
   
  */  


    
  
?>
</body>
</html>