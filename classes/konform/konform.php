<?php
/**
 * Konform base class.
 *
 * @package Konform
 * @author  Drarok Ithaqua <github@catchall.drarok.com>
 */
abstract class Konform_Konform
{
	/**
	 * Method to use to submit the form.
	 *
	 * @var string
	 */
	protected $_method = 'post';

	/**
	 * Action (location) to submit the form to.
	 *
	 * @var string
	 */
	protected $_action = '';

	/**
	 * Other attributes (name => value) to apply to the form.
	 *
	 * @var array
	 */
	protected $_attributes = array();

	/**
	 * Fields, keyed on their name, with label and rules.
	 *
	 * Example:
	 *
	 * 'group' => array(
	 * 		'label' => 'Person or group',
	 * 		'rules' => array('not_empty'),
	 *	),
	 *
	 * @var array
	 */
	protected $_fields = array();

	/**
	 * Form data.
	 *
	 * @var array
	 */
	protected $_data = array();

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	protected $_errors = array();

	/**
	 * The file we should load for error messages. Defaults to class name if NULL.
	 *
	 * @var mixed
	 */
	protected $_errorMessageFile;

	/**
	 * Create a form with the given data.
	 *
	 * Only expected data is stored, so you can pass the whole $_POST array in.
	 *
	 * @param array $data Array of data to validate.
	 */
	public function __construct(array $data = NULL)
	{
		// Allow subclasses to set up the fields first.
		$this->_init();

		// Set the data.
		$this->data($data);
	}

	/**
	 * Validate the data, returning array of errors (if any).
	 *
	 * @return array
	 */
	public function validate()
	{
		$validation = new Validation($this->_data);

		foreach ($this->_fields as $fieldName => $fieldOptions) {
			foreach (Arr::get($fieldOptions, 'rules', array()) AS $fieldRule) {
				if (is_array($fieldRule)) {
					// Take the 1st param as the name, the rest as params.
					$ruleName = array_shift($fieldRule);
					$ruleParams = $fieldRule;
				} else {
					// Just use the rule as passed with no params.
					$ruleName = $fieldRule;
					$ruleParams = NULL;
				}

				// Check if the named function is actually a method on this object.
				if (! function_exists($ruleName) && method_exists($this, $ruleName)) {
					$ruleName = array($this, $ruleName);
				}

				$validation->rule($fieldName, $ruleName, $ruleParams);
				$validation->label($fieldName, Arr::get($fieldOptions, 'label', $fieldName));
			}
		}

		if ($validation->check()) {
			$this->_errors = array();
			return TRUE;
		} else {
			if (! (bool) $errorMessageFile = $this->_errorMessageFile) {
				// Konform_Contact => 'konform/contact'
				$errorMessageFile = strtolower(str_replace('_', '/', get_class($this)));
			}
			$this->_errors = $validation->errors($errorMessageFile);
			return FALSE;
		}
	}

	/**
	 * Getter/setter for the data. Stores only expected array keys.
	 *
	 * @return array
	 */
	public function data(array $data = NULL)
	{
		if ($data !== NULL) {
			// Only take keys that we actually expect.
			$this->_data = Arr::overwrite(array_fill_keys(array_keys($this->_fields), NULL), $data);
		}

		return $this->_data;
	}

	/**
	 * Render the form to an HTML string.
	 *
	 * @return string
	 */
	public function render()
	{
		// TODO: Can we moe this into a view?
		$html = '<form';
		$html .= ' method="' . HTML::chars($this->_method) . '"';
		$html .= ' action="' . HTML::chars($this->_action) . '"';

		foreach ($this->_attributes as $name => $value) {
			$html .= ' ' . $name . '="' . HTML::chars($value) . '"';
		}

		$html .= '>' . PHP_EOL;

		foreach ($this->_fields as $fieldName => $fieldOptions) {
			$type = Arr::get($fieldOptions, 'type', 'text');

			// TODO: This could be more efficient.
			$element = View::factory('konform/element/' . $type);

			// Set standard element properties.
			$element->name = $fieldName;
			$element->label = Arr::get($fieldOptions, 'label', $fieldName);
			$element->required = Arr::get($fieldOptions, 'required', false);
			$element->value = Arr::get($this->_data, $fieldName, '');
			$class = Arr::get($fieldOptions, 'class', '');
			if (array_key_exists($fieldName, $this->_errors)) {
				$class .= ' error';
				$element->error = $this->_errors[$fieldName];
			}
			$element->class = trim($class);

			// Now set properties that are specific to the type.
			switch ($type) {
				case 'select':
					$element->placeholder = Arr::get($fieldOptions, 'placeholder');
					$optionSource = Arr::get($fieldOptions, 'optionSource');
					if (! is_string($optionSource) || ! $optionSource) {
						throw new Konform_Exception('Invalid optionSource for ' . $fieldName);
					}
					$element->options = $this->$optionSource();
					$element->optionValue = Arr::get($fieldOptions, 'optionValue', 'pk');
					$element->optionLabel = Arr::get($fieldOptions, 'optionLabel', 'name');
					if (array_key_exists($fieldName, $this->_data)) {
						$element->selected = $this->_data[$fieldName];
					}
					break;

				case 'text':
				case 'password':
					$element->maxlength = Arr::get($fieldOptions, 'maxlength');
					break;

				case 'textarea':
					$element->cols = Arr::get($fieldOptions, 'cols', 60);
					$element->rows = Arr::get($fieldOptions, 'rows', 10);
					break;

				case 'submit':
					// Nothing special required, but we need to have a 'case' to avoid the Exception, below.
					break;

				default:
					throw new Konform_Exception('Invalid element type: ' . $type);
					break;
			}

			$html .= $element->render() . PHP_EOL;
		}

		$html .= '</form>' . PHP_EOL;

		return $html;
	}

	/**
	 * Does nothing, but is called in the constructor for the benefits of subclasses.
	 *
	 * @return void
	 */
	protected function _init()
	{
	}

	/**
	 * Add to the fields array, useful for creating form options in the _init method.
	 *
	 * @param string $fieldName    Name of the field (used as the key in $this->_fields).
	 * @param array  $fieldOptions Array of options (the value stored in $this->_fields).
	 *
	 * @return Konform
	 * @chainable
	 */
	protected function _addField($fieldName, $fieldOptions)
	{
		$this->_fields[$fieldName] = $fieldOptions;
		return $this;
	}
}