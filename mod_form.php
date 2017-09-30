<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The main mod_kalmediares configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_kalmediares
 * @copyright  (C) 2016-2017 Yamaguchi University <info-cc@ml.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/yukaltura/locallib.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * class of YU Kaltura Media resource setting form.
 * @package mod_kalmediares
 * @copyright  (C) 2016-2017 Yamaguchi University <info-cc@ml.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kalmediares_mod_form extends moodleform_mod {

    protected $_default_player = false;

    /**
     * This function outputs a resource information form.
     * @access protected
     * @param none.
     * @return nothing.
     */
    protected function definition() {
        global $CFG, $COURSE, $PAGE;

        $kaltura = new yukaltura_connection();
        $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

        $loginsession = '';

        if (!empty($connection)) {
            $loginsession = $connection->getKs();
        }

        $PAGE->requires->css('/mod/kalmediares/css/kalmediares.css');
        $PAGE->requires->css('/local/yukaltura/css/simple_selector.css');

        $partnerid = local_yukaltura_get_partner_id();
        $host = local_yukaltura_get_host();

        /*
         * This line is needed to avoid a PHP warning when the form is submitted.
         * Because this value is set as the default for one of the formslib elements.
         */
        $uiconfid = '';

        // Check if connection to Kaltura can be established.
        if ($connection) {
            $PAGE->requires->js('/local/yukaltura/js/jquery-3.0.0.js', true);
            $PAGE->requires->js_call_amd('local_yukaltura/simple_selector');

            $courseid = $COURSE->id;

            $conversionscript = "../local/yukaltura/check_conversion.php?courseid={$courseid}&entry_id=";
            $uiconfid = local_yukaltura_get_player_uiconf('player_resource');
            $progressbarmarkup = $this->draw_progress_bar();
        }

        if (local_yukaltura_has_mobile_flavor_enabled() && local_yukaltura_get_enable_html5()) {

            $url = new moodle_url(local_yukaltura_html5_javascript_url($uiconfid));
            $PAGE->requires->js($url, true);
        }

        $mform =& $this->_form;

        // Hidden fields.
        $attr = array('id' => 'entry_id');
        $mform->addElement('hidden', 'entry_id', '', $attr);
        $mform->setType('entry_id', PARAM_NOTAGS);

        $attr = array('id' => 'media_title');
        $mform->addElement('hidden', 'media_title', '', $attr);
        $mform->setType('media_title', PARAM_TEXT);

        $attr = array('id' => 'uiconf_id');
        $mform->addElement('hidden', 'uiconf_id', '', $attr);
        $mform->setDefault('uiconf_id', $uiconfid);
        $mform->setType('uiconf_id', PARAM_INT);

        $attr = array('id' => 'widescreen');
        $mform->addElement('hidden', 'widescreen', '', $attr);
        $mform->setDefault('widescreen', 0);
        $mform->setType('widescreen', PARAM_INT);

        $attr = array('id' => 'height');
        $mform->addElement('hidden', 'height', '', $attr);
        $mform->setDefault('height', '365');
        $mform->setType('height', PARAM_TEXT);

        $attr = array('id' => 'width');
        $mform->addElement('hidden', 'width', '', $attr);
        $mform->setDefault('width', '400');
        $mform->setType('width', PARAM_TEXT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'kalmediares'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        if (local_yukaltura_login(true, '')) {
            $mform->addElement('header', 'video', get_string('media_hdr', 'kalmediares'));
            $this->add_media_definition($mform);
        } else {
            $mform->addElement('static', 'connection_fail', get_string('conn_failed_alt', 'local_yukaltura'));
        }

         $mform->addElement('header', 'access', get_string('access_hdr', 'kalmediares'));
         $this->add_access_definition($mform);

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * This function return HTML markup for progress bar.
     * @access private
     * @param none.
     * @return string - HTML markup for progress bar.
     */
    private function draw_progress_bar() {
        $attr = array('id' => 'progress_bar');
        $progressbar = html_writer::tag('span', '', $attr);

        $attr = array('id' => 'slider_border');
        $sliderborder = html_writer::tag('div', $progressbar, $attr);

        $attr = array('id' => 'loading_text');
        $loadingtext  = html_writer::tag('div', get_string('checkingforjava', 'mod_kalmediares'), $attr);

        $attr = array('id' => 'progress_bar_container',
                        'style' => 'width:100%; padding-left:10px; padding-right:10px; visibility: hidden');
        $output = html_writer::tag('span', $sliderborder . $loadingtext, $attr);

        return $output;

    }

    /**
     * This function add "Access" part to module form.
     * @access private
     * @param object $mform - form object.
     * @return string - HTML markup for "Access" part.
     */
    private function add_access_definition($mform) {
        $accessgroup = array();
        $attry = array('id' => 'internal', 'name' => 'internal');
        $options = array('0' => 'No', '1' => 'Yes');
        $select = $mform->addElement('select', 'internal', get_string('internal', 'mod_kalmediares'), $options);
        $select->setSelected('0');
        $acessgroup[] =& $select;
    }

    /**
     * This function add "Media" part to module form.
     * @access private
     * @param object $mform - form object.
     * @return string - HTML markup for "Media" part.
     */
    private function add_media_definition($mform) {
        global $COURSE;

        $thumbnail = $this->get_thumbnail_markup();

        $mform->addElement('static', 'add_media_thumb', '&nbsp;', $thumbnail);

        if (empty($this->current->entry_id)) {
            $prop = array('style' => 'display:none;');
        }

        $radioarray = array();
        $attributes = array();
        $context    = null;

        $selectorurl = new moodle_url('/local/yukaltura/simple_selector.php');
        $attr = array('onclick' => 'fadeInSelectorWindow("' . $selectorurl . '")');

        $mediagroup = array();
        $mediagroup[] =& $mform->createElement('button', 'add_media', get_string('add_media', 'kalmediares'), $attr);

        $propertiesurl = new moodle_url('/local/yukaltura/media_properties.php');
        $prop = array('onclick' => 'fadeInPropertiesWindow(\'' . $propertiesurl . '\');');

        if (empty($this->current->entry_id)) {
            $prop += array('style' => 'visibility: hidden;');
        }

        $mediagroup[] =& $mform->createElement('button', 'media_properties', get_string('media_properties', 'kalmediares'), $prop);

        $mform->addGroup($mediagroup, 'media_group', '&nbsp;', '&nbsp;', false);

    }

    /**
     * This function return HTML markup to display popup panel.
     * @access private
     * @param none.
     * @return string - HTML markup to display popup panel.
     */
    private function get_popup_markup() {

        $output = '';

        // Panel markup to set media properties.
        $attr = array('id' => 'media_properties_panel', 'style' => 'display: none;');
        $output .= html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', get_string('media_prop_header', 'kalmediares'), $attr);

        $attr = array('class' => 'bd');

        $propertiesmarkup = $this->get_media_preferences_markup();

        $output .= html_writer::tag('div', $propertiesmarkup, $attr);

        $output .= html_writer::end_tag('div');

        // Panel markup to preview media.
        $attr = array('id' => 'media_preview_panel', 'style' => 'display: none;');
        $output .= html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', get_string('media_preview_header', 'kalmediares'), $attr);

        $attr = array('class' => 'bd',
                      'id' => 'media_preview_body');

        $output .= html_writer::tag('div', '', $attr);

        return $output;
    }

    /**
     * This function return HTML markup to display thumbnail.
     * @access private
     * @param none.
     * @return string - HTML markup to display thumbnail.
     */
    private function get_thumbnail_markup() {
        global $CFG;

        $source = '';

        /*
         * tabindex -1 is required in order for the focus event to be capture
         * amongst all browsers.
         */
        $attr = array('id' => 'notification',
                      'class' => 'notification',
                      'tabindex' => '-1');
        $output = html_writer::tag('div', '', $attr);

        $source = $CFG->wwwroot . '/local/yukaltura/pix/vidThumb.png';
        $alt    = get_string('add_media', 'kalmediares');
        $title  = get_string('add_media', 'kalmediares');

        if (!empty($this->current->entry_id)) {

            $entries = new KalturaStaticEntries();

            $entryobj = KalturaStaticEntries::get_entry($this->current->entry_id, null, false);

            if (isset($entryobj->thumbnailUrl)) {
                $source = $entryobj->thumbnailUrl;
                $alt    = $entryobj->name;
                $title  = $entryobj->name;
            }

        }

        $attr = array('id' => 'media_thumbnail',
                      'src' => $source,
                      'alt' => $alt,
                      'title' => $title,
                      'onchange' => 'replaceAddMediaLabel(\'' . get_string('replace_media', 'mod_kalmediares') . '\');');

        $output .= html_writer::empty_tag('img', $attr);

        return $output;

    }


    /**
     * This function returns an array of media resource players.
     *
     * If the override configuration option is checked, then this function will
     * only return a single array entry with the overridden player
     * @ access private
     * @param none     *
     * @return array - First element will be an array whose keys are player ids
     * and values are player name.  Second element will be the default selected
     * player.  The default player is determined by the Kaltura configuraiton
     * settings (local_yukaltura).
     */
    private function get_media_resource_players() {

        // Get user's players.
        $players = local_yukaltura_get_custom_players();

        // Kaltura regular player selection.
        $choices = array(KALTURA_PLAYER_PLAYERREGULARDARK  => get_string('player_regular_dark', 'local_yukaltura'),
                         KALTURA_PLAYER_PLAYERREGULARLIGHT => get_string('player_regular_light', 'local_yukaltura'),
                         );

        if (!empty($players)) {
            $choices = $choices + $players;
        }

        // Set default player only if the user is adding a new activity instance.
        $defaultplayerid = local_yukaltura_get_player_uiconf('player_resource');

        /*
         * If the default player id does not exist in the list of choice,
         * then the user must be using a custom player id, add it to the list.
         */
        if (!array_key_exists($defaultplayerid, $choices)) {
            $choices = $choices + array($defaultplayerid => get_string('custom_player', 'kalmediares'));
        }

        // Check if player selection is globally overridden.
        if (local_yukaltura_get_player_override()) {
            return array(array( $defaultplayerid => $choices[$defaultplayerid]),
                         $defaultplayerid
                        );
        }

        return array($choices, $defaultplayerid);

    }

    /**
     * Create player properties panel markup.  Default values are loaded from
     * the javascript (see function "handle_cancel" in yukaltura.js
     * @access private
     * @param - none
     * @return string - html markup for media preferences.
     */
    private function get_media_preferences_markup() {
        $output = '';

        // Display name input box.
        $attr = array('for' => 'media_prop_name');
        $output .= html_writer::tag('label', get_string('media_prop_name', 'kalmediares'), $attr);
        $output .= '&nbsp;';

        $attr = array('type' => 'text',
                      'id' => 'media_prop_name',
                      'name' => 'media_prop_name',
                      'value' => '',
                      'maxlength' => '100');
        $output .= html_writer::empty_tag('input', $attr);
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');

        // Display section element for player design.
        $attr = array('for' => 'media_prop_player');
        $output .= html_writer::tag('label', get_string('media_prop_player', 'kalmediares'), $attr);
        $output .= '&nbsp;';

        list($options, $defaultoption) = $this->get_media_resource_players();

        $attr = array('id' => 'media_prop_player');

        $output .= html_writer::select($options, 'media_prop_player', $defaultoption, false, $attr);
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');

        // Display player dimensions radio button.
        $attr = array('for' => 'media_prop_dimensions');
        $output .= html_writer::tag('label', get_string('media_prop_dimensions', 'kalmediares'), $attr);
        $output .= '&nbsp;';

        $options = array(0 => get_string('normal', 'kalmediares'),
                         1 => get_string('widescreen', 'kalmediares')
                         );

        $attr = array('id' => 'media_prop_dimensions');
        $selected = !empty($defaults) ? $defaults['media_prop_dimensions'] : array();
        $output .= html_writer::select($options, 'media_prop_dimensions', $selected, array(), $attr);

        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');

        // Display player size drop down button.
        $attr = array('for' => 'media_prop_size');
        $output .= html_writer::tag('label', get_string('media_prop_size', 'kalmediares'), $attr);
        $output .= '&nbsp;';

        $options = array(0 => get_string('media_prop_size_large', 'kalmediares'),
                         1 => get_string('media_prop_size_small', 'kalmediares'),
                         2 => get_string('media_prop_size_custom', 'kalmediares')
                         );

        $attr = array('id' => 'media_prop_size');
        $selected = !empty($defaults) ? $defaults['media_prop_size'] : array();

        $output .= html_writer::select($options, 'media_prop_size', $selected, array(), $attr);

        // Display custom player size.
        $output .= '&nbsp;&nbsp;';

        $attr = array('type' => 'text',
                      'id' => 'media_prop_width',
                      'name' => 'media_prop_width',
                      'value' => '',
                      'maxlength' => '3',
                      'size' => '3',
                      );
        $output .= html_writer::empty_tag('input', $attr);

        $output .= '&nbsp;x&nbsp;';

        $attr = array('type' => 'text',
                      'id' => 'media_prop_height',
                      'name' => 'media_prop_height',
                      'value' => '',
                      'maxlength' => '3',
                      'size' => '3',
                      );
        $output .= html_writer::empty_tag('input', $attr);

        return $output;
    }


    /**
     * This function return media properties.
     * @access private
     * @param none.
     * @return array - media properties.
     */
    private function get_default_media_properties() {
        return $properties = array('media_prop_player' => 4674741,
                                   'media_prop_dimensions' => 0,
                                   'media_prop_size' => 0,
                                  );
    }

    /**
     * This function changes form information after media selected.
     * @access public
     * @param none.
     * @return nothing.
     */
    public function definition_after_data() {
        $mform = $this->_form;

        if (!empty($mform->_defaultValues['entry_id'])) {
            foreach ($mform->_elements as $key => $data) {

                if ($data instanceof MoodleQuickForm_group) {

                    foreach ($data->_elements as $key2 => $data2) {
                        if (0 == strcmp('add_media', $data2->_attributes['name'])) {
                            $mform->_elements[$key]->_elements[$key2]->setValue(get_string('replace_media', 'kalmediares'));
                            break;
                        }

                        if (0 == strcmp('pres_info', $data2->_attributes['name'])) {
                            $mform->_elements[$key]->_elements[$key2]->setValue('');
                            break;
                        }
                    }
                }

            }

        }

    }

}
