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

/**
 * XSLT Stylesheet
 */
class XSLTStylesheet
{
	/**
	 * XSLT content loading mode.
	 *
	 * Use a &lt;xsl:import&gt; node to append XSLT content to the XSLTStylesheet DOM document
	 * @var integer
	 */
	const LOAD_IMPORT = 1;

	/**
	 * XSLT content loading mode.
	 *
	 * When a conflict occurs between an existing XSL node and one beeing added,
	 * the existing node is replaced by the new one.
	 *
	 * @var integer
	 */
	const LOAD_REPLACE_EXISTING = 2;

	/**
	 * XSLT content loading mode.
	 *
	 * When a conflict occurs between an existing XSL node and one beeing added,
	 * the new node is ignored.
	 *
	 * @var integer
	 */
	const LOAD_KEEP_EXISTING = 3;

	/**
	 * Construct a new XSLTStylesheet
	 */
	public function __construct()
	{
		$this->m_baseURIs = array ();
		$this->clear();
	}

	/**
	 * Output content of XSLT stylesheet
	 */
	public function __toString()
	{
		return $this->m_dom->saveXML();
	}

	/**
	 * Call method of the inner DOMDocument if allowed
	 *
	 * @param string $method
	 * @param array $args
	 * @throws \BadMethodCallException
	 * @return mixed
	 */
	public function __call($method, $args)
	{
		$allowedMethods = array (
				'saveXML'
		);

		if (in_array($method, $allowedMethods))
		{
			return call_user_func_array(array (
					$this->m_dom,
					$method
			), $args);
		}

		throw new \BadMethodCallException($method . ' does not exists or is not allowed');
	}

	/**
	 * Get member of the inner DOMDocument if allowed
	 *
	 * @param string $member
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function __get($member)
	{
		$allowedMember = array (
				'documentElement',
				'documentURI',
				'encoding',
				'formatOutput',
				'implementation',
				'preserveWhiteSpace',
				'resolveExternals'
		);
		if (in_array($member, $allowedMember))
		{
			return $this->m_dom->$member;
		}

		throw new \InvalidArgumentException($member . ' property does not exists or is not allowed');
	}

	/**
	 * Clear stylesheet content
	 */
	public function clear()
	{
		$this->m_dom = $this->newDocument();
	}

	/**
	 * @return \DOMDocument
	 */
	public function dom()
	{
		return $this->m_dom;
	}

	/**
	 * Consolidate XSLT stylesheet by replacing all &lt;xsl:import&gt; nodes by the content of the referenced document
	 */
	public function consolidate()
	{
		$this->consolidateDocument($this->m_dom, null);
	}

	/**
	 * Clear and load a XSLT stylesheet file.
	 *
	 * The default behavior is similar to DOMDocument::load()
	 *
	 * @param string $filepath XSLT stylesheet to load
	 * @param integer $mode Load mode. Must be one of
	 *        <ul>
	 *        <li>XSLTStylesheet::LOAD_IMPORT</li>
	 *        <li>XSLTStylesheet::LOAD_REPLACE_EXISTING</li>
	 *        <li>XSLTStylesheet::LOAD_KEEP_EXISTING</li>
	 *        </ul>
	 */
	public function load($filepath, $mode = self::LOAD_REPLACE_EXISTING)
	{
		$this->clear();
		if ($mode & self::LOAD_IMPORT)
		{
			$import = $this->m_dom->createElementNS(self::XSLT_NAMESPACE_URI, 'import');
			$import->setAttribute('href', $filepath);
			$this->setBaseURI($import, dirname(realpath($filepath)));
		}
		else
		{
			$this->m_dom->load($filepath);
			//$this->m_dom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . self::XML_NAMESPACE_PREFIX, self::XML_NAMESPACE_URI);

			$xpath = $this->newXPATH($this->m_dom);
			$queryString = self::XSLT_NAMESPACE_PREFIX . ':include|' . self::XSLT_NAMESPACE_PREFIX . ':import';
			$result = $xpath->query($queryString);
			foreach ($result as $node)
			{
				if ($node->hasAttributeNS(self::XML_NAMESPACE_URI, 'base'))
				{
					$this->setBaseURI($node, $node->getAttributeNS(self::XML_NAMESPACE_URI, 'base'));
				}
				else
				{
					$this->setBaseURI($node, dirname(realpath($filepath)));
				}
			}
		}
	}

