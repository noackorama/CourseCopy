<?php

class CopyController extends PluginController
{
    public function info_action()
    {
        PageLayout::setTitle(_("Wie soll kopiert werden?"));
        $this->dozentensearch = new SQLSearch(
            "SELECT DISTINCT auth_user_md5.user_id, CONCAT(auth_user_md5.Vorname, \" \", auth_user_md5.Nachname), auth_user_md5.perms, auth_user_md5.username " .
            "FROM auth_user_md5 LEFT JOIN user_info ON (user_info.user_id = auth_user_md5.user_id) " .
            "WHERE (CONCAT(auth_user_md5.Vorname, \" \", auth_user_md5.Nachname) LIKE :input " .
                "OR CONCAT(auth_user_md5.Nachname, \" \", auth_user_md5.Vorname) LIKE :input " .
                "OR CONCAT(auth_user_md5.Nachname, \", \", auth_user_md5.Vorname) LIKE :input " .
                "OR auth_user_md5.username LIKE :input) " .
                "AND " . get_vis_query() . " " .
                "AND auth_user_md5.perms = 'dozent' " .
            "ORDER BY Vorname, Nachname", _("Dozentennamen eingeben"), "user_id");
    }

    public function process_action()
    {
        if (Request::isPost()) {
            $dozent = null;
            if (Request::option("dozent_id")) {
                $dozent = User::find(Request::option("dozent_id"));
                if (!$dozent['perms'] === "dozent") {
                    $dozent = null;
                }
            }
            $semester = Semester::find(Request::option("semester_id"));
            if ($semester) {
                $lock_copied_courses = Request::get('lock_copied_courses');
                foreach (Request::getArray("c") as $course_id) {
                    $oldcourse = Course::find($course_id);

                    if ($oldcourse) {
                        $newcourse = new Course();
                        $newcourse->setData($oldcourse->toArray());
                        $newcourse['chdate'] = time();
                        $newcourse['mkdate'] = time();
                        $newcourse->setId($newcourse->getNewId());
                        $newcourse['start_time'] = $semester['beginn'];
                        $newcourse->store();

                        if ($lock_copied_courses) {
                            //Get the ID of the locked admission courseset:
                            $locked_admission_id = CourseSet::getGlobalLockedAdmissionSetId();
                            if ($locked_admission_id) {
                                $locked_admission = new CourseSet($locked_admission_id);
                                $locked_admission->addCourse($newcourse->id);
                                $locked_admission->store();
                            }
                        }

                        //Dozenten
                        if ($dozent) {
                            $coursemember = new CourseMember();
                            $coursemember['user_id'] = $dozent->getId();
                            $coursemember['seminar_id'] = $newcourse->getId();
                            $coursemember['status'] = "dozent";
                            $coursemember->store();
                        } else {
                            foreach ($oldcourse->members->filter(function ($member) {
                                return $member['status'] === "dozent";
                            }) as $dozentmember) {
                                $coursemember = new CourseMember();
                                $coursemember->setData($dozentmember->toArray());
                                $coursemember['seminar_id'] = $newcourse->getId();
                                $coursemember['mkdate'] = time();
                                $coursemember->store();
                            }
                        }

                        //Studienbereiche
                        $statement = DBManager::get()->prepare("
                            INSERT IGNORE INTO seminar_sem_tree
                            SET seminar_id = :course_id,
                                sem_tree_id = :sem_tree_id
                        ");
                        foreach ($oldcourse->study_areas as $studyarea) {
                            $statement->execute(array(
                                'course_id' => $newcourse->getId(),
                                'sem_tree_id' => $studyarea->getId()
                            ));
                        }

                        //Beteiligte Einrichtungen
                        $statement = DBManager::get()->prepare("
                            INSERT IGNORE INTO seminar_inst
                            SET seminar_id = :course_id,
                                institut_id = :institut_id
                        ");
                        foreach ($oldcourse->institutes as $institute) {
                            $statement->execute(array(
                                'course_id' => $newcourse->getId(),
                                'institut_id' => $institute->getId()
                            ));
                        }

                        //Datenfelder
                        foreach ($oldcourse->datafields as $datafieldentry) {
                            $newentry = new DatafieldEntryModel();
                            $newentry->setData($datafieldentry->toArray());
                            $newentry['range_id'] = $newcourse->getId();
                            $newentry['mkdate'] = time();
                            $newentry['chdate'] = time();
                            $newentry->store();
                        }

                        if (Request::get("cycles")) {
                            foreach ($oldcourse->cycles as $cycledate) {
                                $newcycle = new SeminarCycleDate();
                                $newcycle->setData($cycledate->toArray());
                                $newcycle->setId($newcycle->getNewId());
                                $newcycle['seminar_id'] = $newcourse->getId();
                                $newcycle['mkdate'] = time();
                                $newcycle['chdate'] = time();
                                $newcycle->store();
                            }
                        }
                    }
                }
                PageLayout::postSuccess(_("Die Veranstaltungen wurden erfolgreich kopiert."));
            }
        }
        $this->redirect(URLHelper::getURL("dispatch.php/admin/courses/index"));
    }
}
