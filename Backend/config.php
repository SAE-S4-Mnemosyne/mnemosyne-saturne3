<?php
$host = "localhost";
$user = "root";
$pass = "root";   // sur MAMP c’est root
$db   = "sae_sankey";

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

