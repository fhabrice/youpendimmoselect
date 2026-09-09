<?php
$provinceOptions = provinces_rdc();
$cityOptions = cities();
$inputClass = 'tw-mt-2 tw-w-full tw-rounded-xl tw-border tw-border-slate-300 tw-bg-white tw-px-4 tw-py-3 tw-text-slate-900 tw-outline-none tw-transition focus:tw-border-youpendi-500 focus:tw-ring-4 focus:tw-ring-sky-100';
$labelClass = 'tw-block tw-text-sm tw-font-semibold tw-text-slate-700';
?>
<section class="tw-bg-slate-50 tw-py-10 md:tw-py-16">
  <div class="container tw-max-w-6xl">
    <div class="tw-overflow-hidden tw-rounded-3xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-youpendi lg:tw-grid lg:tw-grid-cols-[0.85fr_1.15fr]">
      <aside class="tw-relative tw-isolate tw-overflow-hidden tw-bg-gradient-to-br tw-from-youpendi-900 tw-via-youpendi-700 tw-to-emerald-600 tw-p-8 tw-text-white md:tw-p-12" aria-label="Avantages de l’espace bailleur">
        <div class="tw-absolute tw--right-20 tw--top-20 tw--z-10 tw-h-64 tw-w-64 tw-rounded-full tw-bg-white/10"></div>
        <div class="tw-absolute tw--bottom-24 tw--left-20 tw--z-10 tw-h-72 tw-w-72 tw-rounded-full tw-bg-sky-300/10"></div>
        <span class="tw-inline-flex tw-rounded-full tw-border tw-border-white/30 tw-bg-white/10 tw-px-4 tw-py-2 tw-text-xs tw-font-bold tw-uppercase tw-tracking-[0.18em]">Espace propriétaire</span>
        <h1 class="tw-mt-6 tw-text-3xl tw-font-extrabold tw-leading-tight md:tw-text-4xl">Valorisez vos biens partout en RDC</h1>
        <p class="tw-mt-4 tw-text-base tw-leading-7 tw-text-sky-50">Créez votre espace bailleur et confiez vos annonces à une équipe immobilière présente dans les 26 provinces.</p>
        <ul class="tw-mt-8 tw-grid tw-gap-4 tw-p-0" role="list">
          <li class="tw-flex tw-items-start tw-gap-3"><span class="tw-grid tw-h-7 tw-w-7 tw-shrink-0 tw-place-items-center tw-rounded-full tw-bg-white tw-font-black tw-text-youpendi-700">✓</span><span><strong class="tw-block">Publication encadrée</strong><small class="tw-text-sky-100">Chaque bien est contrôlé avant sa mise en ligne.</small></span></li>
          <li class="tw-flex tw-items-start tw-gap-3"><span class="tw-grid tw-h-7 tw-w-7 tw-shrink-0 tw-place-items-center tw-rounded-full tw-bg-white tw-font-black tw-text-youpendi-700">✓</span><span><strong class="tw-block">Suivi centralisé</strong><small class="tw-text-sky-100">Biens, locataires, revenus et documents dans un seul espace.</small></span></li>
          <li class="tw-flex tw-items-start tw-gap-3"><span class="tw-grid tw-h-7 tw-w-7 tw-shrink-0 tw-place-items-center tw-rounded-full tw-bg-white tw-font-black tw-text-youpendi-700">✓</span><span><strong class="tw-block">Couverture nationale</strong><small class="tw-text-sky-100">Sélection précise de la province et de la ville en RDC.</small></span></li>
        </ul>
        <p class="tw-mb-0 tw-mt-10 tw-border-t tw-border-white/20 tw-pt-5 tw-text-sm tw-text-sky-100">Déjà inscrit ? <a class="tw-font-bold tw-text-white tw-underline tw-underline-offset-4" href="<?= e(base_url('connexion')) ?>">Se connecter à mon espace</a></p>
      </aside>

      <div class="tw-p-6 md:tw-p-10 lg:tw-p-12">
        <p class="tw-m-0 tw-text-xs tw-font-extrabold tw-uppercase tw-tracking-[0.18em] tw-text-youpendi-600">Inscription bailleur</p>
        <h2 class="tw-mb-2 tw-mt-2 tw-text-2xl tw-font-extrabold tw-text-slate-900 md:tw-text-3xl">Créer mon compte propriétaire</h2>
        <p class="tw-mt-0 tw-text-sm tw-leading-6 tw-text-slate-600">Renseignez vos coordonnées. Les champs marqués d’un astérisque sont obligatoires.</p>

        <?php foreach (flashes() as $type => $msgs): foreach ($msgs as $m): ?>
          <div class="flash flash-<?= e($type === 'error' ? 'error' : $type) ?> tw-mt-4"><?= e($m) ?></div>
        <?php endforeach; endforeach; ?>

        <form class="tw-mt-7 tw-grid tw-gap-5" method="post" action="<?= e(base_url('inscription-bailleur')) ?>">
          <?= csrf_field() ?>
          <div class="tw-grid tw-gap-5 md:tw-grid-cols-2">
            <label class="<?= $labelClass ?>">Prénom *
              <input class="<?= $inputClass ?>" name="first_name" autocomplete="given-name" required value="<?= e(old('first_name')) ?>">
            </label>
            <label class="<?= $labelClass ?>">Nom
              <input class="<?= $inputClass ?>" name="last_name" autocomplete="family-name" value="<?= e(old('last_name')) ?>">
            </label>
            <label class="<?= $labelClass ?>">E-mail *
              <input class="<?= $inputClass ?>" type="email" name="email" autocomplete="email" required value="<?= e(old('email')) ?>">
            </label>
            <label class="<?= $labelClass ?>">Téléphone / WhatsApp *
              <input class="<?= $inputClass ?>" type="tel" name="phone" autocomplete="tel" required value="<?= e(old('phone')) ?>">
            </label>
            <label class="<?= $labelClass ?>">Province *
              <select class="<?= $inputClass ?>" name="province" required>
                <option value="">Sélectionner une province</option>
                <?php foreach ($provinceOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= old('province') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label class="<?= $labelClass ?>">Ville *
              <select class="<?= $inputClass ?>" name="city" required data-selected-city="<?= e(old('city')) ?>">
                <option value="">Sélectionner une ville</option>
                <?php foreach ($cityOptions as $city): ?><option value="<?= e($city) ?>" <?= old('city') === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?>
              </select>
            </label>
          </div>
          <label class="<?= $labelClass ?>">Adresse
            <input class="<?= $inputClass ?>" name="address" autocomplete="street-address" placeholder="Commune, quartier, avenue…" value="<?= e(old('address')) ?>">
          </label>
          <div class="tw-grid tw-gap-5 md:tw-grid-cols-2">
            <label class="<?= $labelClass ?>">Mot de passe * <small class="tw-font-normal tw-text-slate-500">(8 caractères minimum)</small>
              <input class="<?= $inputClass ?>" type="password" name="password" autocomplete="new-password" required minlength="8">
            </label>
            <label class="<?= $labelClass ?>">Confirmation *
              <input class="<?= $inputClass ?>" type="password" name="password2" autocomplete="new-password" required minlength="8">
            </label>
          </div>
          <label class="tw-flex tw-items-start tw-gap-3 tw-text-sm tw-leading-6 tw-text-slate-600">
            <input class="tw-mt-1 tw-h-4 tw-w-4 tw-rounded tw-border-slate-300 tw-accent-youpendi-600" type="checkbox" required>
            <span>J’accepte que mes informations soient utilisées pour la gestion de mon compte et de mes biens.</span>
          </label>
          <button class="tw-inline-flex tw-w-full tw-items-center tw-justify-center tw-rounded-xl tw-bg-youpendi-600 tw-px-6 tw-py-3.5 tw-text-base tw-font-extrabold tw-text-white tw-shadow-lg tw-shadow-sky-200 tw-transition hover:tw-bg-youpendi-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-sky-200" type="submit">Créer mon compte et ouvrir mon espace</button>
          <p class="tw-m-0 tw-text-center tw-text-sm tw-text-slate-500">Vos données sont protégées et ne sont jamais publiées sans votre accord.</p>
        </form>
      </div>
    </div>
  </div>
</section>
