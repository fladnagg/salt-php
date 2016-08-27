<?php
/**
 * FormHelper class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\view
 */
namespace salt;

/**
 * HTML Form tag generation
 */
class FormHelper {

	/**
	 * Tag type : Open and close tag
	 */
	const TAG_FULL = 0;
	/**
	 * Tag type : Open tag only
	 */
	const TAG_OPEN = 1;
	/**
	 * Tag type : Close tag only
	 */
	const TAG_CLOSE = 2;

	/** Key for store value before formatting */
	const PARAM_RAW_VALUE = '_saltRawValue';

	/** Key for adding datepicker JS */
	const PARAM_DATEPICKER = '_saltDatePicker';

	/** @var string pattern for naming tags. Character '?' is replaced by last tag name */
	private static $nameContainer = NULL;
	/**
	 * @var mixed[] GET/POST values
	 * @content if nameContainer is used, contains GET/POST values that are resolved early for retrieve form values. */
	private static $values = NULL;
	/** @var string G or P character, for GET/POST */
	private static $method = NULL;
	/** @var boolean TRUE if we can use JQueryUI decoration on form tags */
	private static $withJQueryUI = TRUE;

	/**
	 * Enable or disable usage of JQueryUI
	 * @param boolean $value TRUE (default) for enable JQueryUI, false for disable
	 */
	public static function withJQueryUI($value = TRUE) {
		self::$withJQueryUI = $value;
	}

	/**
	 * Create a form with GET method.
	 * @param string $action Page to send form, NULL for reuse current page
	 * @param mixed[] $params List of parameters to add in hidden input. Can be name => value or just name for reusing value in current query.<br/>
	 * If name is "*", all parameters in current query are reused.<br/>
	 * Example : <b>array('a', 'b'=>2, 'c'=>3)</b> with query <b>a=0&b=1&d=4</b> will produce hidden inputs : <br/>
	 * 		* a with value 0 (reuse query value)<br/>
	 * 		* b with value 2 (override query value)<br/>
	 * 		* c with value 3 (new parameter)<br/>
	 * 		d is not specified, so it is not included.
	 * @param mixed[] $others Other attributes to add in form tag
	 * @return string HTML form tag
	 */
	public static function get($action = NULL, $params = array(), $others = array()) {
		$Input = In::getInstance();
		self::$method = 'G';

		if ($params === NULL) {
			$params = array();
		}

		$query = '';
		if ($action === NULL) {
			$action = $Input->S->RAW->REQUEST_URI;
		}
		$datas = explode('?', $action, 2);
		$action = first($datas);
		if (count($datas)>1) {
			$query = last($datas);
		}

		if ($others === NULL) {
			$others = array();
		}

		$others['method'] = 'get';
		$others['action'] = $action;

		$newParams = self::parseParams($query, $params);

		$hiddens = array();
		foreach($newParams as $k => $v) {
			if (is_array($v)) {
				foreach($v as $kk => $vv) {
					$hiddens[] = self::input($k.'['.$kk.']', 'hidden', $vv);
				}
			} else {
				$hiddens[] = self::input($k, 'hidden', $v);
			}
		}

		$result = self::HTMLtag('form', $others, NULL, self::TAG_OPEN);
		if (count($hiddens)>0) {
			$result.="\n<p style=\"display:none\">\n\t".implode("\n\t", $hiddens)."\n</p>";
		}
		return $result;
	}

