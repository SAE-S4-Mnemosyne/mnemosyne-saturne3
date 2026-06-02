<?php
/**
 * index.php -- Point d'entree public (consultation).
 * Delegue au controller MVC.
 */
require_once __DIR__ . '/app/controllers/ConsultController.php';

$controller = new ConsultController();
$controller->handleRequest();