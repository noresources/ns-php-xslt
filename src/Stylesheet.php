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

/**
 * XSLT stylesheet utility
 */
class Stylesheet
{

	/**
	 * Replace all XSL include and XSLT import nodes by the referenced content
	 * @param string $filename XSLT file path
	 * @return unknown
	 * @return \\DOMDocument
	 */
	public static function consolidateFile($filename)
	{
		$impl = new \DOMImplementation();
		$stylesheet = $impl->createDocument(self::XSLT_NAMESPACE_URI, self::XSLT_NAMESPACE_PREFIX . ':' . self::DOCUMENT_ROOT_ELEMENT);
		$stylesheet->load($filename);
		return self::consolidateDocument($filename, dirname(realpath($filename)));
	}

	/**
	 * @param \DOMDocument $stylesheet XSLT stylesheet document
	 * @param unknown $documentDirectoryPath Reference path of the document
	 * @return \\DOMDocument
	 */
	public static function consolidateDocument(\DOMDocument $stylesheet, $documentDirectoryPath)
	{
		$context = new StylesheetContext($stylesheet, $documentDirectoryPath);
		return self::consolidateStylesheet($context);
	}

	/**
	 * XSLT schema namespace URL
	 * @var string
	 */
	const XSLT_NAMESPACE_URI = 'http://www.w3.org/1999/XSL/Transform';

	/**
	 * XSLT namespace prefix used internally
	 * @var string
	 */
	const XSLT_NAMESPACE_PREFIX = 'xsl';

	/**
	 * Node name of the XSLT stylesheet root element
	 * @var string
	 */
	const DOCUMENT_ROOT_ELEMENT = 'stylesheet';

	/**
	 * XML namespace prefix used internally
	 * @var string
	 */
	const XML_NAMESPACE_PREFIX = 'xml';

	/**
	 * XSM namespace URL
	 * @var string
	 */
	const XML_NAMESPACE_URI = 'http://www.w3.org/XML/1998/namespace';

	/**
	 * @param StylesheetContext $context
	 * @return \\DOMDocument
	 */
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

		return $context->stylesheet;
	}

	private static function consolidateStylesheetNode(StylesheetContext $context, \DOMNode $node)
	{
		$href = $node->getAttribute('href');
		$fullPath = realpath($context->documentDirectoryPath . '/' . $href);

		if (!file_exists($fullPath))
		{
			throw new StylesheetException($href . ' not found');
		}

		// Comment to mark there was a import node here
		$context->stylesheet->documentElement->insertBefore($context->stylesheet->createComment('merged import href="' . $href . '"'), $node);

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
		self::removeDuplicatedTemplates($context);
	}

	private static function removeDuplicatedTemplates(StylesheetContext $context)
	{
		$xpath = new \DOMXPath($context->stylesheet);
		$xpath->registerNamespace(self::XSLT_NAMESPACE_PREFIX, self::XSLT_NAMESPACE_URI);

		$nodeNames = array (
				'template' => array (
						'name',
						'match'
				),
				'param' => array (
						'name'
				),
				'variable' => array (
						'name'
				)
		);

		foreach ($nodeNames as $nodeName => $attributes)
		{
			$nodes = $xpath->query('./' . self::XSLT_NAMESPACE_PREFIX . ':' . $nodeName);
			foreach ($nodes as $node)
			{
				$attributeName = false;
				foreach ($attributes as $name)
				{
					if ($node->hasAttribute($name))
					{
						$attributeName = $name;
						break;
					}
				}
			

				if ($attributeName)
				{
					$query = './' . self::XSLT_NAMESPACE_PREFIX . ':'.$nodeName.'[@' . $attributeName . '="' . $node->getAttribute($attributeName) . '"]';
					$duplicates = $xpath->query($query);
					if ($duplicates->length > 1)
					{
						$c = 0;
						for ($i = 0; $i < $duplicates->length -1; $i++)
						{
							$duplicated = $duplicates->item($i);
							$duplicated->parentNode->removeChild ($duplicated);
						}
					}
				}
			}
		}
	}
}

class StylesheetContext
{

	public $stylesheet;

	public $documentDirectoryPath;

	public function __construct(\DOMDocument $stylesheet, $documentDirectoryPath)
	{
		$this->stylesheet = $stylesheet;
		$this->documentDirectoryPath = $documentDirectoryPath;
	}
}