	/**
	 * Create a form with POST method.
	 * @param string $action Page to send form, NULL for reuse current page
	 * @param mixed[] $params List of parameters to add in method URL. Can be name => value or just name for reusing value in current query.<br/>
	 * If name is "*", all parameters in current query are reused.<br/>
	 * Example : <b>array('a', 'b'=>2, 'c'=>3)</b> with query <b>a=0&b=1&d=4</b> will produce method="...?<b>a=0&b=2&c=3</b>"<br/>
	 * 		* a with value 0 (reuse query value)<br/>
	 * 		* b with value 2 (override query value)<br/>
	 * 		* c with value 3 (new parameter)<br/>
	 * 		d is not specified, so it is not included.<br/>
	 * @param mixed[] $others Other attributes to add in form tag
	 * @return string HTML form tag
	 */
	public static function post($action = NULL, $params = array(), $others = array()) {
		$Input = In::getInstance();
		self::$method = 'P';

		if ($params === NULL) {
			$params = array();
		}

		if ($others === NULL) {
			$others = array();
		}

		$others['method'] = 'post';

		$hiddens = array();

		$query = '';
		if ($action === NULL) {
			$action = $Input->S->RAW->REQUEST_URI;
		}
		$datas = explode('?', $action, 2);
		$action = first($datas);
		if (count($datas)>1) {
			$query = last($datas);
		}

		$newParams = self::parseParams($query, $params);
		$action = $action.'?'.http_build_query($newParams);

		$others['action'] = $action;

		return self::HTMLtag('form', $others, NULL, self::TAG_OPEN);
	}

	/**
	 * Compute new parameters list for form tag
	 * @param string $query Query of URL in QUERY_STRING format : var1=value1&var2=value2...
	 * @param string[] $params List of varX to keep or redefine. '*' for reuse all request parameters, key=>NULL for remove key parameter
	 * @return string[] key=>value
	 */
	private static function parseParams($query, array $params) {
		$newParams = array();
		parse_str($query, $paramsRequest); // $paramsRequest is an output var
		foreach($params as $k => $v) {
			if (is_numeric($k)) {
				if ($v === '*') {
					foreach($paramsRequest as $kk => $vv) {
						$newParams[$kk] = $vv;
					}
				} else if (array_key_exists($v, $paramsRequest)) {
					$newParams[$v] = $paramsRequest[$v];
				}
			} else if ($v === NULL) {
				unset($newParams[$k]);
			} else {
				$newParams[$k] = $v;
			}
		}
		return $newParams;
	}

	/**
	 * Return a HTML form close tag
	 * @return string HTML end form tag
	 */
	public static function end() {
		self::$method = NULL;
		return self::HTMLtag('form', array(), NULL, self::TAG_CLOSE);
	}

	/**
	 * Set a name template for all names generated by next tags
	 * @param string ... $name all names containers : (a, b, c) will give a[b][c][component_name]
	 */
	public static function withNameContainer($name) {
		if (self::$method === NULL) {
			return;
		}

		$Input = In::getInstance();

		$args = func_get_args();

		$first = array_shift($args);
		if ($Input->{self::$method}->ISSET->$first) {
			$values = $Input->{self::$method}->RAW->$first;
			foreach($args as $key) {
				if (array_key_exists($key, $values)) {
					$values = $values[$key];
				}
			}
			if (is_array($values)) {
				self::$values = $values;
			}
		}
		$pattern = $first;
		if (count($args) > 0) {
			$pattern.='['.implode('][', $args).']';
		}
		$pattern.='[?]';
		self::$nameContainer = $Input->HTML($pattern);
	}

	/**
	 * Stopping use a name template
	 */
	public static function withoutNameContainer() {
		self::$nameContainer = NULL;
		self::$values = NULL;
	}

	/**
	 * Get real name to use, if nameContainer is defined
	 * @param string $name simple tag/field name
	 * @return string $name or $name in template if we use withNameContainer()
	 */
	public static function getName($name) {
		if ((self::$nameContainer !== NULL) && ($name !== NULL)) {
			$name = str_replace('?', $name, self::$nameContainer);
		}
		return $name;
	}

