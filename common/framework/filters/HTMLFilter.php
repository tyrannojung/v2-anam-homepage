<?php

namespace Rhymix\Framework\Filters;

use Rhymix\Framework\Config;
use Rhymix\Framework\Security;
use Rhymix\Framework\Storage;
use Rhymix\Framework\URL;

/**
 * The HTML filter class.
 */
class HTMLFilter
{
	/**
	 * HTMLPurifier instances are cached here.
	 */
	protected static $_instances = array();

	/**
	 * Pre-processing and post-processing filters are stored here.
	 */
	protected static $_preproc = array();
	protected static $_postproc = array();

	/**
	 * Default permissions for iframes.
	 */
	protected static $_iframe_permissions = array(
		'accelerometer' => true,
		'autoplay' => true,
		'clipboard-write' => true,
		'encrypted-media' => true,
		'fullscreen' => true,
		'gyroscope' => true,
		'picture-in-picture' => true,
		'screen-wake-lock' => true,
		'web-share' => true,
	);

	/**
	 * Sandbox values for iframes.
	 */
	protected static $_iframe_sandbox = array(
		'allow-downloads' => true,
		'allow-forms' => true,
		'allow-modals' => true,
		'allow-orientation-lock' => true,
		'allow-pointer-lock' => true,
		'allow-popups' => true,
		'allow-presentation' => true,
		'allow-same-origin' => true,
		'allow-scripts' => true,
		'allow-top-navigation' => true,

	);

	/**
	 * List of tags where data-* attributes are allowed.
	 */
	protected static $_data_allowed = array(
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p',
		'a', 'span', 'img', 'picture', 'b', 'i', 'strong', 'em', 'u', 's', 'sub', 'sup',
		'header', 'footer', 'nav', 'main', 'section', 'article', 'aside', 'details', 'summary',
		'ul', 'ol', 'li', 'mark', 'wbr', 'figure', 'figcaption', 'caption',
		'table', 'thead', 'tbody', 'tr', 'th', 'td', 'ins', 'del',
		'iframe', 'video', 'audio', 'source', 'track', 'blockquote', 'code',
	);

	/**
	 * Prepend a pre-processing filter.
	 *
	 * @param callable $callback
	 * @return void
	 */
	public static function prependPreFilter(callable $callback): void
	{
		array_unshift(self::$_preproc, $callback);
	}

	/**
	 * Append a pre-processing filter.
	 *
	 * @param callable $callback
	 * @return void
	 */
	public static function appendPreFilter(callable $callback): void
	{
		self::$_preproc[] = $callback;
	}

	/**
	 * Prepend a post-processing filter.
	 *
	 * @param callable $callback
	 * @return void
	 */
	public static function prependPostFilter(callable $callback): void
	{
		array_unshift(self::$_postproc, $callback);
	}

	/**
	 * Append a post-processing filter.
	 *
	 * @param callable $callback
	 * @return void
	 */
	public static function appendPostFilter(callable $callback): void
	{
		self::$_postproc[] = $callback;
	}

	/**
	 * Filter HTML content to block XSS attacks.
	 *
	 * @param string $input
	 * @param array|bool $allow_classes (optional)
	 * @param bool $allow_editor_components (optional)
	 * @param bool $allow_widgets (optional)
	 * @return string
	 */
	public static function clean(string $input, $allow_classes = false, bool $allow_editor_components = true, bool $allow_widgets = false): string
	{
		if ($allow_classes === true)
		{
			$allowed_classes = null;
		}
		else
		{
			if (is_array($allow_classes))
			{
				$allowed_classes = array_values($allow_classes);
			}
			else
			{
				$allowed_classes = Config::get('mediafilter.classes') ?: array();
			}

			if ($allow_widgets)
			{
				$allowed_classes[] = 'zbxe_widget_output';
			}
		}

		$purifier = self::getHTMLPurifier($allowed_classes);

		foreach (self::$_preproc as $callback)
		{
			$input = $callback($input, $purifier, $allow_editor_components, $allow_widgets);
		}

		$input = self::_preprocess($input, $purifier, $allow_editor_components, $allow_widgets);
		$output = $purifier->purify($input);
		$output = self::_postprocess($output, $purifier, $allow_editor_components, $allow_widgets);

		foreach (self::$_postproc as $callback)
		{
			$output = $callback($output, $purifier, $allow_editor_components, $allow_widgets);
		}

		return $output;
	}

