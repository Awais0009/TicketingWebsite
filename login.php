<?php
// Redirect to auth folder
header('Location: auth/Login.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;