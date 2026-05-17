<?php
// File: backend/auth/logout.php
require_once '../database.php';
session_destroy();
redirect('../../login.php');
?>