	/**
	 * Convert relative URLs to absolute URLs in HTML content.
	 *
	 * This is useful when sending content outside of the website,
	 * such as e-mail and RSS, where relative URLs might not mean the same.
	 *
	 * This method also removes attributes that don't mean anything
	 * when sent outside of the website, such as editor component names.
	 *
	 * This method DOES NOT check HTML content for XSS or other attacks.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function fixRelativeUrls(string $content): string
	{
		$patterns = [
			'!\b(?i:src)=(["\']?)(?:\./|' . preg_quote(\RX_BASEURL, '!') . '|)files/!',
			'!\b(?:data-file-srl|editor_component|widget|id)="[^"]*"\s?!',
			'!\b(?:class="zbxe_widget_output")\s?!',
		];
		$replacements = [
			'src=$1' . URL::getCurrentDomainURL(\RX_BASEURL) . 'files/',
			'',
			'',
		];
		return preg_replace_callback('/<(img|video|audio|source)\b([^>]+)>/i', function($match) use($patterns, $replacements) {
			return preg_replace($patterns, $replacements, $match[0]);
		}, $content);
	}

	/**
	 * Get an instance of HTMLPurifier.
	 *
	 * @param array|null $allowed_classes (optional)
	 * @return \HTMLPurifier
	 */
	public static function getHTMLPurifier(?array $allowed_classes = null): \HTMLPurifier
	{
		// Keep separate instances for different sets of allowed classes.
		if ($allowed_classes !== null)
		{
			$allowed_classes = array_unique($allowed_classes);
			sort($allowed_classes);
		}
		$key = sha1(serialize($allowed_classes));

		// Create an instance with reasonable defaults.
		if (!isset(self::$_instances[$key]))
		{
			// Get the default configuration.
			$config = \HTMLPurifier_Config::createDefault();

			// Customize the default configuration.
			$config->set('Attr.AllowedClasses', $allowed_classes);
			$config->set('Attr.AllowedFrameTargets', array('_blank', '_self'));
			$config->set('Attr.DefaultImageAlt', '');
			$config->set('Attr.EnableID', true);
			$config->set('Attr.IDPrefix', 'user_content_');
			$config->set('AutoFormat.AutoParagraph', false);
			$config->set('AutoFormat.DisplayLinkURI', false);
			$config->set('AutoFormat.Linkify', false);
			$config->set('Core.Encoding', 'UTF-8');
			$config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
			$config->set('HTML.FlashAllowFullScreen', true);
			$config->set('HTML.Nofollow', config('security.nofollow') ? true : false);
			$config->set('HTML.MaxImgLength', null);
			$config->set('CSS.MaxImgLength', null);
			$config->set('CSS.Proprietary', true);
			$config->set('Output.FlashCompat', true);
			$config->set('Output.Newline', "\n");
			$config->set('URI.MakeAbsolute', false);

			// Allow embedding of external multimedia content.
			$config->set('HTML.SafeEmbed', true);
			$config->set('HTML.SafeIframe', true);
			$config->set('HTML.SafeObject', true);
			$config->set('URI.SafeIframeRegexp', MediaFilter::getWhitelistRegex());

			// Set the serializer path.
			$config->set('Cache.SerializerPath', \RX_BASEDIR . 'files/cache/htmlpurifier');
			Storage::createDirectory(\RX_BASEDIR . 'files/cache/htmlpurifier');

			// Support HTML5 and CSS3.
			self::_supportHTML5($config);
			self::_supportCSS3($config);

			// Cache our instance of HTMLPurifier.
			self::$_instances[$key] = new \HTMLPurifier($config);
		}

		// Return the cached instance.
		return self::$_instances[$key];
	}

