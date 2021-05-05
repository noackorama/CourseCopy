<? if (Seminar_Perm::get()->have_studip_perm('dozent', $course_id)) : ?>
<input type="checkbox" name="c[]" value="<?= htmlReady($course_id) ?>">
<? endif ?>