	/**
	 * Get a HTML tag
	 * @param string $tagName name of the HTML tag
	 * @param array $attributes attributes of the tag
	 * @param string $content content of tag : <b>Have to be an escaped HTML String</b>
	 * @param int $tagType type of tag : self::TAG_*
	 * @return string HTML tag
	 */
	public static function HTMLtag($tagName, $attributes, $content = NULL, $tagType = self::TAG_FULL) {
		$Input = In::getInstance();

		if ($tagType === self::TAG_CLOSE) {
			return '</'.$tagName.'>';
		}
		$result='<'.$tagName;
		foreach($attributes as $k=>$v) {
			if (($v !== NULL) && (strpos($k, '_') !== 0)) {
				$result.=' '.$Input->HTML($k).'="'.$Input->HTML($v).'"';
			}
		}
		if ($content === NULL) {
			if ($tagType === self::TAG_OPEN) {
				$result.='>';
			} else {
				$result.='/>';
			}
		} else {
			if ($tagType === self::TAG_OPEN) {
				$result.='>'.$content;
			} else {
				if (trim($content) === '') {
					$content='&nbsp;'; // XHTML validation is grumpy with empty tags (options, etc)
				}
				$result.='>'.$content.'</'.$tagName.'>';
			}
		}
		return $result;
	}

	/**
	 * Get value from previously submitted form
	 * @param string $name name of input (simple name, not the return of self::getName() !)
	 * @return mixed value in GET/POST for this name, with nameContainer support
	 * @throws SaltException if called outside a form
	 */
	public static function getValue($name) {
		if (self::$values !== NULL) {
			if (array_key_exists($name, self::$values)) {
				return self::$values[$name];
			}
		} else {
			$Input = In::getInstance();
			if (self::$method === NULL) {
				throw new SaltException('Please call FormHelper::get() or FormHelper::post() before');
			}
			if ($Input->{self::$method}->ISSET->$name) {
				return $Input->{self::$method}->RAW->$name;
			}
		}
		return NULL;
	}

	/**
	 * Get a HTML tag for modify a field
	 *
	 * For a HTML tag of an existing DAO object, use $dao->FORM->$field instead
	 *
	 * @param Field $field the field
	 * @param string $name name of the HTML tag, can be NULL for use the $field->name
	 * @param mixed $value Have to be in DAO format (timestamp for date, TRUE/FALSE for boolean, etc...)
	 * @param string[] $classes CSS classes. Each element can contains multiple classes separated by space
	 * @param mixed[] $others others HTML attributes : key=>value
	 * @param ViewHelper $helper The helper to use for format the value
	 * @return string HTML tag for the field
	 */
	public static function field(Field $field, $name, $value, $classes = array(), $others = array(), ViewHelper $helper = NULL) {
		$Input = In::getInstance();

		if ($others === NULL) {
			$others = array();
		}

		$others = array_merge($field->displayOptions, $others); // others have priority

		if ($name !== NULL) { // direct call to field(), if we came from FORM, $name === NULL for don't overwrite $name
			$others['name'] = $name;
		}
		if (!array_key_exists('name', $others)) { // value can be NULL, array_key_exists required
			$others['name'] = $field->name;
		}
		if (!array_key_exists('format', $others)) { // value can be NULL, array_key_exists required
			$others['format'] = $field->displayFormat;
		}

		// type guess
		$type = NULL;
		if (isset($others['type'])) {
			$type = $others['type'];
			unset($others['type']);
		} else {
			if ($field->type === FieldType::BOOLEAN) {
				if ($field->nullable) {
					$type = 'select';
				} else {
					$type = 'checkbox';
				}
			}

			if (count($field->values) > 0) {
				$type = 'select';
			}
		}
		if ($type === NULL) {
			$type = 'text';
		}

		// transcode DAO value
		$value = $field->transcodeType($value);

		if ($helper !== NULL) {
			$others[self::PARAM_RAW_VALUE] = $value;
			if ($value !== NULL) {
				$others['value'] = $helper->text($helper->getObject(), $field, $value, $others['format'], array());
			}
			$value = NULL; // value in parameters have more priority than GET/POST
		}

		if (($type === 'text') && ($field->type === FieldType::DATE) && (self::$withJQueryUI)) {
			$others[self::PARAM_DATEPICKER] = TRUE;
		}

		$options = array();
		if (($type === 'select') || ($type === 'radio')) {
			// options construct
			if (count($field->values) > 0) {
				$options = $field->values;
			}
			if (isset($others['options'])) {
				$options = $others['options'];
			}
			if ((count($options) === 0) && ($field->type === FieldType::BOOLEAN)) {
				$options = array('1' => 'Oui', '0' => 'Non');
			}
			if ((count($options) > 0) && $field->nullable && !isset($options[''])) {
				$options = array('' => '') + $options;
			}
		}

		// remove internal keys which are not HTML attributes
		unset($others['options']);
		unset($others['format']);

		$result=NULL;
		switch($type) {
			case 'text' :	$result = self::input($name, 'text', $value, $classes, $others);
			break;
			case 'password' :	$result = self::input($name, 'password', $value, $classes, $others);
			break;
			case 'select' : $result = self::select($name, $options, $value, $classes, $others);
			break;
			case 'radio' : $result = self::radio($name, $options, $value, $classes, $others);
			break;
			case 'checkbox' : $result = self::input($name, 'checkbox', $value, $classes, $others);
			break;
			case 'textarea' : $result = self::textarea($name, $value, $classes, $others);
			break;
		}

		return $result;
	}

