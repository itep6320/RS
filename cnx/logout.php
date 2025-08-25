<?php
session_start();
session_destroy();
header("Location: ../traces/index.php");
exit;
