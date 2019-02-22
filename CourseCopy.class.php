<?php

class CourseCopy extends StudIPPlugin implements AdminCourseAction
{
    public function __construct()
    {
        bindtextdomain('CourseCopy', __DIR__ . '/locale');
        parent::__construct();
    }


    public function getAdminActionURL()
    {
        return PluginEngine::getURL($this, array(), "copy/info");
    }

    public function useMultimode() {
        if (version_compare($GLOBALS['SOFTWARE_VERSION'], "3.4.99", ">=")) {
            //Damit es im Dialog geöffnet wird
            return \Studip\Button::createAccept(_("Kopieren"), "edit", array('data-dialog' => 1));
        } else {
            return _("Kopieren");
        }
    }

    public function getAdminCourseActionTemplate($course_id, $values = null, $semester = null) {
        $factory = new Flexi_TemplateFactory(__DIR__."/views");
        $template = $factory->open("action/checkbox.php");
        $template->set_attribute("course_id", $course_id);
        $template->set_attribute("plugin", $this);
        return $template;
    }
}
