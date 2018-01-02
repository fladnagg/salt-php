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

	/**
	 * Raw format */
	const RAW='raw';
	/**
	 * Key for format
	 */
	const FORMAT_KEY='format';


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
	/** @var string[] List of javascript code to add before closing form */
	private static $javascriptCodes = array();
	/** @var string[] List of javascript token values to use for replace {jsKey} in javascript code, as array(value, token separator) */
	private static $javascriptTokens = array();
	/** @var boolean TRUE for use improved checkbox with FORM method for boolean fields */
	private static $useImprovedCheckbox = TRUE;

	/**
	 * Enable or disable usage of JQueryUI
	 * @param boolean $value TRUE (default) for enable JQueryUI, false for disable
	 */
	public static function withJQueryUI($value = TRUE) {
		self::$withJQueryUI = $value;
	}

	/**
	 * Enable use of improved checkbox in FORM method for boolean fields
	 *
	 * Improved checkbox use jQuery for handle a checkbox mapped to a hidden select field with 0 et 1 values
	 * @param string $value FALSE for disable improved checkbox
	 */
	public static function useImprovedCheckbox($value = TRUE) {
		self::$useImprovedCheckbox = $value;
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
		self::$javascriptCodes = array();
		self::$javascriptTokens = array();

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
		self::$javascriptCodes = array();
		self::$javascriptTokens = array();

		if ($params === NULL) {
			$params = array();
		}

		if ($others === NULL) {
			$others = array();
		}

		$others['method'] = 'post';

		$hiddens = array();

		if ($action === NULL) {
			$action = $Input->S->RAW->REQUEST_URI;
		}

		$anchor = NULL;
		$datas = explode('#', $action, 2);
		if (count($datas) > 1) {
			$anchor = last($datas);
		}
		$datas = first($datas);

		$query = '';
		$datas = explode('?', $datas, 2);
		if (count($datas)>1) {
			$query = last($datas);
		}
		$action = first($datas);

		$newParams = self::parseParams($query, $params);

		if (count($newParams) > 0) {
			$action .= '?'.self::httpBuildQuery($newParams);
		}

		if ($anchor !== NULL) {
			$action.= '#'.$anchor;
		}

		$others['action'] = $action;

		return self::HTMLtag('form', $others, NULL, self::TAG_OPEN);
	}

	/**
	 * Build an HTTP query from an associative array like http_build_query
	 *
	 * Improved http_build_query : parameters with NULL value will be converted to "?parameter"
	 *
	 * @param mixed[] $params
	 * @return string query string
	 * @see http://php.net/manual/en/function.http-build-query.php
	 */
	private static function httpBuildQuery($params) {
		$result = http_build_query($params);
		if (strlen($result) > 0) {
			$result = array($result);
		} else {
			$result = array();
		}
		foreach($params as $k => $v) {
			if ($v === NULL) {
				array_unshift($result, urlencode($k));
			}
		}
		return implode('&', $result);
	}

	/**
	 * Compute new parameters list for form tag
	 * @param string $query Query of URL in QUERY_STRING format : var1=value1&var2=value2...
	 * @param string[] $params List of varX to keep or redefine. '*' for reuse all request parameters, key=>NULL for remove key parameter
	 * @return string[] key=>value
	 */
	private static function parseParams($query, array $params) {
		$newParams = array();

		parse_str($query, $paramsRequest);
		/**
		 * PHP functions parse_str do NOT make a difference between "?foo" and "?foo="
		 * This implementation return NULL and empty string for values in theses cases.
		 */
		foreach(explode('&', $query) as $param) {
			$param = explode('=', $param);
			if (count($param) === 1) {
				$key = preg_replace('#[^A-Za-z_]#', '_', $param[0]);
				$paramsRequest[$key] = NULL;
			}
		}

		foreach($params as $k => $v) {
			if (is_numeric($k)) {
				if ($v === '*') {
					foreach($paramsRequest as $kk => $vv) {
						if (($kk !== '') && ($vv !== '')) {
							$newParams[$kk] = $vv;
						}
					}
				} else if (array_key_exists($v, $paramsRequest)) {
					$newParams[$v] = $paramsRequest[$v];
				}
			} else if ($v === NULL) {
				unset($newParams[$k]);
			} else if ($k !== '') {
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

		$result='';
		$codes = array();
		$tokens = self::$javascriptTokens;
		$keys = implode('|', array_keys($tokens));
		foreach(self::$javascriptCodes as $k => $js) {
			$oldJs = NULL;
			$watchDogs = 10;
			if (count($tokens) > 0) {
				while($oldJs !== $js) {
					if ($watchDogs-- <= 0) {
						throw new SaltException(L::error_view_javascript_recursion);
					}
					$oldJs = $js;
					$js = preg_replace_callback('#\{('.$keys.')\}#', function($matches) use ($tokens) {
						$key = $matches[1];
						return implode($tokens[$key]['separator'], $tokens[$key]['values']);
					}, $js);
				}
			}
			$codes[] = $js;
		}
		if (count(self::$javascriptCodes) > 0) {
			$result.='<script type="text/javascript">';
			$result.=implode("\n\n", $codes);
			$result.='</script>';
		}

		self::$javascriptCodes = array();
		self::$javascriptTokens = array();

		$result.=self::HTMLtag('form', array(), NULL, self::TAG_CLOSE);
		return $result;
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

		self::$values = NULL;

		$first = array_shift($args);
		if ($Input->{self::$method}->ISSET->$first) {
			$values = $Input->{self::$method}->RAW->$first;
			foreach($args as $key) {
				if (array_key_exists($key, $values)) {
					$values = $values[$key];
				} else { // if we don't find a key, don't let values to this state
					$values = array();
					break;
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
			if (($v !== NULL) && (strpos($k, '_') !== 0) && !is_array($v)) {
				$result.=' '.$Input->HTML($k).'="'.$Input->HTML($v).'"';
			}
		}
		if ($content === NULL) {
			if ($tagType !== self::TAG_OPEN) {
				$result.='/';
			}
			$result.='>';
		} else {
			$result.='>'.$content;
			if ($tagType !== self::TAG_OPEN) {
				$result.='</'.$tagName.'>';
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
				throw new SaltException(L::error_view_missing_form);
			}
			if ($Input->{self::$method}->ISSET->$name) {
				return $Input->{self::$method}->RAW->$name;
			}
		}
		return NULL;
	}

	/**
	 * Replace value from previously submitted form
	 * @param string $name name of input (simple name, not the return of self::getName() !)
	 * @param mixed $value value to set
	 * @throws SaltException if called outside a form
	 */
	public static function setValue($name, $value) {
		if (self::$values !== NULL) {
			if (array_key_exists($name, self::$values)) {
				self::$values[$name] = $value;
			}
		} else {
			$Input = In::getInstance();
			if (self::$method === NULL) {
				throw new SaltException(L::error_view_missing_form);
			}
			if ($Input->{self::$method}->ISSET->$name) {
				$Input->{self::$method}->SET->$name = $value;
			}
		}
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
	 * @param Converter $helper The helper to use for format the value
	 * @return string HTML tag for the field
	 */
	public static function field(Field $field, $name, $value, $classes = array(), $others = array(), DAOConverter $helper = NULL) {
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
		if (!array_key_exists(self::FORMAT_KEY, $others)) { // value can be NULL, array_key_exists required
			$others[self::FORMAT_KEY] = $field->displayFormat;
		}

		// type guess
		$type = 'text';
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

		if (($type === 'text') && ($field->type === FieldType::DATE)) {
			if (self::$withJQueryUI) {
				$others[self::PARAM_DATEPICKER] = TRUE;
// 			} else { // not enought browser support it... https://caniuse.com/#search=date
// 				$type = 'date';
			}
		}

		if ($helper !== NULL) {
			// default value if no input value
			if (in_array($type, array('select', 'checkbox', 'radio'))) {
				// value have to be NOT formatted for these types
				$others['value'] = $value;
			} else {
				// but formatter otherwise (text, date, textarea)
				$others['value'] = $helper->text($helper->getObject(), $field, $value, $others[self::FORMAT_KEY], array());
			}
			$value = NULL; // value in parameters have more priority than GET/POST
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
				$options = array('1' => L::view_yes, '0' => L::view_no);
			}
			if ((count($options) > 0) && $field->nullable && !isset($options[''])) {
				$options = array('' => '') + $options;
			}
		}

		// remove internal keys which are not HTML attributes
		unset($others['options']);
		unset($others[self::FORMAT_KEY]);

		$result=NULL;
		switch($type) {
			case 'hidden' :
			case 'text' :
			case 'date' :
			case 'password' :
				$result = self::input($name, $type, $value, $classes, $others);
			break;
			case 'select' : $result = self::select($name, $options, $value, $classes, $others);
			break;
			case 'radio' : $result = self::radio($name, $options, $value, $classes, $others);
			break;
			case 'checkbox' :
				if (self::$useImprovedCheckbox) {
					$result = self::checkbox($name, $value, $classes, $others);
				} else {
					$result = self::input($name, 'checkbox', $value, $classes, $others);
				}
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

		if (count($classes) > 0) {
			$attrs['class'] = implode(' ', $classes);
		}

		if (!isset($name) && isset($attrs['name'])) {
			$name = $attrs['name'];
		}
		$attrs['name'] = self::getName($name);

		if (!isset($attrs['value'])) {
			$attrs['value'] = NULL;
		}
		// PROVIDED ($value, have to be formatted) > INPUT (from previous form submit, not formatted) > DEFAULT (value in $attrs, have to be formatted)
		if (isset($value)) {
			$attrs['value'] = $value;
		} else if (($name !== NULL) && ($value === NULL)) {
			$inputValue = self::getValue($name);
			if ($inputValue !== NULL) {
				// not formatted because value came from last submit, so already formatted
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

		if (array_key_exists('value', $attrs)) { // can be NULL
			$value = $attrs['value'];
			unset($attrs['value']);
		}

		return self::HTMLtag('textarea', $attrs, $Input->HTML($value));
	}

	/**
	 * Return a checkbox HTML tag with a 1/0 implementation.
	 * Unchecked box will return 0 and not an unset name like standard checkbox
	 *
	 * This tag work with and without javascript<br/>
	 * With javascript, if you set the checkbox value (to one of 1/0), you need jQuery AND call change() after val(...)
	 *
	 * @param string $name name of the tag
	 * @param string $value value of the tag
	 * @param string[] $classes CSS classes of the tag
	 * @param mixed[] $others all other attributes for the tag
	 * @return string HTML text tag
	 */
	public static function checkbox($name, $value = NULL, $classes = array(), array $others = array()) {
		static $id = 0;

		$id++;
		$checkId = 's:cy'.$id;
		$uncheckId = 's:cn'.$id;

		if (isset($others['id'])) {
			$checkId = $others['id'];
		}

		$checkedValue = 1;
		$uncheckedValue = 0;

		$uncheckedInput = self::input($name, 'hidden', 0, array(), array(
			'name' => isset($others['name'])?$others['name']:$name,
			'id' => $uncheckId,
			'onchange' => "javascript:var c=document.getElementById(".json_encode($checkId).");".
							"c.checked=(value==".$checkedValue.");".
							"c.value=".$checkedValue.";".
							"document.getElementById(".json_encode($uncheckId).").value=".$uncheckedValue.";",
		));

		$attrs = self::commonTagAttributes($name, $value, $classes, $others);

		$value = $attrs['value'];
		$checked = ($value == 1);

		// Without javascript, there is a little "issue" :
		// 	the name of a checked checkbox send with a GET form will appear twice in URL, with unchecked and checked value.
		//	it work because PHP keep only last value when same name are send
		if ($checked) {
			$key = FormHelper::registerJSPageLoaded();
			FormHelper::registerJSTokenValue($key, 'document.getElementById('.json_encode($uncheckId).').setAttribute("disabled", "disabled")');
		}

		$onchange = '';
		if (isset($attrs['onchange'])) {
			$onchange = trim($attrs['onchange']);
			if (preg_match('#^javascript\s*:#', $onchange) === 1) {
				$onchange = last(explode(':', $onchange, 2));
			}
			$onchange=';'.$onchange;
		}

		$attrs = array_merge($attrs, array(
			'type' => 'checkbox',
			'value' => 1,
			'id' => $checkId,
			'onchange' => "javascript:var c=document.getElementById(".json_encode($uncheckId)."),d='disabled';".
						"this.checked?c.setAttribute(d,d):c.removeAttribute(d);".
						$onchange,
		));
		if ($checked) {
			$attrs['checked'] = 'checked';
		}
		$checkedInput = self::HTMLtag('input', $attrs);

		return $uncheckedInput.$checkedInput;
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

		$value = $attrs['value'];

		if (($value !== NULL) && ($attrs['type'] === 'checkbox')) {
			if ($value !== FALSE) { // FIXME format value before ?
				$attrs['checked'] = 'checked';
			}
			unset($attrs['value']);
		}

		// allow value attrs in checkbox to having other value than 'on' when checked.
		// FIXME don't work well. Have to compare $value to $others['value'] for checked ?
		// $others['values'] is set to helper->text() in ::field()
		if ($attrs['type'] === 'checkbox') {
			if (isset($others['value'])) {
				$attrs['value'] = $others['value'];
			}
		}

		$tag = self::HTMLtag('input', $attrs);

		if (isset($others[self::PARAM_DATEPICKER])) {
			$errorMessage = json_encode(L::error_view_missing_jqueryui);
			$initKey = 'initDateField';
			$key = self::registerJSPageLoaded();
			FormHelper::registerJSTokenValue($key, <<<JS
if (typeof(jQuery)!="undefined") {
	jQuery('{{$initKey}}').datepicker();
} else {
	alert({$errorMessage});
}
JS
, $initKey);
			FormHelper::registerJSTokenValue($initKey, 'input[name="'.$Input->HTML($attrs['name']).'"]', NULL, ',');
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
	 * Build options tag of a select input
	 * @param mixed[] $options all possible values in one of theses formats :<ul>
	 * 		<li>key=>value</li>
	 * 		<li>key=>array('value' => displayValue, 'attr' => attrValue, ...)</li>
	 * 		<li>group_label=>array('group' => array(key=>value))</li>
	 * 		<li>group_label=>array('group' => array(key=>array('value' => displayValue, 'attr' => attrValue, ...)))</li></ul>
	 * @param mixed $selected selected value(s)
	 * @return string HTML text for $options
	 */
	private static function buildSelectOptions(array $options, $selected) {
		$Input = In::getInstance();
		$content = '';

		if (!is_array($selected)) {
			$selected = array($selected);
		}
		$selected = array_map('strval', $selected);

		foreach($options as $k=>$v) {
			$optAttrs = array();
			if (is_array($v)) {
				if (!isset($v['group'])) {
					$optAttrs = $v;
					$v = $optAttrs['value'];
				} else {
					$groupOptions = self::buildSelectOptions($v['group'], $selected);
					unset($v['group']);
					$v['label']=$k;
					$content.= self::HTMLtag('optgroup', $v, $groupOptions);
					continue;
				}
			}
			$optAttrs['value'] = $k;

			if (in_array(strval($k), $selected)) { // always string values
				$optAttrs['selected'] = 'selected';
			}
			if (strlen(trim($v)) === 0) {
				$v = '&nbsp;';
			} else {
				$v = $Input->HTML($v);
			}
			$content.=self::HTMLtag('option', $optAttrs, $v);
		}
		return $content;
	}

	/**
	 * Return a select HTML tag
	 * @param string $name name of the tag
	 * @param mixed[] $options all possible values in one of theses formats :<ul>
	 * 		<li>key=>value</li>
	 * 		<li>key=>array('value' => displayValue, 'attr' => attrValue, ...)</li>
	 * 		<li>group_label=>array('group' => array(key=>value))</li>
	 * 		<li>group_label=>array('group' => array(key=>array('value' => displayValue, 'attr' => attrValue, ...)))</li></ul>
	 * @param string $value value of the tag
	 * @param string[] $classes CSS classes of the tag
	 * @param mixed[] $others all other attributes for the tag
	 * @return string HTML text tag
	 */
	public static function select($name, array $options, $value = NULL, $classes = array(), array $others = array()) {

		$attrs = self::commonTagAttributes($name, $value, $classes, $others);

		$value = $attrs['value'];
		unset($attrs['value']);

		if (is_bool($value)) {
			$value = ($value)?1:0;
		}

		$content = self::buildSelectOptions($options, $value);

		return self::HTMLtag('select', $attrs, $content);
	}

	/**
	 * Register a javascript bloc to add before closing form
	 * @param string $key The key to use for append code : every previous code registered with this key is replaced
	 * @param string $jsCode The javascript code
	 * @return string the $key
	 */
	public static function registerJavascript($key, $jsCode) {
		if ($key === NULL) {
			$key = uniqid(md5($jsCode));
		}
		self::$javascriptCodes[$key] = $jsCode;
		return $key;
	}

	/**
	 * Add a value for a replaceable token in JS code registered to registerJavascript()
	 * @param string $key The key of registerJavascript()
	 * @param string $value Value to use for replace {$key}
	 * @param string $valueKey key for value, multiple call with same key replace the value
	 * @param string $tokenSeparator separator for muliple values
	 * @return string $valueKey
	 */
	public static function registerJSTokenValue($key, $value, $valueKey = NULL, $tokenSeparator = "\n") {
		if (!isset(self::$javascriptTokens[$key])) {
			self::$javascriptTokens[$key] = array('separator' => $tokenSeparator, 'values' => array());
		}
		if ($valueKey === NULL) {
			$valueKey = uniqid(md5($value));
		}
		self::$javascriptTokens[$key]['values'][$valueKey] = $value;
		return $valueKey;
	}

	/**
	 * Register a javascript bloc executed when page was loaded. Require IE9+ or real browser ;o)
	 * @return string the javascript key
	 */
	public static function registerJSPageLoaded() {
		$key = 'pageLoaded';
		return FormHelper::registerJavascript($key, <<<JS
if (typeof(jQuery)!="undefined") {
	jQuery(function() {
		{{$key}}
	});
} else {
	document.addEventListener("DOMContentLoaded", function(event) {
		{{$key}}
	});
}
JS
);
	}

}
