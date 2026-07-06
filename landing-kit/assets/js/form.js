/* ============================================================================
   Обработка формы заявки:
   - валидация на стороне клиента,
   - отправка на /api/submit.php через fetch (без перезагрузки страницы),
   - цель в Яндекс.Метрике при успешной отправке (reachGoal 'lead').

   Подключается в конце <body>. Форма должна иметь id="leadForm".
   ============================================================================ */
(function () {
  'use strict';

  var form = document.getElementById('leadForm');
  if (!form) return;

  var statusEl = form.querySelector('.form-status');
  var submitBtn = form.querySelector('[type="submit"]');

  // Телефон: разрешаем цифры, +, пробелы, скобки, дефис; минимум 10 цифр.
  function isPhone(v) {
    var digits = (v || '').replace(/\D/g, '');
    return digits.length >= 10 && digits.length <= 15;
  }
  function isEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v || '');
  }

  function setError(field, on) {
    field.closest('.field').classList.toggle('field--error', !!on);
  }

  function validate() {
    var ok = true;
    var name = form.elements['name'];
    var phone = form.elements['phone'];
    var email = form.elements['email'];
    var consent = form.elements['consent'];

    if (!name.value.trim()) { setError(name, true); ok = false; } else setError(name, false);

    // Телефон ИЛИ email — хотя бы одно валидное контактное поле.
    var phoneOk = phone && isPhone(phone.value);
    var emailOk = email && isEmail(email.value);
    if (phone) setError(phone, phone.value && !phoneOk);
    if (email) setError(email, email.value && !emailOk);
    if (!phoneOk && !emailOk) {
      if (phone) setError(phone, true);
      if (email) setError(email, true);
      ok = false;
    }

    if (consent && !consent.checked) { ok = false; consent.focus(); }
    return ok;
  }

  function setStatus(msg, kind) {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.className = 'form-status' + (kind ? ' form-status--' + kind : '');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setStatus('', '');

    if (!validate()) {
      setStatus('Проверьте поля: нужны имя и корректный телефон или e-mail, а также согласие.', 'err');
      return;
    }

    var data = new FormData(form);
    submitBtn.disabled = true;
    var prevText = submitBtn.textContent;
    submitBtn.textContent = 'Отправляем…';

    fetch(form.getAttribute('action') || '/api/submit.php', {
      method: 'POST',
      body: data,
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); })
      .then(function (res) {
        if (res && res.ok) {
          setStatus('Спасибо! Заявка принята — свяжемся с вами в ближайшее время.', 'ok');
          form.reset();
          // Цель Яндекс.Метрики. Замените YOUR_METRIKA_ID на номер счётчика.
          if (window.ym && window.METRIKA_ID) {
            window.ym(window.METRIKA_ID, 'reachGoal', 'lead');
          }
          // Опционально: цель Google Ads / другой пиксель можно добавить здесь.
        } else {
          setStatus((res && res.error) || 'Не удалось отправить. Попробуйте позвонить нам.', 'err');
        }
      })
      .catch(function () {
        setStatus('Ошибка сети. Проверьте соединение и попробуйте ещё раз.', 'err');
      })
      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = prevText;
      });
  });
})();
