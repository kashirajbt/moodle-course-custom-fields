<?php
global $CFG;

require_once($CFG->libdir . '/custominfo/lib.php');

/**
 * Base class for the customisable profile fields.
 */
abstract class profile_field_base extends custominfo_field_base {

    protected $objectname = 'user';
    protected $capability = 'moodle/user:update';

    /**
     * Abstract method: Adds the profile field to the moodle form class
     * @param  form  instance of the moodleform class
     */
    function edit_field_add($mform) {
        print_error('mustbeoveride', 'debug', '', 'edit_field_add');
    }


/***** The following methods may be overwritten by child classes *****/

    /**
     * Display the data for this field
     */
    function display_data() {
        $options = new stdClass();
        $options->para = false;
        return format_text($this->data, FORMAT_MOODLE, $options);
    }

    /**
     * Print out the form field in the edit profile page
     * @param   object   instance of the moodleform class
     * $return  boolean
     */
    function edit_field($mform) {

        if ($this->field->visible != PROFILE_VISIBLE_NONE
          or has_capability('moodle/user:update', context_system::instance())) {

            $this->edit_field_add($mform);
            $this->edit_field_set_default($mform);
            $this->edit_field_set_required($mform);
            return true;
        }
        return false;
    }

    /**
     * Tweaks the edit form
     * @param   object   instance of the moodleform class
     * $return  boolean
     */
    function edit_after_data($mform) {

        if ($this->field->visible != PROFILE_VISIBLE_NONE
          or has_capability('moodle/user:update', context_system::instance())) {
            $this->edit_field_set_locked($mform);
            return true;
        }
        return false;
    }

    /**
     * Saves the data coming from form
     * @param   mixed   data coming from the form
     * @return  mixed   returns data id if success of db insert/update, false on fail, 0 if not permitted
     */
    function edit_save_data($usernew) {
        global $DB;

        if (!isset($usernew->{$this->inputname})) {
            // field not present in form, probably locked and invisible - skip it
            return;
        }

        $data = new stdClass();

        $usernew->{$this->inputname} = $this->edit_save_data_preprocess($usernew->{$this->inputname}, $data);

        $data->objectname = 'user';
        $data->objectid = $usernew->id;
        $data->fieldid = $this->field->id;
        $data->data    = $usernew->{$this->inputname};

        if ($dataid = $DB->get_field('custom_info_data', 'id', array('objectid' => $data->objectid, 'fieldid' => $data->fieldid))) {
            $data->id = $dataid;
            $DB->update_record('custom_info_data', $data);
        } else {
            $DB->insert_record('custom_info_data', $data);
        }
    }

    /**
     * Validate the form field from profile page
     * @return  string  contains error message otherwise NULL
     **/
    function edit_validate_field($usernew) {
        global $DB;

        $errors = array();
        // Get input value.
        if (isset($usernew->{$this->inputname})) {
            if (is_array($usernew->{$this->inputname}) && isset($usernew->{$this->inputname}['text'])) {
                $value = $usernew->{$this->inputname}['text'];
            } else {
                $value = $usernew->{$this->inputname};
            }
        } else {
            $value = '';
        }

        // Check for uniqueness of data if required.
        if ($this->is_unique() && (($value !== '') || $this->is_required())) {
            $data = $DB->get_records_sql('
                    SELECT id, objectid
                      FROM {custom_info_data}
                     WHERE fieldid = ?
                       AND ' . $DB->sql_compare_text('data', 255) . ' = ' . $DB->sql_compare_text('?', 255),
                    array($this->field->id, $value));
            if ($data) {
                $existing = false;
                foreach ($data as $v) {
                    if ($v->objectid == $usernew->id) {
                        $existing = true;
                        break;
                    }
                }
                if (!$existing) {
                    $errors[$this->inputname] = get_string('valuealreadyused');
                }
            }
        }
        return $errors;
    }