	/**
	 * Patch HTMLPurifier to support some HTML5 tags and attributes.
	 *
	 * These changes are based on https://github.com/xemlock/htmlpurifier-html5
	 * but modified to support even more tags and attributes.
	 *
	 * @param \HTMLPurifier_Config $config
	 * @return void
	 */
	protected static function _supportHTML5(\HTMLPurifier_Config $config): void
	{
		// Get the HTML definition.
		$def = $config->getHTMLDefinition(true);

		// Add various block-level tags.
		$def->addElement('header', 'Block', 'Flow', 'Common');
		$def->addElement('footer', 'Block', 'Flow', 'Common');
		$def->addElement('nav', 'Block', 'Flow', 'Common');
		$def->addElement('main', 'Block', 'Flow', 'Common');
		$def->addElement('section', 'Block', 'Flow', 'Common');
		$def->addElement('article', 'Block', 'Flow', 'Common');
		$def->addElement('aside', 'Block', 'Flow', 'Common');
		$def->addElement('details', 'Block', 'Flow', 'Common');
		$def->addElement('summary', 'Block', 'Flow', 'Common');

		// Add various inline tags.
		$def->addElement('s', 'Inline', 'Inline', 'Common');
		$def->addElement('sub', 'Inline', 'Inline', 'Common');
		$def->addElement('sup', 'Inline', 'Inline', 'Common');
		$def->addElement('mark', 'Inline', 'Inline', 'Common');
		$def->addElement('wbr', 'Inline', 'Empty', 'Core');

		// Support figures.
		$def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
		$def->addElement('figcaption', 'Inline', 'Flow', 'Common');

		// Support insertions and deletions.
		$def->addElement('ins', 'Block', 'Flow', 'Common', array('cite' => 'URI', 'datetime' => 'Text'));
		$def->addElement('del', 'Block', 'Flow', 'Common', array('cite' => 'URI', 'datetime' => 'Text'));

		// Support the <time> tag.
		$time = $def->addElement('time', 'Inline', 'Inline', 'Common', array('datetime' => 'Text', 'pubdate' => 'Bool'));
		$time->excludes = array('time' => true);

		// Suppport <audio> and <video> tags.
		$def->addElement('audio', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
			'src' => 'URI',
			'type' => 'Text',
			'preload' => 'Enum#auto,metadata,none',
			'controls' => 'Bool',
			'muted' => 'Bool',
			'autoplay' => 'Bool',
			'playsinline' => 'Bool',
			'loop' => 'Bool',
		));
		$def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
			'src' => 'URI',
			'type' => 'Text',
			'width' => 'Length',
			'height' => 'Length',
			'poster' => 'URI',
			'preload' => 'Enum#auto,metadata,none',
			'controls' => 'Bool',
			'muted' => 'Bool',
			'autoplay' => 'Bool',
			'playsinline' => 'Bool',
			'loop' => 'Bool',
		));
		$def->addElement('source', 'Block', 'Empty', 'Common', array(
			'src' => 'URI',
			'media' => 'Text',
			'type' => 'Text',
		));
		$def->addElement('track', 'Block', 'Empty', 'Common', array(
			'src' => 'URI',
			'srclang' => 'Text',
			'label' => 'Text',
			'kind' => 'Enum#captions,chapters,descriptions,metadata,subtitles',
			'default' => 'Bool',
		));

		// Support additional properties.
		$def->addAttribute('details', 'open', 'Bool');
		$def->addAttribute('i', 'aria-hidden', 'Text');
		$def->addAttribute('img', 'srcset', 'Text');
		$def->addAttribute('iframe', 'allow', 'Text');
		$def->addAttribute('iframe', 'allowfullscreen', 'Bool');
		$def->addAttribute('iframe', 'loading', 'Enum#eager,lazy');
		$def->addAttribute('iframe', 'referrerpolicy', 'Enum#no-referrer,no-referrer-when-downgrade,origin,origin-when-cross-origin,same-origin,strict-origin,strict-origin-when-cross-origin,unsafe-url');
		$def->addAttribute('iframe', 'sandbox', 'Text');

		// Support contenteditable="false" (#1710)
		$def->addAttribute('div', 'contenteditable', 'Enum#false');

		// Support editor components and widgets.
		$def->addAttribute('img', 'data-file-srl', 'Number');
		$def->addAttribute('video', 'data-file-srl', 'Number');
		$def->addAttribute('audio', 'data-file-srl', 'Number');
		$def->addAttribute('img', 'editor_component', 'Text');
		$def->addAttribute('div', 'editor_component', 'Text');
		$def->addAttribute('img', 'rx_encoded_properties', 'Text');
		$def->addAttribute('div', 'rx_encoded_properties', 'Text');

		// Support encoded data-* attributes for some tags.
		foreach (self::$_data_allowed as $tag)
		{
			$def->addAttribute($tag, 'rx_encoded_datas', 'Text');
		}
	}

	/**
	 * Patch HTMLPurifier to support more CSS2 and some CSS3 properties.
	 *
	 * These changes are based on:
	 *   - https://github.com/mattiaswelander/htmlpurifier
	 *
	 * @param \HTMLPurifier_Config $config
	 * @return void
	 */
	protected static function _supportCSS3(\HTMLPurifier_Config $config): void
	{
		// Initialize $info.
		$info = array();

		// min-width, max-width, etc.
		$info['min-width'] = $info['max-width'] = $info['min-height'] =
		$info['max-height'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Length(0),
			new \HTMLPurifier_AttrDef_Enum(array('none', 'initial', 'inherit')),
		));

		// border-radius, etc.
		$border_radius = $info['border-top-left-radius'] =
		$info['border-top-right-radius'] = $info['border-bottom-left-radius'] =
		$info['border-bottom-right-radius'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Length(0),
			new \HTMLPurifier_AttrDef_CSS_Percentage(true),
			new \HTMLPurifier_AttrDef_Enum(array('initial', 'inherit')),
		));
		$info['border-radius'] = new \HTMLPurifier_AttrDef_CSS_Multiple($border_radius);

		// word-break word-wrap, etc.
		$info['word-break'] = new \HTMLPurifier_AttrDef_Enum(array(
			'normal', 'break-all', 'keep-all', 'initial', 'inherit',
		));
		$info['word-wrap'] = new \HTMLPurifier_AttrDef_Enum(array(
			'normal', 'break-word', 'initial', 'inherit',
		));
		$info['text-overflow'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_Enum(array('clip', 'ellipsis', 'initial', 'inherit')),
		));

		// text-shadow
		$info['text-shadow'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Multiple(new \HTMLPurifier_AttrDef_CSS_Composite(array(
				new \HTMLPurifier_AttrDef_CSS_Length(),
				new \HTMLPurifier_AttrDef_CSS_Color(),
			))),
			new \HTMLPurifier_AttrDef_Enum(array('none', 'initial', 'inherit')),
		));

		// box-shadow and box-sizing
		$info['box-shadow'] = new \HTMLPurifier_AttrDef_CSS_Multiple(new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Length(),
			new \HTMLPurifier_AttrDef_CSS_Percentage(),
			new \HTMLPurifier_AttrDef_CSS_Color(),
			new \HTMLPurifier_AttrDef_Enum(array('none', 'inset', 'initial', 'inherit')),
		)));
		$info['box-sizing'] = new \HTMLPurifier_AttrDef_Enum(array(
			'content-box', 'border-box', 'initial', 'inherit',
		));

		// outline
		$info['outline-color'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Color(),
			new \HTMLPurifier_AttrDef_Enum(array('invert', 'initial', 'inherit')),
		));
		$info['outline-offset'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Length(),
			new \HTMLPurifier_AttrDef_Enum(array('initial', 'inherit')),
		));
		$info['outline-style'] = new \HTMLPurifier_AttrDef_Enum(array(
			'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset', 'initial', 'inherit',
		));
		$info['outline-width'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Length(),
			new \HTMLPurifier_AttrDef_Enum(array('medium', 'thin', 'thick', 'initial', 'inherit')),
		));
		$info['outline'] = new \HTMLPurifier_AttrDef_CSS_Multiple(new \HTMLPurifier_AttrDef_CSS_Composite(array(
			$info['outline-color'], $info['outline-style'], $info['outline-width'],
			new \HTMLPurifier_AttrDef_Enum(array('initial', 'inherit')),
		)));

		// flexbox
		$info['display'] = new \HTMLPurifier_AttrDef_Enum(array(
			'block', 'flex', '-webkit-flex', 'inline', 'inline-block', 'inline-flex', '-webkit-inline-flex', 'inline-table',
			'list-item', 'run-in', 'compact', 'marker', 'table', 'table-row-group', 'table-header-group', 'table-footer-group',
			'table-row', 'table-column-group', 'table-column', 'table-cell', 'table-caption',
			'none', 'initial', 'inherit',
		));
		$info['order'] = new \HTMLPurifier_AttrDef_CSS_Number();
		$info['align-content'] = $info['justify-content'] = new \HTMLPurifier_AttrDef_Enum(array(
			'stretch', 'center', 'flex-start', 'flex-end', 'space-between', 'space-around', 'initial', 'inherit',
		));
		$info['align-items'] = $info['align-self'] = new \HTMLPurifier_AttrDef_Enum(array(
			'stretch', 'center', 'flex-start', 'flex-end', 'baseline', 'initial', 'inherit',
		));
		$info['flex-basis'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Length(),
			new \HTMLPurifier_AttrDef_CSS_Percentage(),
			new \HTMLPurifier_AttrDef_Enum(array('auto', 'initial', 'inherit')),
		));
		$info['flex-direction'] = new \HTMLPurifier_AttrDef_Enum(array(
			'row', 'row-reverse', 'column', 'column-reverse', 'initial', 'inherit',
		));
		$info['flex-wrap'] = new \HTMLPurifier_AttrDef_Enum(array(
			'nowrap', 'wrap', 'wrap-reverse', 'initial', 'inherit',
		));
		$info['flex-flow'] = new \HTMLPurifier_AttrDef_CSS_Multiple(new \HTMLPurifier_AttrDef_CSS_Composite(array(
			$info['flex-direction'], $info['flex-wrap'],
		)));
		$info['flex-grow'] = new \HTMLPurifier_AttrDef_CSS_Number();
		$info['flex-shrink'] = new \HTMLPurifier_AttrDef_CSS_Number();
		$info['flex'] = new \HTMLPurifier_AttrDef_CSS_Multiple(new \HTMLPurifier_AttrDef_CSS_Composite(array(
			$info['flex-grow'], $info['flex-shrink'], $info['flex-basis'],
			new \HTMLPurifier_AttrDef_Enum(array('auto', 'none', 'initial', 'inherit')),
		)));

		// misc
		$info['caption-side'] = new \HTMLPurifier_AttrDef_Enum(array(
			'top', 'bottom', 'initial', 'inherit',
		));
		$info['empty-cells'] = new \HTMLPurifier_AttrDef_Enum(array(
			'show', 'hide', 'initial', 'inherit',
		));
		$info['hanging-punctuation'] = new \HTMLPurifier_AttrDef_Enum(array(
			'none', 'first', 'last', 'allow-end', 'force-end', 'initial', 'inherit',
		));
		$info['overflow'] = $info['overflow-x'] = $info['overflow-y'] = new \HTMLPurifier_AttrDef_Enum(array(
			'visible', 'hidden', 'scroll', 'auto', 'initial', 'inherit',
		));
		$info['resize'] = new \HTMLPurifier_AttrDef_Enum(array(
			'none', 'both', 'horizontal', 'vertical', 'initial', 'inherit',
		));
		$info['aspect-ratio'] = new \HTMLPurifier_AttrDef_CSS_Composite(array(
			new \HTMLPurifier_AttrDef_CSS_Number(),
			new \HTMLPurifier_AttrDef_Enum(array('2/1', '16/9', '4/3', '1/1', '3/4', '9/16', '1/2')),
			new \HTMLPurifier_AttrDef_Enum(array('auto', 'initial', 'inherit')),
		));
		$info['object-fit'] = new \HTMLPurifier_AttrDef_Enum(array(
			'contain', 'cover', 'fill', 'none', 'scale-down', 'initial', 'inherit',
		));

		// Wrap all new properties with a decorator that handles !important.
		$allow_important = $config->get('CSS.AllowImportant');
		$css_definition = $config->getCSSDefinition();
		foreach ($info as $key => $val)
		{
			$css_definition->info[$key] = new \HTMLPurifier_AttrDef_CSS_ImportantDecorator($val, $allow_important);
		}
	}

	/**
	 * Rhymix-specific preprocessing method.
	 *
	 * @param string $content
	 * @param \HTMLPurifier $purifier
	 * @param bool $allow_editor_components (optional)
	 * @param bool $allow_widgets (optional)
	 * @return string
	 */
	protected static function _preprocess(string $content, \HTMLPurifier $purifier, bool $allow_editor_components = true, bool $allow_widgets = false): string
	{
		// Encode widget and editor component properties so that they are not removed by HTMLPurifier.
		if ($allow_editor_components || $allow_widgets)
		{
			$content = self::_encodeWidgetsAndEditorComponents($content, $allow_editor_components, $allow_widgets);
		}

		// Encode data-* attributes.
		$content = self::_encodeDataAttributes($content);
		return $content;
	}

	/**
	 * Rhymix-specific postprocessing method.
	 *
	 * @param string $content
	 * @param \HTMLPurifier $purifier
	 * @param bool $allow_editor_components (optional)
	 * @param bool $allow_widgets (optional)
	 * @return string
	 */
	protected static function _postprocess(string $content, \HTMLPurifier $purifier, bool $allow_editor_components = true, bool $allow_widgets = false): string
	{
		// Define acts to allow and deny.
		$allow_acts = array('procFileDownload');
		$deny_acts = array('dispMemberLogout', 'dispLayoutPreview');

		// Remove tags not supported in Rhymix. Some of these may also have been removed by HTMLPurifier.
		$content = preg_replace_callback('!</?(?:html|body|head|title|meta|base|link|script|style|applet)\b[^>]*>!i', function($matches) {
			return htmlspecialchars($matches[0], ENT_QUOTES, 'UTF-8');
		}, $content);

		// Remove "allow" attributes that should not be allowed.
		$content = preg_replace_callback('!(?<=\s)allow="([^"<>]*?)"!i', function($matches) {
			$result = [];
			foreach (array_map('trim', explode(';', $matches[1])) as $value)
			{
				if (isset(self::$_iframe_permissions[$value]))
				{
					$result[] = $value;
				}
			}
			return 'allow="' . implode('; ', $result) . '"';
		}, $content);

		// Remove "sandbox" attributes that should not be allowed.
		$content = preg_replace_callback('!(?<=\s)sandbox="([^"<>]*?)"!i', function($matches) {
			$result = [];
			foreach (array_map('trim', preg_split('/\s+/', $matches[1])) as $value)
			{
				if (isset(self::$_iframe_sandbox[$value]))
				{
					$result[] = $value;
				}
			}
			return 'sandbox="' . implode(' ', $result) . '"';
		}, $content);

		// Remove object and embed URLs that are not allowed.
		$whitelist = MediaFilter::getWhitelistRegex();
		$content = preg_replace_callback('!<(object|embed|param|audio|video|source|track)([^>]+)>!i', function($matches) use($whitelist) {
			return preg_replace_callback('!([a-zA-Z0-9_-]+)="([^"]+)"!', function($attr) use($whitelist) {
				if (in_array($attr[1], array('data', 'src', 'href', 'url', 'movie', 'source')))
				{
					$url = trim(htmlspecialchars_decode($attr[2]));
					if (preg_match('!^(https?:)?//!i', $url) && !preg_match($whitelist, $url))
					{
						return $attr[1] . '=""';
					}
				}
				return $attr[0];
			}, $matches[0]);
		}, $content);

		// Remove link URLs that may be CSRF attempts.
		$content = preg_replace_callback('!\b(src|href|data|value)="([^"]+)"!i', function($matches) use($allow_acts, $deny_acts) {
			$url = preg_replace('!\s+!', '', htmlspecialchars_decode(rawurldecode($matches[2])));
			if (preg_match('!\bact=((disp|proc)[^&]+)!i', $url, $urlmatches))
			{
				$act = $urlmatches[1];
				if (!in_array($act, $allow_acts) && (in_array($act, $deny_acts) || $urlmatches[2] === 'proc'))
				{
					return $matches[1] . '=""';
				}
			}
			return $matches[0];
		}, $content);

		// Restore widget and editor component properties.
		$content = self::_decodeWidgetsAndEditorComponents($content, $allow_editor_components, $allow_widgets);

		// Restore data-* attributes.
		$content = self::_decodeDataAttributes($content);
		return $content;
	}

	/**
	 * Encode widgets and editor components before processing.
	 *
	 * @param string $content
	 * @param bool $allow_editor_components (optional)
	 * @param bool $allow_widgets (optional)
	 * @return string
	 */
	protected static function _encodeWidgetsAndEditorComponents(string $content, bool $allow_editor_components = true, bool $allow_widgets = false): string
	{
		$regexp = array();
		if ($allow_editor_components)
		{
			$regexp[] = 'editor_component="[^"]+"';
		}
		if ($allow_widgets)
		{
			$regexp[] = 'class="zbxe_widget_output"';
		}
		if (!count($regexp))
		{
			return $content;
		}

		return (string)preg_replace_callback('!<(div|img)([^>]*)(' . implode('|', $regexp) . ')([^>]*)>!i', function($match) {
			$tag = strtolower($match[1]);
			$attrs = array();
			$html = preg_replace_callback('!([a-zA-Z0-9_-]+)="([^"]+)"!', function($attr) use($tag, &$attrs) {
				$attrkey = strtolower($attr[1]);
				if ($tag === 'img' && preg_match('/^(?:width|height|src|alt|ismap|usemap)$/', $attrkey))
				{
					return $attr[0];
				}
				if (preg_match('/^(?:on|data-|(?:accesskey|class|contextmenu|contenteditable|dir|draggable|dropzone|editor_component|hidden|id|lang|name|style|tabindex|title|rx_encoded_properties)$)/i', $attrkey))
				{
					return $attr[0];
				}
				$attrval = trim(utf8_normalize_spaces(utf8_clean(html_entity_decode($attr[2]))));
				if (preg_match('/^javascript:/i', preg_replace('/\s+/', '', $attrval)))
				{
					return '';
				}
				$attrs[$attrkey] = $attrval;
				return '';
			}, $match[0]);
			if ($tag === 'img' && !preg_match('/\ssrc="/', $html))
			{
				$html = substr($html, 0, 4) . ' src=""' . substr($html, 4);
			}
			$encoded_properties = base64_encode(json_encode($attrs));
			$encoded_properties = $encoded_properties . ':' . Security::createSignature($encoded_properties);
			return substr($html, 0, 4) . ' rx_encoded_properties="' . $encoded_properties . '"' . substr($html, 4);
		}, $content);
	}

	/**
	 * Decode widgets and editor components after processing.
	 *
	 * @param string $content
	 * @param bool $allow_editor_components (optional)
	 * @param bool $allow_widgets (optional)
	 * @return string
	 */
	protected static function _decodeWidgetsAndEditorComponents(string $content, bool $allow_editor_components = true, bool $allow_widgets = false): string
	{
		if (!$allow_editor_components)
		{
			$content = preg_replace('!(<(?:div|img)[^>]*)\s(editor_component="(?:[^"]+)")!i', '$1', $content);
		}
		if (!$allow_widgets)
		{
			$content = preg_replace('!(<(?:div|img)[^>]*)\s(widget="(?:[^"]+)")!i', '$1blocked-$2', $content);
		}
		if (!$allow_editor_components && !$allow_widgets)
		{
			return $content;
		}

		return (string)preg_replace_callback('!<(div|img)([^>]*)(\srx_encoded_properties="([^"]+)")!i', function($match) {
			$attrs = array();
			list($encoded_properties, $signature) = explode(':', $match[4]);
			if (!Security::verifySignature($encoded_properties, $signature))
			{
				return str_replace($match[3], '', $match[0]);
			}
			$decoded_properties = json_decode(base64_decode($encoded_properties));
			if (!$decoded_properties)
			{
				return str_replace($match[3], '', $match[0]);
			}
			foreach ($decoded_properties as $key => $val)
			{
				$attrs[] = $key . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
			}
			return str_replace($match[3], ' ' . implode(' ', $attrs), $match[0]);
		}, $content);
	}

	/**
	 * Encode data-* attributes so that they will survive being passed through HTMLPurifier.
	 *
	 * @param string $content
	 * @return string
	 */
	protected static function _encodeDataAttributes(string $content): string
	{
		$tags = implode('|', self::$_data_allowed);
		return (string)preg_replace_callback('!<(' . $tags . ')\s([^>]+)>!i', function($match) {
			$attrs = array();
			$html = preg_replace_callback('!\s(data-[a-zA-Z0-9_-]+)="([^"]*)"!', function($attr) use(&$attrs) {
				$attrkey = strtolower($attr[1]);
				$attrval = trim(utf8_normalize_spaces(utf8_clean(html_entity_decode($attr[2]))));
				if (preg_match('/^(data-file-srl)$/', $attrkey))
				{
					return $attr[0];
				}
				if (preg_match('/^javascript:/i', preg_replace('/\s+/', '', $attrval)))
				{
					return '';
				}
				$attrs[$attrkey] = $attrval;
				return '';
			}, $match[0]);
			$encoded_datas = base64_encode(json_encode($attrs));
			$encoded_datas = $encoded_datas . ':' . Security::createSignature($encoded_datas);
			return rtrim($html, ' />') . ' rx_encoded_datas="' . $encoded_datas . '"' . (preg_match('!/>$!', $html) ? ' />' : '>');
		}, $content);
	}

	/**
	 * Decode data-* attributes after processing.
	 *
	 * @param string $content
	 * @param bool $allow_editor_components (optional)
	 * @param bool $allow_widgets (optional)
	 * @return string
	 */
	protected static function _decodeDataAttributes(string $content): string
	{
		$tags = implode('|', self::$_data_allowed);
		return (string)preg_replace_callback('!<(' . $tags . ')([^>]*)(\srx_encoded_datas="([^"]+)")!i', function($match) {
			$attrs = array();
			list($encoded_datas, $signature) = explode(':', $match[4]);
			if (!Security::verifySignature($encoded_datas, $signature))
			{
				return str_replace($match[3], '', $match[0]);
			}
			$encoded_datas = json_decode(base64_decode($encoded_datas));
			if (!$encoded_datas)
			{
				return str_replace($match[3], '', $match[0]);
			}
			foreach ($encoded_datas as $key => $val)
			{
				$attrs[] = $key . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
			}
			return str_replace($match[3], ' ' . implode(' ', $attrs), $match[0]);
		}, $content);
	}
}
