<section style="padding-top:28px">
  <div class="container">
    <p class="kicker gold">Immo Stay · <?= e($p['reference']) ?></p>
    <h1 class="page-title"><?= e($stay['name'] ?: $p['title']) ?></h1>
    <div class="gallery" style="margin:18px 0">
      <img class="gallery-main" src="<?= e(photo_url($photos[0]['path'] ?? null)) ?>" alt="<?= e($stay['name'] ?: $p['title']) ?>" style="min-height:340px;object-fit:cover"
           onerror="this.onerror=null;this.src='<?= e(photo_placeholder()) ?>'">
      <div class="stack">
        <?php foreach (array_slice($photos,1,3) as $ph): ?>
          <img src="<?= e(photo_url($ph['path'])) ?>" alt="" style="height:110px;object-fit:cover"
               onerror="this.onerror=null;this.src='<?= e(photo_placeholder()) ?>'">
        <?php endforeach; ?>
      </div>
    </div>
    <div class="prop-grid">
      <div class="panel">
        <p><?= nl2br(e($p['description'])) ?></p>
        <p><?= (int)$stay['guests'] ?> voyageurs · <?= (int)$p['bedrooms'] ?> ch. · <?= (int)$stay['beds'] ?> lits</p>
        <h3>Règles</h3>
        <p><?= nl2br(e($stay['rules'])) ?></p>
        <p class="muted">Check-in <?= e($stay['checkin_from']) ?> · Check-out <?= e($stay['checkout_before']) ?></p>
        <h3>Disponibilités (30 jours)</h3>
        <div class="cal">
          <?php foreach (['L','M','M','J','V','S','D'] as $h): ?><div class="h"><?= $h ?></div><?php endforeach; ?>
          <?php foreach ($calendar as $d): ?>
            <div class="d <?= $d['busy']?'busy':'ok' ?>"><?= (int)substr($d['date'],8,2) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <aside class="panel">
        <div class="card-type">Location journalière</div>
        <div class="price" style="font-size:28px"><?= e(money($stay['price_night'])) ?> <span class="muted" style="font-size:16px">/ nuit</span></div>
        <p>Frais : <?= e(money($stay['extra_fees'])) ?></p>
        <form method="post" action="<?= e(base_url('immo-stay/'.$p['id'].'/reserver')) ?>">
          <?= csrf_field() ?>
          <label class="fld">Arrivée<input type="date" name="checkin" required></label>
          <label class="fld">Départ<input type="date" name="checkout" required></label>
          <label class="fld">Voyageurs<input type="number" name="guests" min="1" max="<?= (int)$stay['guests'] ?>" value="1"></label>
          <label class="fld">Nom<input name="guest_name" required value="<?= e(full_name(user() ?? [])) ?>"></label>
          <label class="fld">Téléphone<input name="guest_phone" required value="<?= e(user()['phone'] ?? '') ?>"></label>
          <label class="fld">E-mail<input type="email" name="guest_email" value="<?= e(user()['email'] ?? '') ?>"></label>
          <button class="btn btn-gold btn-block">Demander une réservation</button>
        </form>
      </aside>
    </div>
  </div>
</section>