    /**
     * Sets the default data for the field in the form object
     * @param   object   instance of the moodleform class
     */
    function edit_field_set_default($mform) {
        if (!empty($default)) {
            $mform->setDefault($this->inputname, $this->field->defaultdata);
        }
    }

    /**
     * Sets the required flag for the field in the form object
     * @param   object   instance of the moodleform class
     */
    function edit_field_set_required($mform) {
        global $USER;
        if ($this->is_required() && ($this->userid == $USER->id)) {
            $mform->addRule($this->inputname, get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * HardFreeze the field if locked.
     * @param   object   instance of the moodleform class
     */
    function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() and !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname, $this->data);
        }
    }

    /**
     * Hook for child classess to process the data before it gets saved in database
     * @param   mixed    $data
     * @param   stdClass $datarecord The object that will be used to save the record
     * @return  mixed
     */
    function edit_save_data_preprocess($data, $datarecord) {
        return $data;
    }

    /**
     * Loads a user object with data for this field ready for the edit profile
     * form
     * @param   object   a user object
     */
    function edit_load_user_data($user) {
        if ($this->data !== NULL) {
            $user->{$this->inputname} = $this->data;
        }
    }

    /**
     * Check if the field data should be loaded into the user object
     * By default it is, but for field types where the data may be potentially
     * large, the child class should override this and return false
     * @return boolean
     */
    function is_user_object_data() {
        return true;
    }


/***** The following methods generally should not be overwritten by child classes *****/

    /**
     * Accessor method: set the userid for this instance
     * @param   integer   $userid  id from the user table
     */
    function set_userid($userid) {
        $this->userid = $userid;
    }

    /**
     * Accessor method: set the fieldid for this instance
     * @param   integer   $fieldid  id from the custom_info_field table
     */
    function set_fieldid($fieldid) {
        $this->fieldid = $fieldid;
    }

    /**
     * Accessor method: Load the field record and user data associated with the
     * object's fieldid and userid
     */
    function load_data() {
        global $DB;

        /// Load the field object
        if (($this->fieldid == 0) or (!($field = $DB->get_record('custom_info_field',
                array('objectname' => 'user', 'id' => $this->fieldid))))) {
            $this->field = NULL;
            $this->inputname = '';
        } else {
            $this->field = $field;
            $this->inputname = 'profile_field_'.$field->shortname;
        }

        if (!empty($this->field)) {
            if ($data = $DB->get_record('custom_info_data',
                    array('objectid' => $this->userid, 'fieldid' => $this->fieldid), 'data, dataformat')) {
                $this->data = $data->data;
                $this->dataformat = $data->dataformat;
            } else {
                $this->data = $this->field->defaultdata;
                $this->dataformat = FORMAT_HTML;
            }
        } else {
            $this->data = NULL;
        }
    }

    // for compatibility with PHP4 code in sub-classes
    public function profile_field_base($fieldid=0, $objectid=0) {
        parent::__construct($fieldid, $objectid);
    }

    /**
     * Check if the field data is visible to the current user
     * @return  boolean
     */
    public function is_visible() {
        global $USER;

        switch ($this->field->visible) {
            case CUSTOMINFO_VISIBLE_ALL:
                return true;
            case CUSTOMINFO_VISIBLE_PRIVATE:
                if ($this->objectid == $USER->id) {
                    return true;
                } else {
                    return has_capability('moodle/user:viewalldetails',
                            context_user::instance($this->objectid));
                }
            case CUSTOMINFO_VISIBLE_NONE:
            default:
                return has_capability($this->capability, context_user::instance($this->objectid));
        }
    }
} /// End of class definition


/***** General purpose functions for customisable user profiles *****/

function profile_load_data($user) {
    global $CFG, $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $user->id);
            $formfield->edit_load_object_data($user);
        }
    }
}

/**
 * Print out the customisable categories and fields for a users profile
 * @param  object   instance of the moodleform class
 * @param int $userid id of user whose profile is being edited.
 */
