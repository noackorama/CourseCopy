<?php

class CopyController extends PluginController
{
    public function info_action()
    {
        if (Request::getArray("c")) {
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
                "ORDER BY Vorname, Nachname", _("Lehrendennamen eingeben"), "user_id");
            $this->semesters = array_reverse(Semester::getAll());
            $this->semester = UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_SEMESTER_ID
                ? Semester::find(UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_SEMESTER_ID)
                : $this->semesters[0];
            if (Seminar_Perm::get()->have_perm('admin')) {
                $this->have_coursegroups = true;
            }
        } else {
            throw new Trails_Exception(400);
        }
    }

    public function semester_start_und_ende_action($semester_id)
    {
        $this->semesters = array_reverse(Semester::getAll());
        $this->semester = Semester::find($semester_id);
    }

    public function process_action()
    {
        if (Request::isPost() && count(Request::getArray("c"))) {
            $params = [
                "semester_id", "dozent_id", "lock_copied_courses",
                "invisible_copied_courses", "cycles", "resource_assignments",
                "week_offset", "end_offset", "copy_tutors", "with_children",
                "contents_scm", "contents_documents"
            ];
            foreach ($params as $param) {
                $config_name = "COURSECOPY_SETTINGS_".strtoupper($param);
                UserConfig::get($GLOBALS['user']->id)->store($config_name, Request::get($param));
            }
            if (!Request::get("dozent_id_parameter")) { //quicksearch-special
                UserConfig::get($GLOBALS['user']->id)->store("COURSECOPY_SETTINGS_DOZENT_ID", "");
            }

            $dozent = null;
            if (UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_DOZENT_ID) {
                $dozent = User::find(UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_DOZENT_ID);
                if (!$dozent['perms'] === "dozent") {
                    $dozent = null;
                }
            }
            $semester = Semester::find(Request::option("semester_id"));
            if ($semester) {
                $lock_copied_courses = Request::get('lock_copied_courses');
                $invisible_copied_courses = Request::get('invisible_copied_courses');
                $course_ids = Request::getArray("c");
                if (Request::get("with_children")) {
                    $statement = DBManager::get()->prepare("
                        SELECT DISTINCT Seminar_id
                        FROM seminare
                        WHERE seminare.parent_course IN (?)
                    ");
                    $statement->execute([$course_ids]);
                    $course_ids = array_merge($course_ids, $statement->fetchAll(PDO::FETCH_COLUMN, 0));
                    $course_ids = array_unique($course_ids);
                    //remind that the children should now be at the end of the array
                }
                $copies = [];
                foreach ($course_ids as $course_id) {
                    $oldcourse = Course::find($course_id);

                    if ($oldcourse && Seminar_Perm::get()->have_studip_perm('dozent', $course_id)) {
                        $newcourse = new Course();
                        $newcourse->setData($oldcourse->toArray());
                        if ($newcourse['parent_course']) {
                            $newcourse['parent_course'] = isset($copies[$newcourse['parent_course']])
                                ? $copies[$newcourse['parent_course']]
                                : null;
                        }
                        $newcourse['chdate'] = time();
                        $newcourse['mkdate'] = time();
                        $newcourse->setId($newcourse->getNewId());
                        $newcourse['start_time'] = $semester['beginn'];
                        if ($invisible_copied_courses) {
                            $newcourse['visible'] = 0;
                        }
                        $newcourse->store();
                        $copies[$course_id] = $newcourse->getId();

                        //copy avatar
                        if (CourseAvatar::getAvatar($course_id)->is_customized()) {
                            CourseAvatar::getAvatar($newcourse->getId())->createFrom(
                                CourseAvatar::getAvatar($course_id)->getFilename(Avatar::NORMAL)
                            );
                        }

                        //Check if the old course is in at least one course
                        //group ("LV-Gruppe") of the module managemeny system:
                        $course_groups = Lvgruppe::findBySeminar($course_id);
                        if ($course_groups) {
                            //Add the copied course to all found course groups:
                            foreach ($course_groups as $course_group) {
                                $course_group->addSeminar($newcourse->id);
                            }
                        }

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

                        //Tutor_innen
                        if (Request::get("copy_tutors")) {
                            foreach ($oldcourse->members->filter(function ($member) {
                                return $member['status'] === "tutor";
                            }) as $tutormember) {
                                $coursemember = new CourseMember();
                                $coursemember->setData($tutormember->toArray());
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

                        $wanted_course_configs = array("KURSVORABINFO_INFO");
                        foreach ($wanted_course_configs as $config) {
                            if (CourseConfig::get($course_id)->$config) {
                                CourseConfig::get($newcourse->getId())->store($config, CourseConfig::get($course_id)->$config);
                            }
                        }


                        if (Request::get("cycles")) {
                            foreach ($oldcourse->cycles as $cycledate) {

                                $statement = DBManager::get()->prepare("
                                    SELECT date_typ
                                    FROM (
                                        SELECT termine.date_typ, COUNT(*) AS number
                                        FROM termine
                                        WHERE termine.metadate_id = :metadate_id
                                        GROUP BY termine.date_typ
                                    ) AS counter
                                    ORDER BY number DESC
                                    LIMIT 1
                                ");
                                $statement->execute(array('metadate_id' => $cycledate->getId()));
                                $date_type = $statement->fetch(PDO::FETCH_COLUMN, 0);

                                $newcycle = new SeminarCycleDate();
                                $newcycle->setData($cycledate->toArray());
                                $newcycle->setId($newcycle->getNewId());
                                $newcycle['seminar_id'] = $newcourse->getId();
                                $newcycle['week_offset'] = Request::get("week_offset");
                                $newcycle['end_offset'] = Request::get("end_offset") !== 10000
                                    ? Request::get("end_offset")
                                    : floor(($semester['vorles_ende'] - $semester['vorles_beginn']) / (86400 * 7));
                                $newcycle['mkdate'] = time();
                                $newcycle['chdate'] = time();
                                $newcycle->store();

                                foreach ($newcycle->dates as $newdate) {
                                    $newdate['date_typ'] = $date_type;
                                    $newdate->store();
                                }

                                if (Request::get("resource_assignments")) {
                                    $statement = DBManager::get()->prepare("
                                        SELECT resource_id
                                        FROM (
                                            SELECT resource_id, COUNT(*) AS number
                                            FROM termine
                                                INNER JOIN resources_assign ON (resources_assign.assign_user_id = termine.termin_id)
                                            WHERE termine.metadate_id = :metadate_id
                                            GROUP BY resources_assign.resource_id
                                        ) AS counter
                                        ORDER BY number DESC
                                        LIMIT 1
                                    ");
                                    $statement->execute(array('metadate_id' => $cycledate->getId()));
                                    $resource_id = $statement->fetch(PDO::FETCH_COLUMN, 0);
                                    if ($resource_id) {
                                        foreach ($newcycle->dates as $newdate) {
                                            $singledate = new SingleDate($newdate);
                                            $singledate->bookRoom($resource_id);
                                        }
                                    }
                                }
                            }
                        }

                        //Inhalte:
                        if (Request::get("contents_scm")) {
                            foreach (StudipScmEntry::findByRange_id($oldcourse->getId(), 'ORDER BY position ASC') as $scm) {
                                $new_scm = new StudipScmEntry();
                                $new_scm->setData($scm->toRawArray());
                                $new_scm->setId($new_scm->getNewId());
                                $new_scm['range_id'] = $newcourse->getId();
                                $new_scm->store();
                            }
                        }
                        if (Request::get("contents_documents")) {
                            $oldtopfolder = Folder::findTopFolder($oldcourse->getId());
                            $newtopfolder = Folder::findTopFolder($newcourse->getId());
                            foreach ($oldtopfolder->file_refs as $fileref) {
                                FileManager::copyFile(
                                    $fileref->getFileType(),
                                    $newtopfolder->getTypedFolder(),
                                    User::findCurrent()
                                );
                            }
                            foreach ($oldtopfolder->subfolders as $subfolder) {
                                FileManager::copyFolder(
                                    $subfolder->getTypedFolder(),
                                    $newtopfolder->getTypedFolder(),
                                    User::findCurrent()
                                );
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
