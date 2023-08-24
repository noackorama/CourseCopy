<?php
$start_weeks = $semester->getStartWeeks();
$last_week = count($start_weeks) - 1;
?>
<label>
    <?= _("Startwoche") ?>
    <select name="week_offset">
        <? foreach ($start_weeks as $i => $text) : ?>
            <option value="<?= $i ?>"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_WEEK_OFFSET == $i ? " checked" : "" ?>>
                <?= $text?>
            </option>
        <? endforeach; ?>
    </select>
</label>

<label>
    <?= _("Endwoche") ?>
    <select name="end_offset">
        <? foreach (array_reverse($start_weeks, true) as $i => $text) : ?>
        <? if ($i == $last_week) : ?>
            <option value="last">
                <?= _('Semesterende: ') . $text?>
            </option>
        <? else : ?>
        <option value="<?= $i ?>"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_END_OFFSET == $i ? " checked" : "" ?>>
            <?= $text?>
        </option>
        <? endif ?>
        <? endforeach ?>
    </select>
</label>