function profile_definition($mform, $userid = 0) {
    global $CFG, $DB;

    // if user is "admin" fields are displayed regardless
    $update = has_capability('moodle/user:update', context_system::instance());

    $categories = $DB->get_records('custom_info_category', array('objectname' => 'user'), 'sortorder ASC');
    if ($categories) {
        foreach ($categories as $category) {
            $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                // check first if *any* fields will be displayed
                $display = false;
                foreach ($fields as $field) {
                    if ($field->visible != CUSTOMINFO_VISIBLE_NONE) {
                        $display = true;
                    }
                }

                // display the header and the fields
                if ($display or $update) {
                    $mform->addElement('header', 'category_'.$category->id, format_string($category->name));
                    foreach ($fields as $field) {
                        require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
                        $newfield = 'profile_field_'.$field->datatype;
                        $formfield = new $newfield($field->id, $userid);
                        $formfield->edit_field($mform);
                    }
                }
            }
        }
    }
}

function profile_definition_after_data($mform, $userid) {
    global $CFG, $DB;

    $userid = ($userid < 0) ? 0 : (int)$userid;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $userid);
            $formfield->edit_after_data($mform);
        }
    }
}

function profile_validation($usernew, $files) {
    global $CFG, $DB;

    $err = array();
    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $usernew->id);
            $err += $formfield->edit_validate_field($usernew, $files);
        }
    }
    return $err;
}

function profile_save_data($usernew) {
    global $CFG, $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $usernew->id);
            $formfield->edit_save_data($usernew);
        }
    }
}

function profile_display_fields($userid) {
    global $CFG, $DB;

    $categories = $DB->get_records('custom_info_category', array('objectname' => 'user'), 'sortorder ASC');
    if ($categories) {
        foreach ($categories as $category) {
            $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                foreach ($fields as $field) {
                    require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
                    $newfield = 'profile_field_'.$field->datatype;
                    $formfield = new $newfield($field->id, $userid);
                    if ($formfield->is_visible() and !$formfield->is_empty()) {
                        print_row(format_string($formfield->field->name.':'), $formfield->display_data());
                    }
                }
            }
        }
    }
}

/**
 * Adds code snippet to a moodle form object for custom profile fields that
 * should appear on the signup page
 * @param  object  moodle form object
 */
function profile_signup_fields($mform) {
    global $CFG, $DB;

     //only retrieve required custom fields (with category information)
    //results are sort by categories, then by fields
    $sql = "SELECT f.id as fieldid, c.id as categoryid, c.name as categoryname, f.datatype
                FROM {custom_info_field} f
                JOIN {custom_info_category} c
                ON f.categoryid = c.id
                WHERE ( c.objectname = 'user' AND f.signup = 1 AND f.visible<>0 )
                ORDER BY c.sortorder ASC, f.sortorder ASC";
    $fields = $DB->get_records_sql($sql);
    if ($fields) {
        $currentcat = null;
        foreach ($fields as $field) {
            //check if we change the categories
            if (!isset($currentcat) || $currentcat != $field->categoryid) {
                 $currentcat = $field->categoryid;
                 $mform->addElement('header', 'category_'.$field->categoryid, format_string($field->categoryname));
            }
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->fieldid);
            $formfield->edit_field($mform);
        }
    }
}

/**
 * Returns an object with the custom profile fields set for the given user
 * @param  integer  userid
 * @return  object
 */
function profile_user_record($userid) {
    global $CFG, $DB;

    $usercustomfields = new stdClass();

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $userid);
            if ($formfield->is_object_data()) {
                $usercustomfields->{$field->shortname} = $formfield->data;
            }
        }
    }

    return $usercustomfields;
}

/**
 * Load custom profile fields into user object
 *
 * Please note originally in 1.9 we were using the custom field names directly,
 * but it was causing unexpected collisions when adding new fields to user table,
 * so instead we now use 'profile_' prefix.
 *
 * @param object $user user object
 * @return void $user object is modified
 */
function profile_load_custom_fields($user) {
    $user->profile = (array)profile_user_record($user->id);
}