	/**
	 * Compute tag attributes
	 *
	 * @param string $name name of the tag
	 * @param string $value value of the tag
	 * @param string[] $classes array of CSS classes, each element can contain multiple classes separated by spaces
	 * @param mixed[] $attrs all others attributes : key=>value
	 * @return mixed[] attributes to use in tag : key=>value
	 */
	private static function commonTagAttributes($name, $value, $classes, $attrs) {
		if ($classes === NULL) {
			$classes = array();
		}

		if (!is_array($classes)) {
			$classes = array($classes);
		}

		if ($attrs === NULL) {
			$attrs = array();
		}

		$params = array();
		if (isset($name)) $params['name'] = $name;
		if (isset($value)) $params['value'] = $value;
		if (count($classes) > 0) $params['class'] = implode(' ', $classes);

		$attrs = array_merge($attrs, $params);
		// MERGE END

		$inputValue = NULL;
		if (isset($attrs['name'])) {
			$name = $attrs['name'];
		}
		$attrs['name'] = self::getName($name);

		if (($name !== NULL) && ($value === NULL)) {
			$inputValue = self::getValue($name);
			if ($inputValue !== NULL) {
				// no transcode because value from last submit, so in same format
				$attrs['value'] = $inputValue;
			}
		}

		return $attrs;
	}

	/**
	 * Return a textarea HTML tag
	 * @param string $name name of the tag
	 * @param string $value value of the tag
	 * @param string[] $classes CSS classes of the tag
	 * @param mixed[] $others all other attributes for the tag
	 * @return string HTML text tag
	 */
	public static function textarea($name, $value = NULL, $classes = array(), array $others = array()) {
		$Input = In::getInstance();

		$attrs = self::commonTagAttributes($name, $value, $classes, $others);

		$value = $attrs['value'];
		unset($attrs['value']);

		return self::HTMLtag('textarea', $attrs, $Input->HTML($value));
	}

