(function () {
  function toInt(value, fallback) {
    var parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function setup(root) {
    if (!root || root.dataset.vrGuestReady === '1') return;
    root.dataset.vrGuestReady = '1';

    var trigger = root.querySelector('.vr-guest-trigger');
    var popover = root.querySelector('.vr-guest-popover');
    var summary = root.querySelector('[data-vr-guest-summary]');
    var closeBtn = root.querySelector('.vr-guest-popover-close');
    var maxGuests = Math.max(1, toInt(root.dataset.maxGuests, 12));
    var maxPets = Math.max(0, toInt(root.dataset.maxPets, 5));
    var inputs = {
      adults: root.querySelector('[data-vr-input="adults"]'),
      children: root.querySelector('[data-vr-input="children"]'),
      pets: root.querySelector('[data-vr-input="pets"]')
    };

    function getValue(name) {
      return inputs[name] ? Math.max(name === 'adults' ? 1 : 0, toInt(inputs[name].value, name === 'adults' ? 1 : 0)) : 0;
    }

    function setValue(name, value) {
      if (!inputs[name]) return;
      inputs[name].value = String(value);
      var valueEl = root.querySelector('[data-vr-value="' + name + '"]');
      if (valueEl) valueEl.textContent = String(value);
    }

    function refreshSummary() {
      var adults = getValue('adults');
      var children = getValue('children');
      var pets = getValue('pets');
      var guests = adults + children;
      var parts = [guests + ' guest' + (guests === 1 ? '' : 's')];
      if (pets > 0) parts.push(pets + ' pet' + (pets === 1 ? '' : 's'));
      if (summary) summary.textContent = parts.join(', ');
    }

    function setDisabled(name, decDisabled, incDisabled) {
      var row = root.querySelector('.vr-guest-row[data-target="' + name + '"]');
      if (!row) return;
      var dec = row.querySelector('.vr-stepper-btn[data-action="dec"]');
      var inc = row.querySelector('.vr-stepper-btn[data-action="inc"]');
      if (dec) dec.disabled = decDisabled;
      if (inc) inc.disabled = incDisabled;
    }

    function syncDisabled() {
      var adults = getValue('adults');
      var children = getValue('children');
      var pets = getValue('pets');
      setDisabled('adults', adults <= 1, adults >= maxGuests - children);
      setDisabled('children', children <= 0, children >= maxGuests - adults);
      setDisabled('pets', pets <= 0, pets >= maxPets);
    }

    function update(name, delta) {
      var next = getValue(name) + delta;
      if (name === 'adults') {
        next = Math.max(1, Math.min(maxGuests - getValue('children'), next));
      } else if (name === 'children') {
        next = Math.max(0, Math.min(maxGuests - getValue('adults'), next));
      } else if (name === 'pets') {
        next = Math.max(0, Math.min(maxPets, next));
      }
      setValue(name, next);
      refreshSummary();
      syncDisabled();
    }

    function open() {
      if (!popover) return;
      popover.removeAttribute('hidden');
      root.classList.add('is-open');
      if (trigger) trigger.setAttribute('aria-expanded', 'true');
      syncDisabled();
    }

    function close() {
      if (!popover) return;
      popover.setAttribute('hidden', '');
      root.classList.remove('is-open');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }

    if (trigger && popover) {
      trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        popover.hasAttribute('hidden') ? open() : close();
      });
    }
    if (closeBtn) {
      closeBtn.addEventListener('click', close);
    }
    if (popover) {
      popover.addEventListener('click', function (event) {
        var button = event.target.closest('.vr-stepper-btn');
        if (!button) return;
        var row = button.closest('.vr-guest-row');
        if (!row) return;
        update(row.dataset.target, button.dataset.action === 'inc' ? 1 : -1);
      });
    }
    document.addEventListener('click', function (event) {
      if (!root.contains(event.target)) close();
    });

    refreshSummary();
    syncDisabled();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-vr-guest-selector]').forEach(setup);
  });
})();
