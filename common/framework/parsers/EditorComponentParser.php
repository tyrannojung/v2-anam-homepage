<?php

namespace Rhymix\Framework\Parsers;

/**
 * Editor component (info.xml) parser class for XE compatibility.
 */
class EditorComponentParser extends BaseParser
{
	/**
	 * Load an XML file.
	 *
	 * @param string $filename
	 * @param string $component_name
	 * @param string $lang
	 * @return ?object
	 */
	public static function loadXML(string $filename, string $component_name, string $lang = ''): ?object
	{
		// Load the XML file.
		$xml = simplexml_load_string(file_get_contents($filename));
		if ($xml === false)
		{
			return null;
		}

		// Get the current language.
		$lang = $lang ?: (\Context::getLangType() ?: 'en');

		// Initialize the module definition.
		$info = new \stdClass;
		$info->component_name = $component_name;

		// Get the XML schema version.
		$version = strval($xml['version']) ?: '0.1';

		// Parse version 0.2
		if ($version === '0.2')
		{
			$info->title = self::_getChildrenByLang($xml, 'title', $lang);
			$info->description = self::_getChildrenByLang($xml, 'description', $lang);
			$info->version = trim($xml->version ?? '');
			$info->date = date('Ymd', strtotime($xml->date . 'T12:00:00Z'));
			$info->homepage = trim($xml->link);
			$info->license = trim($xml->license ?? '');
			$info->license_link = trim($xml->license['link'] ?? '');
			$info->author = array();

			foreach ($xml->author as $author)
			{
				$author_info = new \stdClass;
				$author_info->name = self::_getChildrenByLang($author, 'name', $lang);
				$author_info->email_address = trim($author['email_address'] ?? '');
				$author_info->homepage = trim($author['link'] ?? '');
				$info->author[] = $author_info;
			}
		}

		// Parse version 0.1
		else
		{
			$info->title = self::_getChildrenByLang($xml, 'title', $lang);
			$info->description = self::_getChildrenByLang($xml->author, 'description', $lang);
			$info->version = trim($xml['version'] ?? '');
			$info->date = date('Ymd', strtotime($xml->author['date'] . 'T12:00:00Z'));
			$info->homepage = trim($xml->link) ?: trim($xml->homepage);
			$info->license = trim($xml->license);
			$info->license_link = trim($xml->license['link'] ?? '');
			$info->author = array();

			$author_info = new \stdClass;
			$author_info->name = self::_getChildrenByLang($xml->author, 'name', $lang);
			$author_info->email_address = trim($xml->author['email_address']);
			$author_info->homepage = trim($xml->author['link'] ?? '');
			$info->author[] = $author_info;
		}

		// Get extra_vars.
		if ($xml->extra_vars)
		{
			$info->extra_vars = self::_getExtraVars($xml->extra_vars, $lang, 'editor_component');
		}
		else
		{
			$info->extra_vars = new \stdClass;
		}

		// Return the complete result.
		return $info;
	}
}
