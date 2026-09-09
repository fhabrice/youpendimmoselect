<?php $u = user(); ?>
<div class="owner-bar">
  <div>
    <strong>Espace bailleur</strong>
    <?php if ($u): ?><div class="user-mini"><?= e(full_name($u) ?: ($u['email'] ?? '')) ?></div><?php endif; ?>
  </div>
  <div class="owner-bar-actions">
    <a class="btn btn-sm btn-outline" href="<?= e(base_url('espace/proprietaire')) ?>">Tableau de bord</a>
    <a class="btn btn-sm" href="<?= e(base_url('espace/proprietaire/ajouter')) ?>">+ Publier</a>
    <a class="btn btn-sm btn-outline" href="<?= e(base_url('espace/proprietaire/rapport')) ?>">Rapport</a>
    <a class="btn btn-sm btn-logout" href="<?= e(base_url('deconnexion')) ?>">Déconnexion</a>
  </div>
</div>
