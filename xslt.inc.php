<?php

/**
 * Copyright © 2013 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 *
 * @package Creole
 */
namespace NoreSources\XSLT;

require_once ("xslt.php");

// Modules requirements
if (!defined("NS_PHP_PATH"))
{
	throw new \Exception("NS_PHP_PATH is not defined");
}

if (!defined("NS_PHP_CORE_PATH"))
{
	// Attempt to load default path
	if (!file_exists(NS_PHP_PATH . "/core/core.php"))
	{
		throw new \Exception("NoreSources core module not found");
	}
	
	require_once (NS_PHP_PATH . "/core/core.php");
}

?>
