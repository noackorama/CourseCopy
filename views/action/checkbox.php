<? if (Seminar_Perm::get()->have_studip_perm('dozent', $course_id) && !LockRules::check($course_id, 'seminar_copy')) : ?>
<input type="checkbox" name="c[]" value="<?= htmlReady($course_id) ?>">
<? endif ?>