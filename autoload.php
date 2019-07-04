<?php
spl_autoload_register(function($className) {
	if ($className == 'NoreSources\XSLT\XSLTStylesheet') {
		require_once(__DIR__ . '/XSLTStylesheet.php');
	} elseif ($className == 'NoreSources\XSLT\XSLTProcessor') {
		require_once(__DIR__ . '/XSLTProcessor.php');
	} elseif ($className == 'NoreSources\XSLT\StylesheetException') {
		require_once(__DIR__ . '/Stylesheet.php');
	} elseif ($className == 'NoreSources\XSLT\Stylesheet') {
		require_once(__DIR__ . '/Stylesheet.php');
	} elseif ($className == 'NoreSources\XSLT\StylesheetContext') {
		require_once(__DIR__ . '/Stylesheet.php');
	}
});