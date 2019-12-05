<form class="default" action="<?= PluginEngine::getLink($plugin, array(), "copy/process") ?>" method="post">

    <? foreach (Request::getArray("c") as $course_id) : ?>
        <input type="hidden" name="c[]" value="<?= htmlReady($course_id) ?>">
    <? endforeach ?>

    <fieldset>
        <legend>
            <?= _("Allgemein") ?>
        </legend>

        <label>
            <?= _("In Semester") ?>
            <select name="semester_id" required onChange="var week_offset = jQuery('select[name=week_offset]').val(); var end_offset = jQuery('select[name=end_offset]').val(); jQuery('#semester_start_und_ende').load(STUDIP.URLHelper.getURL('plugins.php/coursecopy/copy/semester_start_und_ende/' + this.value), function () { jQuery('select[name=week_offset]').val(week_offset); jQuery('select[name=end_offset]').val(end_offset); }); ">
                <option value=""></option>
                <? foreach ($semesters as $sem) : ?>
                    <option value="<?= htmlReady($sem->getId()) ?>"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_SEMESTER_ID == $sem->getId() ? " selected" : "" ?>>
                        <?= htmlReady($sem['name']) ?>
                    </option>
                <? endforeach ?>
            </select>
        </label>

        <label>
            <?= _("Lehrende ersetzen durch ...") ?>
            <?
            $qs = QuickSearch::get("dozent_id", $dozentensearch);
            if (UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_DOZENT_ID) {
                $qs->defaultValue(
                    UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_DOZENT_ID,
                    get_fullname(UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_DOZENT_ID)
                );
            }
            echo $qs->render()
            ?>

            <label>
                <input type="checkbox" name="copy_tutors" value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_COPY_TUTORS ? " checked" : "" ?>>
                <?= _('Tutor/-innen mit übernehmen') ?>
            </label>

            <label>
                <input type="checkbox" name="lock_copied_courses" value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_LOCK_COPIED_COURSES ? " checked" : "" ?>>
                <?= _('Kopierte Veranstaltungen sperren') ?>
            </label>

            <label>
                <input type="checkbox" name="invisible_copied_courses" value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_INVISIBLE_COPIED_COURSES ? " checked" : "" ?>>
                <?= _('Kopierte Veranstaltungen unsichtbar schalten') ?>
            </label>
        </label>

    </fieldset>

    <fieldset>
        <legend>
            <?= _("Termine") ?>
        </legend>

        <label>
            <input type="checkbox"
                   name="cycles"
                   onChange="jQuery('#resource_assignments, #semester_start_und_ende').toggle();"
                   value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_CYCLES ? " checked" : "" ?>>
            <?= _("Regelmäßige Termine mit kopieren") ?>
        </label>

        <div id="semester_start_und_ende" style="<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_CYCLES ? "" : "display: none;" ?>">
            <?= $this->render_partial("copy/semester_start_und_ende.php") ?>
        </div>

        <label id="resource_assignments" style="<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_CYCLES ? "" : "display: none;" ?>">
            <input type="checkbox" name="resource_assignments" value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_RESOURCE_ASSIGNMENTS ? " checked" : "" ?>>
            <?= _("Raumbuchungen mit übernehmen") ?>
        </label>

    </fieldset>

    <div data-dialog-button>
        <?= \Studip\Button::create(_("Kopieren"), "copy", array('onclick' => "return window.confirm('"._("Wirklich kopieren?")."');")) ?>
    </div>
</form>
