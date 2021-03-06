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

use \DOMDocument;
use \DOMNode;

const XSLT_NS = "http://www.w3.org/1999/XSL/Transform";
const XSLT_PREFIX = "xsl";

// / Extension of the PHP XSLTProcessor class
class XSLTProcessor
{

	public function __construct($filepath = null)
	{
		$this->processor = new \XSLTProcessor();
		
		if (file_exists($filepath))
		{
			$this->importStylesheet($filepath);
		}
		else
		{
			$impl = new \DOMImplementation();
			$this->xsl = $impl->createDocument(XSLT_NS, null);
			$root = $this->xsl->createElementNS(XSLT_NS, XSLT_PREFIX . ":stylesheet");
			$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . XSLT_PREFIX, XSLT_NS);
			$root->setAttribute("version", "1.0");
			$this->xsl->appendChild($root);
			$this->xslFirstTemplateNode = null;
		}
	}

	/**
	 * Override currently imported stylesheets
	 *
	 * @param \DOMNode $node        	
	 */
	public function importStylesheet(\DOMNode $node)
	{
		$this->xslFirstTemplateNode = null;
		$this->xsl = $node;
		
		$xpath = new \DOMXPath($this->xsl);
		$xpath->registerNamespace(XSLT_PREFIX, XSLT_NS);
		
		$res = $xpath->query("xsl:template[1]", $this->xsl->documentElement);
		if ($res->length)
		{
			$this->xslFirstTemplateNode = $res->item(0);
		}
	}
	
	// / Append stylesheet elements
	/**
	 *
	 * @param string $filepath        	
	 */
	public function appendStylesheet($filepath, $useImport = false)
	{
		if ($useImport)
		{
			$import = $this->xsl->createElementNS(XSLT_NS, "import");
			$import->setAttribute("href", $filepath);
			if ($this->xslFirstTemplateNode)
			{
				$this->xslFirstTemplateNode->parentNode->insertBefore($import, $this->xslFirstTemplateNode);
			}
			else
			{
				$this->xsl->documentElement->appendChild($import);
			}
		}
		else
		{
			$doc = new DOMDocument();
			$doc->load($filepath);
			$xpath = new \DOMXPath($doc);
			$xpath->registerNamespace(XSLT_PREFIX, XSLT_NS);
			$dirname = dirname(realpath($filepath));
			
			$xslXPath = new \DOMXPath($this->xsl);
			$registeredNamespaces = $xslXPath->query("namespace::*", $this->xsl->documentElement);
			
			$res = $xpath->query("namespace::*", $doc->documentElement);
			foreach ($res as $n)
			{
				$p = (strlen($n->prefix)) ? ":" . $n->prefix : "";
				
				$skip = false;
				foreach ($registeredNamespaces as $ns)
				{
					if ($ns->nodeValue == $n->nodeValue)
					{
						$skip = true;
						break;
					}
				}
				
				if ($skip)
				{
					continue;
				}
				
				$this->xsl->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns' . $p, $n->nodeValue);
			}
			
			$res = $xpath->query("xsl:import|xsl:include", $doc->documentElement);
			foreach ($res as $n)
			{
				$n = $this->xsl->importNode($n);
				if ($this->xslFirstTemplateNode)
				{
					$this->xslFirstTemplateNode->parentNode->insertBefore($n, $this->xslFirstTemplateNode);
				}
				else
				{
					$this->xsl->documentElement->appendChild($n);
				}
				
				$href = $dirname . "/" . $n->getAttribute("href");
				$n->setAttribute("href", $href);
			}
			
			$res = $xpath->query("xsl:variable|xsl:param", $doc->documentElement);
			foreach ($res as $n)
			{
				$n = $this->xsl->importNode($n);
				if ($this->xslFirstTemplateNode)
				{
					$this->xslFirstTemplateNode->parentNode->insertBefore($n, $this->xslFirstTemplateNode);
				}
				else
				{
					$this->xsl->documentElement->appendChild($n);
				}
			}
			
			$res = $xpath->query("xsl:template", $doc->documentElement);
			foreach ($res as $n)
			{
				$n = $this->xsl->importNode($n, true);
				$this->xsl->documentElement->appendChild($n);
			}
			
			// Set or replace output mode
			$res = $xpath->query("xsl:output", $doc->documentElement);
			if ($res->item(0))
			{
				$n = $this->xsl->importNode($res->item(0), true);
				if ($this->xslOutputNode)
				{
					$this->xsl->replaceChild($n, $this->xslOutputNode);
				}
				else if ($this->xslFirstTemplateNode)
				{
					$this->xslFirstTemplateNode->parentNode->insertBefore($n, $this->xslFirstTemplateNode);
				}
				else
				{
					$this->xsl->documentElement->appendChild($n);
				}
				
				$this->xslOutputNode = $n;
			}
		}
	}

	/**
	 *
	 * @param DOMNode $nodes        	
	 * @return DOMDocument
	 */
	public function transformToDoc(DOMNode $nodes)
	{
		$this->processor->importStylesheet($this->xsl);
		return $this->processor->transformToDoc($nodes);
	}

	/**
	 *
	 * @param DOMNode $nodes        	
	 * @return string
	 */
	public function transformToXML(DOMNode $nodes)
	{
		$this->processor->importStylesheet($this->xsl);
		return $this->processor->transformToXML($nodes);
	}

	public function setParameter($namespace, $parameter, $value)
	{
		$this->processor->setParameter($namespace, $parameter, $value);
	}

	/**
	 *
	 * @var DOMDocument
	 */
	private $xsl;

	private $xslFirstTemplateNode;

	private $xslOutputNode;

	/**
	 *
	 * @var \XSLTProcessor
	 */
	private $processor;
}

?>
