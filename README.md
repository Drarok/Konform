# Konform - Simple form generator for Kohana 3.2+

## Requirements

You'll need the [KOtal] module, as the views are written in PHPTAL.

[KOtal]: https://github.com/Drarok/KOtal

## Usage

* Create a subclass of Konform (Konform_Contact, for example).
* Set the $_fields property up in your subclass (either in its _init() method, or just at the top of the class).
* Create a messages/konform/contact.php file, and populate it with your error messages.
* Instantiate your class and use it. ;)
* Add some CSS to target these forms in a generic way, and enjoy.

## Examples

Here's a contact form, demonstrating each form element.

	class Konform_Contact extends Konform
	{
		/**
		 * Action (location) to submit the form to.
		 *
		 * @var string
		 */
		protected $_action = '/contact';

		/**
		 * Other attributes (name => value) to apply to the form.
		 *
		 * @var array
		 */
		protected $_attributes = array(
			'id'    => 'something'
			'class' => 'konform contact',
		);

		/**
		 * Fields, keyed on their name, with label, rules, type, and other options.
		 *
		 * @var array
		 */
		protected $_fields = array(
			// Simple text entry box.
			'name' => array(
				'type'      => 'text',
				'label'     => 'Your name',
				'maxlength' => 60,
				'required'  => true,
				'rules'     => array('not_empty'),
			),

			// One with more validation on it, and a custom CSS class applied to the container.
			'email' => array(
				'type'     => 'text',
				'label'    => 'Your email address',
				'required' => true,
				'rules'    => array('not_empty', 'email'),
				'class'    => 'email',
			),

			// Example of a select element.
			'group' => array(
				'type'         => 'select',
				'label'        => 'Person or group',
				'required'     => true,
				'rules'        => array('not_empty'),
				'placeholder'  => 'Please select...',
				'optionSource' => '_getGroups',
				'optionValue'  => 'pk',
				'optionLabel'  => 'name',
			),

			// And a textarea.
			'body' => array(
				'type'     => 'textarea',
				'label'    => 'Your message',
				'required' => true,
				'rules'    => array('not_empty'),
				'cols'     => '70',
				'rows'     => '10',
			),

			// Don't forget to add a submit button.
			'submit' => array(
				'type' => 'submit',
				'label' => 'Submit',
				'class' => 'buttons'
			),
		);

		/**
		 * Get the groups for the dropdown list.
		 *
		 * These source methods must return something iterable (suitable for reading
		 * by foreach - arrays, Iterators, etc).
		 *
		 * @return Database_Result
		 */
		protected function _getGroups()
		{
			return ORM::factory('group')->find_all();
		}
	}

And, here's the controller code to go with it.

	public function action_index()
	{
		$contactForm = new Konform_Contact($this->request->post());
		if ($this->request->post() && $contactForm->validate()) {
			// Looks like we received valid data, so send the emails.
			$this->_sendContactEmail($contactForm->data());
			HTTP::redirect('/contact/thank-you');
		}

		$this->response->body($contactForm->render());
	}