<?php

/**
 * Copyright © 2012-2015 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 * @package XSLT
 */
namespace NoreSources\XSLT;

use NoreSources as ns;
use DOMImplementation;
use DOMDocument;
use DOMNode;
use DOMXPath;

class StylesheetException extends \Exception
{

	public function __construct($message)
	{
		parent::__construct($message);
	}
}

class Stylesheet
{

	public static function consolidateFile($filename)
	{
		$impl = new \DOMImplementation();
		$stylesheet = $impl->createDocument(self::XSLT_NAMESPACE_URI, self::XSLT_NAMESPACE_PREFIX . ':' . self::DOCUMENT_ROOT_ELEMENT);
		$stylesheet->load($filename);
		return self::consolidateDocument($filename, dirname(realpath($filename)));
	}

	public static function consolidateDocument(\DOMDocument $stylesheet, $documentDirectoryPath)
	{
		$context = new StylesheetContext($stylesheet, $documentDirectoryPath);
		return self::consolidateStylesheet($context);
	}
	const XSLT_NAMESPACE_URI = 'http://www.w3.org/1999/XSL/Transform';
	const XSLT_NAMESPACE_PREFIX = 'xsl';
	const DOCUMENT_ROOT_ELEMENT = 'stylesheet';
	const XML_NAMESPACE_PREFIX = 'xml';
	const XML_NAMESPACE_URI = 'http://www.w3.org/XML/1998/namespace';

	private static function consolidateStylesheet(StylesheetContext $context)
	{
		$xpath = new DOMXPath($context->stylesheet);
		$xpath->registerNamespace(self::XSLT_NAMESPACE_PREFIX, self::XSLT_NAMESPACE_URI);

		$queryString = self::XSLT_NAMESPACE_PREFIX . ':include|' . self::XSLT_NAMESPACE_PREFIX . ':import';
		$result = $xpath->query($queryString);
		foreach ($result as $node)
		{
			self::consolidateStylesheetNode($context, $node);
		}
	}

	private static function consolidateStylesheetNode(StylesheetContext $context, \DOMNode $node)
	{
		$href = $node->getAttribute('href');
		$fullPath = realpath($context->documentDirectoryPath . '/' . $href);

		if (!file_exists($fullPath))
		{
			throw new StylesheetException($href . ' not found');
		}
		
		$context->stylesheet->documentElement->insertBefore(
				$context->stylesheet->createComment('merged import href="' . $href . '"'), 
				$node);

		$impl = new \DOMImplementation();
		$sub = $impl->createDocument(self::XSLT_NAMESPACE_URI, self::XSLT_NAMESPACE_PREFIX . ':' . self::DOCUMENT_ROOT_ELEMENT);
		$sub->load($fullPath);

		self::consolidateDocument($sub, dirname($fullPath));

		foreach ($sub->documentElement->childNodes as $n)
		{
			$i = $context->stylesheet->importNode($n, true);
			$context->stylesheet->documentElement->insertBefore($i, $node);
		}

		$node->parentNode->removeChild($node);
	}
}

class StylesheetContext
{
	public $stylesheet;
	public $documentDirectoryPath;
	
	public function __construct (\DOMDocument $stylesheet, $documentDirectoryPath)
	{
		$this->stylesheet = $stylesheet;
		$this->documentDirectoryPath = $documentDirectoryPath;
	}
}

