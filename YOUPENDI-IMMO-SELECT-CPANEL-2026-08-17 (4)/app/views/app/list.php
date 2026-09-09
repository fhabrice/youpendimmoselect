<div class="section-head">
  <div>
    <p class="kicker gold"><?= e($kicker ?? 'Back-office') ?></p>
    <h1 class="app-title"><?= e($title) ?></h1>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (!empty($export)): ?><a class="btn btn-outline btn-sm" href="?<?= e(qs(['export'=>'csv'])) ?>">Export CSV</a><?php endif; ?>
    <?php if (!empty($create)): ?><a class="btn btn-gold btn-sm" href="<?= e(base_url($create)) ?>"><?= e($createLabel ?? 'Ajouter') ?></a><?php endif; ?>
  </div>
</div>
<?php if (!empty($extraTop)) echo $extraTop; ?>
<?php if (!empty($filters)): ?>
<form class="filters" method="get">
  <?php foreach ($filters as $name => $opts): ?>
    <?php if (is_array($opts) && ($opts['type'] ?? '') === 'text'): ?>
      <input name="<?= e($name) ?>" value="<?= e($_GET[$name] ?? '') ?>" placeholder="<?= e($opts['label']) ?>">
    <?php elseif (is_array($opts)): ?>
      <select name="<?= e($name) ?>">
        <option value=""><?= e($opts[''] ?? $name) ?></option>
        <?php foreach ($opts as $k=>$v): if ($k==='') continue; ?>
          <option value="<?= e($k) ?>" <?= (($_GET[$name]??'')==(string)$k)?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  <?php endforeach; ?>
  <button class="btn btn-sm">Filtrer</button>
</form>
<?php endif; ?>
<div class="table-wrap">
  <table>
    <thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <?php foreach ($r as $cell): ?>
          <td><?= $cell ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td class="empty" colspan="<?= count($cols) ?>">Aucun enregistrement.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (($pg['pages'] ?? 1) > 1): ?>
  <div class="pager"><?php for($i=1;$i<=$pg['pages'];$i++): ?><a href="?<?= e(qs(['page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?></div>
<?php endif; ?>
