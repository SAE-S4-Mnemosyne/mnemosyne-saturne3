<?php
/**
 * login.php -- Point d'entree authentification.
 * Delegue au controller MVC.
 */
require_once __DIR__ . '/app/controllers/AuthController.php';

$controller = new AuthController();
$controller->handleRequest();
