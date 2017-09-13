<?php

if (!headers_sent())
{
  http_response_code(503);
}

?>
<!DOCTYPE HTML>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update in progress</title>
</head>
<body>

<p style="margin-top: 100px; text-align: center;">Update in progress.</p>

</body>
</html>