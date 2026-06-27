(function ($) {
  // Reliable viewport unit for mobile (handles browser UI chrome)
  function setViewportVar() {
    var vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--wtip-vh', vh + 'px');
  }
  setViewportVar();
  window.addEventListener('resize', setViewportVar);
  window.addEventListener('orientationchange', setViewportVar);

  if (typeof WTIP_DATA === 'undefined') return;

  const state = {
    step: 1,
    substep: 1,
    draft: null,
    isLoggedIn: !!WTIP_DATA.is_logged_in,
    images: [],
    consent: false,
    sending: false,
  };

  function setError(msg) {
    const box = document.querySelector('.wtip-error');
    if (box) box.textContent = msg || '';
  }

  function setSuccess(msg) {
    const box = document.querySelector('.wtip-success');
    if (box) box.textContent = msg || '';
  }

  function renderStep(n) {
    state.step = n;
    setError('');
    setSuccess('');
    document.querySelectorAll('.wtip-step').forEach((s, idx) => s.classList.toggle('active', idx === (n - 1)));
    document.querySelectorAll('.wtip-panel').forEach(p => p.style.display = 'none');

    if (n === 1) document.querySelector('#wtip-step1').style.display = 'block';
    else if (n === 2) {
      document.querySelector('#wtip-step2').style.display = 'block';
      renderLoginSubstep(1);
    } else if (n === 3) {
      document.querySelector('#wtip-step3').style.display = 'block';
      populateReview();
    }
  }

  function openModal() {
    const modal = document.querySelector('.wtip-modal');
    if (!modal) return;
    modal.style.display = 'block';
    try { modal.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) {}
    renderStep(1);
  }

  function closeModal() {
    const modal = document.querySelector('.wtip-modal');
    if (modal) modal.style.display = 'none';
  }

  function renderLoginSubstep(n) {
    state.substep = n;
    document.querySelectorAll('.wtip-login-substep').forEach(p => p.style.display = 'none');
    document.querySelector('#wtip-substep' + n).style.display = 'block';
  }

  function collectStep1Data() {
    const linkEl = document.querySelector('#wtip-new-link'); // may not exist (hidden/removed)
    const d = {
      new_product_name: WTIP_DATA.product.title || document.querySelector('#wtip-new-name').value.trim(),
      new_product_link: (WTIP_DATA.product && WTIP_DATA.product.link) || (linkEl ? linkEl.value.trim() : window.location.href),
      product_id: (WTIP_DATA.product && WTIP_DATA.product.id) || 0,
      old_product_name: document.querySelector('#wtip-old-name').value.trim(),
      condition: document.querySelector('#wtip-condition').value,
      currency: document.querySelector('#wtip-currency').value,
      expected_value: document.querySelector('#wtip-expected').value.trim(),
    };
    return d;
  }

  function submitStep1() {
    setError('');
    setSuccess('');
    const d = collectStep1Data();
    if (!d.new_product_name || !d.new_product_link || !d.old_product_name || !d.condition || d.expected_value === '' || !d.currency) {
      setError('Please complete all required fields.');
      return;
    }

    const form = new FormData();
    form.append('action', 'wtip_submit_initial');
    form.append('nonce', WTIP_DATA.nonce);
    Object.keys(d).forEach(k => form.append(k, d[k]));

    const fileInput = document.querySelector('#wtip-images');
    if (fileInput && fileInput.files) {
      const max = WTIP_DATA.max_images || 5;
      const files = Array.from(fileInput.files).slice(0, max);
      files.forEach((f, i) => form.append('images[' + i + ']', f));
    }

    toggleSending(true);
    fetch(WTIP_DATA.ajax_url, { method: 'POST', credentials: 'same-origin', body: form })
      .then(r => r.json())
      .then(json => {
        if (!json.success) throw new Error(json.data && json.data.message || 'Failed to save draft.');
        state.draft = json.data;
        state.images = json.data.images || [];
        if (json.data.is_logged_in) renderStep(3);
        else renderStep(2);
      })
      .catch(err => setError(err.message))
      .finally(() => toggleSending(false));
  }

  function sendCode() {
    setError('');
    const email = document.querySelector('#wtip-email').value.trim();
    if (!email) { setError('Please enter your email.'); return; }

    const form = new FormData();
    form.append('action', 'wtip_send_code');
    form.append('nonce', WTIP_DATA.nonce);
    form.append('email', email);

    toggleSending(true);
    fetch(WTIP_DATA.ajax_url, { method: 'POST', credentials: 'same-origin', body: form })
      .then(r => r.json())
      .then(json => {
        if (!json.success) throw new Error(json.data && json.data.message || 'Failed to send code.');
        setSuccess(json.data.message);
        renderLoginSubstep(2);
      })
      .catch(err => setError(err.message))
      .finally(() => toggleSending(false));
  }

  function verifyCode() {
    setError('');
    const email = document.querySelector('#wtip-email').value.trim();
    const code = document.querySelector('#wtip-code').value.trim();
    if (!email || !code) { setError('Enter the code sent to your email.'); return; }

    const form = new FormData();
    form.append('action', 'wtip_verify_code');
    form.append('nonce', WTIP_DATA.nonce);
    form.append('email', email);
    form.append('code', code);

    toggleSending(true);
    fetch(WTIP_DATA.ajax_url, { method: 'POST', credentials: 'same-origin', body: form })
      .then(r => r.json())
      .then(json => {
        if (!json.success) throw new Error(json.data && json.data.message || 'Verification failed.');
        setSuccess(json.data.message);
        state.isLoggedIn = true;
        if (json.data && json.data.nonce) {
          WTIP_DATA.nonce = json.data.nonce;
        }
        showProceedPrompt();
      })
      .catch(err => setError(err.message))
      .finally(() => toggleSending(false));
  }

  function showProceedPrompt() {
    const prompt = document.querySelector('#wtip-proceed');
    prompt.style.display = 'block';
    const yes = prompt.querySelector('.wtip-yes');
    const no = prompt.querySelector('.wtip-no');
    const onYes = () => {
      prompt.style.display = 'none';
      renderStep(3);
      yes.removeEventListener('click', onYes);
      no.removeEventListener('click', onNo);
    };
    const onNo = () => {
      prompt.style.display = 'none';
      closeModal();
      yes.removeEventListener('click', onYes);
      no.removeEventListener('click', onNo);
    };
    yes.addEventListener('click', onYes);
    no.addEventListener('click', onNo);
  }

  function populateReview() {
    const d = collectStep1Data();
    const imagesWrap = document.querySelector('#wtip-review-images');
    imagesWrap.innerHTML = '';
    (state.images || []).forEach(img => {
      const i = new Image();
      i.src = img.url;
      imagesWrap.appendChild(i);
    });

    document.querySelector('#wtip-review-new-name').textContent = d.new_product_name;
    document.querySelector('#wtip-review-new-link').setAttribute('href', d.new_product_link);
    document.querySelector('#wtip-review-old').textContent = d.old_product_name;
    document.querySelector('#wtip-review-condition').textContent = d.condition;
    document.querySelector('#wtip-review-value').textContent = d.expected_value;
    document.querySelector('#wtip-review-currency').textContent = ' ' + d.currency;
  }

  function backToEdit() {
    renderStep(1);
  }

  function confirmTradeIn() {
    setError('');
    setSuccess('');
    const consent = document.querySelector('#wtip-consent').checked;
    if (!consent) { setError('You must agree to the terms to continue.'); return; }

    const form = new FormData();
    form.append('action', 'wtip_confirm_request');
    form.append('nonce', WTIP_DATA.nonce);
    form.append('consent', consent ? '1' : '');
    // Pass draft_key as a fallback when cookie is missing (cross-context or strict browsers)
    form.append('draft_key', (state.draft && state.draft.draft_key) ? state.draft.draft_key : '');

    toggleSending(true);
    fetch(WTIP_DATA.ajax_url, { method: 'POST', credentials: 'same-origin', body: form })
      .then(r => r.json())
      .then(json => {
        if (!json.success) throw new Error(json.data && json.data.message || 'Failed to submit request.');
        const msg = json.data.message || 'Trade-in request submitted!';
        setSuccess(msg + ' Redirecting...');
        const wa = json.data.whatsapp_url;
        // Auto-close and redirect in same tab
        setTimeout(() => {
          closeModal();
          if (wa) {
            window.location.href = wa;
          }
        }, 900);
      })
      .catch(err => setError(err.message))
      .finally(() => toggleSending(false));
  }

  function toggleSending(flag) {
    state.sending = flag;
    const btns = document.querySelectorAll('.wtip-btn');
    btns.forEach(b => b.disabled = flag);
    const next = document.querySelector('#wtip-next');
    if (next) next.innerHTML = flag ? '<span class="wtip-spinner"></span>' : WTIP_DATA.strings.next;
    const confirmBtn = document.querySelector('#wtip-confirm');
    if (confirmBtn) confirmBtn.innerHTML = flag ? '<span class="wtip-spinner"></span>' : WTIP_DATA.strings.confirm;
  }

  function initForm() {
    const trigger = document.querySelector('.wtip-trigger');
    if (trigger) trigger.addEventListener('click', openModal);

    document.querySelectorAll('.wtip-close').forEach(c => c.addEventListener('click', closeModal));
    document.querySelector('#wtip-next').addEventListener('click', submitStep1);
    document.querySelector('#wtip-send-code').addEventListener('click', sendCode);
    const resend = document.querySelector('#wtip-resend-code');
    if (resend) resend.addEventListener('click', sendCode);
    document.querySelector('#wtip-verify').addEventListener('click', verifyCode);
    document.querySelector('#wtip-edit').addEventListener('click', backToEdit);
    document.querySelector('#wtip-confirm').addEventListener('click', confirmTradeIn);

    if (WTIP_DATA.product) {
      if (WTIP_DATA.product.title) document.querySelector('#wtip-new-name').value = WTIP_DATA.product.title;
      // Link is hidden; we keep it in JS data
    }

    const condSel = document.querySelector('#wtip-condition');
    if (condSel) {
      condSel.innerHTML = '';
      (WTIP_DATA.condition_options || []).forEach(opt => {
        const o = document.createElement('option');
        o.value = opt;
        o.textContent = opt;
        condSel.appendChild(o);
      });
    }
  }

  document.addEventListener('DOMContentLoaded', initForm);
})(jQuery);
