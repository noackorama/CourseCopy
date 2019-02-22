<?php

class CopyController extends PluginController
{
    public function info_action()
    {
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
            CSRFProtection::verifyUnsafeRequest();

            $dozent = null;
            if (Request::option("dozent_id")) {
                $dozent = User::find(Request::option("dozent_id"));
                if (!$dozent['perms'] === "dozent") {
                    $dozent = null;
                }
            }
            $semester = Semester::find(Request::option("semester_id"));
            if ($semester) {
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

                        if (version_compare($GLOBALS['SOFTWARE_VERSION'], "3.99.99", "<")) {
                            //Document folders (Stud.IP 3.5)
                            $insert_folder = DBManager::get()->prepare("
                                INSERT IGNORE INTO folder
                                SET folder_id = MD5(CONCAT(:seminar_id, 'allgemeine_dateien')),
                                    range_id = :seminar_id,
                                    user_id = :user_id,
                                    name = :name,
                                    description = :description,
                                    mkdate = UNIX_TIMESTAMP(),
                                    chdate = UNIX_TIMESTAMP()
                            ");
                            $insert_folder->execute(array(
                                'seminar_id' => $newcourse->getId(),
                                'user_id' => $GLOBALS['user']->id,
                                'name' => _("Allgemeiner Dateiordner"),
                                'description' => _("Ablage für allgemeine Ordner und Dokumente der Veranstaltung")
                            ));
                        }

                        $copy_regular_room_assignments = false;
                        if (Request::get('regular_room_assignments')) {
                            $copy_regular_room_assignments = true;
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

                                if ($copy_regular_room_assignments) {
                                    //Check which room have been assigned to the
                                    //"old" course's dates. If it was always
                                    //the same room we can copy the regular
                                    //assignments and use that room, if it is
                                    //available.

                                    $old_room = null;
                                    $room = null;
                                    foreach ($cycledate->dates as $date) {
                                        if ($date->room_assignment) {
                                            if ($date->room_assignment->resource instanceof Resource) {
                                                $old_room = $room;
                                                $room = $date->room_assignment->resource->getDerivedClassInstance();
                                                if (($old_room instanceof Room) && ($room->id != $old_room->id)) {
                                                    //The rooms differ: we can skip
                                                    //copying the assignments.
                                                    $room = null;
                                                    break;
                                                }
                                            }
                                        }
                                    }

                                    if ($room == null) {
                                        continue;
                                    }

                                    //Check the user's permissions:
                                    //If the user has permissions to book the room,
                                    //create room bookings. If no booking permissions
                                    //are defined, create room requests.

                                    $has_booking_rights = $room->userHasPermission(
                                        User::findCurrent(),
                                        'autor'
                                    );

                                    if ($has_booking_rights) {
                                        foreach ($newcycle->dates as $date) {
                                            //Create new assignments.
                                            $assignment = new ResourceAssignment();
                                            $assignment->resource_id = $room->id;
                                            $assignment->range_id = $newcourse->id;
                                            $assignment->booking_user_id = $GLOBALS['user']->id;
                                            $assignment->assignment_type = '0';
                                            $assignment->begin = $date->date;
                                            $assignment->end = $date->end_time;
                                            $assignment->repeat_end = '0';
                                            $assignment->repeat_quantity = '0';
                                            $assignment->repetition_interval = '';
                                            $assignment->store();
                                        }
                                    } else {
                                        //Create a resource request for the cycle:
                                        $request = new ResourceRequest();
                                        $request->category_id = $room->category_id;
                                        $request->resource_id = $room->id;
                                        $request->metadate_id = $newcycle->id;
                                        $request->user_id = $GLOBALS['user']->id;
                                        $request->store();
                                    }
                                }
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
