<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * This class declares the form that describes a custominfo category.
 */
class category_form extends moodleform {

    /**
     * Define the form
     */
    public function definition () {
        $mform =& $this->_form;

        $strrequired = get_string('required');

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'editcategory');
        $mform->setType('action', PARAM_ALPHANUMEXT);

        $mform->addElement('text', 'name', get_string('profilecategoryname', 'admin'), 'maxlength="255" size="30"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', $strrequired, 'required', null, 'client');

        $this->add_action_buttons(true);
    } /// End of function

    /**
     * perform some moodle validation
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $data  = (object)$data;

        $duplicate = $DB->record_exists('custom_info_category',
                array('objectname' => $this->_customdata['objectname'], 'name' => $data->name));

        /// Check the name is unique
        if (!empty($data->id)) { // we are editing an existing record
            $olddata = $DB->get_record('custom_info_category',
                    array('objectname' => $this->_customdata['objectname'], 'id' => $data->id));
            // name has changed, new name in use, new name in use by another record
            $dupfound = (($olddata->name !== $data->name) && $duplicate && ($data->id != $duplicate->id));
        }
        else { // new profile category
            $dupfound = $duplicate;
        }

        if ($dupfound ) {
            $errors['name'] = get_string('profilecategorynamenotunique', 'admin');
        }

        return $errors;
    }
}


