<?php

/**
 * Copyright © 2012 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 * @package XSLT
 */
namespace NoreSources\XSLT;

const VERSION_MAJOR = 0;
const VERSION_MINOR = 1;
const VERSION_PATCH = 0;

/**
 * Version string of NoreSources XSLT module.
 * The version string can be used with the PHP function version_compare()
 * @return XSLT module version
 */
function version_string()
{
	return (VERSION_MAJOR . "." . VERSION_MINOR . "." . VERSION_PATCH);
}

function version_number()
{
	return (VERSION_MAJOR * 10000 + VERSION_MINOR * 100 + VERSION_PATCH);
}

?>
