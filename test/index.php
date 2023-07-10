<?php

error_reporting(E_ALL);

$cmd1 = 'php ' . __DIR__ . '/select-sql-builder.php';
exec($cmd1, $output, $return_var1);

$cmd2 = 'php ' . __DIR__ . '/upsert-sql-builder.php';
exec($cmd2, $output, $return_var2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test - JSON-Schema-SQL-Builder</title>
</head>
<body>
  <?php if ($return_var1 === 0 && $return_var2 === 0): ?>
    <h1 style="background-color:#32CD32;">Test passed!</h1>
  <?php else: ?>
    <h1 style="background-color:#FF0000;">Test failed!</h1>
  <?php endif; ?>
  <h2>Output</h2>
  <pre>
    <?php print_r($output); ?>
  </pre>
</body>
</html>