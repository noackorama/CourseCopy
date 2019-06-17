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
                    <option value="<?= htmlReady($semester->getId()) ?>"><?= htmlReady($semester['name']) ?></option>
                <? endforeach ?>
            </select>
        </label>

        <label>
            <?= _("Dozenten ersetzen durch ...") ?>
            <?= QuickSearch::get("dozent_id", $dozentensearch)->render() ?>
        </label>

        <label>
            <input type="checkbox" name="cycles" value="1" checked>
            <?= _("Regelmäßige Termine mit kopieren") ?>
        </label>
        <label>
            <input type="checkbox" name="lock_copied_courses" value="1">
            <?= _('Kopierte Veranstaltungen sperren') ?>
        </label>
    </fieldset>
    <div data-dialog-button>
        <?= \Studip\Button::create(_("Kopieren"), "copy", array('onclick' => "return window.confirm('"._("Wirklich kopieren?")."');")) ?>
    </div>
</form>
