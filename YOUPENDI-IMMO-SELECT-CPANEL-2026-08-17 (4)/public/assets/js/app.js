(function () {
  var b = document.getElementById('burger');
  var n = document.getElementById('pubnav');
  if (b && n) b.addEventListener('click', function () { n.classList.toggle('open'); });
  var sb = document.getElementById('sideburger');
  var side = document.getElementById('sidebar');
  if (sb && side) sb.addEventListener('click', function () { side.classList.toggle('open'); });

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  var city = document.querySelector('[name=city],[name=ville]');
  var commune = document.querySelector('[name=commune]');
  var quartier = document.querySelector('[name=quartier]');
  var mapC = window.YP_COMMUNES || {};
  var mapQ = window.YP_QUARTIERS || {};
  var mapProvinces = window.YP_CITIES_BY_PROVINCE || {};

  // Chaque formulaire province/ville est autonome. Les villes sont reconstruites
  // depuis la carte officielle embarquée : aucune dépendance CDN n'est requise.
  document.querySelectorAll('select[name="province"]').forEach(function (provinceSelect) {
    var form = provinceSelect.closest('form') || document;
    var citySelect = form.querySelector('select[name="city"], select[name="ville"]');
    if (!citySelect) return;
    var selectedCity = citySelect.getAttribute('data-selected-city') || citySelect.value || '';
    function citiesForProvince() {
      if (provinceSelect.value && mapProvinces[provinceSelect.value]) {
        return mapProvinces[provinceSelect.value];
      }
      return Object.keys(mapProvinces).reduce(function (all, province) {
        return all.concat(mapProvinces[province] || []);
      }, []).filter(function (value, index, all) { return all.indexOf(value) === index; }).sort();
    }
    function rebuildCities(keep) {
      var allowed = citiesForProvince();
      var current = keep || '';
      citySelect.innerHTML = '';
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = provinceSelect.value ? 'Sélectionner une ville' : 'Toutes les villes';
      citySelect.appendChild(placeholder);
      allowed.forEach(function (value) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        option.selected = value === current;
        citySelect.appendChild(option);
      });
      if (current && allowed.indexOf(current) === -1) citySelect.value = '';
    }
    rebuildCities(selectedCity);
    provinceSelect.addEventListener('change', function () {
      rebuildCities('');
      citySelect.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  function fill(sel, arr, keep) {
    if (!sel) return;
    var cur = keep || sel.value;
    sel.innerHTML = '<option value="">Tous</option>';
    (arr || []).forEach(function (v) {
      var o = document.createElement('option');
      o.value = v; o.textContent = v;
      if (v === cur) o.selected = true;
      sel.appendChild(o);
    });
  }
  if (city) {
    city.addEventListener('change', function () {
      fill(commune, mapC[city.value]);
      fill(quartier, mapQ[city.value]);
    });
  }

  var form = document.getElementById('searchform');
  var duree = document.getElementById('duree');
  var prixlab = document.getElementById('prixlab');
  if (form) {
    form.setAttribute('data-mensuel', form.action);
    form.setAttribute('data-stay', form.action.replace(/biens\/?$/, 'immo-stay'));
  }
  document.querySelectorAll('.hero .rent-switch button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.hero .rent-switch button').forEach(function (b) { b.classList.remove('on'); });
      btn.classList.add('on');
      var mode = btn.getAttribute('data-mode');
      if (duree) duree.value = mode;
      if (form) form.action = mode === 'journalier' ? form.getAttribute('data-stay') : form.getAttribute('data-mensuel');
      if (prixlab) prixlab.textContent = mode === 'journalier' ? 'Prix min / nuit' : 'Loyer min / mois';
    });
  });

  var wabtn = document.getElementById('wabtn');
  var wabox = document.getElementById('wabox');
  var waclose = document.getElementById('waclose');
  if (wabtn && wabox) {
    wabtn.addEventListener('click', function (e) {
      if (window.innerWidth > 640) {
        e.preventDefault();
        wabox.classList.toggle('open');
      }
    });
  }
  if (waclose && wabox) waclose.addEventListener('click', function () { wabox.classList.remove('open'); });

  document.querySelectorAll('.gallery-main').forEach(function (main) {
    var wrap = main.closest('.gallery');
    if (!wrap) return;
    wrap.querySelectorAll('img').forEach(function (img) {
      img.addEventListener('click', function () {
        if (img === main) return;
        var tmp = main.src;
        main.src = img.src;
        img.src = tmp;
      });
    });
  });
})();