	/**
	 * @param string $filename Output file path
	 * @param string $options \DOMDocument::save options
	 */
	public function save($filename, $options = 0)
	{
		$path = dirname(realpath($filename));
		$xpath = $this->newXPATH($this->m_dom);
		$queryString = self::XSLT_NAMESPACE_PREFIX . ':include|' . self::XSLT_NAMESPACE_PREFIX . ':import';
		$result = $xpath->query($queryString);
		if ($result->length)
		{
			$dom = clone $this->m_dom;
			$xpath = $this->newXPATH($dom);
			$result = $xpath->query($queryString);
			foreach ($result as $node)
			{
				$href = $node->getAttribute('href');
				$base = null;
				if ($this->hasBaseURI($node))
				{
					$base = $this->getBaseURI($node);
				}

				//echo ('rebase ' . $base . '/' . $href . '\n');
				//echo ('to: ' . $path . '\n');

				if (!$base)
				{
					continue;
				}

				$to = dirname(realpath($base . '/' . $href));

				$relativePath = ns\PathUtil::getRelative($path, $to);
				$relativePath .= '/' . basename($href);

				//echo ('relative: ' . $relativePath . '\n');
				//echo ('full: ' . $path . '/' . $relativePath . '\n');

				$node->setAttribute('href', $relativePath);
				if ($node->hasAttributeNS(self::XML_NAMESPACE_URI, self::XML_NAMESPACE_PREFIX . ':base'))
				{
					$node->removeAttributeNS(self::XML_NAMESPACE_URI, self::XML_NAMESPACE_PREFIX . ':base');
				}

				if ($node->hasAttributeNS(self::XML_NAMESPACE_URI, 'base'))
				{
					$node->removeAttributeNS(self::XML_NAMESPACE_URI, 'base');
				}
			}

			return $dom->save($filename, $options);
		}
		else
		{
			return $this->m_dom->save($filename, $options);
		}
	}

	/**
	 * Append another stylesheet resource
	 *
	 * @param string $filepath XSLT stylesheet to load
	 * @param integer $mode Load mode
	 *        <ul>
	 *        <li>XSLTStylesheet::LOAD_IMPORT</li>
	 *        <li>XSLTStylesheet::LOAD_REPLACE_EXISTING</li>
	 *        <li>XSLTStylesheet::LOAD_KEEP_EXISTING</li>
	 *        </ul>
	 *       
	 *        	@note LOAD_IMPORT mode can't be used if anything other than import and include nodes
	 *        	is present in the current stylesheet
	 */
	public function append($filepath, $mode = self::LOAD_REPLACE_EXISTING)
	{
		if (($mode == self::LOAD_IMPORT))
		{
			if ($this->importAllowed())
			{
				$import = $this->m_dom->createElementNS(self::XSLT_NAMESPACE_URI, 'import');
				$import->setAttribute('href', realpath($filepath));
				return;
			}
		}

		$dom = $this->newDocument();
		$dom->load($filepath);
		//$dom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . self::XML_NAMESPACE_PREFIX, self::XML_NAMESPACE_URI);

		$this->consolidateDocument($dom, dirname(realpath($filepath)));
		foreach ($dom->documentElement->childNodes as $n)
		{
			$i = $this->m_dom->importNode($n, true);
			$this->m_dom->documentElement->appendChild($i);
		}
	}