	/**
	 * Return an input HTML tag
	 * @param string $name name of the tag
	 * @param string $type value of type attribute
	 * @param string $value value of the tag
	 * @param string[] $classes CSS classes of the tag
	 * @param mixed[] $others all other attributes for the tag
	 * @return string HTML text tag
	 */
	public static function input($name, $type = 'text', $value = NULL, $classes = array(), array $others = array()) {
		$Input = In::getInstance();

		$attrs = self::commonTagAttributes($name, $value, $classes, $others);

		if (!isset($attrs['type'])) {
			$attrs['type'] = $type;
		}

		if (($value === NULL) && array_key_exists(self::PARAM_RAW_VALUE, $attrs)) {
			$value = $attrs[self::PARAM_RAW_VALUE];
		}
		if (($value === NULL) && array_key_exists('value', $attrs)) {
			$value = $attrs['value'];
		}

		if (($value !== NULL) && ($attrs['type'] === 'checkbox')) {
			if (($value === TRUE) || ($value === 'on')) { // FIXME format value before ?
				$attrs['checked'] = 'checked';
			}
			unset($attrs['value']);
		}

		// allow value attrs in checkbox to having other value than 'on' when checked.
		// FIXME don't work well. Have to compare $value to $others['value'] for checked ?
		// $others['values'] is set to helper->text() in ::field()
		if (($attrs['type'] === 'checkbox') && isset($others['value'])) {
			$attrs['value'] = $others['value'];
		}

		$tag = self::HTMLtag('input', $attrs);

		if (isset($others[self::PARAM_DATEPICKER])) {
			// if we have a nameContainer, we have to escape '[' and ']' in name for find the input a[b][c]
			// [] in JS have to be double-escaped with \, so, we have to generate : a\\[b\\]\\[c\\]
			// for each \, we have to write 4 \ in preg_replace (2 for PHP parser, 2 for PCRE parser)
			// so, total is 8 \
			$tag.='<script type="text/javascript">
					$(function() {
						$("input[name='.preg_replace('#([\[\]])#', '\\\\\\\\$1', $Input->HTML($attrs['name'])).']").datepicker();
					});
				</script>';
		}

		return $tag;
	}

	/**
	 * Return a list of radio HTML tag
	 * @param string $name name of the tag
	 * @param string $options all possible values key=>value
	 * @param string $value value of the tag
	 * @param string[] $classes CSS classes of the tag
	 * @param mixed[] $others all other attributes for the tag
	 * @return string[] HTML text tag
	 */
	public static function radio($name, array $options, $value = NULL, $classes = array(), array $others = array()) {
		// TODO
// 		else if ($attrs['type'] === 'radio') { // FIXME : radio have to use OPTIONS, like select
// 			if ($attrs['value'] === TRUE) {			// radio value is NOT TRUE !!!
// 				$attrs['checked'] = 'checked';		// ok for this tag, on selected radio input
// 			}
// 		}
	}

	/**
	 * Return a select HTML tag
	 * @param string $name name of the tag
	 * @param string $options all possible values key=>value
	 * @param string $value value of the tag
	 * @param string[] $classes CSS classes of the tag
	 * @param mixed[] $others all other attributes for the tag
	 * @return string HTML text tag
	 */
	public static function select($name, array $options, $value = NULL, $classes = array(), array $others = array()) {
		$Input = In::getInstance();

		$attrs = self::commonTagAttributes($name, $value, $classes, $others);

		if (array_key_exists('value', $attrs)) {
			$value = $attrs['value'];
			unset($attrs['value']);
		}

		if (is_bool($value)) {
			$value = ($value)?1:0;
		}

		$rawValue = $value;
		if (isset($attrs[self::PARAM_RAW_VALUE])) { // if PARAM_RAW_VALUE is null, don't set, finding an option always failed
			$rawValue = $attrs[self::PARAM_RAW_VALUE];
		}

		$content = '';
		foreach($options as $k=>$v) {
			$optAttrs = array('value' => $k);

			if (strval($rawValue) === strval($k)) { // always string values
				$optAttrs['selected'] = 'selected';
			}
			if (strlen(trim($v)) === 0) {
				$v = '&nbsp;';
			} else {
				$v = $Input->HTML($v);
			}
			$content.=self::HTMLtag('option', $optAttrs, $v);
		}

		return self::HTMLtag('select', $attrs, $content);
	}
}