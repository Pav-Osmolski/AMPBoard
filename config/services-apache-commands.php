<?php
/** Explicit apache-commands dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$apacheCommands = new \AMPBoard\Apache\ShellCommandRunner();
