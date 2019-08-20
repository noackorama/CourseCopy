<label>
    <?= _("Startwoche") ?>
    <select name="week_offset">
        <? $i = 0 ?>
        <? while ($semester['vorles_beginn'] + 86400 * 7 * $i < $semester['vorles_ende']) : ?>
            <option value="<?= $i ?>"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_WEEK_OFFSET == $i ? " checked" : "" ?>>
                <?= sprintf(_("%s. Semesterwoche (ab %s)"), $i + 1, date("d.m.Y", $semester['vorles_beginn'] + 86400 * 7 * $i)) ?>
            </option>
            <? $i++ ?>
        <? endwhile ?>
    </select>
</label>

<label>
    <?= _("Endwoche") ?>
    <select name="end_offset">
        <option value="10000"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_END_OFFSET == 10000 ? " checked" : "" ?>>
            <?= _("Letzte Semesterwoche") ?>
        </option>
        <? $i = floor(($semester['vorles_ende'] - $semester['vorles_beginn']) / (86400 * 7)) - 1 ?>
        <? while ($i >= 0) : ?>
            <option value="<?= $i ?>"<?= UserConfig::get($GLOBALS['user']->id)->COURSECOPY_SETTINGS_END_OFFSET == $i ? " checked" : "" ?>>
                <?= sprintf(_("%s. Semesterwoche (ab %s)"), $i + 1, date("d.m.Y", $semester['vorles_beginn'] + 86400 * 7 * $i)) ?>
            </option>
            <? $i-- ?>
        <? endwhile ?>
    </select>
</label>