	private function importAllowed()
	{
		$xpath = $this->newXPATH($this->m_dom);
		$nodeNames = array (
				'import',
				'include'
		);
		$queryString = '';
		foreach ($nodeNames as $nodeName)
		{
			if (strlen($queryString))
			{
				$queryString .= '|';
			}
			$queryString .= self::XSLT_NAMESPACE_PREFIX . ':' . $nodeName;
		}
		$queryString = 'count(' . $queryString . ')';

		$a = $xpath->evaluate('count(*)', $this->m_dom->documentElement);
		$c = $xpath->evaluate($queryString, $this->m_dom->documentElement);

		if ($a != $c)
		{
			return false;
		}

		return true;
	}

	private function consolidateDocument(DOMDocument &$dom, $documentDirectoryPath = null)
	{
		$xpath = $this->newXPATH($dom);
		$queryString = self::XSLT_NAMESPACE_PREFIX . ':include|' . self::XSLT_NAMESPACE_PREFIX . ':import';
		$result = $xpath->query($queryString);
		foreach ($result as $node)
		{
			$p = $documentDirectoryPath;
			if ($this->hasBaseURI($node))
			{
				$p = $this->getBaseURI($node);
			}
			$this->consolidateNode($dom, $p, $node);
		}
	}

	private function consolidateNode(DOMDocument &$dom, $documentDirectoryPath, DOMNode $node)
	{
		$href = $node->getAttribute('href');
		// echo ($node->nodeName . ': ' . $documentDirectoryPath . '::' . $href . '\n');
		$fullPath = realpath($documentDirectoryPath . '/' . $node->getAttribute('href'));

		$dom->documentElement->insertBefore($dom->createComment($node->nodeName . ': ' . $documentDirectoryPath . '/' . $href), $node);

		if (file_exists($fullPath))
		{
			$sub = $this->newDocument();
			$sub->load($fullPath);
			//$dom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . self::XML_NAMESPACE_PREFIX, self::XML_NAMESPACE_URI);

			$this->consolidateDocument($sub, dirname($fullPath));
			foreach ($sub->documentElement->childNodes as $n)
			{
				$i = $dom->importNode($n, true);
				$dom->documentElement->insertBefore($i, $node);
			}
		}
		else
		{}

		$node->parentNode->removeChild($node);
	}

	private function newXPATH(DOMDocument &$dom)
	{
		$xpath = new DOMXPath($dom);
		$xpath->registerNamespace(self::XSLT_NAMESPACE_PREFIX, self::XSLT_NAMESPACE_URI);
		return $xpath;
	}

	private function newDocument()
	{
		$impl = new \DOMImplementation();
		$doc = $impl->createDocument(self::XSLT_NAMESPACE_URI, self::XSLT_NAMESPACE_PREFIX . ':' . self::DOCUMENT_ROOT_ELEMENT);

		return $doc;
	}

	private function hasBaseURI($node)
	{
		//return $node->hetAttributeNS(self::XML_NAMESPACE_URI, 'base');
		return array_key_exists($node->getAttribute('href'), $this->m_baseURIs);
	}

	private function getBaseURI($node)
	{
		//return $node->getAttributeNS(self::XML_NAMESPACE_URI, 'base');
		return $this->m_baseURIs[$node->getAttribute('href')];
	}

	private function setBaseURI($node, $base)
	{
		//$node->setAttributeNS(self::XML_NAMESPACE_URI, 'base', $base);
		$this->m_baseURIs[$node->getAttribute('href')] = $base;
	}

	/**
	 * @var \DOMDocument XSLT stylesheet DOM
	 */
	private $m_dom;

	private $m_baseURIs;
	const XSLT_NAMESPACE_URI = 'http://www.w3.org/1999/XSL/Transform';
	const XSLT_NAMESPACE_PREFIX = 'xsl';
	const DOCUMENT_ROOT_ELEMENT = 'stylesheet';
	const XML_NAMESPACE_PREFIX = 'xml';
	const XML_NAMESPACE_URI = 'http://www.w3.org/XML/1998/namespace';
}
