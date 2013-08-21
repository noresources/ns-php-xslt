<?php

/**
 * Copyright © 2012 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 *
 * @package XSLT
 */
namespace NoreSources\XSLT;

use \DOMImplementation;
use \DOMDocument;
use \DOMNode;
use \DOMXPath;

require_once ("xslt.php");

/**
 * XSLT Stylesheet
 */
class XSLTStylesheet
{
	const DOCUMENT_ROOT_ELEMENT = "stylesheet";
	const MODE_IMPORT = 1;
	const MODE_REPLACE_EXISTING = 2;
	const MODE_KEEP_EXISTING = 3;
	
	const XML_NAMESPACE_PREFIX = "xml";
	const XML_NAMESPACE_URI = "http://www.w3.org/XML/1998/namespace";
	
	/**
	 * @param unknown $base
	 */
	public function __construct($base = null)
	{
		$this->m_basepath = $base;
		$this->clear();
	}

	public function __toString()
	{
		return $this->m_dom->saveXML();
	}

	/**
	 * Call method of the inner DOMDocument if allowed
	 *
	 * @param unknown $method        	
	 * @param unknown $args        	
	 * @return \BadFunctionCallException
	 */
	public function __call($method, $args)
	{
		$allowedMethods = array (
				"saveXML" 
		);
		
		if (in_array($method, $allowedMethods))
		{
			return call_user_func_array(array (
					$this->m_dom,
					$method 
			), $args);
		}
		
		if (method_exists($method, $this->m_dom))
		{
			return new \BadFunctionCallException($method . " is not allowed in " . get_class($this));
		}
		
		return new \BadFunctionCallException();
	}

	/**
	 * Clear stylesheet content
	 */
	public function clear()
	{
		$this->m_dom = $this->newDocument();
	}
	
	public function dom()
	{
		return $this->m_dom;
	}
	
	public function consolidate()
	{
		$this->consolidateDocument($this->m_dom, getcwd());
	}
	
	/**
	 * Clear and load a XSLT stylesheet file.
	 *
	 * The default behavior is similar to DOMDocument::load()
	 *
	 * @param string $filepath
	 *        	XSLT stylesheet to load
	 * @param integer $mode
	 *        	Load mode
	 */
	public function load($filepath, $mode = self::MODE_REPLACE_EXISTING)
	{
		$this->clear();
		if ($mode & self::MODE_IMPORT)
		{
			$import = $this->m_dom->createElementNS(XSLT_NAMESPACE_URI, "import");
			$import->setAttribute("href", $filepath);
		}
		else
		{
			$this->m_dom->load($filepath);
			$xpath = $this->newXPATH($this->m_dom);
			$queryString = XSLT_NAMESPACE_PREFIX . ":include|" . XSLT_NAMESPACE_PREFIX . ":import";
			$result = $xpath->query($queryString);
			foreach ($result as $node)
			{
				if (!$node->hasAttributeNS(self::XML_NAMESPACE_URI, "base"))
				{
					$node->setAttributeNS(self::XML_NAMESPACE_URI, self::XML_NAMESPACE_PREFIX . ":base", dirname(realpath($filepath)));
				}
			}
		}
	}

	/**
	 * Append another stylesheet resource
	 *
	 * @param string $filepath
	 *        	XSLT stylesheet to load
	 * @param integer $mode
	 *        	Load mode
	 *        	
	 *        	@note MODE_IMPORT mode can't be used if anything other than import and include nodes
	 *        	is present in the current stylesheet
	 */
	public function append($filepath, $mode = self::MODE_IMPORT)
	{
		if (($mode == self::MODE_IMPORT))
		{
			if ($this->importAllowed())
			{
				$import = $this->m_dom->createElementNS(XSLT_NAMESPACE_URI, "import");
				$import->setAttribute("href", $filepath);
				return;
			}
		}
		
		$dom = $this->newDocument();
		$dom->load($filepath);
		$this->consolidateDocument($dom, dirname(realpath($filepath)));
		foreach ($dom->documentElement->childNodes as $n)
		{
			$i = $this->m_dom->importNode($n, true);
			$this->m_dom->documentElement->appendChild($i);
		}
	}

	private function consolidateDocument(DOMDocument &$dom, $documentDirectoryPath)
	{
		$xpath = $this->newXPATH($dom);
		$queryString = XSLT_NAMESPACE_PREFIX . ":include|" . XSLT_NAMESPACE_PREFIX . ":import";
		$result = $xpath->query($queryString);
		foreach ($result as $node)
		{
			$this->consolidateNode($dom, $documentDirectoryPath, $node);
		}
	}

	private function consolidateNode(DOMDocument &$dom, $documentDirectoryPath, DOMNode $node)
	{
		$href = $node->getAttribute("href");
		//echo ($node->nodeName . ": " . $documentDirectoryPath . "::" . $href . "\n");
		$fullPath = realpath($documentDirectoryPath . "/" . $node->getAttribute("href"));
		
		$dom->documentElement->insertBefore($dom->createComment($node->nodeName . ": " . $documentDirectoryPath . "/" . $href), $node);
		
		if (file_exists($fullPath))
		{
			$sub = $this->newDocument();
			$sub->load($fullPath);
			$this->consolidateDocument($sub, dirname($fullPath));
			foreach ($sub->documentElement->childNodes as $n)
			{
				$i = $dom->importNode($n, true);
				$dom->documentElement->insertBefore($i, $node);
			}
		}
		else 
		{
			
		}
				
		$node->parentNode->removeChild($node);
	}

	public function importAllowed()
	{
		$xpath = $this->newXPATH($this->m_dom);
		$nodeNames = array (
				"import",
				"include" 
		);
		$queryString = "";
		foreach ($nodeNames as $nodeName)
		{
			if (strlen($queryString))
			{
				$queryString .= "|";
			}
			
			$queryString .= XSLT_NAMESPACE_PREFIX . ":" . $nodeName;
		}
		$queryString = "count(" . $queryString . ")";
		
		$a = $xpath->evaluate("count(*)", $this->m_dom->documentElement);
		$c = $xpath->evaluate($queryString, $this->m_dom->documentElement);
		
		if ($a != $c)
		{
			return false;
		}
		
		return true;
	}

	private function newXPATH(DOMDocument &$dom)
	{
		$xpath = new DOMXPath($dom);
		$xpath->registerNamespace(XSLT_NAMESPACE_PREFIX, XSLT_NAMESPACE_URI);
		return $xpath;
	}
	
	private function newDocument()
	{
		$impl = new \DOMImplementation();
		$doc = $impl->createDocument(XSLT_NAMESPACE_URI, XSLT_NAMESPACE_PREFIX . ":" . self::DOCUMENT_ROOT_ELEMENT);
		$doc->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:' . self::XML_NAMESPACE_PREFIX, self::XML_NAMESPACE_URI);
		
		return $doc;
	}
	
	/**
	 * @var DOMDocument XSLT stylesheet DOM
	 */
	private $m_dom;
}

?>
