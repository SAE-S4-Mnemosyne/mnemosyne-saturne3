<?php
/**
 * admin.php -- Point d'entree administration.
 * Delegue au controller MVC.
 */
require_once __DIR__ . '/app/controllers/AdminController.php';

$controller = new AdminController();
$controller->handleRequest();
