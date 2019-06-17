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
        //Damit es im Dialog geöffnet wird
        return \Studip\Button::createAccept(
            dgettext('CourseCopy', 'Kopieren'),
            'edit',
            array('data-dialog' => 1)
        );
    }

    public function getAdminCourseActionTemplate($course_id, $values = null, $semester = null) {
        $factory = new Flexi_TemplateFactory(__DIR__."/views");
        $template = $factory->open("action/checkbox.php");
        $template->set_attribute("course_id", $course_id);
        $template->set_attribute("plugin", $this);
        return $template;
    }
}
