<form class="default" action="<?= PluginEngine::getLink($plugin, array(), "copy/process") ?>" method="post">

    <? foreach (Request::getArray("c") as $course_id) : ?>
        <input type="hidden" name="c[]" value="<?= htmlReady($course_id) ?>">
    <? endforeach ?>

    <fieldset>
        <legend>
            <?= _("Kopieroptionen") ?>
        </legend>

        <label>
            <?= _("In Semester") ?>
            <select name="semester_id" required>
                <option value=""></option>
                <? foreach (array_reverse(Semester::getAll()) as $semester) : ?>
                    <option value="<?= htmlReady($semester->getId()) ?>"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_SEMESTER_ID == $semester->getId() ? " selected" : "" ?>>
                        <?= htmlReady($semester['name']) ?>
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
        </label>

        <label>
            <input type="checkbox"
                   name="cycles"
                   onChange="jQuery('#resource_assignments').toggle();"
                   value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_CYCLES ? " checked" : "" ?>>
            <?= _("Regelmäßige Termine mit kopieren") ?>
        </label>

        <label id="resource_assignments" style="<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_CYCLES ? "" : "display: none;" ?>">
            <input type="checkbox" name="resource_assignments" value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_RESOURCE_ASSIGNMENTS ? " checked" : "" ?>>
            <?= _("Raumbuchungen mit übernehmen") ?>
        </label>

        <label>
            <input type="checkbox" name="lock_copied_courses" value="1"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_LOCK_COPIED_COURSES ? " checked" : "" ?>>
            <?= _('Kopierte Veranstaltungen sperren') ?>
        </label>
    </fieldset>
    <div data-dialog-button>
        <?= \Studip\Button::create(_("Kopieren"), "copy", array('onclick' => "return window.confirm('"._("Wirklich kopieren?")."');")) ?>
    </div>
</form>
